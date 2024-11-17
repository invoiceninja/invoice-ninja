<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\CompanyToken;
use App\Models\ClientContact;
use App\Jobs\Util\ReminderJob;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *  App\Jobs\Util\ReminderJob
 */
class ReminderTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();
    }
    public $company;

    public $user;

    public $payload;

    public $account;

    public $client;

    public $token;

    public $cu;

    public $invoice;

    private function buildData($settings = null)
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        if(!$settings) {
            $settings = CompanySettings::defaults();
            $settings->client_online_payment_notification = false;
            $settings->client_manual_payment_notification = false;
        }

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;

        $company_token->save();

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'bob',
            'address1' => '1234',
            'balance' => 100,
            'paid_to_date' => 50,
        ]);

        ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'first_name' => 'john',
                'last_name' => 'doe',
                'email' => 'john@doe.com',
                'send_email' => true,
            ]);

        $this->invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'date' => now()->addSeconds($this->client->timezone_offset())->format('Y-m-d'),
            'next_send_date' => null,
            'due_date' => Carbon::now()->addSeconds($this->client->timezone_offset())->addDays(5)->format('Y-m-d'),
            'last_sent_date' => now()->addSeconds($this->client->timezone_offset()),
            'reminder_last_sent' => null,
            'status_id' => 2,
            'amount' => 10,
            'balance' => 10,
        ]);

    }

    public function testReminderScheduleNy()
    {
                
        $settings = CompanySettings::defaults();
        $settings->timezone_id = '15';
        $settings->entity_send_time = 6;
        $settings->payment_terms = '14';
        $settings->send_reminders = true;
        $settings->enable_reminder1 = true;
        $settings->enable_reminder2 = false;
        $settings->enable_reminder3 = false;
        $settings->enable_reminder_endless = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->schedule_reminder2 = '';
        $settings->schedule_reminder3 = '';
        $settings->num_days_reminder1 = 1;
        $settings->num_days_reminder2 = 0;
        $settings->num_days_reminder3 = 0;
        $settings->endless_reminder_frequency_id = '1';

        $this->buildData($settings);

        $this->travelTo(Carbon::parse('2024-09-20')->startOfDay()->addHours(1));

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => 10,
            'balance' => 10,
            'date' => '2024-09-19',
            'number' => 'JJJ1-11-2024',
            'due_date' => '2024-09-19',
            'status_id' => 2,
            'last_sent_date' => '19-09-2024',
        ]);

        $this->assertEquals(1, $invoice->company->settings->num_days_reminder1);

        $invoice->service()->setReminder($settings)->save();

        $this->assertEquals(10, $invoice->balance);
        $this->assertEquals('2024-09-20', $invoice->next_send_date->format('Y-m-d'));

        
        $x = false;
        do {

            $this->travelTo(now()->addHour());
            (new ReminderJob())->handle();
            $invoice = $invoice->fresh();

            $x = (bool)$invoice->reminder1_sent;
        } while ($x === false);

        $this->assertNotNull($invoice->reminder_last_sent);
        $this->assertEquals(now()->addDays(1), $invoice->next_send_date);

        $x = 0;
        do {

            $this->travelTo(now()->addHour());
            (new ReminderJob())->handle();
            $invoice = $invoice->fresh();

            $x++;
        } while ($x < 24);

        $this->assertEquals(now()->addDays(1), $invoice->next_send_date);



    }

    public function testDKRemindersNotSending()
    {

        //Schedule
        // Settings:
        // Payment terms: 14 days
        // 1st reminder: after 5 days
        // 2st reminder: after 10 days
        // 3rd reminder: after 15 days
        // Endless reminder: all 2 weeks
        // Email settings: 9:00
        // Timezone: UTC+1

        $settings = CompanySettings::defaults();
        $settings->timezone_id = '40';
        $settings->entity_send_time = 9;
        $settings->payment_terms = '14';
        $settings->send_reminders = true;
        $settings->enable_reminder1 = true;
        $settings->enable_reminder2 = true;
        $settings->enable_reminder3 = true;
        $settings->enable_reminder_endless = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->schedule_reminder2 = 'after_invoice_date';
        $settings->schedule_reminder3 = 'after_invoice_date';
        $settings->lock_invoices = true;
        $settings->num_days_reminder1 = 5;
        $settings->num_days_reminder2 = 10;
        $settings->num_days_reminder3 = 15;
        $settings->endless_reminder_frequency_id = '3';

        $this->buildData($settings);

        $this->travelTo(Carbon::parse('2024-03-01')->startOfDay());

        $invoice = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'amount' => 10,
            'balance' => 10,
            'date' => '2024-03-01',
            'number' => 'X-11-2024',
            'due_date' => null,
            'status_id' => 1,
            'last_sent_date' => '2024-03-01',
        ]);

        //baseline checks pass
        $invoice->service()->createInvitations()->markSent()->save();
        $this->assertGreaterThan(0, $invoice->balance);
        $this->assertEquals(5, $this->invoice->company->settings->num_days_reminder1);
        $this->assertEquals('2024-03-01', now()->format('Y-m-d'));
        $this->assertEquals('2024-03-15', $invoice->due_date);
        $this->assertEquals('2024-03-06', $invoice->next_send_date->format('Y-m-d'));

        // //day five:  schedule send time 7am UTC
        $this->travelTo(now()->addDays(5)->startOfDay());
        $this->assertEquals('2024-03-06', now()->format('Y-m-d'));


        $x = false;
        do {

            $this->travelTo(now()->addHour());
            (new ReminderJob())->handle();
            $invoice = $invoice->fresh();

            $x = (bool)$invoice->reminder1_sent;
        } while($x === false);

        $this->assertNotNull($invoice->reminder_last_sent);

        //check next send date is on day "10"
        $this->assertEquals(now()->addDays(5), $invoice->next_send_date);

        $this->travelTo(now()->copy()->addDays(5)->startOfDay()->addHours(5));
        $this->assertEquals('2024-03-11', now()->format('Y-m-d'));

        $this->travelTo(now()->copy()->addHour());
        (new ReminderJob())->handle();
        $invoice = $invoice->fresh();

        $this->assertGreaterThan(0, $invoice->balance);
        $this->assertNull($invoice->reminder2_sent);

        $x = false;
        do {

            $this->travelTo(now()->addHour());
            (new ReminderJob())->handle();
            $invoice = $invoice->fresh();

            $x = (bool)$invoice->reminder2_sent;
        } while ($x === false);

        $this->assertNotNull($invoice->reminder2_sent);
        $this->assertEquals($invoice->reminder2_sent, $invoice->reminder_last_sent);

        $this->assertEquals(now()->addDays(5), $invoice->next_send_date);

        //check next send date is on day "15"
        $this->assertEquals(now()->addDays(5), $invoice->next_send_date);

        $this->travelTo(now()->copy()->addDays(5)->startOfDay()->addHours(5));
        $this->assertEquals('2024-03-16', now()->format('Y-m-d'));

        $this->travelTo(now()->copy()->addHour());
        (new ReminderJob())->handle();
        $invoice = $invoice->fresh();

        $this->assertGreaterThan(0, $invoice->balance);
        $this->assertNull($invoice->reminder3_sent);

        $x = false;
        do {

            $this->travelTo(now()->addHour());
            (new ReminderJob())->handle();
            $invoice = $invoice->fresh();

            $x = (bool)$invoice->reminder3_sent;
        } while ($x === false);

        $this->assertNotNull($invoice->reminder3_sent);
        $this->assertEquals($invoice->reminder3_sent, $invoice->reminder_last_sent);

        //endless reminders
        $this->assertEquals(now()->addDays(14), $invoice->next_send_date);

        $this->travelTo(now()->addDays(14)->startOfDay());

        $this->assertEquals('2024-03-30', now()->format('Y-m-d'));

        $x = false;
        do {

            $this->travelTo(now()->addHour());
            (new ReminderJob())->handle();
            $invoice = $invoice->fresh();

            $x = (bool)Carbon::parse($invoice->next_send_date)->gt(now()->addDays(2));

        } while($x === false);


        $this->assertEquals(now()->addDays(14), $invoice->next_send_date);

    }

    public function testForUtcEdgeCaseOnTheFirstOfMonth()
    {

        $this->travelTo(Carbon::parse('2024-03-01')->startOfDay());

        $this->invoice->status_id = 2;
        $this->invoice->amount = 10;
        $this->invoice->balance = 10;
        $this->invoice->next_send_date = null;
        $this->invoice->date = '2024-03-01';
        $this->invoice->last_sent_date = now();
        $this->invoice->due_date = Carbon::parse('2024-03-01')->addDays(30)->format('Y-m-d');
        $this->invoice->reminder_last_sent = null;
        $this->invoice->save();

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'before_due_date';
        $settings->num_days_reminder1 = 14;
        $settings->enable_reminder2 = false;
        $settings->schedule_reminder2 = '';
        $settings->num_days_reminder2 = 0;
        $settings->enable_reminder3 = false;
        $settings->schedule_reminder3 = '';
        $settings->num_days_reminder3 = 0;
        $settings->timezone_id = '15';
        $settings->entity_send_time = 6;
        $settings->endless_reminder_frequency_id = '';
        $settings->enable_reminder_endless = false;

        $this->invoice->service()->setReminder($settings)->save();

        $this->invoice = $this->invoice->fresh();

        $this->assertEquals('2024-03-17', \Carbon\Carbon::parse($this->invoice->next_send_date)->startOfDay()->format('Y-m-d'));

    }

    public function testReminderInThePast()
    {

        $translations = new \stdClass();
        $translations->late_fee_added = "Fee added :date";

        $settings = $this->company->settings;
        $settings->enable_reminder1 = false;
        $settings->schedule_reminder1 = '';
        $settings->num_days_reminder1 = 1;
        $settings->enable_reminder2 = false;
        $settings->schedule_reminder2 = '';
        $settings->num_days_reminder2 = 2;
        $settings->enable_reminder3 = false;
        $settings->schedule_reminder3 = '';
        $settings->num_days_reminder3 = 3;
        $settings->timezone_id = '29';
        $settings->entity_send_time = 0;
        $settings->endless_reminder_frequency_id = '5';
        $settings->enable_reminder_endless = true;
        $settings->translations = $translations;
        $settings->late_fee_amount1 = '0';
        $settings->late_fee_amount2 = '0';
        $settings->late_fee_amount3 = '0';

        $this->buildData(($settings));

        $this->invoice->date = now()->subMonths(2)->format('Y-m-d');
        $this->invoice->due_date = now()->subMonth()->format('Y-m-d');
        $this->invoice->last_sent_date = now();

        $this->invoice->service()->setReminder($settings)->save();

        $this->invoice = $this->invoice->fresh();

        $this->assertEquals(now()->startOfDay()->addMonthNoOverflow()->format('Y-m-d'), \Carbon\Carbon::parse($this->invoice->next_send_date)->startOfDay()->format('Y-m-d'));
    }

    public function testsForTranslationsInReminders()
    {

        $translations = new \stdClass();
        $translations->late_fee_added = "Fee added :date";

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 1;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'after_invoice_date';
        $settings->num_days_reminder2 = 2;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_invoice_date';
        $settings->num_days_reminder3 = 3;
        $settings->timezone_id = '29';
        $settings->entity_send_time = 0;
        $settings->endless_reminder_frequency_id = '';
        $settings->enable_reminder_endless = false;
        $settings->translations = $translations;
        $settings->late_fee_amount1 = '101';
        $settings->late_fee_amount2 = '102';
        $settings->late_fee_amount3 = '103';

        $this->buildData(($settings));

        $this->assertEquals("Fee added :date", $this->company->settings->translations->late_fee_added);
        $fetched_settings = $this->client->getMergedSettings();
        $this->assertEquals("Fee added :date", $fetched_settings->translations->late_fee_added);

        $this->invoice->service()->setReminder($settings)->save();

        $this->invoice = $this->invoice->fresh();

        $this->assertEquals(now()->addSeconds($this->client->timezone_offset())->format('Y-m-d'), $this->invoice->date);
        $this->assertNotNull($this->invoice->next_send_date);
        $this->assertEquals(now()->addDay()->addSeconds($this->client->timezone_offset())->format('Y-m-d 00:00:00'), $this->invoice->next_send_date);

        $this->travelTo(now()->addDay()->startOfDay()->addHour());

        (new ReminderJob())->handle();
        $this->invoice = $this->invoice->fresh();
        $this->assertNotNull($this->invoice->reminder1_sent);
        $this->assertNotNull($this->invoice->reminder_last_sent);

        $fee = collect($this->invoice->line_items)->where('type_id', 5)->first();

        $this->assertEquals(101, $fee->cost);
        $this->assertEquals('Fee added '.now()->format('d/M/Y'), $fee->notes);

        $this->travelTo(now()->addDay()->startOfDay()->addHour());

        (new ReminderJob())->handle();
        $this->invoice = $this->invoice->fresh();
        $this->assertNotNull($this->invoice->reminder2_sent);
        $this->assertNotNull($this->invoice->reminder_last_sent);

        $fee = collect($this->invoice->line_items)->where('cost', 102)->first();

        $this->assertEquals(102, $fee->cost);
        $this->assertEquals('Fee added '.now()->format('d/M/Y'), $fee->notes);

        $this->travelTo(now()->addDay()->startOfDay()->addHour());

        (new ReminderJob())->handle();
        $this->invoice = $this->invoice->fresh();
        $this->assertNotNull($this->invoice->reminder3_sent);
        $this->assertNotNull($this->invoice->reminder_last_sent);

        $fee = collect($this->invoice->line_items)->where('cost', 103)->first();

        $this->assertEquals(103, $fee->cost);
        $this->assertEquals('Fee added '.now()->format('d/M/Y'), $fee->notes);

        $this->travelBack();

    }

    public function testForReminderFiringCorrectly()
    {
        $this->invoice->status_id = 2;
        $this->invoice->amount = 10;
        $this->invoice->balance = 10;
        $this->invoice->next_send_date = null;
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->last_sent_date = now();
        $this->invoice->due_date = Carbon::now()->addDays(5)->format('Y-m-d');
        $this->invoice->reminder_last_sent = null;
        $this->invoice->save();

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 2;
        $settings->enable_reminder2 = false;
        $settings->schedule_reminder2 = '';
        $settings->num_days_reminder2 = 0;
        $settings->enable_reminder3 = false;
        $settings->schedule_reminder3 = '';
        $settings->num_days_reminder3 = 0;
        $settings->timezone_id = '109';
        $settings->entity_send_time = 6;
        $settings->endless_reminder_frequency_id = '';
        $settings->enable_reminder_endless = false;

        $this->client->company->settings = $settings;
        $this->client->push();

        $client_settings = $settings;
        $client_settings->timezone_id = '5';
        $client_settings->entity_send_time = 8;

        $this->invoice->client->settings = $client_settings;
        $this->invoice->push();

        $this->invoice = $this->invoice->service()->markSent()->save();
        $this->invoice->service()->setReminder($client_settings)->save();

        $this->invoice = $this->invoice->fresh();

        //due to UTC server time, we actually send the "day before"
        $this->assertEquals(now()->addDays(1)->format('Y-m-d'), Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'));

        $this->travelTo(now()->startOfDay());

        $travel_date = Carbon::parse($this->invoice->next_send_date);
        $x = false;
        for($x = 0; $x < 50; $x++) {

            (new ReminderJob())->handle();

            if(now()->gt($travel_date) && !$x) {


                $this->assertNotNull($this->invoice->reminder1_sent);
                $this->assertNotNull($this->invoice->reminder_last_sent);
                $x = true;
            }


            if(!$x) {
                $this->invoice = $this->invoice->fresh();
                $this->assertNull($this->invoice->reminder1_sent);
                $this->assertNull($this->invoice->reminder_last_sent);
            }

            $this->travelTo(now()->addHours(1));

        }

        // nlog("traveller ".now()->format('Y-m-d'));
        (new ReminderJob())->handle();
        $this->invoice = $this->invoice->fresh();
        $this->assertNotNull($this->invoice->reminder1_sent);

    }

    public function testForSingleEndlessReminder()
    {
        $this->invoice->next_send_date = null;
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->last_sent_date = now();
        $this->invoice->due_date = Carbon::now()->addDays(5)->format('Y-m-d');
        $this->invoice->save();

        $settings = $this->company->settings;
        $settings->enable_reminder1 = false;
        $settings->schedule_reminder1 = '';
        $settings->num_days_reminder1 = 0;
        $settings->enable_reminder2 = false;
        $settings->schedule_reminder2 = '';
        $settings->num_days_reminder2 = 0;
        $settings->enable_reminder3 = false;
        $settings->schedule_reminder3 = '';
        $settings->num_days_reminder3 = 0;
        $settings->timezone_id = '5';
        $settings->entity_send_time = 8;
        $settings->endless_reminder_frequency_id = '5';
        $settings->enable_reminder_endless = true;

        $this->client->company->settings = $settings;
        $this->client->push();

        $client_settings = $settings;
        $client_settings->timezone_id = '5';
        $client_settings->entity_send_time = 8;

        $this->invoice->client->settings = $client_settings;
        $this->invoice->push();

        $this->invoice = $this->invoice->service()->markSent()->save();
        $this->invoice->service()->setReminder($client_settings)->save();

        $this->invoice = $this->invoice->fresh();

        $this->assertEquals(now()->addMonthNoOverflow()->format('Y-m-d'), Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'));

    }


    public function testForClientTimezoneEdges()
    {
        $this->invoice->next_send_date = null;
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(5)->format('Y-m-d');
        $this->invoice->save();

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'before_due_date';
        $settings->num_days_reminder1 = 4;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 2;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 3;
        $settings->timezone_id = '15';
        $settings->entity_send_time = 8;

        $this->client->company->settings = $settings;
        $this->client->push();

        $client_settings = $settings;
        $client_settings->timezone_id = '15';
        $client_settings->entity_send_time = 8;

        $this->invoice->client->settings = $client_settings;
        $this->invoice->push();

        $this->invoice = $this->invoice->service()->markSent()->save();
        $this->invoice->service()->setReminder($client_settings)->save();

        $next_send_date = Carbon::parse($this->invoice->next_send_date);
        $calculatedReminderDate = Carbon::parse($this->invoice->due_date)->subDays(4)->addSeconds($this->invoice->client->timezone_offset());

        // nlog($next_send_date->format('Y-m-d h:i:s'));
        // nlog($calculatedReminderDate->format('Y-m-d h:i:s'));

        $this->travelTo($calculatedReminderDate);

        $reminder_template = $this->invoice->calculateTemplate('invoice');

        $this->assertEquals('reminder1', $reminder_template);

        $this->assertTrue($next_send_date->eq($calculatedReminderDate));

        $this->invoice->service()->touchReminder($reminder_template)->save();

        $this->assertNotNull($this->invoice->last_sent_date);
        $this->assertNotNull($this->invoice->reminder1_sent);
        $this->assertNotNull($this->invoice->reminder_last_sent);

        //calc next send date
        $this->invoice->service()->setReminder()->save();

        $next_send_date = Carbon::parse($this->invoice->next_send_date);

        // nlog($next_send_date->format('Y-m-d h:i:s'));

        $calculatedReminderDate = Carbon::parse($this->invoice->due_date)->subDays(2)->addSeconds($this->invoice->client->timezone_offset());
        $this->assertTrue($next_send_date->eq($calculatedReminderDate));

        $this->travelTo(now()->addDays(2));

        $reminder_template = $this->invoice->calculateTemplate('invoice');

        $this->assertEquals('reminder2', $reminder_template);
        $this->invoice->service()->touchReminder($reminder_template)->save();
        $this->assertNotNull($this->invoice->reminder2_sent);

        $this->invoice->service()->setReminder()->save();

        $next_send_date = Carbon::parse($this->invoice->next_send_date);
        $calculatedReminderDate = Carbon::parse($this->invoice->due_date)->addDays(3)->addSeconds($this->invoice->client->timezone_offset());
        $this->assertTrue($next_send_date->eq($calculatedReminderDate));

        // nlog($next_send_date->format('Y-m-d h:i:s'));
    }

    // public function testReminderQueryCatchesDate()
    // {
    //     $this->invoice->next_send_date = now()->format('Y-m-d');
    //     $this->invoice->save();

    //     $invoices = Invoice::where('next_send_date', Carbon::today())->get();

    //     $this->assertEquals(1, $invoices->count());
    // }

    public function testReminderHits()
    {
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 7;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 1;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), Carbon::now()->addDays(7)->format('Y-m-d'));

    }

    public function testReminderHitsScenarioH1()
    {
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'before_due_date';
        $settings->num_days_reminder1 = 2;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'after_due_date';
        $settings->num_days_reminder2 = 14;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 30;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), Carbon::now()->addDays(30)->subDays(2)->format('Y-m-d'));

    }

    /* Cant set a reminder in the past so need to skip reminder 2 and go straigh to reminder 3*/
    public function testReminderNextSendRecalculation()
    {
        $this->invoice->date = now()->subDays(2)->format('Y-m-d');
        $this->invoice->due_date = now()->addDays(30)->format('Y-m-d');
        $this->invoice->reminder1_sent = now()->subDays(1)->format('Y-m-d');
        $this->invoice->last_sent_date = now()->subDays(1)->format('Y-m-d');
        $this->invoice->next_send_date = now()->subDays(1)->format('Y-m-d');
        $this->invoice->reminder2_sent = null;

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 1;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'after_invoice_date';
        $settings->num_days_reminder2 = 2;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_invoice_date';
        $settings->num_days_reminder3 = 3;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->invoice->fresh();

        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), now()->addDay()->format('Y-m-d'));
    }

    public function testReminder3NextSendRecalculation()
    {
        $this->invoice->date = now()->subDays(3)->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');
        $this->invoice->reminder1_sent = now()->subDays(2)->format('Y-m-d');
        $this->invoice->reminder2_sent = now()->subDays(1)->format('Y-m-d');

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 1;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'after_invoice_date';
        $settings->num_days_reminder2 = 2;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_invoice_date';
        $settings->num_days_reminder3 = 3;

        $this->company->settings = $settings;
        $this->invoice->service()->markSent();
        $this->invoice->service()->setReminder($settings)->save();

        $this->invoice->fresh();

        $this->assertEquals(Carbon::parse($this->invoice->next_send_date)->format('Y-m-d'), now()->format('Y-m-d'));
    }

    public function testReminderIsSet()
    {
        $this->invoice->next_send_date = null;
        $this->invoice->date = now()->format('Y-m-d');
        $this->invoice->due_date = Carbon::now()->addDays(30)->format('Y-m-d');
        $this->invoice->save();

        $settings = $this->company->settings;
        $settings->enable_reminder1 = true;
        $settings->schedule_reminder1 = 'after_invoice_date';
        $settings->num_days_reminder1 = 7;
        $settings->enable_reminder2 = true;
        $settings->schedule_reminder2 = 'before_due_date';
        $settings->num_days_reminder2 = 1;
        $settings->enable_reminder3 = true;
        $settings->schedule_reminder3 = 'after_due_date';
        $settings->num_days_reminder3 = 1;

        $this->company->settings = $settings;
        $this->invoice = $this->invoice->service()->markSent()->save();
        $this->invoice->service()->setReminder($settings)->save();

        $this->assertNotNull($this->invoice->next_send_date);
    }
}

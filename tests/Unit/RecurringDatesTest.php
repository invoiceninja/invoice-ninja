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

namespace Tests\Unit;

use App\DataMapper\CompanySettings;
use App\DataMapper\DefaultSettings;
use App\Factory\RecurringInvoiceFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\RecurringInvoice;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  \App\Models\RecurringInvoice
 */
class RecurringDatesTest extends TestCase
{
    use MakesHash;
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }

    public function testDueDateDaysCalculationsTZ2()
    {
        
        $settings = CompanySettings::defaults();
        $settings->timezone_id = '15'; // New York

        $company = Company::factory()->create([
            'account_id'=>$this->account->id,
            'settings' => $settings,
        ]);

        $client = Client::factory()->create([
            'company_id' =>$company->id,
            'user_id' => $this->user->id,
        ]);

        $this->travelTo(\Carbon\Carbon::create(2024, 12, 1, 17, 0, 0));

        $recurring_invoice = RecurringInvoiceFactory::create($company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $client->id;
        $recurring_invoice->status_id = RecurringInvoice::STATUS_DRAFT;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = '1';
        $recurring_invoice->next_send_date = now();
        $recurring_invoice->save();
        $recurring_invoice = $recurring_invoice->calc()->getInvoice();
        $recurring_invoice->service()->sendNow();
        $invoice = $recurring_invoice->invoices()->latest()->first();

        $this->assertGreaterThan(0, $recurring_invoice->invoices()->count());
        $this->assertEquals('2025-01-01', $invoice->due_date);

    }

    public function testDueDateDaysCalculationsTZ()
    {
        $this->travelTo(\Carbon\Carbon::create(2024, 11, 15, 17, 0, 0));

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;
        $recurring_invoice->status_id = RecurringInvoice::STATUS_DRAFT;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = '1';
        $recurring_invoice->next_send_date = now();
        $recurring_invoice->save();
        $recurring_invoice = $recurring_invoice->calc()->getInvoice();
        $recurring_invoice->service()->sendNow();
        $invoice = $recurring_invoice->invoices()->latest()->first();

        $this->assertGreaterThan(0, $recurring_invoice->invoices()->count());
        $this->assertEquals('2024-12-01', $invoice->due_date);

    }
    

    public function testDueDateDaysCalculations()
    {

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;
        $recurring_invoice->status_id = RecurringInvoice::STATUS_DRAFT;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = '1';
        $recurring_invoice->next_send_date = now();
        $recurring_invoice->save();
        $recurring_invoice = $recurring_invoice->calc()->getInvoice();

        $recurring_invoice->service()->sendNow();

        $invoice = $recurring_invoice->invoices()->latest()->first();

        $this->assertGreaterThan(0, $recurring_invoice->invoices()->count());
        $expected_due_date = now()->addMonth()->startOfMonth()->format('Y-m-d');
        $this->assertEquals($expected_due_date, $invoice->due_date);

    }

    public function testDailyFrequencyCalc6()
    {
        $this->travelTo(now()->subHours(8));

        $account = Account::factory()->create();

        $settings = CompanySettings::defaults();
        $settings->entity_send_time = '1';
        $settings->timezone_id = '113';

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        $client = Client::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

        $recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => now()->format('Y-m-d'),
            'next_send_date_client' => now()->format('Y-m-d'),
            'date' => now()->format('Y-m-d'),
            'remaining_cycles' => -1,
            'status_id' => 1,
        ]);

        $recurring_invoice->service()->start()->save();

        $this->assertEquals('1', $client->getSetting('entity_send_time'));
        $this->assertEquals('113', $client->getSetting('timezone_id'));

        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date)->format('Y-m-d'));
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        $recurring_invoice->save();

        $this->assertEquals(now()->startOfDay()->addDay()->addSeconds($client->timezone_offset()), Carbon::parse($recurring_invoice->next_send_date));
        $this->assertEquals(now()->addDay()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        $recurring_invoice->save();

        $this->assertEquals(now()->startOfDay()->addDays(2)->addSeconds($client->timezone_offset()), Carbon::parse($recurring_invoice->next_send_date));
        $this->assertEquals(now()->addDays(2)->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $this->travelBack();

    }




    public function testDailyFrequencyCalc5()
    {

        $account = Account::factory()->create();

        $settings = CompanySettings::defaults();
        $settings->entity_send_time = '23';
        $settings->timezone_id = '113';

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        $client = Client::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);



        $recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => now()->format('Y-m-d'),
            'next_send_date_client' => now()->format('Y-m-d'),
            'date' => now()->format('Y-m-d'),
            'remaining_cycles' => -1,
            'status_id' => 1,
        ]);

        $recurring_invoice->service()->start()->save();

        $this->assertEquals('23', $client->getSetting('entity_send_time'));
        $this->assertEquals('113', $client->getSetting('timezone_id'));

        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date)->format('Y-m-d'));
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        $recurring_invoice->save();

        $this->assertEquals(now()->startOfDay()->addDay()->addSeconds($client->timezone_offset()), Carbon::parse($recurring_invoice->next_send_date));
        $this->assertEquals(now()->addDay()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

    }


    public function testDailyFrequencyCalc4()
    {

        $account = Account::factory()->create();

        $settings = CompanySettings::defaults();
        $settings->entity_send_time = '6';
        $settings->timezone_id = '1';

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        $client = Client::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);



        $recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => now()->format('Y-m-d'),
            'next_send_date_client' => now()->format('Y-m-d'),
            'date' => now()->format('Y-m-d'),
            'remaining_cycles' => -1,
            'status_id' => 1,
        ]);

        $recurring_invoice->service()->start()->save();

        $this->assertEquals('6', $client->getSetting('entity_send_time'));
        $this->assertEquals('1', $client->getSetting('timezone_id'));

        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date)->format('Y-m-d'));
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        $recurring_invoice->save();

        $this->assertEquals(now()->startOfDay()->addDay()->addSeconds($client->timezone_offset()), Carbon::parse($recurring_invoice->next_send_date));
        $this->assertEquals(now()->addDay()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

    }

    public function testDailyFrequencyCalc3()
    {

        $account = Account::factory()->create();

        $settings = CompanySettings::defaults();
        $settings->entity_send_time = '1';
        $settings->timezone_id = '1';

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        $client = Client::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

        $this->assertEquals('1', $client->getSetting('entity_send_time'));
        $this->assertEquals('1', $client->getSetting('timezone_id'));

        $recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => now()->format('Y-m-d'),
            'next_send_date_client' => now()->format('Y-m-d'),
            'date' => now()->format('Y-m-d'),
            'remaining_cycles' => -1,
            'status_id' => 1,
        ]);

        $recurring_invoice->service()->start()->save();

        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date)->format('Y-m-d'));
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        $recurring_invoice->save();

        $this->assertEquals(now()->startOfDay()->addDay()->addSeconds($client->timezone_offset()), Carbon::parse($recurring_invoice->next_send_date));
        $this->assertEquals(now()->addDay()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));


    }

    public function testDailyFrequencyCalc2()
    {
        $account = Account::factory()->create();

        $settings = CompanySettings::defaults();
        $settings->entity_send_time = '23';
        $settings->timezone_id = '113';

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        $client = Client::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

        $this->assertEquals('23', $client->getSetting('entity_send_time'));
        $this->assertEquals('113', $client->getSetting('timezone_id'));

        $recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => now()->format('Y-m-d'),
            'next_send_date_client' => now()->format('Y-m-d'),
            'date' => now()->format('Y-m-d'),
            'remaining_cycles' => -1,
            'status_id' => 1,
        ]);

        $recurring_invoice->service()->start()->save();

        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date)->format('Y-m-d'));
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        $recurring_invoice->save();

        $this->assertEquals(now()->startOfDay()->addDay()->addSeconds($client->timezone_offset()), Carbon::parse($recurring_invoice->next_send_date));
        $this->assertEquals(now()->addDay()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

    }

    public function testDailyFrequencyCalc()
    {
        $account = Account::factory()->create();

        $settings = CompanySettings::defaults();
        $settings->entity_send_time = '1';
        $settings->timezone_id = '113';

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        $client = Client::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $client->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

        $this->assertEquals('1', $client->getSetting('entity_send_time'));
        $this->assertEquals('113', $client->getSetting('timezone_id'));

        $recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id,
            'frequency_id' => RecurringInvoice::FREQUENCY_DAILY,
            'next_send_date' => now()->format('Y-m-d'),
            'next_send_date_client' => now()->format('Y-m-d'),
            'date' => now()->format('Y-m-d'),
            'remaining_cycles' => -1,
            'status_id' => 1,
        ]);

        $recurring_invoice->service()->start()->save();

        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date)->format('Y-m-d'));
        $this->assertEquals(now()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));

        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        $recurring_invoice->save();

        $this->assertEquals(now()->startOfDay()->addDay()->addSeconds($client->timezone_offset()), Carbon::parse($recurring_invoice->next_send_date));
        $this->assertEquals(now()->addDay()->format('Y-m-d'), Carbon::parse($recurring_invoice->next_send_date_client)->format('Y-m-d'));



    }

    public function testRecurringDatesDraftInvoice()
    {
        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;
        $recurring_invoice->save();

        $recurring_invoice->calc()->getInvoice();

        $this->assertEquals(0, count($recurring_invoice->recurringDates()));
    }

    public function testRecurringDatesPendingInvoice()
    {
        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;

        $recurring_invoice->status_id = RecurringInvoice::STATUS_PENDING;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = '5';
        $recurring_invoice->next_send_date = now();

        $recurring_invoice->save();

        $recurring_invoice->calc()->getInvoice();

        $this->assertEquals(5, count($recurring_invoice->recurringDates()));
    }

    public function testRecurringDatesPendingInvoiceWithNoDueDate()
    {
        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->line_items = $this->buildLineItems();
        $recurring_invoice->client_id = $this->client->id;

        $recurring_invoice->status_id = RecurringInvoice::STATUS_PENDING;
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = 5;
        $recurring_invoice->due_date_days = null;
        $recurring_invoice->next_send_date = now();

        $recurring_invoice->save();

        $recurring_invoice->calc()->getInvoice();

        $this->assertEquals(5, count($recurring_invoice->recurringDates()));
    }

    public function testCompareDatesLogic()
    {
        $date = now()->startOfDay()->format('Y-m-d');

        $this->assertTrue(Carbon::parse($date)->lte(now()->startOfDay()));
    }
}

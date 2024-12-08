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
use App\DataMapper\InvoiceItem;
use App\Factory\ClientGatewayTokenFactory;
use App\Factory\InvoiceItemFactory;
use App\Jobs\Util\ReminderJob;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class LateFeeTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    public $account;

    public $company;

    public $client;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();

        $this->withoutExceptionHandling();

    }

    private function buildData($settings)
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

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $settings = new \stdClass();
        $settings->currency_id = '1';

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'settings' => $settings,
        ]);

        return $client;
    }

    public function testAddLateFeeAppropriately()
    {
        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = '5';
        $invoice_item->product_key = trans('texts.fee');
        $invoice_item->notes = ctrans('texts.late_fee_added', ['date' => 'xyz']);
        $invoice_item->quantity = 1;
        $invoice_item->cost = 20;

        $invoice_items = $this->invoice->line_items;
        $invoice_items[] = $invoice_item;

        $this->invoice->line_items = $invoice_items;

        $this->assertGreaterThan(1, count($this->invoice->line_items));

        /**Refresh Invoice values*/
        $invoice = $this->invoice->calc()->getInvoice();

        $this->assertGreaterThan(1, count($this->invoice->line_items));

    }

    public function testModelBehaviourInsideMap()
    {
        $i = Invoice::factory()->count(5)
        ->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'discount' => 0,
        ]);

        $i->each(function ($invoice) {
            $this->assertGreaterThan(1, count($invoice->line_items));
        });

        $this->assertCount(5, $i);

        $invoices = $i->map(function ($invoice) {
            $invoice->service()->removeUnpaidGatewayFees();
            return $invoice;
        });

        $invoices->each(function ($invoice) {
            $this->assertGreaterThan(1, count($invoice->line_items));
        });

        $ids = $invoices->pluck('id');

        $i->each(function ($invoice) {

            $line_items = $invoice->line_items;

            $item = new InvoiceItem();
            $item->type_id = '3';
            $item->product_key = trans('texts.fee');
            $item->quantity = 1;
            $item->cost = 10;

            $line_items[] = $item;

            $item = new InvoiceItem();
            $item->type_id = '5';
            $item->product_key = trans('texts.fee');
            $item->quantity = 1;
            $item->cost = 10;

            $line_items[] = $item;
            $invoice->line_items = $line_items;
            $invoice->saveQuietly();

            // return $invoice;
        });

        $invoices = Invoice::whereIn('id', $ids)->cursor()->map(function ($invoice) {
            $this->assertGreaterThan(0, count($invoice->line_items));

            $invoice->service()->removeUnpaidGatewayFees();
            $invoice = $invoice->fresh();
            $this->assertGreaterThan(0, count($invoice->line_items));

            return $invoice;
        });

        $invoices->each(function ($invoice) {
            $this->assertGreaterThan(0, count($invoice->line_items));
        });

    }

    public function testCollectionPassesIsArray()
    {
        $line_items = collect($this->invoice->line_items);
        $this->assertTrue(is_array($this->invoice->line_items));
        $this->assertTrue(is_iterable($line_items));
        $this->assertFalse(is_array($line_items));
    }

    public function testLineItemResiliency()
    {
        $line_count = count($this->invoice->line_items);
        $this->assertGreaterThan(0, $line_count);

        $this->invoice->service()->removeUnpaidGatewayFees();

        $this->invoice = $this->invoice->fresh();

        $this->assertCount($line_count, $this->invoice->line_items);
    }

    public function testCollectionAsLineItemArray()
    {

        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'discount' => 0,
        ]);

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->type_id = '1';

        $line_items[] = $item;

        $item = new InvoiceItem();
        $item->type_id = '5';
        $item->product_key = trans('texts.fee');
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1;
        $item->type_id = '3';

        $line_items[] = $item;

        $i->line_items = $line_items;

        $this->assertEquals(3, count($line_items));

        $i = $i->calc()->getInvoice();

        $this->assertEquals(3, count($i->line_items));
        $this->assertEquals(21, $i->amount);

        // $invoice_items = collect($invoice_items)->filter(function ($item) {
        //     return $item->type_id != '3';
        // });

        // $this->invoice->line_items = $invoice_items;

    }

    public function testLateFeeRemovals()
    {

        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 0.00;
        $data[1]['fee_percent'] = 1;
        $data[1]['fee_tax_name1'] = '';
        $data[1]['fee_tax_rate1'] = 0;
        $data[1]['fee_tax_name2'] = '';
        $data[1]['fee_tax_rate2'] = 0;
        $data[1]['fee_tax_name3'] = '';
        $data[1]['fee_tax_rate3'] = 0;
        $data[1]['adjust_fee_percent'] = false;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $cg = new \App\Models\CompanyGateway();
        $cg->company_id = $this->company->id;
        $cg->user_id = $this->user->id;
        $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $cg->require_cvv = true;
        $cg->require_billing_address = true;
        $cg->require_shipping_address = true;
        $cg->update_details = true;
        $cg->config = encrypt(config('ninja.testvars.stripe'));
        $cg->fees_and_limits = $data;
        $cg->save();

        $cgt = ClientGatewayTokenFactory::create($this->company->id);
        $cgt->client_id = $this->client->id;
        $cgt->token = '';
        $cgt->gateway_customer_reference = '';
        $cgt->gateway_type_id = 1;
        $cgt->company_gateway_id = $cg->id;
        $cgt->save();


        $i = Invoice::factory()->create([
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
            'discount' => 0,
        ]);

        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->type_id = '1';

        $line_items[] = $item;

        $item = new InvoiceItem();
        $item->type_id = '5';
        $item->product_key = trans('texts.fee');
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 1;
        $item->type_id = '3';

        $line_items[] = $item;

        $i->line_items = $line_items;

        $i = $i->calc()->getInvoice();

        $this->assertEquals(3, count($i->line_items));
        $this->assertEquals(21, $i->amount);

        $invoice_items = (array) $i->line_items;

        $invoice_items = collect($invoice_items)->filter(function ($item) {
            return $item->type_id != '3';
        });

        $i->line_items = $invoice_items;

        $i = $i->calc()->getInvoice();

        $this->assertEquals(20, $i->amount);

        $i->line_items = collect($i->line_items)
                                    ->reject(function ($item) {
                                        return $item->type_id == '3';
                                    })->toArray();

        $this->assertEquals(2, count($i->line_items));

        $i->service()->autoBill();

        $i = $i->fresh();

        $this->assertCount(2, $i->line_items);
        $this->assertEquals(20, $i->amount);

        $line_items = $i->line_items;

        $item = new InvoiceItem();
        $item->type_id = '5';
        $item->product_key = trans('texts.fee');
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        $i->line_items = $line_items;

        $i = $i->calc()->getInvoice();
        $this->assertEquals(30, $i->amount);
        $this->assertCount(3, $i->line_items);

        $i->service()->autoBill();
        $i = $i->fresh();

        $this->assertEquals(30, $i->amount);
        $this->assertCount(3, $i->line_items);

    }

    public function testLateFeeAdded()
    {

        $this->travelTo(now()->startOfDay()->subDays(10));

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;
        $settings->late_fee_amount1 = 10;
        $settings->late_fee_percent1 = 0;
        $settings->lock_invoices = 'off';
        $settings->enable_reminder1 = true;
        $settings->num_days_reminder1 = 10;
        $settings->schedule_reminder1 = 'after_due_date';
        $settings->entity_send_time = 0;

        $client = $this->buildData($settings);

        $i = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->startOfDay()->format('Y-m-d'),
            'due_date' => now()->startOfDay()->addDays(10)->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
            'last_sent_date' => now()->format('Y-m-d'),
        ]);

        $i = $i->calc()->getInvoice();
        $i->service()->markSent()->setReminder($client->getMergedSettings())->applyNumber()->createInvitations()->save();

        $this->assertNotNull($i->next_send_date);

        $this->travelBack();
        $this->travelTo(now()->addDays(10)->startOfDay());

        $i = $i->fresh();

        $this->assertEquals(10, $i->amount);
        $this->assertEquals(10, $i->balance);

        $reflectionMethod = new \ReflectionMethod(ReminderJob::class, 'sendReminderForInvoice');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs(new ReminderJob(), [$i]);

        $i = $i->refresh();

        $this->assertNotNull($i->reminder1_sent);
        $this->assertEquals(20, $i->balance);

        $this->travelBack();

    }


    public function testLateFeeAddedWithCustomSendTime()
    {

        $this->travelTo(now()->startOfDay()->subDays(10));
    
        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;
        $settings->late_fee_amount1 = 10;
        $settings->late_fee_percent1 = 0;
        $settings->lock_invoices = 'off';
        $settings->enable_reminder1 = true;
        $settings->num_days_reminder1 = 10;
        $settings->schedule_reminder1 = 'after_due_date';
        $settings->entity_send_time = 6;

        $client = $this->buildData($settings);

        $i = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => 2,
            'total_taxes' => 1,
            'date' => now()->startOfDay()->format('Y-m-d'),
            'due_date' => now()->startOfDay()->addDays(10)->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
            'last_sent_date' => now()->format('Y-m-d'),
        ]);

        $i = $i->calc()->getInvoice();
        $i->service()->markSent()->setReminder($client->getMergedSettings())->applyNumber()->createInvitations()->save();

        $this->assertNotNull($i->next_send_date);

        $this->travelBack();
        $this->travelTo(now()->addDays(10)->startOfDay());

        $i = $i->fresh();

        $this->assertEquals(10, $i->amount);
        $this->assertEquals(10, $i->balance);

        $x = false;

        do {
            
            (new ReminderJob())->handle();
            $invoice = $i->fresh();

            $x = (bool)$invoice->reminder1_sent;
            $this->travelTo(now()->addHour());

        }while($x === false);
        
        $i = $i->fresh();
        
        $this->assertNotNull($i->reminder1_sent);
        $this->assertEquals(20, $i->balance);

        $this->travelBack();

    }



    public function testLateFeeAddedToNewInvoiceWithLockedInvoiceConfig()
    {

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;
        $settings->late_fee_amount1 = 10;
        $settings->late_fee_percent1 = 0;
        $settings->lock_invoices = 'when_sent';
        $settings->enable_reminder1 = true;
        $settings->num_days_reminder1 = 10;
        $settings->schedule_reminder1 = 'after_due_date';
        $settings->entity_send_time = 0;

        $client = $this->buildData($settings);

        $i = Invoice::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'amount' => 0,
            'balance' => 0,
            'status_id' => Invoice::STATUS_DRAFT,
            'total_taxes' => 1,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->subDays(10)->format('Y-m-d'),
            'terms' => 'nada',
            'discount' => 0,
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'uses_inclusive_taxes' => false,
            'line_items' => $this->buildLineItems(),
        ]);

        $i = $i->calc()->getInvoice();
        $i->service()->applyNumber()->createInvitations()->markSent()->save();

        $this->assertEquals(10, $i->amount);
        $this->assertEquals(10, $i->balance);
        $this->assertEquals(10, $client->fresh()->balance);

        $reflectionMethod = new \ReflectionMethod(ReminderJob::class, 'sendReminderForInvoice');
        $reflectionMethod->setAccessible(true);
        $reflectionMethod->invokeArgs(new ReminderJob(), [$i]);

        $i->fresh();

        $this->assertEquals(10, $i->balance);
        $this->assertEquals(20, $client->fresh()->balance);
    }


    public function testLateFeeBalances()
    {
        $this->assertEquals(10, $this->client->balance);
        $this->assertEquals(10, $this->invoice->balance);

        $this->invoice = $this->setLateFee($this->invoice, 5, 0);

        $this->assertEquals(15, $this->client->fresh()->balance);
        $this->assertEquals(15, $this->invoice->fresh()->balance);
    }

    private function setLateFee($invoice, $amount, $percent): Invoice
    {
        $temp_invoice_balance = $invoice->balance;

        if ($amount <= 0 && $percent <= 0) {
            return $invoice;
        }

        $fee = $amount;

        if ($invoice->partial > 0) {
            $fee += round($invoice->partial * $percent / 100, 2);
        } else {
            $fee += round($invoice->balance * $percent / 100, 2);
        }

        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = '5';
        $invoice_item->product_key = trans('texts.fee');
        $invoice_item->notes = ctrans('texts.late_fee_added', ['date' => now()]);
        $invoice_item->quantity = 1;
        $invoice_item->cost = $fee;

        $invoice_items = $invoice->line_items;
        $invoice_items[] = $invoice_item;

        $invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $invoice = $invoice->calc()->getInvoice();

        $invoice->client->service()->updateBalance($invoice->balance - $temp_invoice_balance)->save();
        $invoice->ledger()->updateInvoiceBalance($invoice->balance - $temp_invoice_balance, "Late Fee Adjustment for invoice {$invoice->number}");

        return $invoice;
    }
}

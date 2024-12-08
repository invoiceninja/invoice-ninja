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

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\InvoiceRepository;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Helpers\Invoice\InvoiceSum
 */
class InvoiceTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $invoice;

    public $invoice_calc;

    public $settings;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->invoice->line_items = $this->buildLineItems();

        $this->invoice->uses_inclusive_taxes = true;

        $this->invoice_calc = new InvoiceSum($this->invoice);
    }


    public function testInvoiceItemRoundingWithDiscountIsPercent()
    {

        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        // $item = InvoiceItemFactory::create();
        // $item->quantity = 1;
        // $item->cost = 21.00;
        // $item->tax_name1 = 'mwst';
        // $item->tax_rate1 = 19;
        // $item->type_id = '1';
        // $item->tax_id = '1';
        // $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1.75;
        $item->cost = 49.58;
        $item->tax_name1 = 'mwst';
        $item->tax_rate1 = 19;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;


        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
            'is_amount_discount' => false
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();
        $ii = $ii->service()->markSent()->save();

        $this->assertEquals(86.77, $ii->calc()->getSubTotal());
        $this->assertEquals(16.49, $ii->total_taxes);
    }

    public function testInvoiceItemRoundingWithDiscountIsAmount()
    {

        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        // $item = InvoiceItemFactory::create();
        // $item->quantity = 1;
        // $item->cost = 21.00;
        // $item->tax_name1 = 'mwst';
        // $item->tax_rate1 = 19;
        // $item->type_id = '1';
        // $item->tax_id = '1';
        // $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1.75;
        $item->cost = 49.58;
        $item->tax_name1 = 'mwst';
        $item->tax_rate1 = 19;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;


        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
            'is_amount_discount' => true
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();
        $ii = $ii->service()->markSent()->save();

        $this->assertEquals(86.77, $ii->calc()->getSubTotal());
        $this->assertEquals(16.49, $ii->total_taxes);
    }


    public function testDeletingCancelledAndTrashedInvoicePayment()
    {
        
        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10.00;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;

        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();

        $ii = $ii->service()->markSent()->save();

        $this->assertEquals(10, $ii->balance);
        $this->assertEquals(2, $ii->status_id);

        $data = [
            'client_id' => $c->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => 5,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $payment_data = $response->json();

        $payment_hashed_id = $payment_data['data']['id'];

        $ii = $ii->fresh();

        $this->assertEquals(5, $ii->balance);
        $this->assertEquals(5, $ii->paid_to_date);
        $this->assertEquals(3, $ii->status_id);

        $ii->service()->handleCancellation()->save();

        $this->assertEquals(0, $ii->balance);
        $this->assertEquals(5, $ii->paid_to_date);
        $this->assertEquals(5, $ii->status_id);

        $repo = new InvoiceRepository();
        $repo->archive($ii);

        $payment = Payment::find($this->decodePrimaryKey($payment_hashed_id));
        $payment->service()->deletePayment(false)->save();

        $ii = $ii->fresh();
        
        $this->assertEquals(0, $ii->balance);
        $this->assertEquals(0, $ii->paid_to_date);
        $this->assertEquals(5, $ii->status_id);

    }

    public function testDeletingCancelledInvoicePayment()
    {
        
        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10.00;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;

        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();

        $ii = $ii->service()->markSent()->save();

        $this->assertEquals(10, $ii->balance);
        $this->assertEquals(2, $ii->status_id);

        $data = [
            'client_id' => $c->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $i->hashed_id,
                    'amount' => 5,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $payment_data = $response->json();

        $payment_hashed_id = $payment_data['data']['id'];

        $ii = $ii->fresh();

        $this->assertEquals(5, $ii->balance);
        $this->assertEquals(5, $ii->paid_to_date);
        $this->assertEquals(3, $ii->status_id);

        $ii->service()->handleCancellation()->save();

        $this->assertEquals(0, $ii->balance);
        $this->assertEquals(5, $ii->paid_to_date);
        $this->assertEquals(5, $ii->status_id);

        $payment = Payment::find($this->decodePrimaryKey($payment_hashed_id));
        $payment->service()->deletePayment(false)->save();

        $ii = $ii->fresh();
        
        $this->assertEquals(0, $ii->balance);
        $this->assertEquals(0, $ii->paid_to_date);
        $this->assertEquals(5, $ii->status_id);

    }

    public function testRefundPaidToDateRelation()
    {
                
        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10.00;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;

        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
            'uses_inclusive_taxes' => false,
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();

        $ii = $ii->service()->markSent()->save();

        $this->assertEquals(10, $ii->balance);
        $this->assertEquals(2, $ii->status_id);
        
        $ii = $ii->service()->markPaid()->save();

        $this->assertEquals(10, $ii->amount);
        $this->assertEquals(0, $ii->balance);
        $this->assertEquals(10, $ii->paid_to_date);
        $this->assertEquals(4, $ii->status_id);

        $p = $ii->payments->first();

        $this->assertEquals(10, $p->amount);
        $this->assertEquals(10, $p->applied);
        $this->assertEquals(0, $p->refunded);

        $refund_data['gateway_refund']=false;
        $refund_data['invoices'][] = [
            'invoice_id' => $ii->id,
            'amount' => 10
        ];

        $p->service()->refundPayment($refund_data);

        $ii = $ii->fresh();

        $this->assertEquals(2, $ii->status_id);
        $this->assertEquals(10, $ii->balance);
        $this->assertEquals(0, $ii->paid_to_date);

    }

    public function testRappenRounding()
    {

        $c_settings = $this->client->settings;
        $c_settings->enable_rappen_rounding = true;

        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $c_settings,
        ]);

        $this->assertEquals(0, $c->balance);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10.01;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;

        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();

        $this->assertEquals(10, $ii->amount);

        $ii->service()->markSent()->save();

        $this->assertEquals(10, $c->fresh()->balance);

    }

    public function testRappenRoundingUp()
    {

        $c_settings = $this->client->settings;
        $c_settings->enable_rappen_rounding = true;

        $c = \App\Models\Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'settings' => $c_settings,
        ]);

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10.09;
        $item->type_id = '1';
        $item->tax_id = '1';
        $line_items[] = $item;

        $i = Invoice::factory()->create([
            'discount' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'line_items' => $line_items,
            'status_id' => 1,
        ]);

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();

        $this->assertEquals(10.10, round($ii->amount, 2));

        $ii->service()->markSent()->save();

        $this->assertEquals(10.10, $c->fresh()->balance);

        $item = InvoiceItemFactory::create();
        $item->quantity = 2;
        $item->cost = 10.09;
        $item->type_id = '1';
        $item->tax_id = '1';

        $i->line_items = [$item];

        $invoice_calc = new InvoiceSum($i);
        $ii = $invoice_calc->build()->getInvoice();

        $ii->client->service()->calculateBalance($ii);

        $this->assertEquals(20.20, round($ii->amount, 2));
        $this->assertEquals(20.20, round($ii->balance, 2));
        $this->assertEquals(20.20, round($c->fresh()->balance, 2));
    }

    public function testPartialDueDateCast()
    {
        $i = Invoice::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id
        ]);

        $this->assertNull($i->partial_due_date);

        $i = Invoice::factory()
        ->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'partial_due_date' => '2023-10-10',
        ]);

        $this->assertEquals('2023-10-10', $i->partial_due_date->format('Y-m-d'));
    }

    public function testMarkPaidWithPartial()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 50;
        $line_items[] = $item;

        $this->invoice->partial = 5;
        $this->invoice->partial_due_date = now()->addDay();
        $this->invoice->due_date = now()->addDays(10);
        $this->invoice->line_items = $line_items;
        $this->invoice->save();

        $invoice_calc = new InvoiceSum($this->invoice);

        /** @var \App\Models\Invoice $ii */
        $ii = $invoice_calc->build()->getInvoice();
        $invoice = $ii->service()->markSent()->save();

        $this->assertEquals(5, $invoice->partial);
        $this->assertNotNull($invoice->partial_due_date);
        $this->assertEquals(50, $invoice->amount);

        $invoice = $invoice->service()->markPaid()->save();

        $this->assertEquals(0, $invoice->partial);
        $this->assertEquals(0, $invoice->balance);

        $this->assertNull($invoice->partial_due_date);
    }

    public function testSurchargesAndTaxes()
    {

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->discount = 0;
        $invoice->is_amount_discount = true;
        $invoice->status_id = 2;
        $invoice->tax_name1 = 'GST';
        $invoice->tax_rate1 = 10;
        $invoice->custom_surcharge1 = 100;
        $invoice->custom_surcharge_tax1 = true;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'line1';
        $line_item->notes = 'Test';
        $line_item->tax_id = 1;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $calc = $invoice->calc();
        $invoice = $calc->getInvoice();

        $this->assertEquals(200, $invoice->amount);
        $this->assertEquals(200, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(18.18, $calc->getTotalTaxes());

    }

    public function testSurchargesAndTaxes2()
    {

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->discount = 0;
        $invoice->is_amount_discount = true;
        $invoice->status_id = 2;
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->custom_surcharge1 = 100;
        $invoice->custom_surcharge_tax1 = true;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'line1';
        $line_item->notes = 'Test';
        $line_item->tax_id = 1;
        $line_item->tax_name1 = 'GST';
        $line_item->tax_rate1 = 10;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $calc = $invoice->calc();
        $invoice = $calc->getInvoice();

        $this->assertEquals(200, $invoice->amount);
        $this->assertEquals(200, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(9.09, $calc->getTotalTaxes());

    }


    public function testSurchargesAndTaxesExclusive()
    {

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 0;
        $invoice->is_amount_discount = true;
        $invoice->status_id = 2;
        $invoice->tax_name1 = 'GST';
        $invoice->tax_rate1 = 10;
        $invoice->custom_surcharge1 = 10;
        $invoice->custom_surcharge_tax1 = true;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 10;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'line1';
        $line_item->notes = 'Test';
        $line_item->tax_id = 1;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $calc = $invoice->calc();
        $invoice = $calc->getInvoice();

        $this->assertEquals(22, $invoice->amount);
        $this->assertEquals(22, $invoice->balance);
        $this->assertEquals(0, $invoice->paid_to_date);
        $this->assertEquals(2, $calc->getTotalTaxes());

    }


    public function testGrossTaxAmountCalcuations()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->discount = 14191;
        $invoice->is_amount_discount = true;
        $invoice->status_id = 2;
        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 8000;
        $line_item->tax_rate1 = 5;
        $line_item->tax_name1 = 'CA';
        $line_item->product_key = 'line1';
        $line_item->notes = 'Test';
        $line_item->tax_id = 1;
        $line_items[] = $line_item;

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 9500;
        $line_item->tax_rate1 = 5;
        $line_item->tax_name1 = 'CA';
        $line_item->product_key = 'line2';
        $line_item->notes = 'Test';
        $line_item->tax_id = 1;

        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $calc = $invoice->calc();
        $invoice = $calc->getInvoice();

        $this->assertEquals(3474.45, $invoice->amount);
        $this->assertEquals(14191, $invoice->discount);
        $this->assertEquals(165.45, $invoice->total_taxes);

        $item = collect($invoice->line_items)->firstWhere('product_key', 'line1');
        $this->assertEquals(75.63, $item->tax_amount);
        $this->assertEquals(8075.63, $item->gross_line_total);

        $item = collect($invoice->line_items)->firstWhere('product_key', 'line2');
        $this->assertEquals(89.82, $item->tax_amount);
        $this->assertEquals(9589.82, $item->gross_line_total);


    }

    public function testTaskRoundingPrecisionThree()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 25;
        $line_item->cost = 0.333;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem();
        $line_item->quantity = 25;
        $line_item->cost = 0.333;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem();
        $line_item->quantity = 25;
        $line_item->cost = 1.333;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem();
        $line_item->quantity = 25;
        $line_item->cost = 0.267;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $line_item = new InvoiceItem();
        $line_item->quantity = 25;
        $line_item->cost = 0.05;
        $line_item->tax_rate1 = 0;
        $line_item->tax_name1 = '';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(57.92, $invoice->amount);

    }

    public function testRoundingWithLargeUnitCostPrecision()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 82.6446;
        $line_item->tax_rate1 = 21;
        $line_item->tax_name1 = 'Test';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(99.99, $invoice->amount);

    }

    public function testInclusiveRounding()
    {
        $this->invoice->line_items = [];
        $this->invoice->discount = 0;
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->save();


        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 50;
        $item->tax_name1 = "taxy";
        $item->tax_rate1 = 19;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 50;
        $item->tax_name1 = "taxy";
        $item->tax_rate1 = 19;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;
        $this->invoice->save();

        $invoice_calc = new InvoiceSumInclusive($this->invoice);

        $invoice_calc->build();
        // $this->invoice->save();

        $this->assertEquals($invoice_calc->getTotalTaxes(), 15.96);
    }

    private function buildLineItems()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;

        $line_items[] = $item;

        return $line_items;
    }

    public function testInvoiceTotals()
    {
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
    }

    public function testInvoiceTotalsWithDiscount()
    {
        $this->invoice->discount = 5;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotal(), 15);
        //$this->assertEquals($this->invoice_calc->getBalance(), 15);
    }

    public function testInvoiceTotalsWithDiscountWithSurcharge()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithInclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        // $this->assertEquals($this->invoice_calc->getTotal(), 21.5);
        //$this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithExclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->uses_inclusive_taxes = false;
        $this->invoice->is_amount_discount = true;

        //$this->invoice_calc = new InvoiceSum($this->invoice, $this->settings);

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        // $this->assertEquals($this->invoice_calc->getGrossSubTotal(), 22);
        $this->assertEquals($this->invoice_calc->getTotal(), 21.5);
        //$this->assertEquals($this->invoice_calc->getBalance(), 21.5);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.5);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithDoubleExclusiveTax()
    {
        $this->invoice_calc = new InvoiceSum($this->invoice);

        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_name2 = 'GST';
        $this->invoice->tax_rate2 = 10;
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 23);
        //$this->assertEquals($this->invoice_calc->getBalance(), 23);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 3);
    }

    public function testLineItemTaxRatesInclusiveTaxes()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->custom_value1 = 0;

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getBalance(), 20);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.82);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
    }

    public function testLineItemTaxRatesExclusiveTaxes()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;
        $this->invoice->discount = 0;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_name2 = 'GST';
        $this->invoice->tax_rate2 = 10;

        $this->invoice->uses_inclusive_taxes = false;
        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        // $this->assertEquals($this->invoice_calc->getGrossSubTotal(), 22);
        $this->assertEquals($this->invoice_calc->getTotal(), 26);
        //$this->assertEquals($this->invoice_calc->getBalance(), 26);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 4);
        //$this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
    }
}

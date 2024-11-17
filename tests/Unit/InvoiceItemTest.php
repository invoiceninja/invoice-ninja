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
use App\Helpers\Invoice\InvoiceItemSum;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Helpers\Invoice\InvoiceItemSum
 */
class InvoiceItemTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();
    }


    public function testEdgeCasewithDiscountsPercentageAndTaxCalculations()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = 0;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->tax_name1 = '';
        $invoice->tax_name2 = '';
        $invoice->tax_name3 = '';

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 22;
        $line_item->tax_name1 = 'Km';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_item->is_amount_discount = false;
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(122, $invoice->amount);
        $this->assertEquals(22, $invoice->total_taxes);
    }


    public function testDiscountsWithInclusiveTaxes()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = true;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(90, $invoice->amount);
        $this->assertEquals(8.18, $invoice->total_taxes);
    }


    public function testDiscountsWithInclusiveTaxesNegativeInvoice()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = true;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-90, $invoice->amount);
        $this->assertEquals(-8.18, $invoice->total_taxes);
    }

    public function testDicountsWithTaxes()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = true;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(99, $invoice->amount);
        $this->assertEquals(9, $invoice->total_taxes);
    }


    public function testDicountsWithTaxesNegativeInvoice()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = true;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-99, $invoice->amount);
        $this->assertEquals(-9, $invoice->total_taxes);
    }

    public function testDicountsWithTaxesPercentage()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(99, $invoice->amount);
        $this->assertEquals(9, $invoice->total_taxes);
    }

    public function testDicountsWithTaxesPercentageOnLine()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = true;
        $invoice->is_amount_discount = false;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->is_amount_discount = false;
        $line_item->discount = 10;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(81, $invoice->amount);
        $this->assertEquals(7.36, $invoice->total_taxes);
    }

    public function testDicountsWithExclusiveTaxesPercentageOnLine()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->is_amount_discount = false;
        $line_item->discount = -10;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-133.1, $invoice->amount);
        $this->assertEquals(-12.1, $invoice->total_taxes);
    }


    public function testDicountsWithTaxesNegativeInvoicePercentage()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = false;
        $invoice->discount = -10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = -1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(-121, $invoice->amount);
        $this->assertEquals(-10, $invoice->discount);
        $this->assertEquals(-11, $invoice->total_taxes);
    }



    public function testDicountPercentageWithTaxes()
    {
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id);
        $invoice->client_id = $this->client->id;
        $invoice->uses_inclusive_taxes = false;
        $invoice->is_amount_discount = true;
        $invoice->discount = 10;

        $line_items = [];

        $line_item = new InvoiceItem();
        $line_item->quantity = 1;
        $line_item->cost = 100;
        $line_item->tax_rate1 = 10;
        $line_item->tax_name1 = 'GST';
        $line_item->product_key = 'Test';
        $line_item->notes = 'Test';
        $line_items[] = $line_item;

        $invoice->line_items = $line_items;
        $invoice->save();

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(99, $invoice->amount);
        $this->assertEquals(9, $invoice->total_taxes);
    }




    public function testInvoiceItemTotalSimple()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 10);
    }

    public function testInvoiceItemTotalSimpleWithGrossTaxes()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->tax_rate1 = 10;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 10);
        $this->assertEquals($item_calc->getGrossLineTotal(), 11);
    }

    public function testInvoiceItemTotalSimpleWithDiscount()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 8);
    }

    public function testInvoiceItemTotalSimpleWithDiscountAndGrossLineTotal()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;
        $item->tax_rate1 = 10;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 8);
        $this->assertEquals($item_calc->getGrossLineTotal(), 8.8);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecision()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getLineTotal(), 7.48);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2;
        $item->tax_rate1 = 10;

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $this->invoice->line_items = [$item];

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.80);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithSingleExclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;
        $item->tax_rate1 = 10;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 0.75);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleInclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;
        $item->tax_rate1 = 10;
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = true;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        $this->assertEquals($item_calc->getTotalTaxes(), 2.06);
    }

    public function testInvoiceItemTotalSimpleWithDiscountWithPrecisionWithDoubleExclusiveTax()
    {
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->is_amount_discount = true;
        $item->discount = 2.521254522145214511;
        $item->tax_name1 = 'GST';
        $item->tax_rate1 = 10;
        $item->tax_name2 = 'VAT';
        $item->tax_rate2 = 17.5;

        $this->invoice->line_items = [$item];

        $settings = new \stdClass();
        $settings->inclusive_taxes = false;
        $settings->precision = 2;

        $item_calc = new InvoiceItemSum($this->invoice, $settings);
        $item_calc->process();

        nlog($item_calc->getGroupedTaxes());
        
        $this->assertEquals($item_calc->getTotalTaxes(), 2.06);
        $this->assertEquals($item_calc->getGroupedTaxes()->count(), 2);
    }
}

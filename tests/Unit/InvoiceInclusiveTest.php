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

use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSumInclusive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *   App\Helpers\Invoice\InvoiceSumInclusive
 */
class InvoiceInclusiveTest extends TestCase
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

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
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

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals(round($this->invoice_calc->getSubTotal(),0), 20);
        $this->assertEquals(round($this->invoice_calc->getTotal(),0), 20);
    }

    public function testInvoiceTotalsWithDiscount()
    {
        $this->invoice->discount = 5;


        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 15);
        $this->assertEquals($this->invoice_calc->getBalance(), 15);
    }

    public function testInvoiceTotalsWithDiscountWithSurcharge()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;


        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithInclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->custom_surcharge_tax1 = false;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->is_amount_discount = true;
        $this->invoice->line_items = $this->buildLineItems();

        $calc = $this->invoice->calc();

        $this->assertEquals($calc->getSubTotal(), 20);
        $this->assertEquals($calc->getTotalTaxes(), 1.36);
        $this->assertEquals($calc->getTotal(), 20);
        $this->assertEquals($calc->getBalance(), 20);
    }

    public function testInvoiceTotalsWithPercentDiscountWithSurchargeWithInclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->is_amount_discount = false;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.73);
        $this->assertEquals($this->invoice_calc->getTotal(), 24);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithExclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->is_amount_discount = true;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        //$this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.5);
    }

    public function testInvoiceTotalsWithDiscountWithSurchargeWithDoubleExclusiveTax()
    {
        $this->invoice->discount = 5;
        $this->invoice->custom_surcharge1 = 5;
        $this->invoice->tax_name1 = 'GST';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_name2 = 'GST';
        $this->invoice->tax_rate2 = 10;
        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->is_amount_discount = true;


        $this->invoice_calc = new InvoiceSumInclusive($this->invoice);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 2.72);
    }

    public function testLineItemTaxRatesInclusiveTaxes()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->tax_rate2 = 0;
        $item->tax_name2 = '';
        $item->tax_rate3 = 0;
        $item->tax_name3 = '';
        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->tax_rate2 = 0;
        $item->tax_name2 = '';
        $item->tax_rate3 = 0;
        $item->tax_name3 = '';

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 0;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 20);
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.82);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxes()
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
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_name1 = 'dog';
        $this->invoice->tax_name2 = 'cat';
        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;

        $this->invoice_calc = null;
        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $this->assertEquals(20, round($this->invoice_calc->getSubTotal(),0));
        $this->assertEquals(5.46, $this->invoice_calc->getTotalTaxes());
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 20);
        $this->assertEquals($this->invoice_calc->getBalance(), 20);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxesAndDiscounts()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 'a10';
        $item->discount = 5;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 'a10';
        $item->discount = 5;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 5;
        $this->invoice->is_amount_discount = false;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;
        $this->invoice->tax_name1 = 'VAT';
        $this->invoice->tax_name2 = 'VAT';

        $this->invoice_calc = null;
        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $line_items = $this->invoice_calc->invoice_items->getLineItems();
        // nlog($this->invoice_calc->getTaxMap());

        $this->assertEquals(19, $this->invoice_calc->getSubTotal());
        $this->assertEquals(0.95, $this->invoice_calc->getTotalDiscount());
        $this->assertEquals(4.92, $this->invoice_calc->getTotalTaxes());


        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 18.05);
        $this->assertEquals($this->invoice_calc->getBalance(), 18.05);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxesAndAmountDiscounts()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 5;
        $this->invoice->is_amount_discount = true;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;

        $this->invoice->tax_name1 = 'dog';
        $this->invoice->tax_name2 = 'cat';

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $line_items = $this->invoice_calc->invoice_items->getLineItems();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 10);
        $this->assertEquals($this->invoice_calc->getTotalDiscount(), 5);
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 1.36);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 5);
        $this->assertEquals($this->invoice_calc->getBalance(), 5);
    }

    public function testLineItemTaxRatesInclusiveTaxesWithInvoiceTaxesAndAmountDiscountsWithLargeCosts()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100;
        $item->tax_rate1 = 10;
        $item->tax_name1 = 10;
        $item->discount = 5;

        $line_items[] = $item;

        $this->invoice->line_items = $line_items;

        $this->invoice->uses_inclusive_taxes = true;
        $this->invoice->discount = 5;
        $this->invoice->is_amount_discount = true;
        $this->invoice->custom_surcharge1 = 0;

        $this->invoice->tax_rate1 = 10;
        $this->invoice->tax_rate2 = 10;

        $this->invoice->tax_name1 = 'dog';
        $this->invoice->tax_name2 = 'cat';

        $this->invoice_calc = new InvoiceSumInclusive($this->invoice, $this->settings);
        $this->invoice_calc->build();

        $line_items = $this->invoice_calc->invoice_items->getLineItems();

        $this->assertEquals($this->invoice_calc->getSubTotal(), 190);
        $this->assertEquals($this->invoice_calc->getTotalDiscount(), 5);
        $this->assertEquals($this->invoice_calc->getTotalTaxes(), 50.46);
        $this->assertEquals(count($this->invoice_calc->getTaxMap()), 1);
        $this->assertEquals($this->invoice_calc->getTotal(), 185);
        $this->assertEquals($this->invoice_calc->getBalance(), 185);
    }
}

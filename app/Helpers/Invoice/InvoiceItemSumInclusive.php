<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Invoice;

use App\Models\Quote;
use App\Utils\Number;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\RecurringQuote;
use App\Models\RecurringInvoice;
use App\DataMapper\Tax\RuleInterface;
use App\Utils\Traits\NumberFormatter;

class InvoiceItemSumInclusive
{
    use NumberFormatter;
    use Discounter;
    use Taxer;

    //@phpstan-ignore-next-line
    private array $eu_tax_jurisdictions = [
        'AT', // Austria
        'BE', // Belgium
        'BG', // Bulgaria
        'CY', // Cyprus
        'CZ', // Czech Republic
        'DE', // Germany
        'DK', // Denmark
        'EE', // Estonia
        'ES', // Spain
        'FI', // Finland
        'FR', // France
        'GR', // Greece
        'HR', // Croatia
        'HU', // Hungary
        'IE', // Ireland
        'IT', // Italy
        'LT', // Lithuania
        'LU', // Luxembourg
        'LV', // Latvia
        'MT', // Malta
        'NL', // Netherlands
        'PL', // Poland
        'PT', // Portugal
        'RO', // Romania
        'SE', // Sweden
        'SI', // Slovenia
        'SK', // Slovakia
    ];

    private array $tax_jurisdictions = [
        'AT', // Austria
        'BE', // Belgium
        'BG', // Bulgaria
        'CY', // Cyprus
        'CZ', // Czech Republic
        'DE', // Germany
        'DK', // Denmark
        'EE', // Estonia
        'ES', // Spain
        'FI', // Finland
        'FR', // France
        'GR', // Greece
        'HR', // Croatia
        'HU', // Hungary
        'IE', // Ireland
        'IT', // Italy
        'LT', // Lithuania
        'LU', // Luxembourg
        'LV', // Latvia
        'MT', // Malta
        'NL', // Netherlands
        'PL', // Poland
        'PT', // Portugal
        'RO', // Romania
        'SE', // Sweden
        'SI', // Slovenia
        'SK', // Slovakia

        'US', // USA

        'AU', // Australia
    ];

    protected RecurringInvoice | Invoice | Quote | Credit | PurchaseOrder | RecurringQuote $invoice;

    private \App\Models\Currency $currency;

    private $total_taxes;

    /** @phpstan-ignore-next-line */
    private $item;

    private $line_items;

    private $sub_total;

    private $tax_collection;

    private bool $calc_tax = false;
    
    private $total_discount;

    private Client | Vendor $client;

    private RuleInterface $rule;

    public function __construct(RecurringInvoice | Invoice | Quote | Credit | PurchaseOrder | RecurringQuote $invoice)
    {
        $this->tax_collection = collect([]);
        $this->total_discount = 0;
        
        $this->invoice = $invoice;
        $this->client = $invoice->client ?? $invoice->vendor;

        if ($this->invoice->client) {
            $this->currency = $this->invoice->client->currency();
            $this->shouldCalculateTax();
        } else {
            $this->currency = $this->invoice->vendor->currency();
        }

        $this->line_items = [];
    }

    public function process()
    {
        if (!$this->invoice->line_items || ! is_iterable($this->invoice->line_items) || count($this->invoice->line_items) == 0) {
            return $this;
        }

        $this->calcLineItems();

        return $this;
    }

    private function calcLineItems()
    {
        foreach ($this->invoice->line_items as $this->item) {
            $this->sumLineItem()
                ->setDiscount()
                ->calcTaxes()
                ->push();
        }

        return $this;
    }

    private function push()
    {
        $this->sub_total += $this->getLineTotal();

        $this->line_items[] = $this->item;

        return $this;
    }

    private function sumLineItem()
    {
        $this->setLineTotal($this->item->cost * $this->item->quantity);

        return $this;
    }

    private function setDiscount()
    {
        if ($this->invoice->is_amount_discount) {
            $this->setLineTotal($this->getLineTotal() - $this->formatValue($this->item->discount, $this->currency->precision));
            $this->total_discount += $this->item->discount;
        } else {
            $this->setLineTotal($this->getLineTotal() - $this->formatValue(($this->item->line_total * ($this->item->discount / 100)), $this->currency->precision));
            $this->total_discount += ($this->item->line_total * ($this->item->discount / 100));
        }

        $this->item->is_amount_discount = $this->invoice->is_amount_discount;

        return $this;
    }


    /**
     * Attempts to calculate taxes based on the clients location
     *
     * @return self
     */
    private function calcTaxesAutomatically(): self
    {
        $this->rule->tax($this->item);

        $precision = strlen(substr(strrchr($this->rule->tax_rate1, "."), 1));

        $this->item->tax_name1 = $this->rule->tax_name1;
        $this->item->tax_rate1 = round($this->rule->tax_rate1, $precision);

        $precision = strlen(substr(strrchr($this->rule->tax_rate2, "."), 1));

        $this->item->tax_name2 = $this->rule->tax_name2;
        $this->item->tax_rate2 = round($this->rule->tax_rate2, $precision);

        $precision = strlen(substr(strrchr($this->rule->tax_rate3, "."), 1));

        $this->item->tax_name3 = $this->rule->tax_name3;
        $this->item->tax_rate3 = round($this->rule->tax_rate3, $precision);

        $this->invoice->tax_name1 = '';
        $this->invoice->tax_rate1 = 0;
        $this->invoice->tax_name2 = '';
        $this->invoice->tax_rate2 = 0;
        $this->invoice->tax_name3 = '';
        $this->invoice->tax_rate3 = 0;

        return $this;
    }


    /**
     * Taxes effect the line totals and item costs. we decrement both on
     * application of inclusive tax rates.
     */
    private function calcTaxes()
    {

        if ($this->calc_tax) {
            $this->calcTaxesAutomatically();
        }

        if ($this->client->is_tax_exempt) {
            $this->item->tax_rate1 = 0;
            $this->item->tax_rate2 = 0;
            $this->item->tax_rate3 = 0;
            $this->item->tax_name1 = '';
            $this->item->tax_name2 = '';
            $this->item->tax_name3 = '';
        }

        $item_tax = 0;

        $amount = $this->item->line_total - ($this->item->line_total * ($this->invoice->discount / 100));

        /** @var float $item_tax_rate1_total */
        $item_tax_rate1_total = $this->calcInclusiveLineTax($this->item->tax_rate1, $amount);

        /** @var float $item_tax */
        $item_tax += $this->formatValue($item_tax_rate1_total, $this->currency->precision);

        if (strlen($this->item->tax_name1) > 1) {
            $this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total, $amount, $this->item->tax_id ?? '1');
        }

        $item_tax_rate2_total = $this->calcInclusiveLineTax($this->item->tax_rate2, $amount);

        $item_tax += $this->formatValue($item_tax_rate2_total, $this->currency->precision);

        if (strlen($this->item->tax_name2) > 1) {
            $this->groupTax($this->item->tax_name2, $this->item->tax_rate2, $item_tax_rate2_total, $amount, $this->item->tax_id ?? '1');
        }

        $item_tax_rate3_total = $this->calcInclusiveLineTax($this->item->tax_rate3, $amount);

        $item_tax += $this->formatValue($item_tax_rate3_total, $this->currency->precision);

        if (strlen($this->item->tax_name3) > 1) {
            $this->groupTax($this->item->tax_name3, $this->item->tax_rate3, $item_tax_rate3_total, $amount, $this->item->tax_id ?? '1');
        }

        $this->item->tax_amount = $this->formatValue($item_tax, $this->currency->precision);

        try{
            $this->item->net_cost = round(($amount - $this->item->tax_amount)/$this->item->quantity, $this->currency->precision);
        } catch (\DivisionByZeroError $e) {
            $this->item->net_cost = $this->item->cost;
        }

        $this->setTotalTaxes($this->formatValue($item_tax, $this->currency->precision));

        return $this;
    }

    private function groupTax($tax_name, $tax_rate, $tax_total, $amount, $tax_id = '')
    {
        $group_tax = [];

        $key = str_replace(' ', '', $tax_name.$tax_rate);

        $group_tax = ['key' => $key, 'total' => $tax_total, 'tax_name' => $tax_name.' '.Number::formatValueNoTrailingZeroes(floatval($tax_rate), $this->client).'%', 'tax_id' => $tax_id, 'base_amount' => $amount];

        $this->tax_collection->push(collect($group_tax));
    }

    public function getTotalDiscount()
    {
        return $this->total_discount;
    }

    public function getTotalTaxes()
    {
        return $this->total_taxes;
    }

    public function setTotalTaxes($total)
    {
        $this->total_taxes = $total;

        return $this;
    }

    public function setLineTotal($total)
    {
        $this->item->gross_line_total = round(($total + 0.000000000000004),2);

        $this->item->line_total = round(($total + 0.000000000000004),2);

        return $this;
    }

    public function getLineTotal()
    {
        return $this->item->line_total;
    }

    public function getGrossLineTotal()
    {
        return $this->item->line_total;
    }

    public function getLineItems()
    {
        return $this->line_items;
    }

    public function getGroupedTaxes()
    {
        return $this->tax_collection;
    }

    public function setGroupedTaxes($group_taxes)
    {
        $this->tax_collection = $group_taxes;

        return $this;
    }

    public function getSubTotal()
    {
        return $this->sub_total;
    }

    public function getGrossSubTotal()
    {
        return $this->sub_total;
    }

    public function setSubTotal($value)
    {
        $this->sub_total = $value;

        return $this;
    }

    /**
     * Invoice Amount Discount.
     *
     * The problem, when calculating invoice level discounts,
     * the tax collected changes.
     *
     * We need to synthetically reduce the line_total amounts
     * and recalculate the taxes and then pass back
     * the updated map
     */
    public function calcTaxesWithAmountDiscount()
    {
        $this->setGroupedTaxes(collect([]));

        foreach ($this->line_items as $this->item) {
            if ($this->sub_total == 0) {
                $amount = $this->item->line_total;
            } else {
                $amount = $this->item->line_total - ($this->invoice->discount * ($this->item->line_total / $this->sub_total));
            }

            $item_tax = 0;

            $item_tax_rate1_total = $this->calcInclusiveLineTax($this->item->tax_rate1, $amount);

            $item_tax += $item_tax_rate1_total;

            if ($item_tax_rate1_total != 0) {
                $this->groupTax($this->item->tax_name1, $this->item->tax_rate1, $item_tax_rate1_total, $amount, $this->item->tax_id ?? '1');
            }

            $item_tax_rate2_total = $this->calcInclusiveLineTax($this->item->tax_rate2, $amount);

            $item_tax += $item_tax_rate2_total;

            if ($item_tax_rate2_total != 0) {
                $this->groupTax($this->item->tax_name2, $this->item->tax_rate2, $item_tax_rate2_total, $amount, $this->item->tax_id) ?? '1';
            }

            $item_tax_rate3_total = $this->calcInclusiveLineTax($this->item->tax_rate3, $amount);

            $item_tax += $item_tax_rate3_total;

            if ($item_tax_rate3_total != 0) {
                $this->groupTax($this->item->tax_name3, $this->item->tax_rate3, $item_tax_rate3_total, $amount, $this->item->tax_id) ?? '1';
            }

            $this->setTotalTaxes($this->getTotalTaxes() + $item_tax);
            $this->item->gross_line_total = $this->getLineTotal();

            $this->item->tax_amount = $item_tax;

            try{
                $this->item->net_cost = round($amount * (100 / (100 + ($this->item->tax_rate1+$this->item->tax_rate2+$this->item->tax_rate3))) / $this->item->quantity, $this->currency->precision+1);
                $this->item->net_cost = round($this->item->net_cost, $this->currency->precision);
            } catch (\DivisionByZeroError $e) {
                $this->item->net_cost = $this->item->cost;
            }

        }

        return $this;

        // $this->setTotalTaxes($item_tax);
    }


    private function shouldCalculateTax(): self
    {

        if (!$this->invoice->company?->calculate_taxes || $this->invoice->company->account->isFreeHostedClient()) {//@phpstan-ignore-line
            $this->calc_tax = false;
            return $this;
        }

        if (in_array($this->client->company->country()->iso_3166_2, $this->tax_jurisdictions)) { //only calculate for supported tax jurisdictions

            $class = "App\DataMapper\Tax\\".$this->client->company->country()->iso_3166_2."\\Rule";

            $this->rule = new $class();

            if ($this->rule->regionWithNoTaxCoverage($this->client->country->iso_3166_2 ?? false)) {
                return $this;
            }

            $this->rule
                 ->setEntity($this->invoice)
                 ->init();

            $this->calc_tax = $this->rule->shouldCalcTax();

            return $this;
        }

        return $this;
    }


}

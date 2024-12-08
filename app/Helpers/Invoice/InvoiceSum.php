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

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Vendor;
use App\Utils\Number;
use App\Utils\Traits\NumberFormatter;
use Illuminate\Support\Collection;

class InvoiceSum
{
    use Taxer;
    use CustomValuer;
    use Discounter;
    use NumberFormatter;

    protected RecurringInvoice | Invoice | Quote | Credit | PurchaseOrder | RecurringQuote $invoice;

    public $tax_map;

    public $invoice_item;

    public $total_taxes = 0;

    private $total = 0;

    private $total_discount = 0;

    private $total_custom_values;

    private $total_tax_map;

    private $sub_total = 0;

    private $gross_sub_total = 0;

    private $precision;

    private Client | Vendor $client;

    public InvoiceItemSum $invoice_items;

    private $rappen_rounding = false;
    /**
     * Constructs the object with Invoice and Settings object.
     *
     * @param RecurringInvoice | Invoice | Quote | Credit | PurchaseOrder | RecurringQuote $invoice;
     */
    public function __construct($invoice)
    {

        $this->invoice = $invoice;
        $this->client = $invoice->client ?? $invoice->vendor;

        $this->precision = $this->client->currency()->precision;
        $this->rappen_rounding = $this->client->getSetting('enable_rappen_rounding');

        $this->tax_map = new Collection();

    }

    public function build()
    {
        $this->calculateLineItems()
             ->calculateDiscount()
             ->calculateInvoiceTaxes()
             ->calculateCustomValues()
             ->setTaxMap()
             ->calculateTotals()
             ->calculateBalance()
             ->calculatePartial();

        return $this;
    }

    private function calculateLineItems(): self
    {
        $this->invoice_items = new InvoiceItemSum($this->invoice);
        $this->invoice_items->process();
        $this->invoice->line_items = $this->invoice_items->getLineItems();
        $this->total = $this->invoice_items->getSubTotal();
        $this->setSubTotal($this->invoice_items->getSubTotal());
        $this->setGrossSubTotal($this->invoice_items->getGrossSubTotal());

        return $this;
    }

    private function calculateDiscount(): self
    {
        $this->total_discount = $this->discount($this->invoice_items->getSubTotal());

        $this->total -= $this->total_discount;

        return $this;
    }

    private function calculateCustomValues(): self
    {
        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge1);

        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge2);

        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge3);

        $this->total_custom_values += $this->valuer($this->invoice->custom_surcharge4);

        $this->total += $this->total_custom_values;

        return $this;
    }

    private function calculateInvoiceTaxes(): self
    {
        if ($this->client->is_tax_exempt) {
            $this->invoice->tax_name1 = '';
            $this->invoice->tax_name2 = '';
            $this->invoice->tax_name3 = '';
            $this->invoice->tax_rate1 = 0;
            $this->invoice->tax_rate2 = 0;
            $this->invoice->tax_rate3 = 0;
            $this->total_taxes = 0;
            $this->total_tax_map = [];
            return $this;
        }

        if (is_string($this->invoice->tax_name1) && strlen($this->invoice->tax_name1) >= 2) {
            $tax = $this->taxer($this->total, $this->invoice->tax_rate1);
            $tax += $this->getSurchargeTaxTotalForKey($this->invoice->tax_name1, $this->invoice->tax_rate1);

            $this->total_taxes += $tax;
            $this->total_tax_map[] = ['name' => $this->invoice->tax_name1.' '.Number::formatValueNoTrailingZeroes(floatval($this->invoice->tax_rate1), $this->client).'%', 'total' => $tax];
        }

        if (is_string($this->invoice->tax_name2) && strlen($this->invoice->tax_name2) >= 2) {
            $tax = $this->taxer($this->total, $this->invoice->tax_rate2);
            $tax += $this->getSurchargeTaxTotalForKey($this->invoice->tax_name2, $this->invoice->tax_rate2);

            $this->total_taxes += $tax;
            $this->total_tax_map[] = ['name' => $this->invoice->tax_name2.' '.Number::formatValueNoTrailingZeroes(floatval($this->invoice->tax_rate2), $this->client).'%', 'total' => $tax];
        }

        if (is_string($this->invoice->tax_name3) && strlen($this->invoice->tax_name3) >= 2) {
            $tax = $this->taxer($this->total, $this->invoice->tax_rate3);
            $tax += $this->getSurchargeTaxTotalForKey($this->invoice->tax_name3, $this->invoice->tax_rate3);

            $this->total_taxes += $tax;
            $this->total_tax_map[] = ['name' => $this->invoice->tax_name3.' '.Number::formatValueNoTrailingZeroes(floatval($this->invoice->tax_rate3), $this->client).'%', 'total' => $tax];
        }

        return $this;
    }

    /**
     * Calculates the balance.
     *
     * @return     self  The balance.
     */
    private function calculateBalance(): self
    {
        $this->setCalculatedAttributes();

        return $this;
    }

    private function calculatePartial(): self
    {
        if (! isset($this->invoice->id) && isset($this->invoice->partial)) {
            $this->invoice->partial = max(0, min(Number::roundValue($this->invoice->partial, 2), $this->invoice->balance));
        }

        return $this;
    }

    private function calculateTotals(): self
    {
        $this->total += $this->total_taxes;

        return $this;
    }

    /**
     * Allow us to get the entity without persisting it
     * @return Invoice the temp
     */
    public function getTempEntity()
    {
        $this->setCalculatedAttributes();

        return $this->invoice;
    }

    public function getInvoice()
    {
        //Build invoice values here and return Invoice
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getQuote()
    {
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getCredit()
    {
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getPurchaseOrder()
    {
        $this->setCalculatedAttributes();
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    public function getRecurringInvoice()
    {

        $this->setCalculatedAttributes();
        $this->invoice->balance = $this->invoice->amount;
        $this->invoice->saveQuietly();

        return $this->invoice;
    }

    /**
     * Build $this->invoice variables after
     * calculations have been performed.
     */
    private function setCalculatedAttributes(): self
    {
        if ($this->invoice->status_id == Invoice::STATUS_CANCELLED) {
            $this->invoice->balance = 0;
        } elseif ($this->invoice->status_id != Invoice::STATUS_DRAFT) {
            if ($this->invoice->amount != $this->invoice->balance) {
                $this->invoice->balance = Number::roundValue($this->getTotal(), $this->precision) - $this->invoice->paid_to_date; //21-02-2024 cannot use the calculated $paid_to_date here as it could send the balance backward.
            } else {
                $this->invoice->balance = Number::roundValue($this->getTotal(), $this->precision);
            }
        }
        /* Set new calculated total */
        $this->invoice->amount = $this->formatValue($this->getTotal(), $this->precision);

        $this->invoice->total_taxes = $this->getTotalTaxes();

        if ($this->rappen_rounding) {
            $this->invoice->amount = $this->roundRappen($this->invoice->amount);
            $this->invoice->balance = $this->roundRappen($this->invoice->balance);
            $this->total = $this->roundRappen($this->total);
            $this->invoice->total_taxes = $this->roundRappen($this->invoice->total_taxes);
        }


        return $this;
    }


    public function roundRappen($value): float
    {
        return round($value / .05, 0) * .05;
    }

    public function getSubTotal()
    {
        return $this->sub_total;
    }

    public function setSubTotal($value)
    {
        $this->sub_total = $value;

        return $this;
    }

    public function getGrossSubTotal()
    {
        return $this->gross_sub_total;
    }

    public function setGrossSubTotal($value): self
    {
        $this->gross_sub_total = $value;

        return $this;
    }

    public function getTotalDiscount()
    {
        return $this->total_discount;
    }

    public function getTotalTaxes()
    {
        return $this->total_taxes;
    }

    public function getTotalTaxMap()
    {
        return $this->total_tax_map;
    }

    public function getTotal()
    {
        return $this->total;
    }

    public function getTotalSurcharges()
    {
        return $this->total_custom_values;
    }

    public function setTaxMap(): self
    {
        if ($this->invoice->is_amount_discount) {
            $this->invoice_items->calcTaxesWithAmountDiscount();
            $this->invoice->line_items = $this->invoice_items->getLineItems();
        }

        $this->tax_map = collect();

        $keys = $this->invoice_items->getGroupedTaxes()->pluck('key')->unique();

        $values = $this->invoice_items->getGroupedTaxes();

        foreach ($keys as $key) {
            $tax_name = $values->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->pluck('tax_name')->first();

            $tax_rate = $values->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->pluck('tax_rate')->first();

            $tax_id = $values->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->pluck('tax_id')->first();

            $total_line_tax = $values->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->sum('total');

            $base_amount = $values->filter(function ($value, $k) use ($key) {
                return $value['key'] == $key;
            })->sum('base_amount');

            $tax_id = $values->first()['tax_id'] ?? '';

            $this->tax_map[] = ['name' => $tax_name, 'total' => $total_line_tax, 'tax_id' => $tax_id, 'tax_rate' => $tax_rate, 'base_amount' => round($base_amount, 2)];

            $this->total_taxes += $total_line_tax;
        }

        return $this;
    }

    private function getSurchargeTaxTotalForKey($key, $rate)
    {
        $tax_component = 0;

        if ($this->invoice->custom_surcharge_tax1) {
            $tax_component += round($this->invoice->custom_surcharge1 * ($rate / 100), 2);
        }

        if ($this->invoice->custom_surcharge_tax2) {
            $tax_component += round($this->invoice->custom_surcharge2 * ($rate / 100), 2);
        }

        if ($this->invoice->custom_surcharge_tax3) {
            $tax_component += round($this->invoice->custom_surcharge3 * ($rate / 100), 2);
        }

        if ($this->invoice->custom_surcharge_tax4) {
            $tax_component += round($this->invoice->custom_surcharge4 * ($rate / 100), 2);
        }

        return $tax_component;
    }

    public function getTaxMap()
    {
        return $this->tax_map;
    }

    public function getBalance()
    {
        return $this->invoice->balance;
    }

    public function getItemTotalTaxes()
    {
        return $this->getTotalTaxes();
    }

    public function purgeTaxes(): self
    {

        $line_items = collect($this->invoice->line_items);

        $items = $line_items->map(function ($item) {
            $item->tax_rate1 = 0;
            $item->tax_rate2 = 0;
            $item->tax_rate3 = 0;
            $item->tax_name1 = '';
            $item->tax_name2 = '';
            $item->tax_name3 = '';
            $item->discount = 0;

            return $item;
        });

        $this->invoice->line_items = $items->toArray();

        $this->build();

        return $this;
    }

    public function getNetSubtotal()
    {
        return $this->getSubTotal() - $this->getTotalDiscount();
    }

    public function getSubtotalWithSurcharges()
    {
        return $this->getSubTotal() + $this->getTotalSurcharges();
    }
}

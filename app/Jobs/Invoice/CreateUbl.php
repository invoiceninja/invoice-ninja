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

namespace App\Jobs\Invoice;

use App\Models\Company;
use App\Models\Invoice;
use CleverIt\UBL\Invoice\Address;
use CleverIt\UBL\Invoice\Contact;
use CleverIt\UBL\Invoice\Country;
use CleverIt\UBL\Invoice\Generator;
use CleverIt\UBL\Invoice\Invoice as UBLInvoice;
use CleverIt\UBL\Invoice\InvoiceLine;
use CleverIt\UBL\Invoice\Item;
use CleverIt\UBL\Invoice\LegalMonetaryTotal;
use CleverIt\UBL\Invoice\Party;
use CleverIt\UBL\Invoice\TaxCategory;
use CleverIt\UBL\Invoice\TaxScheme;
use CleverIt\UBL\Invoice\TaxSubTotal;
use CleverIt\UBL\Invoice\TaxTotal;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateUbl implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public const INVOICE_TYPE_STANDARD = 380;

    public const INVOICE_TYPE_CREDIT = 381;

    public $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * Execute the job
     */
    public function handle()
    {
        $invoice = $this->invoice;
        $company = $invoice->company;
        $client = $invoice->client;
        $ubl_invoice = new UBLInvoice();

        // invoice
        $ubl_invoice->setId($invoice->number);
        $ubl_invoice->setIssueDate(date_create($invoice->date));
        $ubl_invoice->setInvoiceTypeCode($invoice->amount < 0 ? (string)self::INVOICE_TYPE_CREDIT : (string)self::INVOICE_TYPE_STANDARD);

        $supplier_party = $this->createParty($company, $invoice->user);
        $ubl_invoice->setAccountingSupplierParty($supplier_party);

        $customer_party = $this->createParty($client, $client->contacts[0]);
        $ubl_invoice->setAccountingCustomerParty($customer_party);

        // line items
        $invoice_lines = [];
        $taxable = $this->getTaxable();

        foreach ($invoice->line_items as $index => $item) {
            $itemTaxable = $this->getItemTaxable($item, $taxable);
            $invoice_lines[] = $this->createInvoiceLine($index, $item, $itemTaxable);
        }

        $ubl_invoice->setInvoiceLines($invoice_lines);

        $taxtotal = new TaxTotal();
        $taxAmount1 = $taxAmount2 = $taxAmount3 = 0;

        if (strlen($invoice->tax_name1) > 1) {
            $taxAmount1 = $this->createTaxRate($taxtotal, $taxable, $invoice->tax_rate1, $invoice->tax_name1);
        }

        if (strlen($invoice->tax_name2) > 1) {
            $taxAmount2 = $this->createTaxRate($taxtotal, $taxable, $invoice->tax_rate2, $invoice->tax_name2);
        }

        if (strlen($invoice->tax_name3) > 1) {
            $taxAmount3 = $this->createTaxRate($taxtotal, $taxable, $invoice->tax_rate3, $invoice->tax_name3);
        }

        $taxtotal->setTaxAmount($taxAmount1 + $taxAmount2 + $taxAmount3);
        $ubl_invoice->setTaxTotal($taxtotal);

        $ubl_invoice->setLegalMonetaryTotal((new LegalMonetaryTotal())
            //->setLineExtensionAmount()
            ->setTaxInclusiveAmount($invoice->balance)
            ->setTaxExclusiveAmount($taxable)
            ->setPayableAmount($invoice->balance));

        try {
            return Generator::invoice($ubl_invoice, $invoice->client->getCurrencyCode());
        } catch (Exception $exception) {
            return false;
        }
    }

    private function createParty($company, $user)
    {
        $party = new Party();
        $party->setName($company->present()->name);
        $address = (new Address())
            ->setCityName($company->city)
            ->setStreetName($company->address1)
            ->setBuildingNumber($company->address2)
            ->setPostalZone($company->postal_code);

        if ($company->country_id) {
            $country = new Country();
            $country->setIdentificationCode(($company instanceof Company) ? $company->country()->iso_3166_2 : $company->country->iso_3166_2);
            $address->setCountry($country);
        }

        $party->setPostalAddress($address);
        $party->setPhysicalLocation($address);

        $contact = new Contact();
        $contact->setElectronicMail($user->email);
        $party->setContact($contact);

        return $party;
    }

    private function createInvoiceLine($index, $item, $taxable)
    {
        $invoiceLine = (new InvoiceLine())
            ->setId($index + 1)
            ->setInvoicedQuantity($item->quantity)
            ->setLineExtensionAmount($this->costWithDiscount($item))
            ->setItem((new Item())
                ->setName($item->product_key)
                ->setDescription($item->notes));
        //->setSellersItemIdentification("1ABCD"));

        $taxtotal = new TaxTotal();
        $itemTaxAmount1 = $itemTaxAmount2 = $itemTaxAmount3 = 0;

        if (strlen($item->tax_name1) > 1) {
            $itemTaxAmount1 = $this->createTaxRate($taxtotal, $taxable, $item->tax_rate1, $item->tax_name1);
        }

        if (strlen($item->tax_name2) > 1) {
            $itemTaxAmount2 = $this->createTaxRate($taxtotal, $taxable, $item->tax_rate2, $item->tax_name2);
        }

        if (strlen($item->tax_name3) > 1) {
            $itemTaxAmount3 = $this->createTaxRate($taxtotal, $taxable, $item->tax_rate3, $item->tax_name3);
        }

        $taxtotal->setTaxAmount($itemTaxAmount1 + $itemTaxAmount2 + $itemTaxAmount3);
        $invoiceLine->setTaxTotal($taxtotal);

        return $invoiceLine;
    }

    private function createTaxRate(&$taxtotal, $taxable, $taxRate, $taxName)
    {
        $invoice = $this->invoice;
        $taxAmount = $this->taxAmount($taxable, $taxRate);
        $taxScheme = ((new TaxScheme()))->setId($taxName);

        $taxtotal->addTaxSubTotal((new TaxSubTotal())
                ->setTaxAmount($taxAmount)
                ->setTaxableAmount($taxable)
                ->setTaxCategory((new TaxCategory())
                    ->setId($taxName)
                    ->setName($taxName)
                    ->setTaxScheme($taxScheme)
                    ->setPercent($taxRate)));

        return $taxAmount;
    }

    /**
     * @param $item
     * @param $invoice_total
     * @return float
     */
    private function getItemTaxable($item, $invoice_total)
    {
        $total = $item->quantity * $item->cost;

        if ($this->invoice->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                /** @var float $invoice_total */
                if ($invoice_total + $this->invoice->discount != 0) {
                    $total -= $invoice_total ? ($total / ($invoice_total + $this->invoice->discount) * $this->invoice->discount) : 0;
                }
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
            }
        }

        if ($item->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $item->discount;
            } else {
                $total -= $total * $item->discount / 100;
            }
        }

        return round($total, 2);
    }

    /**
     * @return float|int|mixed
     */
    private function getTaxable()
    {
        $total = 0;

        foreach ($this->invoice->line_items as $item) {
            $line_total = $item->quantity * $item->cost;

            if ($item->discount != 0) {
                if ($this->invoice->is_amount_discount) {
                    $line_total -= $item->discount;
                } else {
                    $line_total -= $line_total * $item->discount / 100;
                }
            }

            $total += $line_total;
        }

        if ($this->invoice->discount > 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $this->invoice->discount;
            } else {
                $total *= (100 - $this->invoice->discount) / 100;
                $total = round($total, 2);
            }
        }

        if ($this->invoice->custom_surcharge1 && $this->invoice->custom_surcharge_tax1) {
            $total += $this->invoice->custom_surcharge1;
        }

        if ($this->invoice->custom_surcharge2 && $this->invoice->custom_surcharge_tax2) {
            $total += $this->invoice->custom_surcharge2;
        }

        if ($this->invoice->custom_surcharge3 && $this->invoice->custom_surcharge_tax3) {
            $total += $this->invoice->custom_surcharge3;
        }

        if ($this->invoice->custom_surcharge4 && $this->invoice->custom_surcharge_tax4) {
            $total += $this->invoice->custom_surcharge4;
        }

        return $total;
    }

    public function costWithDiscount($item)
    {
        $cost = $item->cost;

        if ($item->discount != 0) {
            if ($this->invoice->is_amount_discount) {
                $cost -= $item->discount / $item->quantity;
            } else {
                $cost -= $cost * $item->discount / 100;
            }
        }

        return $cost;
    }

    public function taxAmount($taxable, $rate)
    {
        if ($this->invoice->uses_inclusive_taxes) {
            return round($taxable - ($taxable / (1 + ($rate / 100))), 2);
        } else {
            return round($taxable * ($rate / 100), 2);
        }
    }
}

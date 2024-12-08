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

namespace App\Services\EDocument\Imports;

use App\Models\Vendor;
use App\Models\Company;
use App\Models\Country;
use App\Models\Expense;
use App\Factory\VendorFactory;
use App\Factory\ExpenseFactory;
use App\Services\AbstractService;
use Illuminate\Http\UploadedFile;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\SavesDocuments;
use App\Factory\VendorContactFactory;
use App\Models\Currency;
use App\Repositories\ExpenseRepository;

class UblEDocument extends AbstractService
{
    use SavesDocuments;

    /**
     * @throws \Throwable
     */
    public function __construct(public UploadedFile $file, public Company $company)
    {
        # curl -X POST http://localhost:8000/api/v1/edocument/upload -H "Content-Type: multipart/form-data" -H "X-API-TOKEN: 7tdDdkz987H3AYIWhNGXy8jTjJIoDhkAclCDLE26cTCj1KYX7EBHC66VEitJwWhn" -H "X-Requested-With: XMLHttpRequest" -F _method=PUT -F documents[]=@einvoice.xml
    }

    /**
     * @throws \Throwable
     */
    public function run(): \App\Models\Expense
    {

        $e = new EInvoice();
        $xml = $this->extractInvoiceUbl($this->file->get());
        $invoice = $e->decode('Peppol', $xml, 'xml');
        return $this->buildAndSaveExpense($invoice);

    }

    /**
     * extractInvoiceUbl
     *
     * If the <Invoice object is nested, this method will
     * extract and return only the <Invoice> document.
     *
     * @param  string $xml
     * @return string
     */
    private function extractInvoiceUbl(string $xml): string
    {

        $xml = str_replace('<?xml version="1.0" encoding="UTF-8"?>', '', $xml);
        nlog($xml);

        $dom = new \DOMDocument();
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);

        // Register the namespaces
        $xpath->registerNamespace('sh', 'http://www.unece.org/cefact/namespaces/StandardBusinessDocumentHeader');
        $xpath->registerNamespace('ubl', 'urn:oasis:names:specification:ubl:schema:xsd:Invoice-2');

        // Search for Invoice with default namespace
        $invoiceNodes = $xpath->query('//ubl:Invoice');

        if ($invoiceNodes === false || $invoiceNodes->length === 0) {
            throw new \Exception('No Invoice tag found in XML');
        }

        $invoiceNode = $invoiceNodes->item(0);

        // Create new document with just the Invoice
        $newDom = new \DOMDocument();
        $newNode = $newDom->importNode($invoiceNode, true);
        $newDom->appendChild($newNode);

        return $newDom->saveXML($newDom->documentElement);

    }

    private function buildAndSaveExpense(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): Expense
    {

        $vendor = $this->findOrCreateVendor($invoice);

        $TaxExclusiveAmount = data_get($invoice, 'LegalMonetaryTotal.TaxExclusiveAmount.amount', 0);
        $TaxInclusiveAmount = data_get($invoice, 'LegalMonetaryTotal.TaxExclusiveAmount.amount', 0);
        $ChargeTotalAmount = data_get($invoice, 'LegalMonetaryTotal.ChargeTotalAmount.amount', 0);
        $PayableAmount = data_get($invoice, 'LegalMonetaryTotal.PayableAmount.amount', 0);
        $TaxAmount = data_get($invoice, 'TaxTotal.0.TaxAmount.amount', 0);
        $tax_sub_totals = data_get($invoice, 'TaxTotal.0.TaxSubtotal', []);
        $currency_code = data_get($invoice, 'DocumentCurrencyCode.value', $this->company->currency()->code);
        $date = data_get($invoice, 'IssueDate', now()->format('Y-m-d'));

        $payment_means = [];
        $payment_means[] = data_get($invoice, 'PaymentMeans.0.PaymentMeansCode.name', false);
        $payment_means[] = data_get($invoice, 'PaymentMeans.0.PaymentID.value', false);
        $payment_means[] = data_get($invoice, 'PaymentMeans.0.PayeeFinancialAccount.ID.value', false);
        $payment_means[] = data_get($invoice, 'PaymentMeans.0.PayeeFinancialAccount.Name', false);
        $payment_means[] = data_get($invoice, 'PaymentMeans.0.PayeeFinancialAccount.FinancialInstitutionBranch.ID.value', false);
        $payment_means[] = data_get($invoice, 'PaymentTerms.0.Note', false);

        $private_notes = collect($payment_means)
                                ->reject(function ($means) {
                                    return $means === false;
                                })->implode("\n");

        $invoice_items = data_get($invoice, 'InvoiceLine', []);

        $items = [];

        foreach ($invoice_items as $key => $item) {
            $items[$key]['name'] = data_get($item, 'Item.Name', false);
            $items[$key]['description'] = data_get($item, 'Item.Description', false);
        }

        $public_notes = collect($items)->reject(function ($item) {
            return $item['name'] === false && $item['description'] === false;
        })->map(function ($item) {
            return $item['name'] ?? ' ' . ' ## '. $item['description'] ?? ' '; //@phpstan-ignore-line
        })->implode("\n");

        /** @var \App\Models\Expense $expense */
        $expense = ExpenseFactory::create($this->company->id, $this->company->owner()->id);
        $expense->vendor_id = $vendor->id;
        $expense->amount = $this->company->expense_inclusive_taxes ? $TaxInclusiveAmount : $TaxExclusiveAmount;
        $expense->currency_id = $this->resolveCurrencyId($currency_code);
        $expense->date = $date;
        $expense->private_notes = $private_notes;
        $expense->public_notes = $public_notes;

        $tax_level = 1;

        foreach ($tax_sub_totals as $key => $sub_tax) {
            $amount = data_get($sub_tax, 'TaxAmount.amount', 0);
            $taxable_amount = data_get($sub_tax, 'TaxableAmount.amount', 0);
            $tax_rate = data_get($sub_tax, 'TaxCategory.Percent', 0);
            $id = data_get($sub_tax, 'TaxCategory.ID.value', '');
            $tax_name = data_get($sub_tax, 'TaxCategory.TaxScheme.ID.value', '');

            if (!$this->company->calculate_expense_tax_by_amount && $tax_rate > 0) {

                $expense->{"tax_rate{$tax_level}"} = $tax_rate;
                $expense->{"tax_name{$tax_level}"} = $tax_name;
                $expense->calculate_tax_by_amount = false;

            } else {
                $expense->calculate_tax_by_amount = true;
                $expense->{"tax_amount{$tax_level}"} = $amount; //@phpstan-ignore-line

            }

            $tax_level++;

            if ($tax_level == 4) {
                break;
            }
        }


        $expense->save();

        $repo = new ExpenseRepository();

        $data = [];
        $data['documents'][] = $this->file;

        $expense = $repo->save($data, $expense);

        return $expense;


    }

    private function resolveCurrencyId(string $currency_code): int
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        return $currencies->first(function (Currency $c) use ($currency_code) {
            return $c->code === $currency_code;
        })?->id ?? (int) $this->company->settings->currency_id;
    }

    private function findOrCreateVendor(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): Vendor
    {
        $asp = $invoice->AccountingSupplierParty;

        $vat_number = $this->resolveVendorVat($invoice);
        $id_number = $this->resolveVendorIdNumber($invoice);
        $routing_id = data_get($invoice, 'AccountingSupplierParty.Party.EndpointID.value', false);
        $vendor_name = $this->resolveSupplierName($invoice);

        $vendor = Vendor::query()
                    ->where('company_id', $this->company->id)
                    ->where(function ($q) use ($vat_number, $routing_id, $id_number, $vendor_name) {

                        $q->when($routing_id, function ($q) use ($routing_id) {
                            $q->where('routing_id', $routing_id);
                        })
                        ->when(strlen($vat_number) > 1, function ($q) use ($vat_number) {
                            $q->orWhere('vat_number', $vat_number);
                        })
                        ->when(strlen($id_number) > 1, function ($q) use ($id_number) {
                            $q->orWhere('id_number', $id_number);
                        })
                        ->when(strlen($vendor_name) > 1, function ($q) use ($vendor_name) {
                            $q->orWhere('name', $vendor_name);
                        });

                    })->first();

        return $vendor ?? $this->newVendor($invoice);
    }

    private function resolveSupplierName(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): string
    {
        if (data_get($invoice, 'AccountingSupplierParty.Party.PartyName', false)) {
            $party_name = data_get($invoice, 'AccountingSupplierParty.Party.PartyName', false);
            return data_get($party_name[0], 'Name', '');
        }

        if (data_get($invoice, 'AccountingSupplierParty.Party.PartyLegalEntity', false)) {
            $ple = data_get($invoice, 'AccountingSupplierParty.Party.PartyName', false);
            return data_get($ple[0], 'RegistrationName', '');
        }

        return '';
    }

    private function resolveVendorIdNumber(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): string
    {

        $pts = data_get($invoice, 'AccountingSupplierParty.Party.PartyIdentification', false);

        return $pts ? data_get($pts[0], 'ID.value', '') : '';

    }

    private function resolveVendorVat(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): string
    {

        $pts = data_get($invoice, 'AccountingSupplierParty.Party.PartyTaxScheme', false);

        return $pts ? data_get($pts[0], 'CompanyID.value', '') : '';

    }

    private function newVendor(\InvoiceNinja\EInvoice\Models\Peppol\Invoice $invoice): Vendor
    {
        $vendor = VendorFactory::create($this->company->id, $this->company->owner()->id);


        $data = [
            'name' => $this->resolveSupplierName($invoice),
            'routing_id' => data_get($invoice, 'AccountingSupplierParty.Party.EndpointID.value', ''),
            'vat_number' => $this->resolveVendorVat($invoice),
            'id_number' => $this->resolveVendorIdNumber($invoice),
            'address1' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.StreetName', ''),
            'address2' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.AdditionalStreetName', ''),
            'city' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.CityName', ''),
            'state' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.CountrySubentity', ''),
            'postal_code' => data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.PostalZone', ''),
            'country_id' => $this->resolveCountry(data_get($invoice, 'AccountingSupplierParty.Party.PostalAddress.Country.IdentificationCode.value', '')),
            'currency_id' => $this->resolveCurrencyId(data_get($invoice, 'DocumentCurrencyCode.value', $this->company->currency()->code)),
        ];

        $vendor->fill($data);
        $vendor->save();

        $vendor->service()->applyNumber();

        if (data_get($invoice, 'AccountingSupplierParty.Party.Contact', false)) {
            $vc = VendorContactFactory::create($this->company->id, $this->company->owner()->id);
            $vc->vendor_id = $vendor->id;
            $vc->first_name = data_get($invoice, 'AccountingSupplierParty.Party.Contact.Name', '');
            $vc->phone = data_get($invoice, 'AccountingSupplierParty.Party.Contact.Telephone', '');
            $vc->email = data_get($invoice, 'AccountingSupplierParty.Party.Contact.ElectronicMail', '');
            $vc->save();
        }

        return $vendor->fresh();

    }

    private function resolveCountry(?string $iso_country_code): int
    {
        return Country::query()
                        ->where('iso_3166_2', $iso_country_code)
                        ->orWhere('iso_3166_3', $iso_country_code)
                        ->first()?->id ?? (int)$this->company->settings->country_id;
    }
}

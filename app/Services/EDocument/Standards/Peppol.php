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

namespace App\Services\EDocument\Standards;

use App\DataMapper\Tax\BaseRule;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\Helpers\Invoice\Taxer;
use App\Services\AbstractService;
use App\Helpers\Invoice\InvoiceSum;
use InvoiceNinja\EInvoice\EInvoice;
use App\Utils\Traits\NumberFormatter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Services\EDocument\Gateway\Qvalia\Qvalia;
use InvoiceNinja\EInvoice\Models\Peppol\ItemType\Item;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use InvoiceNinja\EInvoice\Models\Peppol\PartyType\Party;
use InvoiceNinja\EInvoice\Models\Peppol\PriceType\Price;
use InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID;
use InvoiceNinja\EInvoice\Models\Peppol\AddressType\Address;
use InvoiceNinja\EInvoice\Models\Peppol\ContactType\Contact;
use InvoiceNinja\EInvoice\Models\Peppol\CountryType\Country;
use InvoiceNinja\EInvoice\Models\Peppol\PartyIdentification;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxAmount;
use InvoiceNinja\EInvoice\Models\Peppol\TaxTotalType\TaxTotal;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PriceAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PartyNameType\PartyName;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\PayableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxableAmount;
use InvoiceNinja\EInvoice\Models\Peppol\PeriodType\InvoicePeriod;
use InvoiceNinja\EInvoice\Models\Peppol\CodeType\IdentificationCode;
use InvoiceNinja\EInvoice\Models\Peppol\InvoiceLineType\InvoiceLine;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\TaxSubtotalType\TaxSubtotal;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxExclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\TaxInclusiveAmount;
use InvoiceNinja\EInvoice\Models\Peppol\AmountType\LineExtensionAmount;
use InvoiceNinja\EInvoice\Models\Peppol\OrderReferenceType\OrderReference;
use InvoiceNinja\EInvoice\Models\Peppol\MonetaryTotalType\LegalMonetaryTotal;
use InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\ClassifiedTaxCategory;
use InvoiceNinja\EInvoice\Models\Peppol\CustomerPartyType\AccountingCustomerParty;
use InvoiceNinja\EInvoice\Models\Peppol\SupplierPartyType\AccountingSupplierParty;

class Peppol extends AbstractService
{
    use Taxer;
    use NumberFormatter;

    /**
     * Assumptions:
     *
     * Line Item Taxes Only
     * Exclusive Taxes
     *
     */

    private ?string $override_vat_number;

    /** @var array $InvoiceTypeCodes */
    private array $InvoiceTypeCodes = [
        "380" => "Commercial invoice",
        "381" => "Credit note",
        "383" => "Corrected invoice",
        "384" => "Prepayment invoice",
        "386" => "Proforma invoice",
        "875" => "Self-billed invoice",
        "976" => "Factored invoice",
        "84" => "Invoice for cross border services",
        "82" => "Simplified invoice",
        "80" => "Debit note",
        "875" => "Self-billed credit note",
        "896" => "Debit note related to self-billed invoice"
    ];

    /** @var array $tax_codes */
    private array $tax_codes = [
        'AE' => [
            'name' => 'Vat Reverse Charge',
            'description' => 'Code specifying that the standard VAT rate is levied from the invoicee.'
        ],
        'E' => [
            'name' => 'Exempt from Tax',
            'description' => 'Code specifying that taxes are not applicable.'
        ],
        'S' => [
            'name' => 'Standard rate',
            'description' => 'Code specifying the standard rate.'
        ],
        'Z' => [
            'name' => 'Zero rated goods',
            'description' => 'Code specifying that the goods are at a zero rate.'
        ],
        'G' => [
            'name' => 'Free export item, VAT not charged',
            'description' => 'Code specifying that the item is free export and taxes are not charged.'
        ],
        'O' => [
            'name' => 'Services outside scope of tax',
            'description' => 'Code specifying that taxes are not applicable to the services.'
        ],
        'K' => [
            'name' => 'VAT exempt for EEA intra-community supply of goods and services',
            'description' => 'A tax category code indicating the item is VAT exempt due to an intra-community supply in the European Economic Area.'
        ],
        'L' => [
            'name' => 'Canary Islands general indirect tax',
            'description' => 'Impuesto General Indirecto Canario (IGIC) is an indirect tax levied on goods and services supplied in the Canary Islands (Spain) by traders and professionals, as well as on import of goods.'
        ],
        'M' => [
            'name' => 'Tax for production, services and importation in Ceuta and Melilla',
            'description' => 'Impuesto sobre la Producción, los Servicios y la Importación (IPSI) is an indirect municipal tax, levied on the production, processing and import of all kinds of movable tangible property, the supply of services and the transfer of immovable property located in the cities of Ceuta and Melilla.'
        ],
        'B' => [
            'name' => 'Transferred (VAT), In Italy',
            'description' => 'VAT not to be paid to the issuer of the invoice but directly to relevant tax authority. This code is allowed in the EN 16931 for Italy only based on the Italian A-deviation.'
        ]
    ];

    private Company $company;

    private InvoiceSum | InvoiceSumInclusive $calc;

    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private EInvoice $e;

    private string $api_network = Storecove::class; // Storecove::class; // Qvalia::class;

    public Qvalia | Storecove $gateway;

    private string $customizationID = 'urn:cen.eu:en16931:2017#compliant#urn:fdc:peppol.eu:2017:poacc:billing:3.0';

    private string $profileID = 'urn:fdc:peppol.eu:2017:poacc:billing:01:1.0';

    private array $tax_map = [];

    private float $allowance_total = 0;

    private $globalTaxCategories;

    private string $tax_category_id;

    private array $errors = [];

    public function __construct(public Invoice $invoice)
    {
        $this->company = $invoice->company;
        $this->calc = $this->invoice->calc();
        $this->e = new EInvoice();
        $this->gateway = new $this->api_network();
        $this->setSettings()->setInvoice();
    }

    /**
     * Entry point for building document
     *
     * @return self
     */
    public function run(): self
    {
        try {
            $this->getJurisdiction(); //Sets the nexus object into the Peppol document.
            $this->getAllUsedTaxes(); //Maps all used line item taxes

            /** Invoice Level Props */
            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CustomizationID();
            $id->value = $this->customizationID;
            $this->p_invoice->CustomizationID = $id;

            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ProfileID();
            $id->value = $this->profileID;
            $this->p_invoice->ProfileID = $id;

            $this->p_invoice->ID = $this->invoice->number;
            $this->p_invoice->IssueDate = new \DateTime($this->invoice->date);

            if ($this->invoice->due_date) {
                $this->p_invoice->DueDate = new \DateTime($this->invoice->due_date);
            }

            if (strlen($this->invoice->public_notes ?? '') > 0) {
                $this->p_invoice->Note = strip_tags($this->invoice->public_notes);
            }

            $this->p_invoice->DocumentCurrencyCode = $this->invoice->client->currency()->code;

            if ($this->invoice->date && $this->invoice->due_date) {
                $ip = new InvoicePeriod();
                $ip->StartDate = new \DateTime($this->invoice->date);
                $ip->EndDate = new \DateTime($this->invoice->due_date);
                $this->p_invoice->InvoicePeriod = [$ip];
            }

            if ($this->invoice->project_id) {
                $pr = new \InvoiceNinja\EInvoice\Models\Peppol\ProjectReferenceType\ProjectReference();
                $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
                $id->value = $this->invoice->project->number;
                $pr->ID = $id;
                $this->p_invoice->ProjectReference = [$pr];
            }

            /** Auto switch between Invoice / Credit based on the amount value */
            $this->p_invoice->InvoiceTypeCode = ($this->invoice->amount >= 0) ? 380 : 381;

            $this->p_invoice->AccountingSupplierParty = $this->getAccountingSupplierParty();
            $this->p_invoice->AccountingCustomerParty = $this->getAccountingCustomerParty();
            $this->p_invoice->InvoiceLine = $this->getInvoiceLines();
            $this->p_invoice->AllowanceCharge = $this->getAllowanceCharges();
            $this->p_invoice->LegalMonetaryTotal = $this->getLegalMonetaryTotal();
            $this->p_invoice->Delivery = $this->getDelivery();

            $this->setOrderReference()
                 ->setTaxBreakdown()
                 ->setPaymentTerms()
                 ->standardPeppolRules();

            //isolate this class to only peppol changes
            $this->p_invoice = $this->gateway
                                    ->mutator
                                    ->senderSpecificLevelMutators()
                                    ->receiverSpecificLevelMutators()
                                    ->getPeppol();

        } catch (\Throwable $th) {
            nlog("Unable to create Peppol Invoice" . $th->getMessage());
            $this->errors[] = $th->getMessage();
        }

        return $this;

    }

    /**
     * Transforms a stdClass Invoice
     * to Peppol\Invoice::class
     *
     * @param  mixed $invoice
     * @return self
     */
    public function decode(mixed $invoice): self
    {
        $this->p_invoice = $this->e->decode('Peppol', json_encode($invoice), 'json');

        return $this;
    }

    /**
     * Rehydrates an existing e invoice - or - scaffolds a new one
     *
     * @return self
     */
    private function setInvoice(): self
    {
        /** Handle Existing Document */
        if ($this->invoice->e_invoice && isset($this->invoice->e_invoice->Invoice)) {

            $this->decode($this->invoice->e_invoice->Invoice);

            $this->gateway
                ->mutator
                ->setInvoice($this->invoice)
                ->setPeppol($this->p_invoice)
                ->setClientSettings($this->_client_settings)
                ->setCompanySettings($this->_company_settings);

            return $this;

        }

        /** Scaffold new document */
        $this->p_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        /** Set Props */
        $this->gateway
            ->mutator
            ->setInvoice($this->invoice)
            ->setPeppol($this->p_invoice)
            ->setClientSettings($this->_client_settings)
            ->setCompanySettings($this->_company_settings);

        $this->setInvoiceDefaults();

        return $this;
    }

    /**
     * Transforms the settings props into usable models we can merge.
     *
     * @return self
     */
    private function setSettings(): self
    {
        $this->_client_settings = isset($this->invoice->client->e_invoice->Invoice) ? $this->e->decode('Peppol', json_encode($this->invoice->client->e_invoice->Invoice), 'json') : null;

        $this->_company_settings = isset($this->invoice->company->e_invoice->Invoice) ? $this->e->decode('Peppol', json_encode($this->invoice->company->e_invoice->Invoice), 'json') : null;

        return $this;
    }

    /**
     * getInvoice
     *
     * @return \InvoiceNinja\EInvoice\Models\Peppol\Invoice
     */
    public function getInvoice(): \InvoiceNinja\EInvoice\Models\Peppol\Invoice
    {
        return $this->p_invoice;
    }

    /**
     * toXml
     *
     * Builds a full Peppol XML document including tags
     *
     * @return string
     */
    public function toXml(): string
    {
        $e = new EInvoice();
        $xml = $e->encode($this->p_invoice, 'xml');

        $prefix = '<?xml version="1.0" encoding="UTF-8"?>
<Invoice xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2"
    xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"
    xmlns="urn:oasis:names:specification:ubl:schema:xsd:Invoice-2">';

        $suffix = '</Invoice>';

        $xml = str_ireplace(['\n','<?xml version="1.0"?>'], ['', $prefix], $xml);

        $xml .= $suffix;

        return $xml;

    }

    /**
     * toJson
     *
     * Returns the peppol invoice in json format
     *
     * @return string
     */
    public function toJson(): string
    {
        $e = new EInvoice();
        $json =  $e->encode($this->p_invoice, 'json');

        return $json;

    }

    /**
     * toObject
     *
     * returns the Peppol document in object format.
     *
     * @return mixed
     */
    public function toObject(): mixed
    {
        $invoice = new \stdClass();

        $invoice->Invoice = json_decode($this->toJson());

        return $invoice;
    }

    /**
     * toArray
     *
     * Returns the peppol document in Array format
     *
     * @return array
     */
    public function toArray(): array
    {
        return ['Invoice' => json_decode($this->toJson(), true)];
    }


    /**
     * setOrderReference
     *
     * Sets the order reference - if it exists - on the document.
     * @return self
     */
    private function setOrderReference(): self
    {


        if (strlen($this->invoice->po_number ?? '') > 1) {

            $this->p_invoice->BuyerReference = $this->invoice->po_number ?? '';

            $order_reference = new OrderReference();
            $id = new ID();
            $id->value = $this->invoice->po_number;

            $order_reference->ID = $id;
            $this->p_invoice->OrderReference = $order_reference;

        }

        return $this;

    }

    /**
     * getAllowanceCharges
     *
     * Allowance charges are discounts / fees that are
     * applied to line or invoice level items
     *
     * ChargeIndicator flags whether the item is a discount 'false'
     * this prop is ONLY set for discounts. Fees are inferred.
     *
     * @return array
     */
    private function getAllowanceCharges(): array
    {
        $allowances = [];

        //Invoice Level discount
        if ($this->invoice->discount > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->ChargeIndicator = 'false'; // false = discount
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = number_format($this->calc->getTotalDiscount(), 2, '.', '');

            // Add percentage if available
            if ($this->invoice->discount > 0 && !$this->invoice->is_amount_discount) {

                $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
                $allowanceCharge->BaseAmount->amount = number_format($this->calc->getSubtotalWithSurcharges(), 2, '.', '');

                $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                $mfn->value = number_format(round(($this->invoice->discount), 2), 2, '.', '');  // Format to always show 2 decimals
                $allowanceCharge->MultiplierFactorNumeric = $mfn; // Convert percentage to decimal
            }

            $tc = clone $this->globalTaxCategories[0];
            // $tc->Percent = '0';
            unset($tc->TaxExemptionReasonCode);
            unset($tc->TaxExemptionReason);

            $allowanceCharge->TaxCategory[] = $tc;
            $allowanceCharge->AllowanceChargeReason = ctrans('texts.discount');
            $allowances[] = $allowanceCharge;
        }

        //Invoice level surcharges (@todo React - need to turn back on surcharge taxes and use the first tax....)
        if ($this->invoice->custom_surcharge1 > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = number_format($this->invoice->custom_surcharge1, 2, '.', '');
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubtotalWithSurcharges();

            $this->calculateTaxMap($this->invoice->custom_surcharge1);

            $allowanceCharge->TaxCategory = $this->globalTaxCategories;
            $allowanceCharge->AllowanceChargeReason = ctrans('texts.surcharge');
            $allowances[] = $allowanceCharge;

        }

        if ($this->invoice->custom_surcharge2 > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = number_format($this->invoice->custom_surcharge2, 2, '.', '');
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubtotalWithSurcharges();


            $this->calculateTaxMap($this->invoice->custom_surcharge2);

            $allowanceCharge->TaxCategory = $this->globalTaxCategories;
            $allowanceCharge->AllowanceChargeReason = ctrans('texts.surcharge');
            $allowances[] = $allowanceCharge;

        }

        if ($this->invoice->custom_surcharge3 > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = number_format($this->invoice->custom_surcharge3, 2, '.', '');
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubtotalWithSurcharges();

            $this->calculateTaxMap($this->invoice->custom_surcharge3);

            $allowanceCharge->TaxCategory = $this->globalTaxCategories;
            $allowanceCharge->AllowanceChargeReason = ctrans('texts.surcharge');
            $allowances[] = $allowanceCharge;

        }

        if ($this->invoice->custom_surcharge4 > 0) {

            // Add Allowance Charge to Price
            $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
            $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
            $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->Amount->amount = number_format($this->invoice->custom_surcharge4, 2, '.', '');
            $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
            $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
            $allowanceCharge->BaseAmount->amount = (string) $this->calc->getSubtotalWithSurcharges();

            $this->calculateTaxMap($this->invoice->custom_surcharge4);

            $allowanceCharge->TaxCategory = $this->globalTaxCategories;
            $allowanceCharge->AllowanceChargeReason = ctrans('texts.surcharge');
            $allowances[] = $allowanceCharge;

        }

        return $allowances;

    }

    /**
     * getLegalMonetaryTotal
     *
     * @return LegalMonetaryTotal
     */
    private function getLegalMonetaryTotal(): LegalMonetaryTotal
    {
        $taxable = $this->getTaxable();

        $lmt = new LegalMonetaryTotal();

        $lea = new LineExtensionAmount();
        $lea->currencyID = $this->invoice->client->currency()->code;
        $lea->amount = $this->invoice->uses_inclusive_taxes ? round($this->invoice->amount - $this->invoice->total_taxes, 2) : $this->calc->getSubTotal();
        $lmt->LineExtensionAmount = $lea;

        $tea = new TaxExclusiveAmount();
        $tea->currencyID = $this->invoice->client->currency()->code;

        $tea->amount = round($this->invoice->amount - $this->invoice->total_taxes, 2);
        $lmt->TaxExclusiveAmount = $tea;

        $tia = new TaxInclusiveAmount();
        $tia->currencyID = $this->invoice->client->currency()->code;
        $tia->amount = $this->invoice->amount;
        $lmt->TaxInclusiveAmount = $tia;

        $pa = new PayableAmount();
        $pa->currencyID = $this->invoice->client->currency()->code;
        $pa->amount = $this->invoice->amount;
        $lmt->PayableAmount = $pa;

        $am = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\AllowanceTotalAmount();
        $am->currencyID = $this->invoice->client->currency()->code;
        $am->amount = number_format($this->calc->getTotalDiscount(), 2, '.', '');
        $lmt->AllowanceTotalAmount = $am;

        return $lmt;
    }

    /**
     * getTaxType
     *
     * Calculates the PEPPOL code for the tax type
     *
     * @param  string $tax_id
     * @return string
     */
    private function getTaxType(string $tax_id = ''): string
    {
        $tax_type = null;

        switch ($tax_id) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                $tax_type = 'S';
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type =  'E';
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = 'Z';
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = 'AE';
                // no break
            case Product::PRODUCT_INTRA_COMMUNITY:
                $tax_type = 'K';
                break;
        }

        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "ES-CE", "ES-ML", "ES-CN", "SE", "IS", "LI", "NO", "CH"];

        if (empty($tax_type)) {
            if ((in_array($this->company->country()->iso_3166_2, $eu_states) && in_array($this->invoice->client->country->iso_3166_2, $eu_states)) && $this->invoice->company->country()->iso_3166_2 != $this->invoice->client->country->iso_3166_2) {
                $tax_type = 'K'; // EEA Exempt
            } elseif (!in_array($this->invoice->client->country->iso_3166_2, $eu_states)) {
                $tax_type = 'G'; //Free export item, VAT not charged
            } else {
                $tax_type = 'S'; //Standard rate
            }
        }

        if (in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML", "ES-CN"]) && $tax_type == 'S') {

            if ($this->invoice->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = 'L'; //Canary Islands general indirect tax
            } elseif (in_array($this->invoice->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = 'M'; //Tax for production, services and importation in Ceuta and Melilla
            }

        }

        return $tax_type;
    }


    private function resolveTaxExemptReason($item, $ctc = null): mixed
    {

        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "ES-CE", "ES-ML", "ES-CN", "SE", "IS", "LI", "NO", "CH"];

        if ($item->tax_id == '9') {
            $tax_type = 'AE'; // EEA Exempt
            $reason_code = 'vatex-eu-ae';
            $reason = 'Reverse charge';
        } elseif ((in_array($this->company->country()->iso_3166_2, $eu_states) &&
                   in_array($this->invoice->client->country->iso_3166_2, $eu_states)) &&
                   $this->invoice->company->country()->iso_3166_2 != $this->invoice->client->country->iso_3166_2) {
            $tax_type = 'K'; // EEA Exempt
            $reason_code = 'vatex-eu-ic';
            $reason = 'Intra-Community supply';

        } elseif (!in_array($this->invoice->client->country->iso_3166_2, $eu_states)) {
            $tax_type = 'G'; //Free export item, VAT not charged
            $reason_code = 'vatex-eu-g';
            $reason = 'Export outside the EU';
        } else {
            $tax_type = 'O';
            $reason_code = "vatex-eu-o";
            $reason = 'Services outside scope of tax';
        }

        $this->tax_category_id = $tax_type;

        //no vat, build a single tax category for tax exemption

        $taxCategory = new \InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory();
        $taxCategory->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $taxCategory->ID->value = $tax_type;

        if ($this->tax_category_id != 'O') {
            $taxCategory->Percent = '0';
        }

        $taxScheme = new \InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme();
        $taxScheme->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
        $taxScheme->ID->value = $this->standardizeTaxSchemeId('vat');
        $taxCategory->TaxScheme = $taxScheme;
        $terc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\TaxExemptionReasonCode();
        $terc->value = $reason_code;
        $taxCategory->TaxExemptionReasonCode = $terc;
        $taxCategory->TaxExemptionReason = $reason;

        $this->globalTaxCategories = [$taxCategory];

        if ($this->tax_category_id == 'O' && isset($this->p_invoice->AccountingSupplierParty->Party->PartyTaxScheme)) {
            unset($this->p_invoice->AccountingSupplierParty->Party->PartyTaxScheme);
        }

        if ($this->tax_category_id == 'O' && isset($this->p_invoice->AccountingCustomerParty->Party->PartyTaxScheme)) {
            unset($this->p_invoice->AccountingCustomerParty->Party->PartyTaxScheme);
        }

        if ($ctc) {
            $ctc->ID->value = $tax_type;

            if ($this->tax_category_id != 'O') {
                $ctc->Percent = '0';
            }
            // $terc = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\TaxExemptionReasonCode();
            // $terc->value = $reason_code;
            // $ctc->TaxExemptionReasonCode = $terc;
            // $ctc->TaxExemptionReason = $reason;

            return $ctc;
        }

        return $tax_type;

    }


    /**
    * getInvoiceLines
    *
    * Compiles the invoice line items of the document
    *
    * @return array
    */
    private function getInvoiceLines(): array
    {
        $lines = [];

        foreach ($this->invoice->line_items as $key => $item) {

            // $item->line_total = round($item->line_total,2);
            // $item->gross_line_total = round($item->gross_line_total, 2);

            $_item = new Item();
            $_item->Name = $item->product_key;
            $_item->Description = $item->notes;


            $ctc = new ClassifiedTaxCategory();
            $ctc->ID = new ID();
            $ctc->ID->value = $this->getTaxType($item->tax_id);

            if ($item->tax_rate1 > 0) {
                $ctc->Percent = (string)$item->tax_rate1;
            }

            $ts = new TaxScheme();
            $id = new ID();
            $id->value = $this->standardizeTaxSchemeId($item->tax_name1);
            $ts->ID = $id;
            $ctc->TaxScheme = $ts;

            if (floatval($item->tax_rate1) === 0.0) {
                $ctc = $this->resolveTaxExemptReason($item, $ctc);

                if ($this->tax_category_id == 'O') {
                    unset($ctc->Percent);
                }

            }

            $_item->ClassifiedTaxCategory[] = $ctc;

            if ($item->tax_rate2 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $this->getTaxType($item->tax_id);
                $ctc->Percent = (string)$item->tax_rate2;

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $this->standardizeTaxSchemeId($item->tax_name2);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            if ($item->tax_rate3 > 0) {
                $ctc = new ClassifiedTaxCategory();
                $ctc->ID = new ID();
                $ctc->ID->value = $this->getTaxType($item->tax_id);
                $ctc->Percent = (string)$item->tax_rate3;

                $ts = new TaxScheme();
                $id = new ID();
                $id->value = $this->standardizeTaxSchemeId($item->tax_name3);
                $ts->ID = $id;
                $ctc->TaxScheme = $ts;

                $_item->ClassifiedTaxCategory[] = $ctc;
            }

            $line = new InvoiceLine();

            $id = new ID();
            $id->value = (string) ($key + 1);
            $line->ID = $id;

            $iq = new \InvoiceNinja\EInvoice\Models\Peppol\QuantityType\InvoicedQuantity();
            $iq->amount = $item->quantity;
            $iq->unitCode = $item->unit_code ?? 'C62';
            $line->InvoicedQuantity = $iq;

            $lea = new LineExtensionAmount();
            $lea->currencyID = $this->invoice->client->currency()->code;
            $lea->amount = $this->invoice->uses_inclusive_taxes ? round($item->line_total - $this->calcInclusiveLineTax($item->tax_rate1, $item->line_total),2) : round($item->line_total,2);
            $line->LineExtensionAmount = $lea;
            $line->Item = $_item;

            /** Builds the tax map for the document */
            // $this->getItemTaxes($item);

            // Handle Price and Discounts
            if ($item->discount > 0) {

                // Base Price (before discount)
                $basePrice = new Price();
                $basePriceAmount = new PriceAmount();
                $basePriceAmount->currencyID = $this->invoice->client->currency()->code;
                $basePriceAmount->amount = (string)$item->cost;
                $basePrice->PriceAmount = $basePriceAmount;

                // Add Allowance Charge to Price
                $allowanceCharge = new \InvoiceNinja\EInvoice\Models\Peppol\AllowanceChargeType\AllowanceCharge();
                $allowanceCharge->ChargeIndicator = 'false'; // false = discount
                $allowanceCharge->Amount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\Amount();
                $allowanceCharge->Amount->currencyID = $this->invoice->client->currency()->code;
                $allowanceCharge->Amount->amount = number_format($this->calculateTotalItemDiscountAmount($item), 2, '.', '');
                $this->allowance_total += $this->calculateTotalItemDiscountAmount($item);


                // Add percentage if available
                if ($item->discount > 0 && !$item->is_amount_discount) {

                    $allowanceCharge->BaseAmount = new \InvoiceNinja\EInvoice\Models\Peppol\AmountType\BaseAmount();
                    $allowanceCharge->BaseAmount->currencyID = $this->invoice->client->currency()->code;
                    $allowanceCharge->BaseAmount->amount = (string)round(($item->cost * $item->quantity), 2);

                    $mfn = new \InvoiceNinja\EInvoice\Models\Peppol\NumericType\MultiplierFactorNumeric();
                    $mfn->value = (string) round($item->discount, 2);
                    $allowanceCharge->MultiplierFactorNumeric = $mfn; // Convert percentage to decimal
                }

                // }
                // Required reason
                $allowanceCharge->AllowanceChargeReason = ctrans('texts.discount');

                $line->Price = $basePrice;
                $line->AllowanceCharge[] = $allowanceCharge;

            } else {
                // No discount case
                $price = new Price();
                $pa = new PriceAmount();
                $pa->currencyID = $this->invoice->client->currency()->code;
                $pa->amount = (string)$item->cost;
                $price->PriceAmount = $pa;
                $line->Price = $price;
            }

            $lines[] = $line;
        }

        return $lines;
    }

    private function calculateTotalItemDiscountAmount($item): float
    {

        if ($item->is_amount_discount) {
            return $item->discount;
        }

        return ($item->cost * $item->quantity) * ($item->discount / 100);

    }

    /**
     * calculateTaxMap
     *
     * Generates a standard tax_map entry for a given $amount
     *
     * Iterates through all of the globalTaxCategories found in the document
     *
     * @param  float $amount
     * @return self
     */
    private function calculateTaxMap($amount): self
    {

        foreach ($this->globalTaxCategories as $tc) {

            $this->tax_map[] = [
                'taxableAmount' => $amount,
                'taxAmount' => $amount * ($tc->Percent / 100),
                'percentage' => $tc->Percent,
            ];

        }

        return $this;
    }

    /**
     * getAllUsedTaxes
     *
     * Build a full tax category property based on all
     * of the item taxes that have been applied to the invoice.
     *
     * @return self
     */
    private function getAllUsedTaxes(): self
    {
        $this->globalTaxCategories = [];

        collect($this->invoice->line_items)
            ->flatMap(function ($item) {
                return collect([1, 2, 3])
                    ->map(fn ($i) => [
                        'name' => $item->{"tax_name{$i}"} ?? '',
                        'percentage' => $item->{"tax_rate{$i}"} ?? 0,
                        'scheme' => $this->getTaxType($item->tax_id),
                    ])
                    ->filter(fn ($tax) => strlen($tax['name']) > 1);
            })
            ->unique(fn ($tax) => $tax['percentage'] . '_' . $tax['name'])
            ->values()
            ->each(function ($tax) {

                $taxCategory = new \InvoiceNinja\EInvoice\Models\Peppol\TaxCategoryType\TaxCategory();
                $taxCategory->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
                $taxCategory->ID->value = $tax['scheme'];
                $taxCategory->Percent = (string)$tax['percentage'];
                $taxScheme = new \InvoiceNinja\EInvoice\Models\Peppol\TaxSchemeType\TaxScheme();
                $taxScheme->ID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\ID();
                $taxScheme->ID->value = $this->standardizeTaxSchemeId($tax['name']);
                $taxCategory->TaxScheme = $taxScheme;

                $this->globalTaxCategories[] = $taxCategory;

            });

        return $this;

    }


    /**
     * getAccountingSupplierParty
     *
     * @return AccountingSupplierParty
     */
    private function getAccountingSupplierParty(): AccountingSupplierParty
    {

        $asp = new AccountingSupplierParty();

        $party = new Party();
        $party_name = new PartyName();
        $party_name->Name = $this->invoice->company->present()->name();
        $party->PartyName[] = $party_name;

        if (strlen($this->company->settings->vat_number ?? '') > 1) {

            $pi = new PartyIdentification();
            $vatID = new ID();
            $vatID->schemeID = $this->resolveScheme();
            $vatID->value = $this->override_vat_number ?? $this->company->settings->vat_number; //todo if we are cross border - switch to the supplier local vat number

            $pi->ID = $vatID;
            $party->PartyIdentification[] = $pi;
            $pts = new \InvoiceNinja\EInvoice\Models\Peppol\PartyTaxSchemeType\PartyTaxScheme();

            $companyID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CompanyID();
            $companyID->value = $this->override_vat_number ?? $this->company->settings->vat_number;
            $pts->CompanyID = $companyID;

            $ts = new TaxScheme();
            $id = new ID();
            $id->value = $this->standardizeTaxSchemeId('vat');
            $ts->ID = $id;
            $pts->TaxScheme = $ts;

            //@todo if we have an exact GLN/routing number we should update this, otherwise Storecove will proxy and update on transit
            $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\EndpointID();
            $id->value = $this->company->settings->vat_number;
            $id->schemeID = $this->resolveScheme();

            $party->EndpointID = $id;
            $party->PartyTaxScheme[] = $pts;

        }

        $address = new Address();
        $address->CityName = $this->invoice->company->settings->city;
        $address->StreetName = $this->invoice->company->settings->address1;

        if (strlen($this->invoice->company->settings->address2 ?? '') > 1) {
            $address->AdditionalStreetName = $this->invoice->company->settings->address2;
        }

        $address->PostalZone = $this->invoice->company->settings->postal_code;
        // $address->CountrySubentity = $this->invoice->company->settings->state;

        $country = new Country();

        $ic = new IdentificationCode();
        $ic->value = substr($this->invoice->company->country()->iso_3166_2, 0, 2);
        $country->IdentificationCode = $ic;

        $address->Country = $country;
        $party->PostalAddress = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Contact') ?? $this->invoice->company->owner()->present()->email();
        $contact->Telephone = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Telephone') ?? $this->invoice->company->getSetting('phone');
        $contact->Name = $this->gateway->mutator->getSetting('Invoice.AccountingSupplierParty.Party.Name') ?? $this->invoice->company->owner()->present()->name();

        $party->Contact = $contact;

        $ple = new \InvoiceNinja\EInvoice\Models\Peppol\PartyLegalEntity();
        $ple->RegistrationName = $this->invoice->company->present()->name();
        $party->PartyLegalEntity[] = $ple;

        $asp->Party = $party;

        return $asp;
    }

    /**
     * getAccountingCustomerParty
     *
     * @return AccountingCustomerParty
     */
    private function getAccountingCustomerParty(): AccountingCustomerParty
    {

        $acp = new AccountingCustomerParty();

        $party = new Party();

        if (strlen($this->invoice->client->vat_number ?? '') > 1) {

            $pi = new PartyIdentification();

            $vatID = new ID();
            $vatID->schemeID = $this->resolveScheme(true);
            $vatID->value = $this->invoice->client->vat_number;
            $pi->ID = $vatID;

            $party->PartyIdentification[] = $pi;

            $pts = new \InvoiceNinja\EInvoice\Models\Peppol\PartyTaxSchemeType\PartyTaxScheme();

            $companyID = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\CompanyID();
            $companyID->value = $this->invoice->client->vat_number;
            $pts->CompanyID = $companyID;

            $ts = new TaxScheme();
            $id = new ID();
            $id->value = $this->standardizeTaxSchemeId('vat');
            $ts->ID = $id;
            $pts->TaxScheme = $ts;

            $party->PartyTaxScheme[] = $pts;

        }

        $party_name = new PartyName();
        $party_name->Name = $this->invoice->client->present()->name();

        //@todo if we have an exact GLN/routing number we should update this, otherwise Storecove will proxy and update on transit

        $id = new \InvoiceNinja\EInvoice\Models\Peppol\IdentifierType\EndpointID();
        $id->value = $this->invoice->client->routing_id 
        ?? $this->invoice->client->vat_number 
        ?? $this->invoice->client->id_number
        ?? 'fallback1234';
        
        $id->schemeID = $this->resolveScheme(true);
        $party->EndpointID = $id;
        


        $party->PartyName[] = $party_name;

        $address = new Address();
        $address->CityName = $this->invoice->client->city;
        $address->StreetName = $this->invoice->client->address1;

        if (strlen($this->invoice->client->address2 ?? '') > 1) {
            $address->AdditionalStreetName = $this->invoice->client->address2;
        }

        $address->PostalZone = $this->invoice->client->postal_code;
        // $address->CountrySubentity = $this->invoice->client->state;

        $country = new Country();

        $ic = new IdentificationCode();
        $ic->value = substr($this->invoice->client->country->iso_3166_2, 0, 2);

        $country->IdentificationCode = $ic;
        $address->Country = $country;

        $party->PostalAddress = $address;

        $contact = new Contact();
        $contact->ElectronicMail = $this->invoice->client->present()->email();

        if (strlen($this->invoice->client->phone ?? '') > 2) {
            $contact->Telephone = $this->invoice->client->phone;
        }

        $party->Contact = $contact;

        $ple = new \InvoiceNinja\EInvoice\Models\Peppol\PartyLegalEntity();
        $ple->RegistrationName = $this->invoice->client->present()->name();
        $party->PartyLegalEntity[] = $ple;

        $acp->Party = $party;

        return $acp;
    }

    private function getDelivery(): array
    {
        $delivery = new \InvoiceNinja\EInvoice\Models\Peppol\DeliveryType\Delivery();
        $location = new \InvoiceNinja\EInvoice\Models\Peppol\LocationType\DeliveryLocation();

        $address = new Address();
        // $address->CityName = $this->invoice->client->city;
        // $address->StreetName = $this->invoice->client->address1;

        // if (strlen($this->invoice->client->address2 ?? '') > 1) {
        //     $address->AdditionalStreetName = $this->invoice->client->address2;
        // }

        // $address->PostalZone = $this->invoice->client->postal_code;
        // $address->CountrySubentity = $this->invoice->client->state;

        $country = new Country();

        $ic = new IdentificationCode();
        $shipping = $this->invoice->client->shipping_country ? $this->invoice->client->shipping_country->iso_3166_2 : $this->invoice->client->country->iso_3166_2;
        $ic->value = $shipping;

        $country->IdentificationCode = $ic;
        $address->Country = $country;
        $location->Address = $address;
        $delivery->DeliveryLocation = $location;

        return [$delivery];

    }

    /**
     * getTaxable
     *
     * @return float
     */
    private function getTaxable(): float
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

        $total = round($total, 2);

        if ($this->invoice->discount > 0) {
            if ($this->invoice->is_amount_discount) {
                $total -= $this->invoice->discount;
            } else {
                $total *= (100 - $this->invoice->discount) / 100;

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

        return round($total, 2);
    }

    /////////////////  Helper Methods /////////////////////////


    /**
     * setInvoiceDefaults
     *
     * Stubs a default einvoice
     * @return self
     */
    public function setInvoiceDefaults(): self
    {

        // Stub new invoice with company settings.
        if ($this->_company_settings) {
            foreach (get_object_vars($this->_company_settings) as $prop => $value) {
                $this->p_invoice->{$prop} = $value;
            }
        }

        // Overwrite with any client level settings
        if ($this->_client_settings) {
            foreach (get_object_vars($this->_client_settings) as $prop => $value) {
                $this->p_invoice->{$prop} = $value;
            }
        }

        // Plucks special overriding properties scanning the correct settings level
        $settings = [
            'AccountingCostCode' => 7,
            'AccountingCost' => 7,
            'BuyerReference' => 6,
            'AccountingSupplierParty' => 1,
            'AccountingCustomerParty' => 2,
            'PayeeParty' => 1,
            'BuyerCustomerParty' => 2,
            'SellerSupplierParty' => 1,
            'TaxRepresentativeParty' => 1,
            'Delivery' => 1,
            'DeliveryTerms' => 7,
            'PaymentMeans' => 7,
            'PaymentTerms' => 7,
        ];

        //only scans for top level props
        foreach ($settings as $prop => $visibility) {

            if ($prop_value = $this->gateway->mutator->getSetting($prop)) {
                $this->p_invoice->{$prop} = $prop_value;
            }

        }

        return $this;
    }
    
    /**
     * standardPeppolRules
     *
     * Transform UBL => Peppol rules
     * 
     * 1. FinancialInstitutionBranch - remove
     * 2. FinancialInstituion - remove
     * 
     * @return self
     */
    private function standardPeppolRules(): self
    {
        foreach($this->p_invoice->PaymentMeans as &$pm)
        {
            unset($pm->PayeeFinancialAccount->FinancialInstitutionBranch);
        }
        unset($pm);

        return $this;
    }
        
    /**
     * setPaymentTerms
     *
     * If payment terms are defined, we should include these
     * on the invoice.
     * 
     * @return self
     */
    private function setPaymentTerms(): self
    {
        $terms_string = $this->invoice->client->getSetting('payment_terms');

        if(strlen($terms_string) > 1)
        {
            $terms = new \InvoiceNinja\EInvoice\Models\Peppol\PaymentTermsType\PaymentTerms();
            $terms->Note = trans('texts.count_days', ['count' => $terms_string]);

            $this->p_invoice->PaymentTerms[] = $terms;

        }

        return $this;
    }

    public function setTaxBreakdown(): self
    {

        $tax_total = new TaxTotal();
        $taxes = $this->calc->getTaxMap();

        if (count($taxes) < 1) {

            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = (string)0;
            $tax_total->TaxAmount = $tax_amount;

            $tax_subtotal = new TaxSubtotal();

            // Required: TaxableAmount (BT-116)
            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;
            $taxable_amount->amount = (string)round($this->invoice->amount, 2);

            $tax_subtotal->TaxableAmount = $taxable_amount;

            $subtotal_tax_amount = new TaxAmount();
            $subtotal_tax_amount->currencyID = $this->invoice->client->currency()->code;

            $subtotal_tax_amount->amount = (string)0;

            $tax_subtotal->TaxAmount = $subtotal_tax_amount;

            // Required: TaxCategory (BG-23)
            $tax_category = new TaxCategory();

            // Required: TaxCategory ID (BT-118)
            $category_id = new ID();
            $category_id->value = $this->tax_category_id; // Exempt

            $tax_category->ID = $category_id;

            // Required: TaxScheme (BG-23)
            $tax_scheme = new TaxScheme();
            $scheme_id = new ID();
            $scheme_id->value = $this->standardizeTaxSchemeId("taxname");
            $tax_scheme->ID = $scheme_id;
            $tax_category->TaxScheme = $tax_scheme;

            $tax_subtotal->TaxCategory = $this->globalTaxCategories[0];

            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $this->p_invoice->TaxTotal[] = $tax_total;

            return $this;

        }

        foreach ($taxes as $key => $grouped_tax) {
            // Required: TaxAmount (BT-110)
            $tax_amount = new TaxAmount();
            $tax_amount->currencyID = $this->invoice->client->currency()->code;
            $tax_amount->amount = (string)$grouped_tax['total'];
            $tax_total->TaxAmount = $tax_amount;

            // Required: TaxSubtotal (BG-23)
            $tax_subtotal = new TaxSubtotal();

            // Required: TaxableAmount (BT-116)
            $taxable_amount = new TaxableAmount();
            $taxable_amount->currencyID = $this->invoice->client->currency()->code;

            if (floatval($grouped_tax['total']) === 0.0) {
                $taxable_amount->amount = (string)round($this->invoice->amount, 2);
            } else {
                $taxable_amount->amount = (string)round($grouped_tax['base_amount'], 2);
            }
            $tax_subtotal->TaxableAmount = $taxable_amount;

            // Required: TaxAmount (BT-117)
            $subtotal_tax_amount = new TaxAmount();
            $subtotal_tax_amount->currencyID = $this->invoice->client->currency()->code;

            $subtotal_tax_amount->amount = (string)round($grouped_tax['total'], 2);

            $tax_subtotal->TaxAmount = $subtotal_tax_amount;

            // Required: TaxCategory (BG-23)
            $tax_category = new TaxCategory();

            // Required: TaxCategory ID (BT-118)
            $category_id = new ID();
            $category_id->value = $this->getTaxType($grouped_tax['tax_id']); // Standard rate

            $tax_category->ID = $category_id;

            // Required: TaxCategory Rate (BT-119)
            if ($grouped_tax['tax_rate'] > 0) {
                $tax_category->Percent = (string)$grouped_tax['tax_rate'];
            }

            // Required: TaxScheme (BG-23)
            $tax_scheme = new TaxScheme();
            $scheme_id = new ID();
            $scheme_id->value = $this->standardizeTaxSchemeId("taxname");
            $tax_scheme->ID = $scheme_id;
            $tax_category->TaxScheme = $tax_scheme;

            $tax_subtotal->TaxCategory = $this->globalTaxCategories[0];

            $tax_total->TaxSubtotal[] = $tax_subtotal;

            $this->p_invoice->TaxTotal[] = $tax_total;
        }

        return $this;
    }

    public function getJurisdiction()
    {

        //calculate nexus
        $country_code = $this->company->country()->iso_3166_2;
        $br = new \App\DataMapper\Tax\BaseRule();
        $eu_countries = $br->eu_country_codes;

        if ($this->invoice->client->country->iso_3166_2 == $this->company->country()->iso_3166_2) {
            //Domestic Sales
            $country_code = $this->company->country()->iso_3166_2;
        } elseif (in_array($country_code, $eu_countries) && !in_array($this->invoice->client->country->iso_3166_2, $eu_countries)) {
            //EU => FOREIGN sale
        } elseif (in_array($this->invoice->client->country->iso_3166_2, $eu_countries)) {
            // EU Sale
            if ((isset($this->company->tax_data->regions->EU->has_sales_above_threshold) && $this->company->tax_data->regions->EU->has_sales_above_threshold) || !$this->invoice->client->has_valid_vat_number) { //over threshold - tax in buyer country

                $country_code = $this->invoice->client->country->iso_3166_2;

                if (isset($this->ninja_invoice->company->tax_data->regions->EU->subregions->{$country_code}->vat_number)) {
                    $this->override_vat_number = $this->ninja_invoice->company->tax_data->regions->EU->subregions->{$country_code}->vat_number;
                }
            }
        }

        $jurisdiction = new \InvoiceNinja\EInvoice\Models\Peppol\AddressType\JurisdictionRegionAddress();
        $country = new Country();
        $ic = new IdentificationCode();
        $ic->value = $country_code;
        $country->IdentificationCode = $ic;
        $jurisdiction->Country = $country;
        $addressTypeCode = new \InvoiceNinja\EInvoice\Models\Peppol\CodeType\AddressTypeCode();
        $addressTypeCode->value = 'JURISDICTION';  // or the appropriate code from PEPPOL spec
        $jurisdiction->AddressTypeCode = $addressTypeCode;

        return $jurisdiction;

    }


    private function standardizeTaxSchemeId(string $tax_name): string
    {

        $br = new BaseRule();
        $eu_countries = $br->eu_country_codes;

        // If company is in EU, standardize to VAT
        // if (in_array($this->company->country()->iso_3166_2, $eu_countries)) {
        return "VAT";
        // }

        // For non-EU countries, return original or handle specifically
        // return $this->standardizeTaxSchemeId($tax_name);
    }

    /**
     * ResolveScheme
     *
     * If we need to explicitly set the schemeID, we will need to resolve
     * the exact one based on the type the user has, ie. GLN DUNS, validation
     * is performed here, so lots can go wrong if bad data is used.
     *
     * @param  bool $is_client
     * @return string
     */
    private function resolveScheme(bool $is_client = false): string
    {

        $vat_number = $is_client ? $this->invoice->client->vat_number : $this->company->settings->vat_number;
        $tax_number = $is_client ? $this->invoice->client->id_number : $this->company->settings->id_number;
        $country_code = $is_client ? $this->invoice->client->country->iso_3166_2 : $this->company->country()->iso_3166_2;

        return '0037';
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

}

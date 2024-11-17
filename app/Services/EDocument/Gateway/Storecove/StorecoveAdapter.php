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

namespace App\Services\EDocument\Gateway\Storecove;

use App\DataMapper\Tax\BaseRule;
use App\Models\Expense;
use App\Models\Vendor;
use Symfony\Component\Serializer\Serializer;
use Symfony\Component\Serializer\Encoder\XmlEncoder;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use Symfony\Component\Serializer\Encoder\JsonEncoder;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\PropertyInfo\PropertyInfoExtractor;
use App\Services\EDocument\Gateway\Storecove\Models\Invoice;
use Symfony\Component\PropertyInfo\Extractor\PhpDocExtractor;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Serializer\Normalizer\ArrayDenormalizer;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Mapping\Loader\AttributeLoader;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice as PeppolInvoice;
use Symfony\Component\PropertyInfo\Extractor\ReflectionExtractor;
use Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer;
use Symfony\Component\Serializer\Mapping\Factory\ClassMetadataFactory;
use App\Services\EDocument\Gateway\Storecove\PeppolToStorecoveNormalizer;
use App\Services\EDocument\Gateway\Storecove\Transformers\StorecoveExpense;
use App\Services\EDocument\Standards\Peppol;
use Symfony\Component\Serializer\NameConverter\MetadataAwareNameConverter;
use Symfony\Component\Serializer\NameConverter\CamelCaseToSnakeCaseNameConverter;

class StorecoveAdapter
{

    public function __construct(public Storecove $storecove){}

    private Invoice $storecove_invoice;

    private array $errors = [];

    private bool $valid_document = true;
    
    private $ninja_invoice;

    private string $nexus;

    private bool $has_error = false;

    public function validate(): self
    {
        
        if ($this->has_error) {
            return $this;
        }

        return $this;
    }

    public function getInvoice(): Invoice
    {
        return $this->storecove_invoice;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
    
    /**
     * addError
     *
     * Adds an error to the errors array.
     * 
     * @param  string $error
     * @return self
     */
    private function addError(string $error): self
    {
        $this->errors[] = $error;
        
        return $this;
    }
    
    public function deserialize($storecove_object)
    {
        
        $context = [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ];

        $serializer = $this->getSerializer();

        $obj['Invoice'] = $storecove_object['document']['invoice'];

        $storecove_object = $serializer->normalize($obj, null, [\Symfony\Component\Serializer\Normalizer\AbstractObjectNormalizer::SKIP_NULL_VALUES => true]);

        return $serializer->deserialize(json_encode($storecove_object), \App\Services\EDocument\Gateway\Storecove\Models\Invoice::class, 'json', $context);

    }

    /**
     * transform
     *
     * @param  \App\Models\Invoice $invoice
     * @return self
     */
    public function transform($invoice): self
    {
        try {
            $this->ninja_invoice = $invoice;
            $serializer = $this->getSerializer();

            /** Currently - due to class structures, the serialization process goes like this:
             * 
             * e-invoice => Peppol -> XML -> Peppol Decoded -> encode to Peppol -> deserialize to Storecove
             */
            $p = (new Peppol($invoice))->run()->toXml();
            $context = [
                DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
                AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
            ];

            $e = new \InvoiceNinja\EInvoice\EInvoice();
            $peppolInvoice = $e->decode('Peppol', $p, 'xml');
            $parent = \App\Services\EDocument\Gateway\Storecove\Models\Invoice::class;
            $peppolInvoice = $e->encode($peppolInvoice, 'json');
            $this->storecove_invoice = $serializer->deserialize($peppolInvoice, $parent, 'json', $context);

            $this->buildNexus();
        }
        catch(\Throwable $th){

            $this->addError($th->getMessage());
            $this->has_error = true;
        }

        return $this;

    }

    public function getNexus(): string
    {
        return $this->nexus;
    }

    public function decorate(): self
    {
        if($this->has_error)
            return $this;

        //set all taxmap countries - resolve the taxing country
        $lines = $this->storecove_invoice->getInvoiceLines();

        foreach($lines as &$line)
        {
            if(isset($line->taxes_duties_fees))
            {
                foreach($line->taxes_duties_fees as &$tax)
                {
                    $tax->country = $this->nexus;
                    $tax->percentage = $tax->percentage ?? 0; 
                    if(property_exists($tax,'category'))
                        $tax->category = $this->tranformTaxCode($tax->category);
                }
                unset($tax);
            }

            if(isset($line->allowance_charges))
            {
                foreach($line->allowance_charges as &$allowance)
                {
                    if($allowance->reason == ctrans('texts.discount'))
                        $allowance->amount_excluding_tax = $allowance->amount_excluding_tax * -1;


                    foreach($allowance->getTaxesDutiesFees() ?? [] as &$tax)
                    {
                        
                        if (property_exists($tax, 'category')) {
                            $tax->category = $this->tranformTaxCode($tax->category);
                        }

                    }
                    unset($tax);
                }
                unset($allowance);
            }
        }

        $this->storecove_invoice->setInvoiceLines($lines);

        $tax_subtotals = $this->storecove_invoice->getTaxSubtotals();

        foreach($tax_subtotals as &$tax)
        {
            $tax->country = $this->nexus;
            $tax->percentage = $tax->percentage ?? 0;

            if (property_exists($tax, 'category')) 
                $tax->category = $this->tranformTaxCode($tax->category);

        }
        unset($tax);

        $this->storecove_invoice->setTaxSubtotals($tax_subtotals);
        //configure identifiers

        //update payment means codes to storecove equivalents
        $payment_means = $this->storecove_invoice->getPaymentMeansArray();

        foreach($payment_means as &$pm)
        {
            $pm->code = $this->transformPaymentMeansCode($pm->code);
        }
        
        $this->storecove_invoice->setPaymentMeansArray($payment_means);

        $allowances = $this->storecove_invoice->getAllowanceCharges() ?? [];

        foreach($allowances as &$allowance)
        {
            $taxes = $allowance->getTaxesDutiesFees() ?? [];

            foreach($taxes as &$tax)
            {            
                $tax->country = $this->nexus;
                $tax->percentage = $tax->percentage ?? 0;

                if (property_exists($tax, 'category')) {
                    $tax->category = $this->tranformTaxCode($tax->category);
                }
            }
            unset($tax);

            
            if ($allowance->reason == ctrans('texts.discount')) {
                $allowance->amount_excluding_tax = $allowance->amount_excluding_tax * -1;
            }

            $allowance->setTaxesDutiesFees($taxes);

        }
        unset($allowance);

        $this->storecove_invoice->setAllowanceCharges($allowances);

        $this->storecove_invoice->setTaxSystem('tax_line_percentages');
        
        //resolve and set the public identifier for the customer
        $accounting_customer_party = $this->storecove_invoice->getAccountingCustomerParty();

        if(strlen($this->ninja_invoice->client->vat_number) > 2)
        {
            // $id = str_ireplace("fr","", $this->ninja_invoice->client->vat_number);
            $id = $this->ninja_invoice->client->vat_number;
            $scheme = $this->storecove->router->setInvoice($this->ninja_invoice)->resolveTaxScheme($this->ninja_invoice->client->country->iso_3166_2, $this->ninja_invoice->client->classification ?? 'individual');
            $pi = new \App\Services\EDocument\Gateway\Storecove\Models\PublicIdentifiers($scheme, $id);
            $accounting_customer_party->addPublicIdentifiers($pi);
            $this->storecove_invoice->setAccountingCustomerParty($accounting_customer_party);
        }

        return $this;
    }

    private function getSerializer()
    {
                
        $phpDocExtractor = new PhpDocExtractor();
        $reflectionExtractor = new ReflectionExtractor();
        $typeExtractors = [$reflectionExtractor,$phpDocExtractor];
        $descriptionExtractors = [$phpDocExtractor];
        $propertyInitializableExtractors = [$reflectionExtractor];
        $propertyInfo = new PropertyInfoExtractor(
            $propertyInitializableExtractors,
            $descriptionExtractors,
            $typeExtractors,
        );
        $xml_encoder = new XmlEncoder(['xml_format_output' => true, 'remove_empty_tags' => true,]);
        $json_encoder = new JsonEncoder();

        $classMetadataFactory = new ClassMetadataFactory(new AttributeLoader());
        $metadataAwareNameConverter = new MetadataAwareNameConverter($classMetadataFactory, new CamelCaseToSnakeCaseNameConverter());

        $normalizer = new ObjectNormalizer($classMetadataFactory, $metadataAwareNameConverter, null, $propertyInfo);

        $normalizers = [new DateTimeNormalizer(), $normalizer,  new ArrayDenormalizer()];
        $encoders = [$xml_encoder, $json_encoder];
        $serializer = new Serializer($normalizers, $encoders);

        return $serializer;
    }
    
    /**
     * Builds the document and appends an errors prop
     *
     * @return array
     */
    public function getDocument(): mixed
    {

        if($this->has_error)
            return ['errors' => $this->getErrors(), 'document' => false];
        
        
        $serializer = $this->getSerializer();

        $context = [
          DateTimeNormalizer::FORMAT_KEY => 'Y-m-d',
          AbstractObjectNormalizer::SKIP_NULL_VALUES => true,
        ];

        $s_invoice = $serializer->encode($this->storecove_invoice, 'json', $context);

        $s_invoice = json_decode($s_invoice, true);

        $s_invoice = $this->removeEmptyValues($s_invoice);

        $data = [
            'errors' => $this->getErrors(),
            'document' => $s_invoice,
        ];

        return $data;

    }
    
    /**
     * RemoveEmptyValues
     *
     * @param  array $array
     * @return array
     */
    private function removeEmptyValues(array $array): array
    {
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = $this->removeEmptyValues($value);
                if (empty($array[$key])) {
                    unset($array[$key]);
                }
            } elseif ($value === null || $value === '') {
                unset($array[$key]);
            }
        }
        // nlog($array);
        return $array;
    }

    private function buildNexus(): self
    {
        nlog("building nexus");
        //Calculate nexus
        $company_country_code = $this->ninja_invoice->company->country()->iso_3166_2;
        $client_country_code = $this->ninja_invoice->client->country->iso_3166_2;
        $br = new BaseRule();
        $eu_countries = $br->eu_country_codes;

        if ($client_country_code == $company_country_code) {
            //Domestic Sales 
            nlog("domestic sales");
            $this->nexus = $company_country_code;
        } elseif (in_array($company_country_code, $eu_countries) && !in_array($client_country_code, $eu_countries)) {
            //NON-EU Sale
            nlog("non eu");
            $this->nexus = $company_country_code;
        } elseif (in_array($client_country_code, $eu_countries)) {
                    
            // First, determine if we're over threshold
            $is_over_threshold = isset($this->ninja_invoice->company->tax_data->regions->EU->has_sales_above_threshold) &&
                                $this->ninja_invoice->company->tax_data->regions->EU->has_sales_above_threshold;

            // Is this B2B or B2C?
            $is_b2c = strlen($this->ninja_invoice->client->vat_number) < 2 ||
                    !($this->ninja_invoice->client->has_valid_vat_number ?? false) ||
                    $this->ninja_invoice->client->classification == 'individual';

                    
            // B2C, under threshold, no Company VAT Registerd - must charge origin country VAT
            if ($is_b2c && !$is_over_threshold && strlen($this->ninja_invoice->company->settings->vat_number) < 2) {
                nlog("no company vat");
                $this->nexus = $company_country_code;
            } elseif ($is_b2c) {
                if ($is_over_threshold) {
                    // B2C over threshold - need destination VAT number
                    if (!isset($this->ninja_invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number)) {
                        $this->nexus = $client_country_code;
                        $this->addError("Tax Nexus is client country ({$client_country_code}) - however VAT number not present for this region. Document not sent!");
                        return $this;
                    }
                    nlog("B2C");
                    $this->nexus = $client_country_code;
                    $this->setupDestinationVAT($client_country_code);
                } else {
                    nlog("under threshold origin country");
                    // B2C under threshold - origin country VAT
                    $this->nexus = $company_country_code;
                }
            } elseif ($is_over_threshold && !in_array($company_country_code, $eu_countries)){
                    $this->nexus = $client_country_code;
            } else {
                nlog("B2B with valid vat");
                // B2B with valid VAT - origin country
                $this->nexus = $company_country_code;
            }

        }

        if($company_country_code == 'DE' && $client_country_code == 'DE' && $this->ninja_invoice->client->classification == 'government') {
            $this->removeSupplierVatNumber();
        }

        return $this;
    }

    private function removeSupplierVatNumber(): self
    {

        $asp = $this->storecove_invoice->getAccountingSupplierParty();
        $asp->setPublicIdentifiers([]);
        $this->storecove_invoice->setAccountingSupplierParty($asp);

        return $this;
    }

    private function setupDestinationVAT($client_country_code):self
    {
        
        $this->storecove_invoice->setConsumerTaxMode(true);
        $id = $this->ninja_invoice->company->tax_data->regions->EU->subregions->{$client_country_code}->vat_number;
        $scheme = $this->storecove->router->setInvoice($this->ninja_invoice)->resolveTaxScheme($client_country_code, $this->ninja_invoice->client->classification ?? 'individual');

        $pi = new \App\Services\EDocument\Gateway\Storecove\Models\PublicIdentifiers($scheme, $id);
        $asp = $this->storecove_invoice->getAccountingSupplierParty();
        $asp->addPublicIdentifiers($pi);
        $this->storecove_invoice->setAccountingSupplierParty($asp);
        
        return $this;
    }

    private function tranformTaxCode(string $code): ?string
    {

        if($code == 'O' && $this->ninja_invoice->client->classification == 'government')
            return 'exempt';
        
        // elseif($code == 'K' && $this->ninja_invoice->company->getSetting('classification') == 'individual')
        //     return 'reverse_charge';

        return match($code){
            'S' => 'standard',
            'Z' => 'zero_rated',
            'E' => 'exempt',
            'AE' => 'reverse_charge',
            'K' => 'intra_community',
            'G' => 'export',
            'O' => 'outside_scope',
            'L' => 'cgst',
            'I' => 'igst',
            'SS' => 'sgst',
            'B' => 'deemed_supply',
            'SR' => 'srca_s',
            'SC' => 'srca_c',
            'NR' => 'not_registered',
            default => null
        };
    }

    private function transformPaymentMeansCode(?string $code): string
    {
        return match($code){
            '30' => 'credit_transfer',
            '58' => 'sepa_credit_transfer',
            '31' => 'debit_transfer',
            '49' => 'direct_debit',
            '59' => 'sepa_direct_debit',
            '48' => 'card',         // Generic card payment
            '54' => 'bank_card',    
            '55' => 'credit_card',
            '57' => 'standing_agreement',
            '10' => 'cash',
            '20' => 'bank_cheque',
            '21' => 'cashiers_cheque',
            '97' => 'aunz_npp',
            '98' => 'aunz_npp_payid',
            '99' => 'aunz_npp_payto',
            '71' => 'aunz_bpay',
            '72' => 'aunz_postbillpay',
            '73' => 'aunz_uri',
            '50' => 'se_bankgiro',
            '51' => 'se_plusgiro',
            '74' => 'sg_giro',
            '75' => 'sg_card',
            '76' => 'sg_paynow',
            '77' => 'it_mav',
            '78' => 'it_pagopa',
            '42' => 'nl_ga_beneficiary',
            '43' => 'nl_ga_gaccount',
            '1'  => 'undefined',    // Instrument not defined
            default => 'undefined',
        };

    }
    
}
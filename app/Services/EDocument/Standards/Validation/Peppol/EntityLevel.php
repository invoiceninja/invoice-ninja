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

namespace App\Services\EDocument\Standards\Validation\Peppol;

use App\Exceptions\PeppolValidationException;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Services\EDocument\Standards\Peppol;
use App\Services\EDocument\Standards\Validation\XsltDocumentValidator;
use Illuminate\Support\Facades\App;
use XSLTProcessor;

class EntityLevel
{
    private array $eu_country_codes = [
            'AT', // Austria
            'BE', // Belgium
            'BG', // Bulgaria
            'CY', // Cyprus
            'CZ', // Czech Republic
            'DE', // Germany
            'DK', // Denmark
            'EE', // Estonia
            'ES', // Spain
            'ES-CN', // Canary Islands
            'ES-CE', // Ceuta
            'ES-ML', // Melilla
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

    private array $client_fields = [
        'address1',
        'city',
        // 'state',
        'postal_code',
        'country_id',
    ];

    private array $company_settings_fields = [
        'address1',
        'city',
        // 'state',
        'postal_code',
        'country_id',
    ];

    private array $company_fields = [
        // 'legal_entity_id',
        // 'vat_number IF NOT an individual
    ];

    private array $invoice_fields = [
        // 'number',
    ];

    private array $errors = [];

    public function __construct()
    {
    }

    private function init(string $locale): self
    {

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($locale);

        return $this;

    }

    public function checkClient(Client $client): array
    {
        $this->init($client->locale());
        $this->errors['client'] = $this->testClientState($client);
        $this->errors['passes'] = count($this->errors['client']) == 0;

        return $this->errors;

    }

    public function checkCompany(Company $company): array
    {

        $this->init($company->locale());
        $this->errors['company'] = $this->testCompanyState($company);
        $this->errors['passes'] = count($this->errors['company']) == 0;

        return $this->errors;

    }

    public function checkInvoice(Invoice $invoice): array
    {
        $this->init($invoice->client->locale());

        $this->errors['invoice'] = [];
        $this->errors['client'] = $this->testClientState($invoice->client);
        $this->errors['company'] = $this->testCompanyState($invoice->client); // uses client level settings which is what we want

        if(count($this->errors['client']) > 0){
            
            $this->errors['passes'] = false;
            return $this->errors;

        }

        $p = new Peppol($invoice);

        $xml = false;

        try{
            $xml = $p->run()->toXml();

            if(count($p->getErrors()) >= 1){

                foreach($p->getErrors() as $error)
                {
                    $this->errors['invoice'][] = $error;
                }
            }

        }
        catch(PeppolValidationException $e) {
            $this->errors['invoice'] = ['field' => $e->getInvalidField(), 'label' => $e->getInvalidField()];
        }
        catch(\Throwable $th){

        }

        if($xml){
            // Second pass through the XSLT validator
            $xslt = new XsltDocumentValidator($xml);
            $errors = $xslt->validate()->getErrors();

            if(isset($errors['stylesheet']) && count($errors['stylesheet']) > 0){
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['stylesheet']);
            }
        
            if(isset($errors['general']) && count($errors['general']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['general']);
            }

            if (isset($errors['xsd']) && count($errors['xsd']) > 0) {
                $this->errors['invoice'] = array_merge($this->errors['invoice'], $errors['xsd']);
            }

        }

        $this->errors['passes'] = count($this->errors['invoice']) == 0 && count($this->errors['client']) == 0 && count($this->errors['company']) == 0;

        return $this->errors;

    }

    private function testClientState(Client $client): array
    {

        $errors = [];

        foreach($this->client_fields as $field)
        {

            if($this->validString($client->{$field}))
                continue;

            if($field == 'country_id' && $client->country_id >=1)
                continue;

            $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];

        }

        //If not an individual, you MUST have a VAT number if you are in the EU
        if (!in_array($client->classification, ['government', 'individual']) && in_array($client->country->iso_3166_2, $this->eu_country_codes) && !$this->validString($client->vat_number)) {
            $errors[] = ['field' => 'vat_number', 'label' => ctrans("texts.vat_number")];
        }

        return $errors;

    }

    private function testCompanyState(mixed $entity): array
    {
        
        $client = false;
        $vendor = false;
        $settings_object = false;
        $company =false;

        if($entity instanceof Client){
            $client = $entity;
            $company = $entity->company;
            $settings_object = $client;
        }
        elseif($entity instanceof Company){
            $company = $entity;
            $settings_object = $company;    
        }
        elseif($entity instanceof Vendor){
            $vendor = $entity;    
            $company = $entity->company;
            $settings_object = $company;
        }
        elseif($entity instanceof Invoice || $entity instanceof Credit || $entity instanceof Quote){
            $client = $entity->client;
            $company = $entity->company;
            $settings_object = $entity->client;
        }
        elseif($entity instanceof PurchaseOrder){
            $vendor = $entity->vendor;
            $company = $entity->company;
            $settings_object = $company;
        }

        $errors = [];

        foreach($this->company_settings_fields as $field)
        {

            if($this->validString($settings_object->getSetting($field)))
                continue;
    
            $errors[] = ['field' => $field, 'label' => ctrans("texts.{$field}")];

        }

        //test legal entity id present
        if(!is_int($company->legal_entity_id))
            $errors[] = ['field' => "You have not registered a legal entity id as yet."];

        //If not an individual, you MUST have a VAT number
        if($company->getSetting('classification') != 'individual' && !$this->validString($company->getSetting('vat_number')))
        {
            $errors[] = ['field' => 'vat_number', 'label' => ctrans("texts.vat_number")];
        }
        elseif ($company->getSetting('classification') == 'individual' && !$this->validString($company->getSetting('id_number'))) {
            $errors[] = ['field' => 'id_number', 'label' => ctrans("texts.id_number")];
        }


        // foreach($this->company_fields as $field)
        // {

        // }

        return $errors;

    }

    // private function testInvoiceState($entity): array
    // {
    //     $errors = [];

    //     foreach($this->invoice_fields as $field)
    //     {

    //     }

    //     return $errors;
    // }

    // private function testVendorState(): array
    // {

    // }


    /************************************ helpers ************************************/
    private function validString(?string $string): bool
    {
        return iconv_strlen($string) >= 1;
    }

}
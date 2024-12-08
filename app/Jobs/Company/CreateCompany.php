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

namespace App\Jobs\Company;

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\TaxModel;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Country;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Bus\Dispatchable;

class CreateCompany
{
    use MakesHash;
    use Dispatchable;

    protected $request;

    protected $account;

    /**
     * Create a new job instance.
     *
     * @param array $request
     * @param $account
     */
    public function __construct(array $request, $account)
    {
        $this->request = $request;

        $this->account = $account;
    }

    /**
     * Execute the job.
     *
     * @return Company|null
     */
    public function handle(): ?Company
    {
        $settings = CompanySettings::defaults();

        $settings->name = isset($this->request['name']) ? $this->request['name'] : '';

        if ($country_id = $this->resolveCountry()) {
            $settings->country_id = $country_id;
        }

        $company = new Company();
        $company->account_id = $this->account->id;
        $company->company_key = $this->createHash();
        $company->ip = request()->ip();
        $company->settings = $settings;
        $company->db = config('database.default');
        $company->enabled_modules = config('ninja.enabled_modules');
        $company->subdomain = isset($this->request['subdomain']) ? $this->request['subdomain'] : MultiDB::randomSubdomainGenerator();
        $company->custom_fields = new \stdClass();
        $company->default_password_timeout = 1800000;
        $company->client_registration_fields = ClientRegistrationFields::generate();
        $company->markdown_email_enabled = true;
        $company->markdown_enabled = false;
        $company->tax_data = new TaxModel();

        if (Ninja::isHosted()) {
            $company->subdomain = MultiDB::randomSubdomainGenerator();
        } else {
            $company->subdomain = '';
        }

        /** Location Specific Configuration */
        match($settings->country_id) {
            '724' => $company = $this->spanishSetup($company),
            '36'  => $company = $this->australiaSetup($company),
            '710' => $company = $this->southAfticaSetup($company),
            '554' => $company = $this->newZealandSetup($company),
            default => $company->save(),
        };

        return $company;
    }

    /**
     * Resolve Country
     *
     * @return string
     */
    private function resolveCountry(): string
    {
        try {

            $ip = request()->ip();

            if (request()->hasHeader('cf-ipcountry')) {

                $c = Country::query()->where('iso_3166_2', request()->header('cf-ipcountry'))->first();

                if ($c) {
                    return (string)$c->id;
                }

            }

            $details = json_decode(file_get_contents("http://ip-api.com/json/{$ip}"));

            if ($details && property_exists($details, 'countryCode')) {

                $c = Country::query()->where('iso_3166_2', $details->countryCode)->first();

                if ($c) {
                    return (string)$c->id;
                }

            }
        } catch (\Exception $e) {
            nlog("Exception:: CreateCompany::" . $e->getMessage());
            nlog("Could not resolve country => {$e->getMessage()}");
        }

        return '840';

    }

    private function newZealandSetup($company): Company
    {

        $company->enabled_tax_rates = 1;

        $settings = $company->settings;
        $settings->currency_id = '15';
        $settings->timezone_id = '113';

        $company->settings = $settings;

        $company->save();

        return $company;
    }

    private function spanishSetup(Company $company): Company
    {
        try {

            $custom_fields = new \stdClass();
            $custom_fields->contact1 = "Rol|CONTABLE,FISCAL,GESTOR,RECEPTOR,TRAMITADOR,PAGADOR,PROPONENTE,B2B_FISCAL,B2B_PAYER,B2B_BUYER,B2B_COLLECTOR,B2B_SELLER,B2B_PAYMENT_RECEIVER,B2B_COLLECTION_RECEIVER,B2B_ISSUER";
            $custom_fields->contact2 = "Code|single_line_text";
            $custom_fields->contact3 = "Nombre|single_line_text";
            $custom_fields->client1 = "Administración Pública|switch";

            $company->custom_fields = $custom_fields;
            $company->enabled_item_tax_rates = 1;

            $settings = $company->settings;
            $settings->language_id = '7';
            $settings->e_invoice_type = 'Facturae_3.2.2';
            $settings->currency_id = '3';
            $settings->timezone_id = '42';

            $company->settings = $settings;

            $company->save();

            return $company;

        } catch (\Exception $e) {
            nlog("Exception:: CreateCompany::" . $e->getMessage());
            nlog("SETUP: could not complete setup for Spanish Locale");
        }

        $company->save();

        return $company;

    }

    private function southAfticaSetup(Company $company): Company
    {

        try {

            $company->enabled_item_tax_rates = 1;
            $company->enabled_tax_rates = 1;

            $translations = new \stdClass();
            $translations->invoice = "Tax Invoice";

            $settings = $company->settings;
            $settings->currency_id = '4';
            $settings->timezone_id = '56';
            $settings->translations = $translations;

            $company->settings = $settings;

            $company->save();

            return $company;

        } catch (\Exception $e) {
            nlog($e->getMessage());
            nlog("Exception:: CreateCompany::" . $e->getMessage());
            nlog("SETUP: could not complete setup for South African Locale");
        }

        $company->save();

        return $company;


    }

    private function australiaSetup(Company $company): Company
    {
        try {

            $company->enabled_item_tax_rates = 1;
            $company->enabled_tax_rates = 1;

            $translations = new \stdClass();
            $translations->invoice = "Tax Invoice";

            $settings = $company->settings;
            $settings->currency_id = '12';
            $settings->timezone_id = '109';
            $settings->translations = $translations;

            $company->settings = $settings;

            $company->save();

            return $company;

        } catch (\Exception $e) {
            nlog($e->getMessage());
            nlog("Exception:: CreateCompany::" . $e->getMessage());
            nlog("SETUP: could not complete setup for Australian Locale");
        }

        $company->save();

        return $company;

    }

}

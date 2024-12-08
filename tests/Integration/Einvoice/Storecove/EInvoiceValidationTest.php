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

namespace Tests\Integration\Einvoice\Storecove;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\CompanyToken;
use App\Models\ClientContact;
use App\DataMapper\InvoiceItem;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Services\EDocument\Standards\Peppol;
use InvoiceNinja\EInvoice\Models\Peppol\PaymentMeans;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Services\EDocument\Standards\Validation\Peppol\EntityLevel;

class EInvoiceValidationTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeTestData();
    }

    public function testInvalidCompanySettings()
    {
        
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertFalse($validation['passes']);

    }

    public function testValidBusinessCompanySettings()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = 'ABN321231232';
        $settings->classification = 'business';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'legal_entity_id' => 123231,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertTrue($validation['passes']);

    }


    public function testInValidBusinessCompanySettingsNoVat()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = '';
        $settings->classification = 'business';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'legal_entity_id' => 123231,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertFalse($validation['passes']);

    }

    public function testValidIndividualCompanySettingsNoVat()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = '';
        $settings->id_number ='adfadf';
        $settings->classification = 'individual';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'legal_entity_id' => 123231,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertTrue($validation['passes']);

    }

    public function testInValidBusinessCompanySettingsNoLegalEntity()
    {
        
        $settings = CompanySettings::defaults();
        $settings->address1 = '10 Wallaby Way';
        $settings->city = 'Sydney';
        $settings->state = 'NSW';
        $settings->postal_code = '2113';
        $settings->country_id = '1';
        $settings->vat_number = '';
        $settings->classification = 'business';

        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $el = new EntityLevel();
        $validation = $el->checkCompany($company);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettings()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'classification' => 'business',
            'vat_number' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsNoCountry()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => null,
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddress()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => null,
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountry()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '',
            'address2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountryAndAddress1()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountryAndAddress1AndCity()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => '',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);


        $this->assertFalse($validation['passes']);

    }

    public function testInvalidClientSettingsMissingAddressOnlyCountryAndAddress1AndCityAndState()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 1,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);


        $this->assertFalse($validation['passes']);

    }

    public function testValidIndividualClient()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'individual',
            'vat_number' => '',
            'country_id' => 276,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '2113',
        ]);

        $cc = ClientContact::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'first_name' => 'Bob',
            'last_name' => 'Doe',
            'email' => 'wasa@b.com',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        if(!$validation['passes'])
            nlog($validation);

        $this->assertTrue($validation['passes']);

    }

    public function testValidBusinessClient()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'business',
            'vat_number' => 'DE123456789',
            'country_id' => 276,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '2113',
        ]);

        
        $cc = ClientContact::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'first_name' => 'Bob',
            'last_name' => 'Doe',
            'email' => 'wasa@b.com',
        ]);


        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertTrue($validation['passes']);

    }

    public function testInValidBusinessClientNoVat()
    {

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'classification' => 'business',
            'vat_number' => '',
            'country_id' => 276,
            'address1' => '10 Wallaby Way',
            'address2' => '',
            'city' => 'Sydney',
            'state' => 'NSW',
            'postal_code' => '2113',
        ]);

        
        $cc = ClientContact::factory()->create([
            'client_id' => $client->id,
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'first_name' => 'Bob',
            'last_name' => 'Doe',
            'email' => 'wasa@b.com',
        ]);

        $el = new EntityLevel();
        $validation = $el->checkClient($client);

        $this->assertEquals(0, strlen($client->vat_number));

        $this->assertFalse($validation['passes']);

    }
}
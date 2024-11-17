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
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class StorecoveRouterTest extends TestCase
{
    use DatabaseTransactions;
    
    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();
               
        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->faker = \Faker\Factory::create();

    }

    private function buildData()
    {
                
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword'),
        ]);

        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'client_id' => $client->id
        ]);

        $invoice->service()->markSent()->save();

        return $invoice;

    }

    public function testIsBusinessTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS1234567890';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("IS:VAT", $storecove->router->resolveTaxScheme('IS', 'business'));

    }
    
    // Luxembourg Tests
    public function testLuBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('LU:VAT', $storecove->router->resolveRouting('LU', 'business'));
    }

    public function testLuGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('LU:VAT', $storecove->router->resolveRouting('LU', 'government'));
    }

    public function testLuBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('LU:VAT', $storecove->router->resolveTaxScheme('LU', 'business'));
    }

    public function testLuGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 442;
        $client->vat_number = 'LU12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("LU:VAT", $storecove->router->resolveTaxScheme('LU', 'government'));
    }

    // Norway Tests
    public function testNoBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NO:ORG', $storecove->router->resolveRouting('NO', 'business'));
    }

    public function testNoGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NO:ORG', $storecove->router->resolveRouting('NO', 'government'));
    }

    public function testNoBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NO:VAT', $storecove->router->resolveTaxScheme('NO', 'business'));
    }

    public function testNoGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 578;
        $client->vat_number = 'NO123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("NO:VAT", $storecove->router->resolveTaxScheme('NO', 'government'));
    }

    // Netherlands Tests
    public function testNlBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NL:VAT', $storecove->router->resolveRouting('NL', 'business'));
    }

    public function testNlGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NL:OINO', $storecove->router->resolveRouting('NL', 'government'));
    }

    public function testNlBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('NL:VAT', $storecove->router->resolveTaxScheme('NL', 'business'));
    }

    public function testNlGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 528;
        $client->vat_number = 'NL123456789B01';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals(false, $storecove->router->resolveTaxScheme('NL', 'government'));
    }

    // Sweden Tests
    public function testSeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:ORGNR', $storecove->router->resolveRouting('SE', 'business'));
    }

    public function testSeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:ORGNR', $storecove->router->resolveRouting('SE', 'government'));
    }

    public function testSeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:VAT', $storecove->router->resolveTaxScheme('SE', 'business'));
    }

    public function testSeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 752;
        $client->vat_number = 'SE123456789101';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('SE:VAT', $storecove->router->resolveTaxScheme('SE', 'government'));
    }

    // Iceland Tests
    public function testIsBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:KTNR', $storecove->router->resolveRouting('IS', 'business'));
    }

    public function testIsGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:KTNR', $storecove->router->resolveRouting('IS', 'government'));
    }

    public function testIsBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:VAT', $storecove->router->resolveTaxScheme('IS', 'business'));
    }

    public function testIsGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 352;
        $client->vat_number = 'IS123456';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IS:VAT', $storecove->router->resolveTaxScheme('IS', 'government'));
    }

    // Ireland Tests
    public function testIeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveRouting('IE', 'business'));
    }

    public function testIeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveRouting('IE', 'government'));
    }

    public function testIeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveTaxScheme('IE', 'business'));
    }

    public function testIeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 372;
        $client->vat_number = 'IE1234567T';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('IE:VAT', $storecove->router->resolveTaxScheme('IE', 'government'));
    }


    // Denmark Tests
    public function testDkBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:DIGST', $storecove->router->resolveRouting('DK', 'business'));
    }

    public function testDkGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:DIGST', $storecove->router->resolveRouting('DK', 'government'));
    }

    public function testDkBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:ERST', $storecove->router->resolveTaxScheme('DK', 'business'));
    }

    public function testDkGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 208;
        $client->vat_number = 'DK12345678';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DK:ERST', $storecove->router->resolveTaxScheme('DK', 'government'));
    }

    // UK/England Tests
    public function testGbBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveRouting('GB', 'business'));
    }

    public function testGbGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveRouting('GB', 'government'));
    }

    public function testGbBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveTaxScheme('GB', 'business'));
    }

    public function testGbGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 826;
        $client->vat_number = 'GB123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('GB:VAT', $storecove->router->resolveTaxScheme('GB', 'government'));
    }

    public function testBeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56; // Belgium
        $client->vat_number = 'BE0123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:EN', $storecove->router->resolveRouting('BE', 'business'));
    }

    public function testBeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:EN', $storecove->router->resolveRouting('BE', 'government'));
    }

    public function testBeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:VAT', $storecove->router->resolveTaxScheme('BE', 'business'));
    }

    public function testBeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 56;
        $client->vat_number = 'BE0123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('BE:VAT', $storecove->router->resolveTaxScheme('BE', 'government'));
    }


    public function testAtBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('AT:VAT', $storecove->router->resolveRouting('AT', 'business'));

    }

    public function testAtGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("9915:b", $storecove->router->resolveRouting('AT', 'government'));

    }

    public function testAtBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('AT:VAT', $storecove->router->resolveTaxScheme('AT', 'business'));

    }

    public function testAtGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 40;
        $client->vat_number = 'ATU123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals(false, $storecove->router->resolveTaxScheme('AT', 'government'));

    }

    public function testDeSteurNummerRegistration()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        // $client->vat_number = 'DE123456789';
        $client->id_number = '12/345/67890';
        $client->classification = 'individual';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DE:STNR', $storecove->router->resolveRouting('DE', 'individual'));

    }

    public function testDeBusinessClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DE:VAT', $storecove->router->resolveRouting('DE', 'business'));

    }

    public function testDeGovClientRoutingIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals("DE:LWID", $storecove->router->resolveRouting('DE', 'government'));

    }

    public function testDeBusinessClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'business';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals('DE:VAT', $storecove->router->resolveTaxScheme('DE', 'business'));

    }

    public function testDeGovClientTaxIdentifier()
    {
        $invoice = $this->buildData();

        $client = $invoice->client;
        $client->country_id = 276;
        $client->vat_number = 'DE123456789';
        $client->classification = 'government';
        $client->save();

        $storecove = new Storecove();
        $storecove->router->setInvoice($invoice->fresh());

        $this->assertEquals(false, $storecove->router->resolveTaxScheme('DE', 'government'));

    }

    
}
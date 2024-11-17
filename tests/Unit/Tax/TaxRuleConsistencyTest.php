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

namespace Tests\Unit\Tax;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\CompanySettings;
use App\Models\ClientContact;
use App\Services\Tax\StorecoveAdapter;
use Illuminate\Routing\Middleware\ThrottleRequests;
use App\Services\EDocument\Gateway\Storecove\Storecove;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class TaxRuleConsistencyTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();
        
        $this->faker = \Faker\Factory::create();

    }

    private function setupTestData(array $params = []): array
    {
        $company_iso = isset($params['company_country']) ? $params['company_country'] : 'DE';

        $settings = CompanySettings::defaults();
        $settings->vat_number = $params['company_vat'] ?? 'DE123456789';
        $settings->country_id = (string)Country::where('iso_3166_2', $company_iso)->first()->id;
        $settings->email = $this->faker->safeEmail();

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = $params['over_threshold'] ?? false;
        $tax_data->regions->EU->tax_all_subregions = true;

        $this->company->settings = $settings;
        $this->company->tax_data = $tax_data;
        $this->company->save();
        $company = $this->company;

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'country_id' => Country::where('iso_3166_2', $params['client_country'] ?? 'FR')->first()->id,
            'vat_number' => $params['client_vat'] ?? '',
            'classification' => $params['classification'] ?? 'individual',
            'has_valid_vat_number' => $params['has_valid_vat'] ?? false,
            'name' => 'Test Client'
        ]);

        $contact = ClientContact::factory()->create([
            'client_id' => $client->id,
            'company_id' =>$client->company_id,
            'user_id' => $client->user_id,
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'email' => $this->faker->safeEmail()
        ]);

        $invoice = Invoice::factory()->create([
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'discount' => rand(1,10),
        ]);

        $e_invoice = new \InvoiceNinja\EInvoice\Models\Peppol\Invoice();

        $stub = json_decode('{"Invoice":{"Note":"Nooo","PaymentMeans":[{"ID":{"value":"afdasfasdfasdfas"},"PayeeFinancialAccount":{"Name":"PFA-NAME","ID":{"value":"DE89370400440532013000"},"AliasName":"PFA-Alias","AccountTypeCode":{"value":"CHECKING"},"AccountFormatCode":{"value":"IBAN"},"CurrencyCode":{"value":"EUR"},"FinancialInstitutionBranch":{"ID":{"value":"DEUTDEMMXXX"},"Name":"Deutsche Bank"}}}]}}');
        foreach ($stub as $key => $value) {
            $e_invoice->{$key} = $value;
        }

        $invoice->e_invoice = $e_invoice;
        $invoice->save();


        $invoice->setRelation('company', $this->company);

        return compact('company', 'client', 'invoice');
    }

    public function testScenarios()
    {
        $scenarios = [
            'B2C Over Threshold' => [
                'params' => [
                    'company_country' => 'DE',
                    'client_country' => 'FR',
                    'company_vat' => 'DE123456789',
                    'client_vat' => '',
                    'classification' => 'individual',
                    'has_valid_vat' => false,
                    'over_threshold' => true,
                ],
                'expected_rate' => 20, // Should use French VAT
                'expected_nexus' => 'FR',
            ],
            'B2C Under Threshold' => [
                'params' => [
                    'company_country' => 'DE',
                    'client_country' => 'FR',
                    'company_vat' => 'DE123456789',
                    'client_vat' => '',
                    'classification' => 'individual',
                    'has_valid_vat' => false,
                    'over_threshold' => false,
                ],
                'expected_rate' => 19, // Should use German VAT
                'expected_nexus' => 'DE',
            ],
            'B2B Transaction DE FR' => [
                'params' => [
                    'company_country' => 'DE',
                    'client_country' => 'FR',
                    'company_vat' => 'DE123456789',
                    'client_vat' => 'FR123456789',
                    'classification' => 'business',
                    'has_valid_vat' => true,
                    'over_threshold' => true,
                ],
                'expected_rate' => 19, // Should use German VAT
                'expected_nexus' => 'DE',
            ],
            'B2B Transaction US DK' => [
                'params' => [
                    'company_country' => 'US',
                    'client_country' => 'DK',
                    'company_vat' => 'US123456789',
                    'client_vat' => 'DK123456789',
                    'classification' => 'business',
                    'has_valid_vat' => true,
                    'over_threshold' => true,
                ],
                'expected_rate' => 25, // Should use DK VAT
                'expected_nexus' => 'DK',
            ],
        ];

        foreach ($scenarios as $name => $scenario) {
            $data = $this->setupTestData($scenario['params']);

            // Test BaseRule
            $baseRule = new BaseRule();
            $baseRule->setEntity($data['invoice']);
            $baseRule->defaultForeign();

            // Test StorecoveAdapter
            $storecove = new Storecove();
            $storecove->build($data['invoice']);

            $this->assertEquals(
                $scenario['expected_rate'],
                $baseRule->tax_rate1, "{$name} {$scenario['expected_nexus']}"
            );

            $this->assertEquals(
                $storecove->adapter->getNexus(),
                $scenario['expected_nexus']
            );

        }
    }
}

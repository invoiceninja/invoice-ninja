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

use App\DataMapper\CompanySettings;
use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\DE\Rule;
use App\DataMapper\Tax\TaxModel;
use App\DataMapper\Tax\ZipTax\Response;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 *  App\Services\Tax\Providers\EuTax
 */
class EuTaxTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->makeTestData();
    }


    public function testEuToUkTaxCalculation()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->regions->US->tax_all_subregions = true;
        $tax_data->regions->US->has_sales_above_threshold = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 826,
            'state' => 'CA',
            'postal_code' => '90210',
            'shipping_country_id' => 840,
            'has_valid_vat_number' => false,
            'is_tax_exempt' => false,
           
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
        ]);

        $br = new BaseRule();
        $br->setEntity($invoice);
        $this->assertFalse($br->isTaxableRegion());
    }

    public function testEuToUsTaxCalculation()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->regions->US->tax_all_subregions = true;
        $tax_data->regions->US->has_sales_above_threshold = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 840,
            'state' => 'CA',
            'postal_code' => '90210',
            'shipping_country_id' => 840,
            'has_valid_vat_number' => false,
            'is_tax_exempt' => false,
            'tax_data' => new Response([
                'geoState' => 'CA',
                'taxSales' => 0.07,
            ]),
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'tax_id' => Product::PRODUCT_TYPE_PHYSICAL,
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_data' => new Response([
                'geoState' => 'CA',
                'taxSales' => 0.07,
            ]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(107, $invoice->amount);

    }

    public function testEuToBrazilTaxCalculations()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->regions->AU->tax_all_subregions = true;
        $tax_data->regions->AU->has_sales_above_threshold = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 76,
            'shipping_country_id' => 76,
            'has_valid_vat_number' => false,
            'is_tax_exempt' => false,
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'tax_id' => Product::PRODUCT_TYPE_PHYSICAL,
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(100, $invoice->amount);

    }


    public function testEuToAuTaxCalculationExemptProduct()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->regions->AU->tax_all_subregions = true;
        $tax_data->regions->AU->has_sales_above_threshold = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 36,
            'shipping_country_id' => 36,
            'has_valid_vat_number' => false,
            'is_tax_exempt' => false,
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'tax_id' => Product::PRODUCT_TYPE_EXEMPT,
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(100, $invoice->amount);

    }


    public function testEuToAuTaxCalculationExemptClient()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->regions->AU->tax_all_subregions = true;
        $tax_data->regions->AU->has_sales_above_threshold = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 36,
            'shipping_country_id' => 36,
            'has_valid_vat_number' => false,
            'is_tax_exempt' => true,
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'tax_id' => Product::PRODUCT_TYPE_PHYSICAL,
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(100, $invoice->amount);

    }



    public function testEuToAuTaxCalculation()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;
        $tax_data->regions->AU->tax_all_subregions = true;
        $tax_data->regions->AU->has_sales_above_threshold = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 36,
            'shipping_country_id' => 36,
            'has_valid_vat_number' => false,
            'is_tax_exempt' => false,
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'tax_id' => Product::PRODUCT_TYPE_PHYSICAL,
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            // 'tax_data' => new Response([
            //     'geoState' => 'CA',
            //     'taxSales' => 0.07,
            // ]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(110, $invoice->amount);

    }



    public function testInvoiceTaxCalcDetoBeNoVat()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => false,
            'vat_number' => ''
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'tax_id' => 1
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_data' => new Response([]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(21, $invoice->line_items[0]->tax_rate1);
        $this->assertEquals(121, $invoice->amount);
    }

    public function testInvoiceTaxCalcDetoBe()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => true,
            'is_tax_exempt' => false,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_data' => new Response([]),
        ]);

        $invoice = $invoice->calc()->getInvoice()->service()->markSent()->save();

        $this->assertEquals(0, $invoice->line_items[0]->tax_rate1);
        $this->assertEquals(100, $invoice->amount);
    }


    public function testInvoiceTaxCalcDetoDe()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
            'calculate_taxes' => true,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => true,
        ]);

        $invoice = Invoice::factory()->create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'status_id' => 1,
            'user_id' => $this->user->id,
            'uses_inclusive_taxes' => false,
            'discount' => 0,
            'line_items' => [
                [
                    'product_key' => 'Test',
                    'notes' => 'Test',
                    'cost' => 100,
                    'quantity' => 1,
                    'tax_name1' => '',
                    'tax_rate1' => 0,
                    'tax_name2' => '',
                    'tax_rate2' => 0,
                    'tax_name3' => '',
                    'tax_rate3' => 0,
                    'type_id' => '1',
                    'tax_id' => 1,
                ],
            ],
            'tax_rate1' => 0,
            'tax_rate2' => 0,
            'tax_rate3' => 0,
            'tax_name1' => '',
            'tax_name2' => '',
            'tax_name3' => '',
            'tax_data' => new Response([]),
        ]);

        $invoice = $invoice->calc()->getInvoice();

        $this->assertEquals(19, $invoice->line_items[0]->tax_rate1);
        $this->assertEquals(119, $invoice->amount);

    }


    public function testCorrectRuleInit()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);

        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => false,
        ]);

        $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'user_id' => $this->user->id,
        'status_id' => Invoice::STATUS_SENT,
        'tax_data' => new Response([
                    'geoState' => 'CA',
        ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();

        $this->assertEquals('EU', $process->seller_region);
        $this->assertEquals('DE', $process->client_subregion);

        $this->assertFalse($client->has_valid_vat_number);

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertEquals(19, $process->tax_rate);

        $this->assertEquals(7, $process->reduced_tax_rate);


    }

    public function testEuCorrectRuleInit()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => false,
            'vat_number' => ''
        ]);

        $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'user_id' => $this->user->id,
        'status_id' => Invoice::STATUS_SENT,
        'tax_data' => new Response([
                    'geoState' => 'CA',
            ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();

        $this->assertEquals('EU', $process->seller_region);

        $this->assertEquals('BE', $process->client_subregion);

        $this->assertFalse($client->has_valid_vat_number);

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertEquals(21, $process->tax_rate);

        $this->assertEquals(6, $process->reduced_tax_rate);


    }

    public function testForeignCorrectRuleInit()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = true;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 840,
            'shipping_country_id' => 840,
            'state' => 'CA',
            'postal_code' => '90210',
            'has_valid_vat_number' => false,
            'vat_number' => '',
        ]);

        $invoice = Invoice::factory()->create([
           'company_id' => $company->id,
           'client_id' => $client->id,
           'user_id' => $this->user->id,
           'status_id' => Invoice::STATUS_SENT,
           'tax_data' => new Response([
           'geoState' => 'CA',
           ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();

        $this->assertEquals('EU', $process->seller_region);

        $this->assertEquals('CA', $process->client_subregion);

        $this->assertFalse($client->has_valid_vat_number);

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertEquals(0, $process->tax_rate);

        $this->assertEquals(0, $process->reduced_tax_rate);

    }


    public function testSubThresholdCorrectRate()
    {

        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => false,
            'vat_number' => ''
        ]);

        $invoice = Invoice::factory()->create([
        'company_id' => $company->id,
        'client_id' => $client->id,
        'user_id' => $this->user->id,
        'status_id' => Invoice::STATUS_SENT,
        'tax_data' => new Response([
                    'geoState' => 'CA',
        ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertFalse($client->has_valid_vat_number);

        // $this->assertEquals(19, $process->tax_rate);

        // $this->assertEquals(7, $process->reduced_tax_rate);

    }


    //tests with valid vat.
    public function testDeWithValidVat()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => true,
        ]);

        $invoice = Invoice::factory()->create([
           'company_id' => $company->id,
           'client_id' => $client->id,
           'user_id' => $this->user->id,
           'status_id' => Invoice::STATUS_SENT,
           'tax_data' => new Response([
           'geoState' => 'CA',
           ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();


        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->has_valid_vat_number);

        $this->assertEquals(19, $process->tax_rate);

        $this->assertEquals(7, $process->reduced_tax_rate);

    }

    //tests with valid vat.
    public function testDeToEUWithValidVat()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => true,
        ]);

        $invoice = Invoice::factory()->create([
           'company_id' => $company->id,
           'client_id' => $client->id,
           'user_id' => $this->user->id,
           'status_id' => Invoice::STATUS_SENT,
           'tax_data' => new Response([
                    'geoState' => 'CA',
           ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();


        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->has_valid_vat_number);

        $this->assertEquals(0, $process->tax_rate);

        $this->assertEquals(0, $process->reduced_tax_rate);

    }

    public function testTaxExemptionDeSellerBeBuyer()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 56,
            'shipping_country_id' => 56,
            'has_valid_vat_number' => true,
            'is_tax_exempt' => true,
        ]);

        $invoice = Invoice::factory()->create([
           'company_id' => $company->id,
           'client_id' => $client->id,
           'user_id' => $this->user->id,
           'status_id' => Invoice::STATUS_SENT,
           'tax_data' => new Response([
                    'geoState' => 'CA',
           ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();


        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->is_tax_exempt);

        $this->assertEquals(0, $process->tax_rate);

        $this->assertEquals(0, $process->reduced_tax_rate);

    }

    public function testTaxExemptionDeSellerDeBuyer()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->seller_subregion = 'DE';
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 276,
            'shipping_country_id' => 276,
            'has_valid_vat_number' => true,
            'is_tax_exempt' => true,
        ]);

        $invoice = Invoice::factory()->create([
           'company_id' => $company->id,
           'client_id' => $client->id,
           'user_id' => $this->user->id,
           'status_id' => Invoice::STATUS_SENT,
           'tax_data' => new Response([
                    'geoState' => 'CA',
           ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();

        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->is_tax_exempt);

        $this->assertEquals(0, $process->tax_rate);

        $this->assertEquals(0, $process->reduced_tax_rate);

    }

    public function testTaxExemption3()
    {
        $settings = CompanySettings::defaults();
        $settings->country_id = '276'; // germany

        $tax_data = new TaxModel();
        $tax_data->regions->EU->has_sales_above_threshold = false;
        $tax_data->regions->EU->tax_all_subregions = true;

        $company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
            'tax_data' => $tax_data,
        ]);


        $client = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $company->id,
            'country_id' => 840,
            'state' => 'CA',
            'postal_code' => '90210',
            'shipping_country_id' => 840,
            'has_valid_vat_number' => true,
            'is_tax_exempt' => true,
        ]);

        $invoice = Invoice::factory()->create([
           'company_id' => $company->id,
           'client_id' => $client->id,
           'user_id' => $this->user->id,
           'status_id' => Invoice::STATUS_SENT,
           'tax_data' => new Response([
                'geoState' => 'CA',
           ]),
        ]);

        $process = new Rule();
        $process->setEntity($invoice);
        $process->init();


        $this->assertInstanceOf(Rule::class, $process);

        $this->assertTrue($client->is_tax_exempt);

        $this->assertEquals(0, $process->tax_rate);

        $this->assertEquals(0, $process->reduced_tax_rate);

    }

}

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

namespace Tests\Feature\Export;

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Credit;
use League\Csv\Reader;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use Tests\MockAccountData;
use App\Models\CompanyToken;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Expense;
use App\Services\Report\ARDetailReport;
use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * @test
 */
class ReportCsvGenerationTest extends TestCase
{
    use MakesHash;

    public $faker;

    protected function setUp() :void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        $this->buildData();


    }

    public $company;

    public $user;

    public $payload;

    public $account;

    public $client;

    public $token;

    public $cu;

    /**
     *      start_date - Y-m-d
            end_date - Y-m-d
            date_range -
                all
                last7
                last30
                this_month
                last_month
                this_quarter
                last_quarter
                this_year
                custom
            is_income_billed - true = Invoiced || false = Payments
            expense_billed - true = Expensed || false = Expenses marked as paid
            include_tax - true tax_included || false - tax_excluded
     */
    private function buildData()
    {
        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
            'settings' => $settings,
        ]);

        $this->company->settings = $settings;
        $this->company->save();

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken;
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;

        $company_token->save();

        $this->payload = [
            'start_date' => '2000-01-01',
            'end_date' => '2030-01-11',
            'date_range' => 'custom',
            'is_income_billed' => true,
            'include_tax' => false,
        ];

        $this->client = Client::factory()->create([
            'user_id' => $this->user->id,
            // 'assigned_user_id', $this->user->id,
            'company_id' => $this->company->id,
            'is_deleted' => 0,
            'name' => 'bob',
            'address1' => '1234',
            'balance' => 100,
            'paid_to_date' => 50,
        ]);

        ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
                'first_name' => 'john',
                'last_name' => 'doe',
                'email' => 'john@doe.com'
            ]);

    }

    public function testTasksCsvGeneration()
    {

        $log =  '[[1689547165,1689550765,"sumtin",true]]';

        \App\Models\Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'description' => 'test',
            'time_log' => $log,
            'custom_value1' => 'Custom 1',
            'custom_value2' => 'Custom 2',
            'custom_value3' => 'Custom 3',
            'custom_value4' => 'Custom 4',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/tasks', $data);
       
        $csv = $response->streamedContent();

        $this->assertEquals(3600, $this->getFirstValueByColumn($csv, 'Duration'));
        $this->assertEquals('test', $this->getFirstValueByColumn($csv, 'Description'));
        $this->assertEquals('16/Jul/2023', $this->getFirstValueByColumn($csv, 'Start Date'));
        $this->assertEquals('16/Jul/2023', $this->getFirstValueByColumn($csv, 'End Date'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Custom Value 4'));

    }

    public function testProductsCsvGeneration()
    {

        \App\Models\Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'product_key' => 'product_key',
            'notes' => 'notes',
            'cost' => 100,
            'quantity' => 1,
            'custom_value1' => 'Custom 1',
            'custom_value2' => 'Custom 2',
            'custom_value3' => 'Custom 3',
            'custom_value4' => 'Custom 4',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/products', $data);
       
        $csv = $response->streamedContent();

        $this->assertEquals('product_key', $this->getFirstValueByColumn($csv, 'Product'));
        $this->assertEquals('notes', $this->getFirstValueByColumn($csv, 'Notes'));
        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Cost'));
        $this->assertEquals(1, $this->getFirstValueByColumn($csv, 'Quantity'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Custom Value 4'));
    
    }


    public function testPaymentCsvGeneration()
    {

        \App\Models\Payment::factory()->create([
            'amount' => 500,
            'date' => '2020-01-01',
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'transaction_reference' => '1234',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/payments', $data);
       
        $csv = $response->streamedContent();

        $this->assertEquals(500, $this->getFirstValueByColumn($csv, 'Amount'));
        $this->assertEquals(0, $this->getFirstValueByColumn($csv, 'Applied'));
        $this->assertEquals(0, $this->getFirstValueByColumn($csv, 'Refunded'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Date'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Transaction Reference'));
    
    }

    public function testClientCsvGeneration()
    {

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/clients', $data);
       
        $csv = $response->streamedContent();

        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        
        $res = $reader->fetchColumnByName('Street');
        $res = iterator_to_array($res, true);

        $this->assertEquals('1234', $res[1]);

        $res = $reader->fetchColumnByName('Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('bob', $res[1]);

    }

    public function testClientCustomColumnsCsvGeneration()
    {

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","client.user","client.assigned_user","client.balance","client.paid_to_date","client.currency_id"],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/clients', $data);
       
        $csv = $response->streamedContent();

        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Name'));
        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Balance'));
        $this->assertEquals(50, $this->getFirstValueByColumn($csv, 'Paid to Date'));
        $this->assertEquals($this->user->present()->name(), $this->getFirstValueByColumn($csv, 'Client User'));
        $this->assertEquals('', $this->getFirstValueByColumn($csv, 'Client Assigned User'));
        $this->assertEquals('USD', $this->getFirstValueByColumn($csv, 'Client Currency'));

    }





    public function testClientContactCsvGeneration()
    {

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/contacts', $data);
       
        $csv = $response->streamedContent();

        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        
        $res = $reader->fetchColumnByName('First Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('john', $res[1]);

        $res = $reader->fetchColumnByName('Last Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('doe', $res[1]);

        $res = $reader->fetchColumnByName('Email');
        $res = iterator_to_array($res, true);

        $this->assertEquals('john@doe.com', $res[1]);

    }

    private function getFirstValueByColumn($csv, $column)
    {
        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);
        
        $res = $reader->fetchColumnByName($column);
        $res = iterator_to_array($res, true);

        return $res[1];
    }

    public function testCreditCsvGeneration()
    {

        Credit::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'balance' => 50,
            'status_id' => 2,
            'discount' => 10,
            'po_number' => '1234',
            'public_notes' => 'Public',
            'private_notes' => 'Private',
            'terms' => 'Terms',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/credits', $data);

        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Amount'));
        $this->assertEquals('50', $this->getFirstValueByColumn($csv, 'Balance'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Terms'));
    }

    public function testInvoiceCsvGeneration()
    {

        Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'balance' => 50,
            'status_id' => 2,
            'discount' => 10,
            'po_number' => '1234',
            'public_notes' => 'Public',
            'private_notes' => 'Private',
            'terms' => 'Terms',
            'date' => '2020-01-01',
            'due_date' => '2021-01-02',
            'partial_due_date' => '2021-01-03',
            'partial' => 10,
            'discount' => 10,
            'custom_value1' => 'Custom 1',
            'custom_value2' => 'Custom 2',
            'custom_value3' => 'Custom 3',
            'custom_value4' => 'Custom 4',
            'footer' => 'Footer',
            'tax_name1' => 'Tax 1',
            'tax_rate1' => 10,
            'tax_name2' => 'Tax 2',
            'tax_rate2' => 20,
            'tax_name3' => 'Tax 3',
            'tax_rate3' => 30,

        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoices', $data);

        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Amount'));
        $this->assertEquals('50', $this->getFirstValueByColumn($csv, 'Balance'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Terms'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Date'));
        $this->assertEquals('2021-01-02', $this->getFirstValueByColumn($csv, 'Due Date'));
        $this->assertEquals('2021-01-03', $this->getFirstValueByColumn($csv, 'Partial Due Date'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Partial/Deposit'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Custom Value 4'));
        $this->assertEquals('Footer', $this->getFirstValueByColumn($csv, 'Footer'));
        $this->assertEquals('Tax 1', $this->getFirstValueByColumn($csv, 'Tax Name 1'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Tax Rate 1'));
        $this->assertEquals('Tax 2', $this->getFirstValueByColumn($csv, 'Tax Name 2'));
        $this->assertEquals('20', $this->getFirstValueByColumn($csv, 'Tax Rate 2'));
        $this->assertEquals('Tax 3', $this->getFirstValueByColumn($csv, 'Tax Name 3'));
        $this->assertEquals('30', $this->getFirstValueByColumn($csv, 'Tax Rate 3'));
        $this->assertEquals('Sent', $this->getFirstValueByColumn($csv, 'Status'));

    }

    public function testRecurringInvoiceCsvGeneration()
    {

        \App\Models\RecurringInvoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'balance' => 50,
            'status_id' => 2,
            'discount' => 10,
            'po_number' => '1234',
            'public_notes' => 'Public',
            'private_notes' => 'Private',
            'terms' => 'Terms',
            'date' => '2020-01-01',
            'due_date' => '2021-01-02',
            'partial_due_date' => '2021-01-03',
            'partial' => 10,
            'discount' => 10,
            'custom_value1' => 'Custom 1',
            'custom_value2' => 'Custom 2',
            'custom_value3' => 'Custom 3',
            'custom_value4' => 'Custom 4',
            'footer' => 'Footer',
            'tax_name1' => 'Tax 1',
            'tax_rate1' => 10,
            'tax_name2' => 'Tax 2',
            'tax_rate2' => 20,
            'tax_name3' => 'Tax 3',
            'tax_rate3' => 30,
            'frequency_id' => 1,
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/recurring_invoices', $data);

        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Amount'));
        $this->assertEquals('50', $this->getFirstValueByColumn($csv, 'Balance'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Terms'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Date'));
        $this->assertEquals('2021-01-02', $this->getFirstValueByColumn($csv, 'Due Date'));
        $this->assertEquals('2021-01-03', $this->getFirstValueByColumn($csv, 'Partial Due Date'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Partial/Deposit'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Custom Value 4'));
        $this->assertEquals('Footer', $this->getFirstValueByColumn($csv, 'Footer'));
        $this->assertEquals('Tax 1', $this->getFirstValueByColumn($csv, 'Tax Name 1'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Tax Rate 1'));
        $this->assertEquals('Tax 2', $this->getFirstValueByColumn($csv, 'Tax Name 2'));
        $this->assertEquals('20', $this->getFirstValueByColumn($csv, 'Tax Rate 2'));
        $this->assertEquals('Tax 3', $this->getFirstValueByColumn($csv, 'Tax Name 3'));
        $this->assertEquals('30', $this->getFirstValueByColumn($csv, 'Tax Rate 3'));
        $this->assertEquals('Daily', $this->getFirstValueByColumn($csv, 'Frequency'));

    }


    public function testQuoteCsvGeneration()
    {

        \App\Models\Quote::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'balance' => 50,
            'status_id' => 2,
            'discount' => 10,
            'po_number' => '1234',
            'public_notes' => 'Public',
            'private_notes' => 'Private',
            'terms' => 'Terms',
            'date' => '2020-01-01',
            'due_date' => '2020-01-01',
            'partial_due_date' => '2021-01-03',
            'partial' => 10,
            'discount' => 10,
            'custom_value1' => 'Custom 1',
            'custom_value2' => 'Custom 2',
            'custom_value3' => 'Custom 3',
            'custom_value4' => 'Custom 4',
            'footer' => 'Footer',
            'tax_name1' => 'Tax 1',
            'tax_rate1' => 10,
            'tax_name2' => 'Tax 2',
            'tax_rate2' => 20,
            'tax_name3' => 'Tax 3',
            'tax_rate3' => 30,

        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/quotes', $data);

        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Amount'));
        $this->assertEquals('50', $this->getFirstValueByColumn($csv, 'Balance'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Terms'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Date'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Valid Until'));
        $this->assertEquals('2021-01-03', $this->getFirstValueByColumn($csv, 'Partial Due Date'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Partial/Deposit'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Custom Value 4'));
        $this->assertEquals('Footer', $this->getFirstValueByColumn($csv, 'Footer'));
        $this->assertEquals('Tax 1', $this->getFirstValueByColumn($csv, 'Tax Name 1'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Tax Rate 1'));
        $this->assertEquals('Tax 2', $this->getFirstValueByColumn($csv, 'Tax Name 2'));
        $this->assertEquals('20', $this->getFirstValueByColumn($csv, 'Tax Rate 2'));
        $this->assertEquals('Tax 3', $this->getFirstValueByColumn($csv, 'Tax Name 3'));
        $this->assertEquals('30', $this->getFirstValueByColumn($csv, 'Tax Rate 3'));
        $this->assertEquals('Expired', $this->getFirstValueByColumn($csv, 'Status'));

    }


    public function testExpenseCsvGeneration()
    {
        Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'amount' => 100,
            'public_notes' => 'Public',
            'private_notes' => 'Private',            
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/expenses', $data);

        $response->assertStatus(200);

        $csv = $response->streamedContent();

        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Amount'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Private Notes'));
        $this->assertEquals($this->user->present()->name(), $this->getFirstValueByColumn($csv, 'User'));
        
    }


}
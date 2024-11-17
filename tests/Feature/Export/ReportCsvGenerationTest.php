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

use App\DataMapper\CompanySettings;
use App\Export\CSV\PaymentExport;
use App\Export\CSV\ProductExport;
use App\Export\CSV\TaskExport;
use App\Export\CSV\VendorExport;
use App\Factory\CompanyUserFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Http;
use League\Csv\Reader;
use Tests\TestCase;

/**
 * 
 */
class ReportCsvGenerationTest extends TestCase
{
    use MakesHash;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->withoutExceptionHandling();

        Invoice::withTrashed()->cursor()->each(function ($i) { $i->forceDelete();});

        $this->buildData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }

    }

    public $company;

    public $user;

    public $payload;

    public $account;

    public $client;

    public $token;

    public $cu;

    private $all_client_report_keys =  [
        "client.name",
        "client.user",
        "client.assigned_user",
        "client.balance",
        "client.address1",
        "client.address2",
        "client.city",
        "client.country_id",
        "client.currency_id",
        "client.custom_value1",
        "client.custom_value2",
        "client.custom_value3",
        "client.custom_value4",
        "client.industry_id",
        "client.id_number",
        "client.paid_to_date",
        "client.payment_terms",
        "client.phone",
        "client.postal_code",
        "client.private_notes",
        "client.public_notes",
        "client.size_id",
        "client.shipping_address1",
        "client.shipping_address2",
        "client.shipping_city",
        "client.shipping_state",
        "client.shipping_postal_code",
        "client.shipping_country_id",
        "client.state",
        "client.vat_number",
        "client.website",
        "contact.first_name",
        "contact.last_name",
        "contact.email",
        "contact.phone",
        "contact.custom_value1",
        "contact.custom_value2",
        "contact.custom_value3",
        "contact.custom_value4",
    ];

    private $all_payment_report_keys = [
            'payment.date',
            'payment.amount',
            'payment.refunded',
            'payment.applied',
            'payment.transaction_reference',
            'payment.currency',
            'payment.exchange_rate',
            'payment.number',
            'payment.method',
            'payment.status',
            'payment.private_notes',
            'payment.custom_value1',
            'payment.custom_value2',
            'payment.custom_value3',
            'payment.custom_value4',
            'payment.user_id',
            'payment.assigned_user_id'
        ];

    private $all_invoice_report_keys = [
        'invoice.number',
        'invoice.amount',
        'invoice.balance',
        'invoice.paid_to_date',
        'invoice.discount',
        'invoice.po_number',
        'invoice.date',
        'invoice.due_date',
        'invoice.terms',
        'invoice.footer',
        'invoice.status',
        'invoice.public_notes',
        'invoice.private_notes',
        'invoice.uses_inclusive_taxes',
        'invoice.is_amount_discount',
        'invoice.partial',
        'invoice.partial_due_date',
        'invoice.custom_value1',
        'invoice.custom_value2',
        'invoice.custom_value3',
        'invoice.custom_value4',
        'invoice.custom_surcharge1',
        'invoice.custom_surcharge2',
        'invoice.custom_surcharge3',
        'invoice.custom_surcharge4',
        'invoice.exchange_rate',
        'invoice.total_taxes',
        'invoice.assigned_user_id',
        'invoice.user_id',
    ];

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
        /** @var \App\Models\Account $account */
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

        $company_token = new CompanyToken();
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

    public function testContactProps()
    {
        Invoice::factory()->count(5)->create(
            [
                'client_id' => $this->client->id,
                'company_id' => $this->company->id,
                'user_id' => $this->user->id
            ]
        );

        $data = [
            'date_range' => 'all',
            'report_keys' => ['invoice.number','client.name', 'contact.email'],
            'send_email' => false,
            'client_id' => $this->client->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoices', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        $this->assertEquals('john@doe.com', $this->getFirstValueByColumn($csv, 'Contact Email'));

    }

    public function testForcedInsertionOfMandatoryColumns()
    {
        $forced = ['client.name'];

        $report_keys = ['invoice.number','client.name', 'invoice.amount'];
        $array = array_merge($report_keys, array_diff($forced, $report_keys));

        $this->assertEquals('client.name', $array[1]);

        $report_keys = ['invoice.number','invoice.amount'];
        $array = array_merge($report_keys, array_diff($forced, $report_keys));

        $this->assertEquals('client.name', $array[2]); //@phpstan-ignore-line

    }

    private function poll($hash)
    {
        $response = Http::retry(100, 400, throw: false)
                    ->withHeaders([
                        'X-API-SECRET' => config('ninja.api_secret'),
                        'X-API-TOKEN' => $this->token,
                    ])->post(config('ninja.app_url')."/api/v1/exports/preview/{$hash}");

        return $response;
    }


    public function testProductJsonFiltering()
    {

        $query = Invoice::query();

        $products = explode(",", "clown,joker,batman,bob the builder");

        foreach($products as $product) {
            $query->where(function ($q) use ($product) {
                $q->orWhereJsonContains('line_items', ['product_key' => $product]);
            });
        }

        $this->assertEquals(0, $query->count());

        $item = InvoiceItemFactory::create();
        $item->product_key = 'haloumi';

        $line_items = [];

        $line_items[] = $item;
        Invoice::factory()->create(
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'line_items' => $line_items
            ]
        );

        $query->where(function ($q) use ($products) {
            foreach($products as $product) {
                $q->orWhereJsonContains('line_items', ['product_key' => $product]);
            }
        });

        $this->assertEquals(0, $query->count());

        $item = InvoiceItemFactory::create();
        $item->product_key = 'batman';

        $line_items = [];

        $line_items[] = $item;
        $item = InvoiceItemFactory::create();
        $item->product_key = 'bob the builder';

        $line_items[] = $item;

        Invoice::factory()->create(
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'line_items' => $line_items
            ]
        );

        $query = Invoice::query();

        $query->where(function ($q) use ($products) {
            foreach($products as $product) {
                $q->orWhereJsonContains('line_items', ['product_key' => $product]);
            }
        });

        $this->assertEquals(1, $query->count());

        $query = Invoice::query();

        $query->where(function ($q) {
            $q->orWhereJsonContains('line_items', ['product_key' => 'bob the builder']);
        });

        $this->assertEquals(1, $query->count());

        Invoice::withTrashed()->cursor()->each(function ($i) { $i->forceDelete();});

    }

    public function testProductKeyFilterQueries()
    {

        $item = InvoiceItemFactory::create();
        $item->product_key = 'haloumi';

        $line_items = [];

        $line_items[] = $item;
        $q = Invoice::whereJsonContains('line_items', ['product_key' => 'haloumi']);

        $this->assertEquals(0, $q->count());

        Invoice::factory()->create(
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'line_items' => $line_items
            ]
        );

        $this->assertEquals(1, $q->count());

        $q->forceDelete();

        Invoice::factory()->create(
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'line_items' => $line_items
            ]
        );

        $item = InvoiceItemFactory::create();
        $item->product_key = 'bob the builder';

        $line_items = [];

        $line_items[] = $item;

        $q = Invoice::whereJsonContains('line_items', ['product_key' => 'bob the builder']);

        $this->assertEquals(0, $q->count());

        Invoice::factory()->create(
            [
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'client_id' => $this->client->id,
                'line_items' => $line_items
            ]
        );

        $this->assertEquals(1, $q->count());

        $q = Invoice::whereJsonContains('line_items', ['product_key' => 'Bob the builder']);
        $this->assertEquals(0, $q->count());

        $q = Invoice::whereJsonContains('line_items', ['product_key' => 'bob']);
        $this->assertEquals(0, $q->count());

        $q->forceDelete();

        Invoice::withTrashed()->cursor()->each(function ($i) { $i->forceDelete();});
    }

    public function testVendorCsvGeneration()
    {

        $vendor =
        \App\Models\Vendor::factory()->create(
            [
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => 'Vendor 1',
                'city' => 'city',
                'address1' => 'address1',
                'address2' => 'address2',
                'postal_code' => 'postal_code',
                'phone' => 'work_phone',
                'private_notes' => 'private_notes',
                'public_notes' => 'public_notes',
                'website' => 'website',
                'number' => '1234',
            ]
        );

        $data = [
            'date_range' => 'all',
            'report_keys' => [],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/vendors', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        $this->assertEquals('Vendor 1', $this->getFirstValueByColumn($csv, 'Vendor Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Vendor Number'));
        $this->assertEquals('city', $this->getFirstValueByColumn($csv, 'Vendor City'));
        $this->assertEquals('address1', $this->getFirstValueByColumn($csv, 'Vendor Street'));
        $this->assertEquals('address2', $this->getFirstValueByColumn($csv, 'Vendor Apt/Suite'));
        $this->assertEquals('postal_code', $this->getFirstValueByColumn($csv, 'Vendor Postal Code'));
        $this->assertEquals('work_phone', $this->getFirstValueByColumn($csv, 'Vendor Phone'));
        $this->assertEquals('private_notes', $this->getFirstValueByColumn($csv, 'Vendor Private Notes'));
        $this->assertEquals('public_notes', $this->getFirstValueByColumn($csv, 'Vendor Public Notes'));
        $this->assertEquals('website', $this->getFirstValueByColumn($csv, 'Vendor Website'));

        $data = [
            'date_range' => 'all',
            // 'end_date' => 'bail|required_if:date_range,custom|nullable|date',
            // 'start_date' => 'bail|required_if:date_range,custom|nullable|date',
            'report_keys' => [],
            'send_email' => false,
            'include_deleted' => false,
            // 'status' => 'sometimes|string|nullable|in:all,draft,sent,viewed,paid,unpaid,overdue',
        ];

        $export = new VendorExport($this->company, $data);
        $data = $export->returnJson();

        $this->assertNotNull($data);

        $this->assertEquals('Vendor Name', $this->traverseJson($data, 'columns.9.display_value'));
        $this->assertEquals('vendor', $this->traverseJson($data, '0.0.entity'));
        $this->assertEquals('address1', $this->traverseJson($data, '0.0.id'));
        $this->assertNull($this->traverseJson($data, '0.0.hashed_id'));
        $this->assertEquals('address1', $this->traverseJson($data, '0.0.value'));
        $this->assertEquals('vendor.address1', $this->traverseJson($data, '0.0.identifier'));
        $this->assertEquals('address1', $this->traverseJson($data, '0.0.display_value'));
    }

    public function testVendorCustomColumnCsvGeneration()
    {

        \App\Models\Vendor::query()->cursor()->each(function ($t) {
            $t->forceDelete();
        });

        $vendor =
        \App\Models\Vendor::factory()->create(
            [
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => 'Vendor 1',
                'city' => 'city',
                'address1' => 'address1',
                'address2' => 'address2',
                'postal_code' => 'postal_code',
                'phone' => 'work_phone',
                'private_notes' => 'private_notes',
                'public_notes' => 'public_notes',
                'website' => 'website',
                'number' => '1234',
            ]
        );

        $data = [
            'date_range' => 'all',
            'report_keys' => ["vendor.name", "vendor.city", "vendor.number"],
            'send_email' => false,
            'include_deleted' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/vendors', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals('Vendor 1', $this->getFirstValueByColumn($csv, 'Vendor Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Vendor Number'));
        $this->assertEquals('city', $this->getFirstValueByColumn($csv, 'Vendor City'));

        $export = new VendorExport($this->company, $data);
        $data = $export->returnJson();

        $this->assertNotNull($data);

        // $this->assertEquals(0, $this->traverseJson($data, 'columns.0.identifier'));
        $this->assertEquals('Vendor Name', $this->traverseJson($data, 'columns.0.display_value'));
        $this->assertEquals('vendor', $this->traverseJson($data, '0.0.entity'));
        $this->assertEquals('name', $this->traverseJson($data, '0.0.id'));
        $this->assertNull($this->traverseJson($data, '0.0.hashed_id'));
        $this->assertEquals('Vendor 1', $this->traverseJson($data, '0.0.value'));
        $this->assertEquals('vendor.name', $this->traverseJson($data, '0.0.identifier'));
        $this->assertEquals('Vendor 1', $this->traverseJson($data, '0.0.display_value'));
        $this->assertEquals('number', $this->traverseJson($data, '0.2.id'));

    }


    public function testTaskCustomColumnsCsvGeneration()
    {

        $invoice = \App\Models\Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'date' => '2023-01-01',
            'amount' => 1000,
            'balance' => 1000,
            'number' => '123456',
            'status_id' => 2,
            'discount' => 10,
            'po_number' => '12345',
            'public_notes' => 'Public5',
            'private_notes' => 'Private5',
            'terms' => 'Terms5',
            ]);


        $log =  '[[1689547165,1689550765,"sumtin",true]]';

        \App\Models\Task::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'invoice_id' => $invoice->id,
            'description' => 'test1',
            'time_log' => $log,
            'custom_value1' => 'Custom 11',
            'custom_value2' => 'Custom 22',
            'custom_value3' => 'Custom 33',
            'custom_value4' => 'Custom 44',
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                'client.name',
                'invoice.number',
                'invoice.amount',
                'task.start_date',
                'task.end_date',
                'task.duration',
                'task.description',
                'task.custom_value1',
                'task.custom_value2',
                'task.custom_value3',
                'task.custom_value4',
            ],
            'send_email' => false,
            'include_deleted' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/tasks', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        // nlog($csv);
        $this->assertEquals(3600, $this->getFirstValueByColumn($csv, 'Task Duration'));
        $this->assertEquals('test1', $this->getFirstValueByColumn($csv, 'Task Description'));
        $this->assertEquals('16/Jul/2023', $this->getFirstValueByColumn($csv, 'Task Start Date'));
        $this->assertEquals('16/Jul/2023', $this->getFirstValueByColumn($csv, 'Task End Date'));
        $this->assertEquals('Custom 11', $this->getFirstValueByColumn($csv, 'Task Custom Value 1'));
        $this->assertEquals('Custom 22', $this->getFirstValueByColumn($csv, 'Task Custom Value 2'));
        $this->assertEquals('Custom 33', $this->getFirstValueByColumn($csv, 'Task Custom Value 3'));
        $this->assertEquals('Custom 44', $this->getFirstValueByColumn($csv, 'Task Custom Value 4'));
        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('123456', $this->getFirstValueByColumn($csv, 'Invoice Invoice Number'));
        $this->assertEquals(1000, $this->getFirstValueByColumn($csv, 'Invoice Amount'));

        $export = new TaskExport($this->company, $data);
        $data = $export->returnJson();

        $this->assertNotNull($data);

        // $this->assertEquals(0, $this->traverseJson($data, 'columns.0.identifier'));
        $this->assertEquals('Client Name', $this->traverseJson($data, 'columns.0.display_value'));
        $this->assertEquals('client', $this->traverseJson($data, '0.0.entity'));
        $this->assertEquals('name', $this->traverseJson($data, '0.0.id'));
        $this->assertNotNull($this->traverseJson($data, '0.0.hashed_id'));
        $this->assertEquals('bob', $this->traverseJson($data, '0.0.value'));
        $this->assertEquals('client.name', $this->traverseJson($data, '0.0.identifier'));
        $this->assertEquals('bob', $this->traverseJson($data, '0.0.display_value'));

        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/tasks', $data)->assertStatus(200);


        $data = [
            'date_range' => 'all',
            'report_keys' => array_merge(["task.date","task.number"], $this->all_invoice_report_keys),
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/tasks', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


    }

    public function testTasksCsvGeneration()
    {

        \App\Models\Task::query()->cursor()->each(function ($t) {
            $t->forceDelete();
        });

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

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        $this->assertEquals(3600, $this->getFirstValueByColumn($csv, 'Task Duration'));
        $this->assertEquals('test', $this->getFirstValueByColumn($csv, 'Task Description'));
        $this->assertEquals('16/Jul/2023', $this->getFirstValueByColumn($csv, 'Task Start Date'));
        $this->assertEquals('16/Jul/2023', $this->getFirstValueByColumn($csv, 'Task End Date'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Task Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Task Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Task Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Task Custom Value 4'));

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
            'include_deleted' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/products', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals('product_key', $this->getFirstValueByColumn($csv, 'Product'));
        $this->assertEquals('notes', $this->getFirstValueByColumn($csv, 'Notes'));
        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Cost'));
        $this->assertEquals(1, $this->getFirstValueByColumn($csv, 'Quantity'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Custom Value 4'));

        $export = new ProductExport($this->company, $data);
        $data = $export->returnJson();

        $this->assertNotNull($data);

        // $this->assertEquals(0, $this->traverseJson($data, 'columns.0.identifier'));
        $this->assertEquals('Custom Value 1', $this->traverseJson($data, 'columns.0.display_value'));
        $this->assertEquals('custom_value1', $this->traverseJson($data, '0.0.entity'));
        $this->assertEquals('custom_value1', $this->traverseJson($data, '0.0.id'));
        $this->assertNull($this->traverseJson($data, '0.0.hashed_id'));
        $this->assertEquals('Custom 1', $this->traverseJson($data, '0.0.value'));
        $this->assertEquals('custom_value1', $this->traverseJson($data, '0.0.identifier'));
        $this->assertEquals('Custom 1', $this->traverseJson($data, '0.0.display_value'));

    }


    public function testPaymentCsvGeneration()
    {

        $invoice = \App\Models\Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'date' => '2023-01-01',
            'amount' => 100,
            'balance' => 100,
            'number' => '12345',
            'status_id' => 2,
            'discount' => 10,
            'po_number' => '1234',
            'public_notes' => 'Public',
            'private_notes' => 'Private',
            'terms' => 'Terms',
            ]);

        $invoice->client->balance = 100;
        $invoice->client->paid_to_date = 0;
        $invoice->push();

        $invoice->service()->markPaid()->save();

        $data = [
            'date_range' => 'all',
            'report_keys' => [
                "payment.date",
                "payment.amount",
                "invoice.number",
                "invoice.amount",
                "client.name",
                "client.balance",
                "client.paid_to_date"
            ],
            'send_email' => false,
            'include_deleted' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/payments', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        // nlog($csv);

        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Payment Amount'));
        $this->assertEquals(now()->addSeconds($this->company->timezone()->utc_offset)->format('Y-m-d'), $this->getFirstValueByColumn($csv, 'Payment Date'));
        $this->assertEquals('12345', $this->getFirstValueByColumn($csv, 'Invoice Invoice Number'));
        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Invoice Amount'));
        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals(0, $this->getFirstValueByColumn($csv, 'Client Balance'));
        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Client Paid to Date'));

        $export = new PaymentExport($this->company, $data);
        $data = $export->returnJson();

        $this->assertNotNull($data);

        // $this->assertEquals(0, $this->traverseJson($data, 'columns.0.identifier'));
        $this->assertEquals('Payment Date', $this->traverseJson($data, 'columns.0.display_value'));
        // $this->assertEquals(1, $this->traverseJson($data, 'columns.1.identifier'));
        $this->assertEquals('Payment Amount', $this->traverseJson($data, 'columns.1.display_value'));
        // $this->assertEquals(2, $this->traverseJson($data, 'columns.2.identifier'));
        $this->assertEquals('Invoice Invoice Number', $this->traverseJson($data, 'columns.2.display_value'));
        // $this->assertEquals(4, $this->traverseJson($data, 'columns.4.identifier'));
        $this->assertEquals('Client Name', $this->traverseJson($data, 'columns.4.display_value'));


        $this->assertEquals('payment', $this->traverseJson($data, '0.0.entity'));
        $this->assertEquals('date', $this->traverseJson($data, '0.0.id'));
        $this->assertNull($this->traverseJson($data, '0.0.hashed_id'));
        $this->assertEquals('payment.date', $this->traverseJson($data, '0.0.identifier'));


        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/payments', $data)->assertStatus(200);



        $data = [
            'date_range' => 'all',
            'report_keys' => array_merge(["payment.amount","payment.date"], $this->all_invoice_report_keys),
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/payments', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


    }


    public function testPaymentCustomFieldsCsvGeneration()
    {

        \App\Models\Payment::factory()->create([
            'amount' => 500,
            'date' => '2020-01-01',
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
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

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals(500, $this->getFirstValueByColumn($csv, 'Payment Amount'));
        $this->assertEquals(0, $this->getFirstValueByColumn($csv, 'Payment Applied'));
        $this->assertEquals(0, $this->getFirstValueByColumn($csv, 'Payment Refunded'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Payment Date'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Payment Transaction Reference'));

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

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

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

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Name'));
        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Balance'));
        $this->assertEquals(50, $this->getFirstValueByColumn($csv, 'Paid to Date'));
        $this->assertEquals($this->user->present()->name(), $this->getFirstValueByColumn($csv, 'Client User'));
        $this->assertEquals('', $this->getFirstValueByColumn($csv, 'Client Assigned User'));
        $this->assertEquals('USD', $this->getFirstValueByColumn($csv, 'Client Currency'));

    }

    public function testCreditJsonReport()
    {

        Credit::factory()->create([
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'client_id' => $this->client->id,
                'amount' => 100,
                'balance' => 50,
                'number' => '1234',
                'status_id' => 2,
                'discount' => 10,
                'po_number' => '1234',
                'public_notes' => 'Public',
                'private_notes' => 'Private',
                'terms' => 'Terms',
            ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","credit.number","credit.amount","payment.date", "payment.amount"],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/credits?output=json', $data);


        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/preview/'.$arr['message']);

        $response->assertStatus(409);


    }

    public function testCreditCustomColumnsCsvGeneration()
    {

        Credit::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
       ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","credit.number","credit.amount","payment.date", "payment.amount"],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/credits', $data);

        $response->assertStatus(200);
        $arr = $response->json();
        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Credit Credit Number'));
        $this->assertEquals('Unpaid', $this->getFirstValueByColumn($csv, 'Payment Amount'));
        $this->assertEquals('', $this->getFirstValueByColumn($csv, 'Payment Date'));

        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_payment_report_keys,
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/credits', $data)->assertStatus(200);

        $arr = $response->json();
        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

    }

    public function testInvoiceCustomColumnsCsvGeneration()
    {

        \App\Models\Invoice::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
       ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","invoice.number","invoice.amount","payment.date", "payment.amount","invoice.user"],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoices', $data);

        $response->assertStatus(200);
        $arr = $response->json();
        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Invoice Invoice Number'));
        $this->assertEquals('Unpaid', $this->getFirstValueByColumn($csv, 'Payment Amount'));
        $this->assertEquals('', $this->getFirstValueByColumn($csv, 'Payment Date'));

        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoices', $data)->assertStatus(200);

        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_payment_report_keys,
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoices', $data)->assertStatus(200);

    }

    public function testRecurringInvoiceCustomColumnsCsvGeneration()
    {

        \App\Models\RecurringInvoice::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
           'frequency_id' => 1,
       ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","recurring_invoice.number","recurring_invoice.amount", "recurring_invoice.frequency_id"],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/recurring_invoices', $data);

        $response->assertStatus(200);
        $arr = $response->json();
        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Recurring Invoice Invoice Number'));
        $this->assertEquals('Daily', $this->getFirstValueByColumn($csv, 'Recurring Invoice How Often'));

        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/recurring_invoices', $data)->assertStatus(200);

    }


    public function testRecurringInvoiceColumnsCsvGeneration()
    {

        \App\Models\RecurringInvoice::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
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

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Recurring Invoice Invoice Number'));
        $this->assertEquals('Daily', $this->getFirstValueByColumn($csv, 'Recurring Invoice How Often'));
        $this->assertEquals('Active', $this->getFirstValueByColumn($csv, 'Recurring Invoice Status'));

    }


    public function testInvoiceItemsCustomColumnsCsvGeneration()
    {

        \App\Models\Invoice::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
           'line_items' => [
                [
                'quantity' => 10,
                'cost' => 100,
                'line_total' => 1000,
                'is_amount_discount' => true,
                'discount' => 0,
                'notes' => 'item notes',
                'product_key' => 'product key',
                'custom_value1' => 'custom 1',
                'custom_value2' => 'custom 2',
                'custom_value3' => 'custom 3',
                'custom_value4' => 'custom 4',
                'tax_name1' => 'GST',
                'tax_rate1' => 10.00,
                'type_id' => '1',
                ],
           ]
       ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","invoice.number","invoice.amount","payment.date", "payment.amount", "item.quantity", "item.cost", "item.line_total", "item.discount", "item.notes", "item.product_key", "item.custom_value1", "item.tax_name1", "item.tax_rate1",],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoice_items', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Invoice Invoice Number'));
        $this->assertEquals('Unpaid', $this->getFirstValueByColumn($csv, 'Payment Amount'));
        $this->assertEquals('', $this->getFirstValueByColumn($csv, 'Payment Date'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Item Quantity'));
        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Item Cost'));
        $this->assertEquals('1000', $this->getFirstValueByColumn($csv, 'Item Line Total'));
        $this->assertEquals('0', $this->getFirstValueByColumn($csv, 'Item Discount'));
        $this->assertEquals('item notes', $this->getFirstValueByColumn($csv, 'Item Notes'));
        $this->assertEquals('product key', $this->getFirstValueByColumn($csv, 'Item Product'));
        $this->assertEquals('custom 1', $this->getFirstValueByColumn($csv, 'Item Custom Value 1'));
        $this->assertEquals('GST', $this->getFirstValueByColumn($csv, 'Item Tax Name 1'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Item Tax Rate 1'));

        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoice_items', $data)->assertStatus(200);


        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_payment_report_keys,
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoice_items', $data)->assertStatus(200);


        $data = [
                    'date_range' => 'all',
                    'report_keys' => $this->all_payment_report_keys,
                    'send_email' => false,
                    'product_key' => 'haloumi,cheese',
                ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/reports/invoice_items', $data)->assertStatus(200);

    }


    public function testQuoteItemsCustomColumnsCsvGeneration()
    {

        $q = \App\Models\Quote::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
           'line_items' => [
                [
                'quantity' => 10,
                'cost' => 100,
                'line_total' => 1000,
                'is_amount_discount' => true,
                'discount' => 0,
                'notes' => 'item notes',
                'product_key' => 'product key',
                'custom_value1' => 'custom 1',
                'custom_value2' => 'custom 2',
                'custom_value3' => 'custom 3',
                'custom_value4' => 'custom 4',
                'tax_name1' => 'GST',
                'tax_rate1' => 10.00,
                'type_id' => '1',
                ],
           ]
       ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","quote.number","quote.amount", "item.quantity", "item.cost", "item.line_total", "item.discount", "item.notes", "item.product_key", "item.custom_value1", "item.tax_name1", "item.tax_rate1",],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/quote_items', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Quote Number'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Item Quantity'));
        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Item Cost'));
        $this->assertEquals('1000', $this->getFirstValueByColumn($csv, 'Item Line Total'));
        $this->assertEquals('0', $this->getFirstValueByColumn($csv, 'Item Discount'));
        $this->assertEquals('item notes', $this->getFirstValueByColumn($csv, 'Item Notes'));
        $this->assertEquals('product key', $this->getFirstValueByColumn($csv, 'Item Product'));
        $this->assertEquals('custom 1', $this->getFirstValueByColumn($csv, 'Item Custom Value 1'));
        $this->assertEquals('GST', $this->getFirstValueByColumn($csv, 'Item Tax Name 1'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Item Tax Rate 1'));


        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/quote_items', $data)->assertStatus(200);


    }


    public function testPurchaseOrderCsvGeneration()
    {

        $vendor =
        \App\Models\Vendor::factory()->create(
            [
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => 'Vendor 1',
            ]
        );

        \App\Models\PurchaseOrder::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'vendor_id' => $vendor->id,
            'amount' => 100,
            'balance' => 50,
            'status_id' => 2,
            'discount' => 10,
            'number' => '1234',
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
        ])->post('/api/v1/reports/purchase_orders', $data);


        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        $this->assertEquals('100.00', $this->getFirstValueByColumn($csv, 'Purchase Order Amount'));
        $this->assertEquals('50.00', $this->getFirstValueByColumn($csv, 'Purchase Order Balance'));
        $this->assertEquals('10.00', $this->getFirstValueByColumn($csv, 'Purchase Order Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Purchase Order Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Purchase Order Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Purchase Order Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Purchase Order Terms'));
    }


    public function testPurchaseOrderItemsCustomColumnsCsvGeneration()
    {

        $vendor =
        \App\Models\Vendor::factory()->create(
            [
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => 'Vendor 1',
            ]
        );


        \App\Models\PurchaseOrder::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'vendor_id' => $vendor->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'po_number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
           'line_items' => [
                [
                'quantity' => 10,
                'cost' => 100,
                'line_total' => 1000,
                'is_amount_discount' => true,
                'discount' => 0,
                'notes' => 'item notes',
                'product_key' => 'product key',
                'custom_value1' => 'custom 1',
                'custom_value2' => 'custom 2',
                'custom_value3' => 'custom 3',
                'custom_value4' => 'custom 4',
                'tax_name1' => 'GST',
                'tax_rate1' => 10.00,
                'type_id' => '1',
                ],
           ]
       ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["vendor.name","purchase_order.number","purchase_order.amount", "item.quantity", "item.cost", "item.line_total", "item.discount", "item.notes", "item.product_key", "item.custom_value1", "item.tax_name1", "item.tax_rate1",],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/purchase_order_items', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals('Vendor 1', $this->getFirstValueByColumn($csv, 'Vendor Name'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Purchase Order Number'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Item Quantity'));
        $this->assertEquals('100', $this->getFirstValueByColumn($csv, 'Item Cost'));
        $this->assertEquals('1000', $this->getFirstValueByColumn($csv, 'Item Line Total'));
        $this->assertEquals('0', $this->getFirstValueByColumn($csv, 'Item Discount'));
        $this->assertEquals('item notes', $this->getFirstValueByColumn($csv, 'Item Notes'));
        $this->assertEquals('product key', $this->getFirstValueByColumn($csv, 'Item Product'));
        $this->assertEquals('custom 1', $this->getFirstValueByColumn($csv, 'Item Custom Value 1'));
        $this->assertEquals('GST', $this->getFirstValueByColumn($csv, 'Item Tax Name 1'));
        $this->assertEquals('10', $this->getFirstValueByColumn($csv, 'Item Tax Rate 1'));

    }

    public function testQuoteCustomColumnsCsvGeneration()
    {

        \App\Models\Quote::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'amount' => 100,
           'balance' => 50,
           'number' => '1234',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
       ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","quote.number","quote.amount"],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/quotes', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals(floatval(1234), $this->getFirstValueByColumn($csv, 'Quote Number'));
        $this->assertEquals(floatval(100), $this->getFirstValueByColumn($csv, 'Quote Amount'));


        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/quotes', $data)->assertStatus(200);


    }


    public function testInvoicePaidCustomColumnsCsvGeneration()
    {

        $invoice = \App\Models\Invoice::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
           'client_id' => $this->client->id,
           'date' => '2023-01-01',
           'amount' => 100,
           'balance' => 100,
           'number' => '12345',
           'status_id' => 2,
           'discount' => 10,
           'po_number' => '1234',
           'public_notes' => 'Public',
           'private_notes' => 'Private',
           'terms' => 'Terms',
       ]);

        $invoice->service()->markPaid()->save();

        $data = [
            'date_range' => 'all',
            'report_keys' => ["client.name","invoice.number","invoice.amount", "payment.date", "payment.amount"],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/invoices', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('12345', $this->getFirstValueByColumn($csv, 'Invoice Invoice Number'));
        $this->assertEquals(100, $this->getFirstValueByColumn($csv, 'Payment Amount'));
        $this->assertEquals(now()->addSeconds($this->company->timezone()->utc_offset)->format('Y-m-d'), $this->getFirstValueByColumn($csv, 'Payment Date'));

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

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $reader = Reader::createFromString($csv);
        $reader->setHeaderOffset(0);

        $res = $reader->fetchColumnByName('Contact First Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('john', $res[1]);

        $res = $reader->fetchColumnByName('Contact Last Name');
        $res = iterator_to_array($res, true);

        $this->assertEquals('doe', $res[1]);

        $res = $reader->fetchColumnByName('Contact Email');
        $res = iterator_to_array($res, true);

        $this->assertEquals('john@doe.com', $res[1]);

    }

    private function traverseJson($array, $keys)
    {
        $value = data_get($array, $keys, false);

        return $value;
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

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals(floatval(100), $this->getFirstValueByColumn($csv, 'Credit Amount'));
        $this->assertEquals(floatval(50), $this->getFirstValueByColumn($csv, 'Credit Balance'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Credit Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Credit PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Credit Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Credit Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Credit Terms'));


        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/credits', $data)->assertStatus(200);



        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_payment_report_keys,
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/credits', $data)->assertStatus(200);

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

        $response->assertStatus(200);

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals(floatval(100), $this->getFirstValueByColumn($csv, 'Invoice Amount'));
        $this->assertEquals(floatval(50), $this->getFirstValueByColumn($csv, 'Invoice Balance'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Invoice Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Invoice PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Invoice Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Invoice Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Invoice Terms'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Invoice Date'));
        $this->assertEquals('2021-01-02', $this->getFirstValueByColumn($csv, 'Invoice Due Date'));
        $this->assertEquals('2021-01-03', $this->getFirstValueByColumn($csv, 'Invoice Partial Due Date'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Invoice Partial/Deposit'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Invoice Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Invoice Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Invoice Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Invoice Custom Value 4'));
        $this->assertEquals('Footer', $this->getFirstValueByColumn($csv, 'Invoice Footer'));
        $this->assertEquals('Tax 1', $this->getFirstValueByColumn($csv, 'Invoice Tax Name 1'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Invoice Tax Rate 1'));
        $this->assertEquals('Tax 2', $this->getFirstValueByColumn($csv, 'Invoice Tax Name 2'));
        $this->assertEquals(floatval(20), $this->getFirstValueByColumn($csv, 'Invoice Tax Rate 2'));
        $this->assertEquals('Tax 3', $this->getFirstValueByColumn($csv, 'Invoice Tax Name 3'));
        $this->assertEquals(floatval(30), $this->getFirstValueByColumn($csv, 'Invoice Tax Rate 3'));
        $this->assertEquals('Sent', $this->getFirstValueByColumn($csv, 'Invoice Status'));

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

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();


        $this->assertEquals(floatval(100), $this->getFirstValueByColumn($csv, 'Recurring Invoice Amount'));
        $this->assertEquals(floatval(50), $this->getFirstValueByColumn($csv, 'Recurring Invoice Balance'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Recurring Invoice Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Recurring Invoice PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Recurring Invoice Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Recurring Invoice Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Recurring Invoice Terms'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Recurring Invoice Date'));
        $this->assertEquals('2021-01-02', $this->getFirstValueByColumn($csv, 'Recurring Invoice Due Date'));
        $this->assertEquals('2021-01-03', $this->getFirstValueByColumn($csv, 'Recurring Invoice Partial Due Date'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Recurring Invoice Partial/Deposit'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Recurring Invoice Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Recurring Invoice Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Recurring Invoice Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Recurring Invoice Custom Value 4'));
        $this->assertEquals('Footer', $this->getFirstValueByColumn($csv, 'Recurring Invoice Footer'));
        $this->assertEquals('Tax 1', $this->getFirstValueByColumn($csv, 'Recurring Invoice Tax Name 1'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Recurring Invoice Tax Rate 1'));
        $this->assertEquals('Tax 2', $this->getFirstValueByColumn($csv, 'Recurring Invoice Tax Name 2'));
        $this->assertEquals(floatval(20), $this->getFirstValueByColumn($csv, 'Recurring Invoice Tax Rate 2'));
        $this->assertEquals('Tax 3', $this->getFirstValueByColumn($csv, 'Recurring Invoice Tax Name 3'));
        $this->assertEquals(floatval(30), $this->getFirstValueByColumn($csv, 'Recurring Invoice Tax Rate 3'));
        $this->assertEquals('Daily', $this->getFirstValueByColumn($csv, 'Recurring Invoice How Often'));

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

        $arr = $response->json();

        $hash = $arr['message'];

        $response = $this->poll($hash);

        $csv = $response->body();

        $this->assertEquals(floatval(100), $this->getFirstValueByColumn($csv, 'Quote Amount'));
        $this->assertEquals(floatval(50), $this->getFirstValueByColumn($csv, 'Quote Balance'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Quote Discount'));
        $this->assertEquals('1234', $this->getFirstValueByColumn($csv, 'Quote PO Number'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Quote Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Quote Private Notes'));
        $this->assertEquals('Terms', $this->getFirstValueByColumn($csv, 'Quote Terms'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Quote Date'));
        $this->assertEquals('2020-01-01', $this->getFirstValueByColumn($csv, 'Quote Valid Until'));
        $this->assertEquals('2021-01-03', $this->getFirstValueByColumn($csv, 'Quote Partial Due Date'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Quote Partial/Deposit'));
        $this->assertEquals('Custom 1', $this->getFirstValueByColumn($csv, 'Quote Custom Value 1'));
        $this->assertEquals('Custom 2', $this->getFirstValueByColumn($csv, 'Quote Custom Value 2'));
        $this->assertEquals('Custom 3', $this->getFirstValueByColumn($csv, 'Quote Custom Value 3'));
        $this->assertEquals('Custom 4', $this->getFirstValueByColumn($csv, 'Quote Custom Value 4'));
        $this->assertEquals('Footer', $this->getFirstValueByColumn($csv, 'Quote Footer'));
        $this->assertEquals('Tax 1', $this->getFirstValueByColumn($csv, 'Quote Tax Name 1'));
        $this->assertEquals(floatval(10), $this->getFirstValueByColumn($csv, 'Quote Tax Rate 1'));
        $this->assertEquals('Tax 2', $this->getFirstValueByColumn($csv, 'Quote Tax Name 2'));
        $this->assertEquals(floatval(20), $this->getFirstValueByColumn($csv, 'Quote Tax Rate 2'));
        $this->assertEquals('Tax 3', $this->getFirstValueByColumn($csv, 'Quote Tax Name 3'));
        $this->assertEquals(floatval(30), $this->getFirstValueByColumn($csv, 'Quote Tax Rate 3'));
        $this->assertEquals('Expired', $this->getFirstValueByColumn($csv, 'Quote Status'));

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

        $arr = $response->json();
        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

        $this->assertEquals(floatval(100), $this->getFirstValueByColumn($csv, 'Expense Amount'));
        $this->assertEquals('Public', $this->getFirstValueByColumn($csv, 'Expense Public Notes'));
        $this->assertEquals('Private', $this->getFirstValueByColumn($csv, 'Expense Private Notes'));
        $this->assertEquals($this->user->present()->name(), $this->getFirstValueByColumn($csv, 'Expense User'));

        $data = [
            'date_range' => 'all',
            'report_keys' => $this->all_client_report_keys,
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/expenses', $data)->assertStatus(200);

        $arr = $response->json();
        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

    }

    public function testExpenseCustomColumnsCsvGeneration()
    {
        $vendor =
        \App\Models\Vendor::factory()->create(
            [
                'user_id' => $this->user->id,
                'company_id' => $this->company->id,
                'name' => 'Vendor 1',
            ]
        );

        Expense::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'vendor_id' => $vendor->id,
            'amount' => 100,
            'public_notes' => 'Public',
            'private_notes' => 'Private',
            'currency_id' => 1,
        ]);

        $data = [
            'date_range' => 'all',
            'report_keys' => ['client.name','vendor.name','expense.amount','expense.currency_id'],
            'send_email' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/reports/expenses', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $hash = $arr['message'];
        $response = $this->poll($hash);
        $csv = $response->body();

        $this->assertEquals('bob', $this->getFirstValueByColumn($csv, 'Client Name'));
        $this->assertEquals('Vendor 1', $this->getFirstValueByColumn($csv, 'Vendor Name'));
        $this->assertEquals(floatval(100), $this->getFirstValueByColumn($csv, 'Expense Amount'));
        $this->assertEquals('USD', $this->getFirstValueByColumn($csv, 'Expense Currency'));

    }


}

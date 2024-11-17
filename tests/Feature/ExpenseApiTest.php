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

namespace Tests\Feature;

use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Session;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\ExpenseController
 */
class ExpenseApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();
    }

    public function testBulkUpdatesTaxes()
    {
        Expense::factory(5)->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $expenses = Expense::query()
                            ->where('company_id', $this->company->id)
                            ->where('client_id', $this->client->id)
                            ->where('vendor_id', $this->vendor->id);

        $this->assertCount(5, $expenses->get());

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'tax1',
            'new_value' => 'GST||10',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);


        $expenses->cursor()->each(function ($e){
            $this->assertEquals('GST', $e->tax_name1);
            $this->assertEquals(10, $e->tax_rate1);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'custom_value1',
            'new_value' => 'CUSTOMCUSTOM123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertEquals('CUSTOMCUSTOM123', $e->custom_value1);
        });

        $data = [
                    'action' => 'bulk_update',
                    'ids' => $expenses->get()->pluck('hashed_id'),
                    'column' => 'should_be_invoiced',
                    'new_value' => false,
                ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertFalse((bool)$e->should_be_invoiced);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'should_be_invoiced',
            'new_value' => true,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertTrue((bool)$e->should_be_invoiced);
        });


        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'should_be_invoiced',
            'new_value' => false,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertFalse((bool)$e->should_be_invoiced);
        });


        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'uses_inclusive_taxes',
            'new_value' => true,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertTrue((bool)$e->uses_inclusive_taxes);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'private_notes',
            'new_value' => 'TESTEST123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertEquals('TESTEST123', $e->private_notes);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $expenses->get()->pluck('hashed_id'),
            'column' => 'public_notes',
            'new_value' => 'TESTEST123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses/bulk', $data);

        $response->assertStatus(200);

        $expenses->cursor()->each(function ($e) {
            $this->assertEquals('TESTEST123', $e->private_notes);
        });



    }

    public function testVendorPayment()
    {
        $data = [
            'amount' => 100,
            'payment_date' => now()->format('Y-m-d'),
            'vendor_id' => $this->vendor->hashed_id,
            'date' => '2021-10-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses', $data);


        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($this->vendor->hashed_id, $arr['data']['vendor_id']);
        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['payment_date']);

        $data = [
            'amount' => 100,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/expenses/'.$arr['data']['id'], $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals(now()->format('Y-m-d'), $arr['data']['payment_date']);

    }


    public function testExpensePutWithVendorStatus()
    {


        $data =
        [
            'vendor_id' => $this->vendor->hashed_id,
            'amount' => 10,
            'date' => '2021-10-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/expenses', $data);

        $arr = $response->json();
        $response->assertStatus(200);


        $this->assertEquals($this->vendor->hashed_id, $arr['data']['vendor_id']);

        $data = [
            'payment_date' => now()->format('Y-m-d')
        ];

        $response = $this->withHeaders([
                    'X-API-SECRET' => config('ninja.api_secret'),
                    'X-API-TOKEN' => $this->token,
                ])->putJson('/api/v1/expenses/'.$arr['data']['id'], $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertEquals($this->vendor->hashed_id, $arr['data']['vendor_id']);

    }

    public function testTransactionIdClearedOnDelete()
    {
        $bi = BankIntegration::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'account_id' => $this->account->id
        ]);

        $bt = BankTransaction::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'bank_integration_id' => $bi->id,
        ]);

        $e = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'transaction_id' => $bt->id,
        ]);

        $this->assertNotNull($e->transaction_id);

        $expense_repo = app('App\Repositories\ExpenseRepository');
        $e = $expense_repo->delete($e);

        $this->assertNull($e->transaction_id);
    }

    public function testExpenseGetClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses?client_status=paid');

        $response->assertStatus(200);
    }

    public function testExpensePost()
    {
        $data = [
            'public_notes' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $this->assertNotEmpty($arr['data']['number']);
    }

    public function testDuplicateNumberCatch()
    {
        $data = [
            'public_notes' => $this->faker->firstName(),
            'number' => 'iamaduplicate',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $response->assertStatus(302);
    }

    public function testExpensePut()
    {
        $data = [
            'public_notes' => $this->faker->firstName(),
            'number' => 'Coolio',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id), $data);

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/', $data);

        $response->assertStatus(302);
    }

    public function testExpenseGet()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id));

        $response->assertStatus(200);
    }

    public function testExpenseGetSort()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses?sort=public_notes|desc');

        $response->assertStatus(200);
    }

    public function testExpenseNotArchived()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/expenses/'.$this->encodePrimaryKey($this->expense->id));

        $arr = $response->json();

        $this->assertEquals(0, $arr['data']['archived_at']);
    }

    public function testExpenseArchived()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertNotNull($arr['data'][0]['archived_at']);
    }

    public function testExpenseRestored()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);
    }

    public function testExpenseDeleted()
    {
        $data = [
            'ids' => [$this->encodePrimaryKey($this->expense->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertTrue($arr['data'][0]['is_deleted']);
    }

    public function testExpenseBulkCategorize()
    {

        $eXX = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);


        $e = Expense::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $ec = ExpenseCategory::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'name' => 'Test Category',
        ]);

        $data = [
            'category_id' => $ec->hashed_id,
            'action' => 'bulk_categorize',
            'ids' => [$this->encodePrimaryKey($e->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses/bulk', $data);

        $arr = $response->json();

        $this->assertEquals($ec->hashed_id, $arr['data'][0]['category_id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/expenses");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertGreaterThan(1, count($arr['data']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/expenses?categories={$ec->hashed_id}");

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(1, $arr['data']);

    }

    public function testAddingExpense()
    {
        $data = [
            'name' => $this->faker->firstName(),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expense_categories', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $category_id = $arr['data']['id'];

        $data =
        [
            'vendor_id' => $this->vendor->hashed_id,
            'category_id' => $category_id,
            'amount' => 10,
            'date' => '2021-10-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/expenses', $data);

        $arr = $response->json();
        $response->assertStatus(200);
    }
}

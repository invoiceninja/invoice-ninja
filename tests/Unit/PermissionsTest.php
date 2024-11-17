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

namespace Tests\Unit;

use App\Factory\CompanyUserFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\User;
use Tests\TestCase;

/**
 * 
 */
class PermissionsTest extends TestCase
{
    public User $user;

    public CompanyUser $cu;

    public Company $company;

    public $faker;

    public $token;

    protected function setUp(): void
    {
        parent::setUp();
        
        if (\App\Models\Country::count() == 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--force' => true]);
        }

        $this->faker = \Faker\Factory::create();

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $this->company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $this->user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => '123',
            'email' =>  $this->faker->safeEmail(),
        ]);

        $this->cu = CompanyUserFactory::create($this->user->id, $this->company->id, $account->id);
        $this->cu->is_owner = false;
        $this->cu->is_admin = false;
        $this->cu->is_locked = false;
        $this->cu->permissions = '["view_client"]';
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;
        $company_token->save();
    }

    public function testClientOverviewPermissions()
    {
        $u = User::factory()->create([
            'account_id' => $this->company->account_id,
            'confirmation_code' => '123',
            'email' =>  $this->faker->safeEmail(),
        ]);

        $c = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $u->id,
        ]);

        Invoice::factory()->create([
            'company_id' => $this->company->id,
            'client_id' => $c->id,
            'user_id' => $u->id,
            'status_id' => 2
        ]);

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["edit_client","create_client","create_invoice","edit_invoice","create_quote","edit_quote"]';
        $low_cu->save();


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices');

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(2, count($data));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get("/api/v1/invoices?include=client&client_id={$c->hashed_id}&sort=id|desc&per_page=10&page=1&filter=&status=active");

        $response->assertStatus(200);

        $data = $response->json();

        $this->assertEquals(2, count($data));

    }


    public function testHasExcludedPermissions()
    {
        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_client"]';
        $low_cu->save();

        $this->assertTrue($this->user->hasExcludedPermissions(["view_client"]));

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = 'view_client';
        $low_cu->save();

        $this->assertTrue($this->user->hasExcludedPermissions(["view_client"]));

    }

    public function testHasExcludedPermissions2()
    {
        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_client","edit_all"]';
        $low_cu->save();

        $this->assertFalse($this->user->hasExcludedPermissions(["view_client"], ['edit_all']));

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = 'view_client,edit_all';
        $low_cu->save();

        $this->assertFalse($this->user->hasExcludedPermissions(["view_client"], ['edit_all']));

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = 'view_client,view_all';
        $low_cu->save();

        $this->assertFalse($this->user->hasExcludedPermissions(["view_client"], ['view_all']));


        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = 'view_client,view_invoice';
        $low_cu->save();

        $this->assertFalse($this->user->hasExcludedPermissions(["view_client"], ['view_invoice']));

    }

    public function testIntersectPermissions()
    {
        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_client"]';
        $low_cu->save();

        $this->assertFalse($this->user->hasIntersectPermissions(["viewclient"]));
        $this->assertTrue($this->user->hasIntersectPermissions(["view_client"]));


        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_all"]';
        $low_cu->save();

        $this->assertFalse($this->user->hasIntersectPermissions(["viewclient"]));
        $this->assertTrue($this->user->hasIntersectPermissions(["view_client"]));

        $this->assertFalse($this->user->hasIntersectPermissions(["viewbank_transaction"]));
        $this->assertTrue($this->user->hasIntersectPermissions(["view_bank_transaction"]));

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["create_all"]';
        $low_cu->save();

        $this->assertFalse($this->user->hasIntersectPermissions(["createclient"]));
        $this->assertTrue($this->user->hasIntersectPermissions(["create_client"]));

        $this->assertFalse($this->user->hasIntersectPermissions(["createbank_transaction"]));
        $this->assertTrue($this->user->hasIntersectPermissions(["create_bank_transaction"]));
        $this->assertTrue($this->user->hasIntersectPermissions(['create_bank_transaction','edit_bank_transaction','view_bank_transaction']));
    }

    public function testViewClientPermission()
    {
        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_client"]';
        $low_cu->save();

        $this->assertFalse($this->user->hasPermission("viewclient"));

        // this is aberrant
        $this->assertFalse($this->user->hasPermission("view____client"));

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["edit_client"]';
        $low_cu->save();

        $this->assertTrue($this->user->hasPermission("view_client"));


        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["edit_all"]';
        $low_cu->save();

        $this->assertTrue($this->user->hasPermission("view_client"));

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_all"]';
        $low_cu->save();

        $this->assertFalse($this->user->hasPermission("edit_client"));

        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["edit_all"]';
        $low_cu->save();

        $this->assertTrue($this->user->hasPermission('view_invoice'));
        $this->assertTrue($this->user->hasPermission('view_client'));
        $this->assertTrue($this->user->hasPermission('view_recurring_invoice'));
        $this->assertTrue($this->user->hasPermission('view_product'));
        $this->assertTrue($this->user->hasPermission('view_payment'));
        $this->assertTrue($this->user->hasPermission('view_quote'));
        $this->assertTrue($this->user->hasPermission('view_credit'));
        $this->assertTrue($this->user->hasPermission('view_project'));
        $this->assertTrue($this->user->hasPermission('view_task'));
        $this->assertTrue($this->user->hasPermission('view_vendor'));
        $this->assertTrue($this->user->hasPermission('view_purchase_order'));
        $this->assertTrue($this->user->hasPermission('view_expense'));
        $this->assertTrue($this->user->hasPermission('view_bank_transaction'));


        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["edit_recurring_invoice"]';
        $low_cu->save();

        $this->assertTrue($this->user->hasPermission('view_recurring_invoice'));
    }

    public function testPermissionResolution()
    {
        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(Invoice::class)));

        $this->assertEquals('view_invoice', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(Client::class)));
        $this->assertEquals('view_client', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(RecurringInvoice::class)));
        $this->assertEquals('view_recurring_invoice', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Product::class)));
        $this->assertEquals('view_product', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Payment::class)));
        $this->assertEquals('view_payment', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Quote::class)));
        $this->assertEquals('view_quote', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Credit::class)));
        $this->assertEquals('view_credit', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Project::class)));
        $this->assertEquals('view_project', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Task::class)));
        $this->assertEquals('view_task', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Vendor::class)));
        $this->assertEquals('view_vendor', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\PurchaseOrder::class)));
        $this->assertEquals('view_purchase_order', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\Expense::class)));
        $this->assertEquals('view_expense', $class);

        $class = 'view'.lcfirst(class_basename(\Illuminate\Support\Str::snake(App\Models\BankTransaction::class)));
        $this->assertEquals('view_bank_transaction', $class);

        $this->assertEquals('invoice', \Illuminate\Support\Str::snake(class_basename(Invoice::class)));

        $this->assertEquals('recurring_invoice', \Illuminate\Support\Str::snake(class_basename(RecurringInvoice::class)));
    }

    public function testExactPermissions()
    {
        $this->assertTrue($this->user->hasExactPermissionAndAll("view_client"));
        $this->assertFalse($this->user->hasExactPermissionAndAll("view_all"));
    }

    public function testMissingPermissions()
    {
        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '[""]';
        $low_cu->save();

        $this->assertFalse($this->user->hasExactPermissionAndAll("view_client"));
        $this->assertFalse($this->user->hasExactPermissionAndAll("view_all"));
    }

    public function testViewAllValidPermissions()
    {
        $low_cu = CompanyUser::where(['company_id' => $this->company->id, 'user_id' => $this->user->id])->first();
        $low_cu->permissions = '["view_all"]';
        $low_cu->save();

        $this->assertTrue($this->user->hasExactPermissionAndAll("view_client"));
        $this->assertTrue($this->user->hasExactPermissionAndAll("view_all"));
    }

    public function testReturnTypesOfStripos()
    {
        $this->assertEquals(0, stripos("view_client", ''));

        $all_permission = '[]';
        $this->assertFalse(stripos($all_permission, "view_client") !== false);
        $this->assertTrue(stripos($all_permission, "view_client") == 0);
        $this->assertFalse(is_int(stripos($all_permission, "view_client")));

        $all_permission = ' ';
        $this->assertFalse(stripos($all_permission, "view_client") !== false);
        $this->assertFalse(is_int(stripos($all_permission, "view_client")));

        $all_permission = "";//problems are empty strings
        $this->assertTrue(empty($all_permission));

        $this->assertFalse(stripos($all_permission, "view_client") !== false);
        $this->assertFalse(is_int(stripos($all_permission, "view_client")));

        $all_permission = 'view';//will always pass currently
        $this->assertFalse(stripos($all_permission, "view_client") !== false);
        $this->assertFalse(is_int(stripos($all_permission, "view_client")));

        $all_permission = "view_client";
        $this->assertTrue(stripos($all_permission, "view_client") !== false);
        $this->assertTrue(is_int(stripos($all_permission, "view_client")) !== false);

        $this->assertTrue(is_int(stripos($all_permission, "view_client")));
    }
}

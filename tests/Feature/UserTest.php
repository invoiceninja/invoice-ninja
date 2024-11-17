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

use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Http\Middleware\PasswordProtection;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\UserController
 */
class UserTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    private $default_email = 'attach@gmail.com';

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = \Faker\Factory::create();

        $this->withoutMiddleware(
            ThrottleRequests::class,
            PasswordProtection::class
        );

        $this->makeTestData();

        // $this->withoutExceptionHandling();

    }

    private function mockAccount()
    {

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword'),
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);

        $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $token;
        $company_token->is_system = true;
        $company_token->save();

        return $company_token;

    }

    // public function testCrossAccountFunctionality()
    // {
    //     $ct = $this->mockAccount();

    //     $u= $ct->user;

    //     auth()->login($u, true);

    //     $account = Account::factory()->create([
    //             'hosted_client_count' => 1000,
    //             'hosted_company_count' => 1000,
    //         ]);

    //     $account->num_users = 3;
    //     $account->save();

    //     $user = User::factory()->create([
    //         'account_id' => $this->account->id,
    //         'confirmation_code' => 'xyz123',
    //         'email' => $this->faker->unique()->safeEmail(),
    //         'password' => \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword'),
    //     ]);


    //     $user_repo = new UserRepository();


    //     // try{
    //         $x = $user_repo->save(['first_name' => 'bobby'], $user);
    //     // }
    //     // catch(\Exception $e){

    //         // $this->assertEquals(401, $e->getCode());
    //     // }

    //     nlog($x);


    // }

    public function testValidEmailUpdate()
    {
        $company_token = $this->mockAccount();
        $user = $company_token->user;
        $user->load('company_user');

        $data = $user->toArray();
        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(200);

    }


    public function testNullEmail()
    {
        
        $company_token = $this->mockAccount();
        $user = $company_token->user;
        $user->load('company_user');

        $data = $user->toArray();
        $data['email'] = '';
        unset($data['password']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(422);

        $data = $user->toArray();
        unset($data['password']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(200);

        $data = $user->toArray();

        $data['email'] = $this->faker->unique()->safeEmail();
        unset($data['password']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals($arr['data']['email'], $data['email']);
    }


    public function testUserLocale()
    {
        $this->user->language_id = "13";
        $this->user->save();

        $this->assertEquals("fr_CA", $this->user->getLocale());

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/statics');

        $response->assertStatus(200);

    }



    public function testUserResponse()
    {
        $company_token = $this->mockAccount();

        $data = [
                'first_name' => 'hey',
                'last_name' => 'you',
                'email' => 'normal_user@gmail.com',
                'company_user' => [
                    'is_admin' => true,
                    'is_owner' => false,
                    'permissions' => 'create_client,create_invoice',
                ],
                'phone' => null,
            ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->post('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $user = $response->json();
        $user_id = $user['data']['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/users', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertCount(2, $arr['data']);

        //archive the user we just created:

        $data = [
            'action' => 'archive',
            'ids' => [$user_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users/bulk', $data);

        $response->assertStatus(200);

        $this->assertCount(1, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?status=active&without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?status=archived&without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(1, $response->json()['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get("/api/v1/users?status=deleted&without={$company_token->user->hashed_id}");

        $response->assertStatus(200);
        $this->assertCount(0, $response->json()['data']);


    }

    public function testUserAttemptingtToDeleteThemselves()
    {

        $account = Account::factory()->create([
            'hosted_client_count' => 1000,
            'hosted_company_count' => 1000,
        ]);

        $account->num_users = 3;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $this->account->id,
            'confirmation_code' => 'xyz123',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => \Illuminate\Support\Facades\Hash::make('ALongAndBriliantPassword'),
        ]);

        $settings = CompanySettings::defaults();
        $settings->client_online_payment_notification = false;
        $settings->client_manual_payment_notification = false;

        $company = Company::factory()->create([
            'account_id' => $account->id,
            'settings' => $settings,
        ]);


        $cu = CompanyUserFactory::create($user->id, $company->id, $account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->is_locked = false;
        $cu->save();

        $token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = $token;
        $company_token->is_system = true;
        $company_token->save();

        $data = [
            'ids' => [$user->hashed_id],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users/bulk?action=delete', $data);


        $response->assertStatus(401);

    }

    public function testDisconnectUserOauthMailer()
    {
        $user =
        User::factory()->create([
            'account_id' => $this->account->id,
            'email' => $this->faker->safeEmail(),
            'oauth_user_id' => '123456789',
            'oauth_provider_id' => '123456789',
        ]);

        $response = $this->withHeaders([
            'X-API-TOKEN' => $this->token,
        ])->post("/api/v1/users/{$user->hashed_id}/disconnect_mailer");

        $response->assertStatus(200);

        $user->fresh();

        $this->assertNull($user->oauth_user_token);
        $this->assertNull($user->oauth_user_refresh_token);

    }

    public function testUserFiltersWith()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/users?with='.$this->user->hashed_id);

        $response->assertStatus(200);
    }

    public function testUserList()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->get('/api/v1/users');

        $response->assertStatus(200);
    }

    public function testValidationRulesPhoneIsNull()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
            ],
            'phone' => null,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);
    }

    public function testValidationRulesPhoneIsBlankString()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
            ],
            'phone' => "",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $user_id = $this->decodePrimaryKey($arr['data']['id']);
        $user = User::find($user_id);


        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
                'notifications' => '',
            ],
            'phone' => "",
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$user->hashed_id.'?include=company_user', $data);
    }

    public function testUserStore()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob1@good.ole.boys.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
                'notifications' => '',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
    }

    public function testUserAttachAndDetach()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        $data = [
            'first_name' => 'Test',
            'last_name' => 'Palloni',
            'email' => $this->default_email,
        ];

        $response = false;

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
                'X-API-PASSWORD' => 'ALongAndBriliantPassword',
            ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->delete('/api/v1/users/'.$arr['data']['id'].'/detach_from_company?include=company_user');

        $response->assertStatus(200);

        $user_id = $this->decodePrimaryKey($arr['data']['id']);

        $cu = CompanyUser::whereUserId($user_id)->whereCompanyId($this->company->id)->first();
        $ct = CompanyToken::whereUserId($user_id)->whereCompanyId($this->company->id)->first();
        $user = User::find($user_id);

        $this->assertNull($cu);
        $this->assertNull($ct);
        $this->assertNotNull($user);
    }

    public function testAttachUserToMultipleCompanies()
    {
        $this->withoutMiddleware(PasswordProtection::class);

        /* Create New Company */
        $company2 = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = $this->user->id;
        $company_token->company_id = $company2->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = \Illuminate\Support\Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        /*Manually link this user to the company*/
        $cu = CompanyUserFactory::create($this->user->id, $company2->id, $this->account->id);
        $cu->is_owner = true;
        $cu->is_admin = true;
        $cu->save();

        /*Create New Blank User and Attach to Company 2*/
        $data = [
            'first_name' => 'Test',
            'last_name' => 'Palloni',
            'email' => $this->default_email,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        // $this->assertNotNull($new_user->company_user);
        // $this->assertEquals($new_user->company_user->company_id, $company2->id);

        /*Create brand new user manually with company_user object and attach to a different company*/
        $data = [
            'first_name' => 'hey',
            'last_name' => 'you',
            'email' => 'bob@good.ole.boys.co2.com',
            'company_user' => [
                'is_admin' => false,
                'is_owner' => false,
                'permissions' => 'create_client,create_invoice',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
        ])->postJson('/api/v1/users?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
        $this->assertFalse($arr['data']['company_user']['is_admin']);
        $this->assertFalse($arr['data']['company_user']['is_owner']);
        $this->assertEquals($arr['data']['company_user']['permissions'], 'create_client,create_invoice');

        $user = User::whereEmail('bob@good.ole.boys.co2.com')->first();

        $this->assertNotNull($user);

        $cu = CompanyUser::whereUserId($user->id)->whereCompanyId($company2->id)->first();

        $this->assertNotNull($cu);

        /*Update the user permissions of this user*/
        $data = [
            'first_name' => 'Captain',
            'last_name' => 'Morgain',
            'email' => 'bob@good.ole.boys.co2.com',
            'company_user' => [
                'is_admin' => true,
                'is_owner' => false,
                'permissions' => 'create_invoice,create_invoice',
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $company_token->token,
            'X-API-PASSWORD' => 'ALongAndBriliantPassword',
        ])->putJson('/api/v1/users/'.$this->encodePrimaryKey($user->id).'?include=company_user', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['company_user']);
        $this->assertTrue($arr['data']['company_user']['is_admin']);
        $this->assertFalse($arr['data']['company_user']['is_owner']);
        $this->assertEquals($arr['data']['company_user']['permissions'], 'create_invoice,create_invoice');
    }
}

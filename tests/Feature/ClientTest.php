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

use Tests\TestCase;
use App\Models\User;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Account;
use App\Models\Company;
use App\Models\Currency;
use Tests\MockAccountData;
use Illuminate\Support\Str;
use App\Models\CompanyToken;
use App\Models\GroupSetting;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use Tests\Unit\GroupSettingsTest;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\DataMapper\DefaultSettings;
use App\Factory\InvoiceItemFactory;
use App\Factory\GroupSettingFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *  App\Http\Controllers\ClientController
 */
class ClientTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    public $client_id;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->withoutExceptionHandling();

        Client::reguard();
        ClientContact::reguard();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    public function testBulkGroupAssignment()
    {
        Client::factory()->count(5)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
            ]);
        });

        $gs = GroupSettingFactory::create($this->company->id, $this->user->id);
        $gs->name = 'testtest';
        $gs->save();

        $ids = Client::where('company_id', $this->company->id)->get()->pluck('hashed_id')->toArray();
        $data = [
            'action' => 'assign_group',
            'ids' => $ids,
            'group_settings_id' => $gs->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/bulk', $data);

        $arr = $response->json();

        Client::query()->whereIn('id', $this->transformKeys($ids))->cursor()->each(function ($c) use ($gs, $arr) {
            $this->assertEquals($gs->id, $c->group_settings_id);
        });

        foreach($arr['data'] as $client_response) {

            $this->assertEquals($gs->hashed_id, $client_response['group_settings_id']);
        }
    }

    public function testClientExchangeRateCalculation()
    {
        $settings = ClientSettings::defaults();
        $settings->currency_id = 12;

        $c = Client::factory()
                ->create([
                    'company_id' => $this->company->id,
                    'user_id' => $this->user->id,
                    'settings' => $settings
                ]);

        $settings = $this->company->settings;
        $settings->currency_id = '3';

        $this->company->saveSettings($settings, $this->company);

        $client_exchange_rate = round($c->setExchangeRate(), 2);

        $aud_currency = Currency::find(12);
        $eur_currency = Currency::find(3);

        $synthetic_exchange = $aud_currency->exchange_rate / $eur_currency->exchange_rate;

        $this->assertEquals($client_exchange_rate, round($synthetic_exchange, 2));

    }

    public function testClientIsPrimaryScalarTransform()
    {
        $data = [
            
                'address1' => '105 Drive',
                'address2' => '122',
                'city' => 'NoRoses',
                'contacts' => [
                    '0' => [
                        'email' => 'craig@gmail.za',
                        'first_name' => 'Leon',
                        'last_name' => 'Labagne'
                    ],
                    'is_primary' => true,
                    'send_email' => false
                ],
                'country_id' => 710,
                'id_number' => '2003/028851/06',
                'name' => 'Targas Ltd',
                'postal_code' => '2196',
                'private_notes' => 'DMARC Client | Tenant ID: 45 | Team Name: Targas',
                'state' => 'Gauteng',
                'vat_number' => 'VAT: 33'
            
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients', $data);

        $response->assertStatus(200);

    }

    public function testStoreClientFixes2()
    {
        $data = [
            "contacts" => [
                [
                "email" => "tenda@gmail.com",
                "first_name" => "Tenda",
                "last_name" => "Bavuma",
                ],
            ],
            "name" => "Tenda Bavuma",
            ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertTrue($arr['data']['contacts'][0]['is_primary']);
        $this->assertTrue($arr['data']['contacts'][0]['send_email']);

    }


    public function testStoreClientFixes()
    {
        $data = [
            "contacts" => [
            [
            "email" => "tenda@gmail.com",
            "first_name" => "Tenda",
            "is_primary" => true,
            "last_name" => "Bavuma",
            "password" => null,
            "send_email" => true
            ],
        ],
            "country_id" => "356",
            "display_name" => "Tenda Bavuma",
            "name" => "Tenda Bavuma",
            "shipping_country_id" => "356",
            ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients', $data);

        $response->assertStatus(200);
    }

    public function testClientMergeContactDrop()
    {

        $c = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $c->id,
            'company_id' => $this->company->id,
        ]);


        $c1 = Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $c1->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $c1->id,
            'company_id' => $this->company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $c1->id,
            'company_id' => $this->company->id,
            'email' => ''
        ]);


        $this->assertEquals(2, $c->contacts->count());
        $this->assertEquals(3, $c1->contacts->count());

        $c->service()->merge($c1);

        $c = $c->fresh();

        // nlog($c->contacts->pluck('email'));

        $this->assertEquals(4, $c->contacts->count());

    }

    private function buildLineItems($number = 2)
    {
        $line_items = [];

        for ($x = 0; $x < $number; $x++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            $item->cost = 10;

            $line_items[] = $item;
        }

        return $line_items;
    }

    public function testCreditBalance()
    {
        $this->client->credit_balance = 0;
        $this->client->save();

        $this->assertEquals(0, $this->client->credit_balance);

        $credit = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '34343xx43',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'line_items' => $this->buildLineItems()
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/credits/', $credit)
            ->assertStatus(200);

        $arr = $response->json();

        $credit_id = $arr['data']['id'];

        $credit = Credit::find($this->decodePrimaryKey($credit_id));

        $this->assertNotNull($credit);

        $this->assertEquals(0, $credit->balance);

        $credit->service()->markSent()->save();

        $this->assertEquals(20, $credit->balance);
        $this->assertEquals(20, $credit->client->fresh()->credit_balance);

        //lets now update the credit and increase its balance, this should also increase the credit balance

        $data = [
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'number' => '34343xx43',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
            'line_items' => $this->buildLineItems(3)
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/credits/'.$credit->hashed_id, $data)
            ->assertStatus(200);

        $credit = $credit->fresh();

        $this->assertEquals(30, $credit->balance);
        $this->assertEquals(30, $credit->client->fresh()->credit_balance);
    }

    public function testStoreClientUsingCountryCode()
    {
        $data = [
            'name' => 'Country Code Name',
            'country_code' => 'US',
            'currency_code' => 'USD',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $arr = $response->json();
        $client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(840, $client->country_id);
        $this->assertEquals(1, $client->settings->currency_id);

        $data = [
            'name' => 'Country Code Name',
            'country_code' => 'USA',
            'currency_code' => 'USD',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $arr = $response->json();
        $client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(840, $client->country_id);
        $this->assertEquals(1, $client->settings->currency_id);

        $data = [
            'name' => 'Country Code Name',
            'country_code' => 'AU',
            'currency_code' => 'AUD',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data);

        $arr = $response->json();
        $client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $this->assertEquals(36, $client->country_id);
        $this->assertEquals(12, $client->settings->currency_id);
    }

    public function testClientList()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients');

        $response->assertStatus(200);
    }

    /*
     *  ClientController
     */
    public function testClientRestEndPoints()
    {
        Client::factory()->count(3)->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
            ClientContact::factory()->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->count(2)->create([
                'user_id' => $this->user->id,
                'client_id' => $c->id,
                'company_id' => $this->company->id,
            ]);
        });

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id).'/edit');

        $response->assertStatus(200);

        $client_update = [
            'name' => 'A Funky Name',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), $client_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', ['name' => 'New Client'])
            ->assertStatus(200);

        $response->assertStatus(200);

        $this->client->is_deleted = true;
        $this->client->save();

        $client_update = [
            'name' => 'Double Funk',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->encodePrimaryKey($this->client->id), $client_update)
            ->assertStatus(400);
    }

    public function testDefaultTimeZoneFromClientModel()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',
        ]);

        $userPermissions = collect([
            'view_invoice',
            'view_client',
            'edit_client',
            'edit_invoice',
            'create_invoice',
            'create_client',
        ]);

        $userSettings = DefaultSettings::userSettings();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => $userPermissions->toJson(),
            'settings' => json_encode($userSettings),
            'is_locked' => 0,
        ]);

        Client::factory()->count(3)->create(['user_id' => $user->id, 'company_id' => $company->id])->each(function ($c) use ($user, $company) {
            ClientContact::factory()->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
                'is_primary' => 1,
            ]);

            ClientContact::factory()->count(2)->create([
                'user_id' => $user->id,
                'client_id' => $c->id,
                'company_id' => $company->id,
            ]);
        });

        $this->client = Client::whereUserId($user->id)->whereCompanyId($company->id)->first();

        $this->assertNotNull($this->client);

        /* Make sure we have a valid settings object*/
        $this->assertEquals($this->client->getSetting('timezone_id'), 1);

        /* Make sure we are harvesting valid data */
        $this->assertEquals($this->client->timezone()->name, 'Pacific/Midway');

        /* Make sure NULL settings return the correct count (0) instead of throwing an exception*/
        $this->assertEquals($this->client->contacts->count(), 3);
    }

    public function testClientCreationWithIllegalContactObject()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',

        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => '',
            'settings' => '',
            'is_locked' => 0,
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = $user->first_name.' '.$user->last_name;
        $company_token->token = Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        $this->token = $company_token->token;

        $data = [
            'name' => 'A loyal Client',
            'contacts' => $this->faker->unique()->safeEmail(),
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }
    }

    public function testCreatingClientAndContacts()
    {
        $account = Account::factory()->create();
        $company = Company::factory()->create([
            'account_id' => $account->id,
        ]);

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::factory()->create([
            'account_id' => $account->id,
            'confirmation_code' => $this->createDbHash(config('database.default')),
            'email' => 'whiz@gmail.com',

        ]);

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'notifications' => CompanySettings::notificationDefaults(),
            'permissions' => '',
            'settings' => '',
            'is_locked' => 0,
        ]);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = $user->first_name.' '.$user->last_name;
        $company_token->token = Str::random(64);
        $company_token->is_system = true;
        $company_token->save();

        $this->token = $company_token->token;

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                ['email' => $this->faker->unique()->safeEmail()],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data)
                ->assertStatus(200);

        // $arr = $response->json();

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '*****',
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/clients/', $data)
                ->assertStatus(200);

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1Qajsj...33',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        $response->assertStatus(200);

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1Qajsj...33',
                ],
                [
                    'email' => $this->faker->unique()->safeEmail(),
                    'password' => '1234AAAAAaaaaa',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client_id = $arr['data']['id'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/clients/'.$this->client_id, $data)->assertStatus(200);

        $arr = $response->json();

        $safe_email = $this->faker->unique()->safeEmail();

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $safe_email,
                    'password' => '',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $contact = $this->client->contacts()->whereEmail($safe_email)->first();

        $this->assertEquals(0, strlen($contact->password));

        $safe_email = $this->faker->unique()->safeEmail();

        $data = [
            'name' => 'A loyal Client',
            'contacts' => [
                [
                    'email' => $safe_email,
                    'password' => 'AFancyDancy191$Password',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client = Client::find($this->decodePrimaryKey($arr['data']['id']));

        $contact = $this->client->contacts()->whereEmail($safe_email)->first();

        $this->assertGreaterThan(1, strlen($contact->password));

        $password = $contact->password;

        $data = [
            'name' => 'A Stary eyed client',
            'contacts' => [
                [
                    'id' => $contact->hashed_id,
                    'email' => $safe_email,
                    'password' => '*****',
                ],
            ],
        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/clients/'.$this->client->hashed_id, $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->client = Client::find($this->decodePrimaryKey($arr['data']['id']));
        $this->client->fresh();

        $contact = $this->client->contacts()->whereEmail($safe_email)->first();

        $this->assertEquals($password, $contact->password);
    }
}

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

use App\DataMapper\ClientSettings;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class ClientSettingsTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->faker = \Faker\Factory::create();
    }


    public function testBadProps()
    {
        $client = \App\Models\Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'settings' => ClientSettings::defaults(),
        ]);

        $this->assertNotNull($client);

        $settings = $client->settings;

        $settings->timezone_id = '15';

        $client->saveSettings($settings, $client);

        $this->assertNotNull($client);

        $settings->something_crazy_here = '5424234234';

        $client->saveSettings($settings, $client);

        $this->assertFalse(property_exists($client->settings, 'something_crazy_here'));

    }

    public function testClientValidSettingsWithBadProps()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '43',
                'language_id' => '1',
                'website' => null,
                'address1' => null,
                'address2' => null,
                'city' => null,
                'state' => null,
                'postal_code' => null,
                'phone' => null,
                'email' => null,
                'vat_number' => null,
                'id_number' => null,
                'purchase_order_terms' => null,
                'purchase_order_footer' => null,
                'besr_id' => null,
                'qr_iban' => null,
                'name' => 'frank',
                'custom_value1' => null,
                'custom_value2' => null,
                'custom_value3' => null,
                'custom_value4' => null,
                'invoice_terms' => null,
                'quote_terms' => null,
                'quote_footer' => null,
                'credit_terms' => null,
                'credit_footer' => null,
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('frank', $arr['data']['settings']['name']);

        $client_id = $arr['data']['id'];

        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '43',
                'language_id' => '1',
                'name' => 'white',
            ],
        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/clients/'.$client_id, $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('white', $arr['data']['settings']['name']);

        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '43',
                'language_id' => '1',
                'website' => null,
                'address1' => null,
                'besr_id' => null,
                'qr_iban' => null,
                'name' => 'white',
                'custom_value1' => null,
                'custom_value2' => null,
                'custom_value3' => null,
                'custom_value4' => null,
                'invoice_terms' => null,
                'quote_terms' => null,
                'quote_footer' => null,
                'credit_terms' => null,
                'credit_footer' => null,
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/clients/'.$client_id, $data);

        $response->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals('white', $arr['data']['settings']['name']);

        // $this->assertEquals('1', $arr['data']['settings']['currency_id']);
        // $this->assertEquals('1', $arr['data']['settings']['language_id']);
        // $this->assertEquals('1', $arr['data']['settings']['payment_terms']);
        // $this->assertEquals(10, $arr['data']['settings']['default_task_rate']);
        // $this->assertEquals(true, $arr['data']['settings']['send_reminders']);
        // $this->assertEquals('1', $arr['data']['settings']['valid_until']);
    }



    public function testClientBaseline()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('1', $arr['data']['settings']['currency_id']);
    }

    public function testClientValidSettings()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
        }

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('1', $arr['data']['settings']['currency_id']);
        $this->assertEquals('1', $arr['data']['settings']['language_id']);
        $this->assertEquals('1', $arr['data']['settings']['payment_terms']);
        $this->assertEquals(10, $arr['data']['settings']['default_task_rate']);
        $this->assertEquals(true, $arr['data']['settings']['send_reminders']);
        $this->assertEquals('1', $arr['data']['settings']['valid_until']);
    }

    public function testClientIllegalCurrency()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Cooliox2',
            'settings' => [
                'currency_id' => 'a',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '2',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


        $response->assertStatus(422);
    }

    public function testClientIllegalLanguage()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => 'a',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);

        $response->assertStatus(422);
    }

    public function testClientIllegalPaymenTerms()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => 'a',
                'valid_until' => '1',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


        $response->assertStatus(422);
    }

    public function testClientIllegalValidUntil()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => 'a',
                'default_task_rate' => 10,
                'send_reminders' => true,
            ],
        ];

        $response = false;


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


        $response->assertStatus(422);
    }

    public function testClientIllegalDefaultTaskRate()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 'a',
                'send_reminders' => true,
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertFalse(array_key_exists('default_task_rate', $arr));
    }

    public function testClientIllegalSendReminderBool()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 'a',
                'send_reminders' => 'faaalse',
            ],
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/clients/', $data);


        $response->assertStatus(422);
    }

    public function testClientSettingBools()
    {
        $data = [
            'name' => $this->faker->firstName(),
            'id_number' => 'Coolio',
            'settings' => [
                'currency_id' => '1',
                'language_id' => '1',
                'payment_terms' => '1',
                'valid_until' => '1',
                'default_task_rate' => 'a',
                'send_reminders' => 'true',
            ],
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/clients/', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            nlog($message);
        }

        $response->assertStatus(200);
    }
}

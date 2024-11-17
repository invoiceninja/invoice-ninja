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
use Tests\MockAccountData;
use App\Models\GatewayType;
use App\Models\CompanyGateway;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;


class ClientGatewayTokenApiTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    protected $faker;
    protected CompanyGateway $cg;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        if (! config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        $this->withoutExceptionHandling();

        CompanyGateway::whereNotNull('id')->delete();

        $data = [];
        $data[1]['min_limit'] = -1;
        $data[1]['max_limit'] = -1;
        $data[1]['fee_amount'] = 0.00;
        $data[1]['fee_percent'] = 2;
        $data[1]['fee_tax_name1'] = 'GST';
        $data[1]['fee_tax_rate1'] = 10;
        $data[1]['fee_tax_name2'] = 'GST';
        $data[1]['fee_tax_rate2'] = 10;
        $data[1]['fee_tax_name3'] = 'GST';
        $data[1]['fee_tax_rate3'] = 10;
        $data[1]['adjust_fee_percent'] = true;
        $data[1]['fee_cap'] = 0;
        $data[1]['is_enabled'] = true;

        $data[2]['min_limit'] = -1;
        $data[2]['max_limit'] = -1;
        $data[2]['fee_amount'] = 0.00;
        $data[2]['fee_percent'] = 1;
        $data[2]['fee_tax_name1'] = 'GST';
        $data[2]['fee_tax_rate1'] = 10;
        $data[2]['fee_tax_name2'] = 'GST';
        $data[2]['fee_tax_rate2'] = 10;
        $data[2]['fee_tax_name3'] = 'GST';
        $data[2]['fee_tax_rate3'] = 10;
        $data[2]['adjust_fee_percent'] = true;
        $data[2]['fee_cap'] = 0;
        $data[2]['is_enabled'] = true;

        //disable ach here
        $json_config = json_decode(config('ninja.testvars.stripe'));

        $this->cg = new CompanyGateway();
        $this->cg->company_id = $this->company->id;
        $this->cg->user_id = $this->user->id;
        $this->cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
        $this->cg->require_cvv = true;
        $this->cg->require_billing_address = true;
        $this->cg->require_shipping_address = true;
        $this->cg->update_details = true;
        $this->cg->config = encrypt(json_encode($json_config));
        $this->cg->fees_and_limits = $data;
        $this->cg->save();
    }


    public function testCompanyGatewaySettableOnToken()
    {
                
        $data = [
            'client_id' => $this->client->hashed_id,
            'company_gateway_id' => $this->cg->hashed_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'token' => 'tokey',
            'gateway_customer_reference' => 'reffy',
            'meta' => '{}',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/client_gateway_tokens', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $t1 = $arr['data']['id'];

        $this->assertEquals($data['company_gateway_id'], $arr['data']['company_gateway_id']);
        $this->assertEquals($data['gateway_type_id'], $arr['data']['gateway_type_id']);
        $this->assertEquals('tokey', $arr['data']['token']);
        $this->assertEquals('reffy', $arr['data']['gateway_customer_reference']);

        $this->assertNotNull($arr['data']['token']);

    }

    public function testClientGatewaySetDefault()
    {
        $data = [
            'client_id' => $this->client->hashed_id,
            'company_gateway_id' => $this->cg->hashed_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'meta' => '{}',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/client_gateway_tokens', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $t1 = $arr['data']['id'];

        $this->assertNotNull($arr['data']['token']);


        $data = [
            'client_id' => $this->client->hashed_id,
            'company_gateway_id' => $this->cg->hashed_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'is_default' => false,
            'meta' => '{}',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/client_gateway_tokens', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $t2 = $arr['data']['id'];

        $this->assertNotNull($arr['data']['token']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/client_gateway_tokens/{$t2}/setAsDefault", []);

        $response->assertStatus(200);

        $this->assertTrue($response->json()['data']['is_default']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson("/api/v1/client_gateway_tokens/{$t1}/setAsDefault", []);

        $response->assertStatus(200);

        $this->assertTrue($response->json()['data']['is_default']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->getJson("/api/v1/clients/{$this->client->hashed_id}", []);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(2,$arr['data']['gateway_tokens']);
        
        foreach($arr['data']['gateway_tokens'] as $token)
        {
            if($token['id'] == $t1){
                $this->assertTrue($token['is_default']);
            }
            else {
                $this->assertFalse($token['is_default']);
            }
        }

    }



    public function testClientGatewayPostPost()
    {
        $data = [
            'client_id' => $this->client->hashed_id,
            'company_gateway_id' => $this->cg->hashed_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'meta' => '{}',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/client_gateway_tokens', $data);

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertNotNull($arr['data']['token']);
    }

    public function testClientPut()
    {
        $data = [
            'client_id' => $this->client->hashed_id,
            'company_gateway_id' => $this->cg->hashed_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'meta' => '{}',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/client_gateway_tokens', $data);

        $arr = $response->json();

        $response->assertStatus(200);

        $data = [
            'token' => 'a_testy_token',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/client_gateway_tokens/'.$arr['data']['id'], $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('a_testy_token', $arr['data']['token']);
    }

    public function testClientGet()
    {
        $data = [
            'client_id' => $this->client->hashed_id,
            'company_gateway_id' => $this->cg->hashed_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'meta' => '{}',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/client_gateway_tokens', $data);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/client_gateway_tokens/'.$arr['data']['id']);

        $response->assertStatus(200);
    }
}

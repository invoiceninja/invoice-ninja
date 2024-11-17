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

use App\Models\Client;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\MockAccountData;
use Tests\TestCase;

class ClientModelTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

        if (! config('ninja.testvars.stripe')) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }

        if(CompanyGateway::count() == 0) {
            $this->markTestSkipped('Skip test no company gateways installed');
        }

    }

    public function testNewWithoutAndDeletedClientFilters()
    {

        $this->invoice->amount = 10;
        $this->invoice->balance = 10;
        $this->invoice->status_id = 2;
        $this->invoice->date = now()->subDays(2);
        $this->invoice->due_date = now()->addDays(2);
        $this->invoice->save();

        $cd = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);


        $cd2 = Client::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
        ]);

        $invoice_count = Invoice::where('company_id', $this->company->id)->count();

        $this->assertGreaterThan(0, $invoice_count);

        $i = Invoice::factory()->create([
            'client_id' => $cd->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status_id' => 2,
            'amount' => 10,
            'balance' => 10,
            'date' => now()->subDays(2)->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
        ]);


        $i2 = Invoice::factory()->create([
            'client_id' => $cd2->id,
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'status_id' => 2,
            'amount' => 10,
            'balance' => 10,
            'date' => now()->subDays(2)->format('Y-m-d'),
            'due_date' => now()->addDays(5)->format('Y-m-d'),
            'partial' => 0,
            'partial_due_date' => null,
        ]);

        $response = $this->withHeaders([
        'X-API-SECRET' => config('ninja.api_secret'),
        'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?status=active');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($invoice_count + 2, count($arr['data']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?upcoming=true&status=active&include=client');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($invoice_count + 2, count($arr['data']));

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?upcoming=true&status=active&without_deleted_clients=true');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($invoice_count + 2, count($arr['data']));


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?upcoming=true&status=active&filter_deleted_clients=true');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($invoice_count + 2, count($arr['data']));

        $cd2->is_deleted = true;
        $cd2->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?upcoming=true&status=active&without_deleted_clients=true');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($invoice_count + 1, count($arr['data']));


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?upcoming=true&status=active&filter_deleted_clients=true');

        $response->assertStatus(200);
        $arr = $response->json();

        $this->assertEquals($invoice_count + 1, count($arr['data']));


    }

    public function testPaymentMethodsWithCreditsEnforced()
    {

        $payment_methods = $this->client->service()->getPaymentMethods(40);

        $this->assertGreaterThan(0, CompanyGateway::count());

        $this->assertEquals(2, count($payment_methods));
    }
}

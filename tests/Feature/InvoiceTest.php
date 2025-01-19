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
use App\Models\Client;
use App\Models\Invoice;
use App\Models\Project;
use Tests\MockAccountData;
use App\Models\Subscription;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Factory\InvoiceItemFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Repositories\InvoiceRepository;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *  App\Http\Controllers\InvoiceController
 */
class InvoiceTest extends TestCase
{
    use MakesHash;
    use DatabaseTransactions;
    use MockAccountData;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();
    }


    public function testLineItemValidation()
    {

        $line_items = [];

        $item = new \stdClass();
        $item->quantity = 1;
        $item->cost = 100000000;
        $item->type_id = '1';
        $item->tax_name1 = ['tax_name1'];
        $item->tax_rate1 = 10;
        $item->tax_name2 = 'tax_name2';
        $item->tax_rate2 = 10;
        $item->tax_name3 = 'tax_name3';
        $item->tax_rate3 = 10;

        $line_items[] = $item;

        $data = [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $this->client->hashed_id,
            'line_items' => $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true', $data)
            ->assertStatus(200);

        $arr = $response->json();

    }

    public function testMaxDiscount()
    {


        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100000000;
        $item->type_id = '1';

        $line_items[] = $item;

        $data = [
            'status_id' => 1,
            'number' => '',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'client_id' => $this->client->hashed_id,
            'line_items' => $line_items,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, $arr['data']['status_id']);
        $this->assertEquals(100000000, $arr['data']['amount']);
        $this->assertEquals(100000000, $arr['data']['balance']);

        $data = [
                'status_id' => 1,
                'number' => '',
                'discount' => 100000000,
                'is_amount_discount' => 1,
                'po_number' => '3434343',
                'public_notes' => 'notes',
                'is_deleted' => 0,
                'custom_value1' => 0,
                'custom_value2' => 0,
                'custom_value3' => 0,
                'custom_value4' => 0,
                'client_id' => $this->client->hashed_id,
                'line_items' => $line_items,
            ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, $arr['data']['status_id']);
        $this->assertEquals(0, $arr['data']['amount']);
        $this->assertEquals(0, $arr['data']['balance']);
        $this->assertEquals(100000000, $arr['data']['discount']);

        $line_items = [];
        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 100000000;
        $item->discount = 100000000;
        $item->type_id = '1';

        $line_items[] = $item;

        $data = [
                'status_id' => 1,
                'number' => '',
                'discount' => 0,
                'is_amount_discount' => 1,
                'po_number' => '3434343',
                'public_notes' => 'notes',
                'is_deleted' => 0,
                'custom_value1' => 0,
                'custom_value2' => 0,
                'custom_value3' => 0,
                'custom_value4' => 0,
                'client_id' => $this->client->hashed_id,
                'line_items' => $line_items,
            ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices?mark_sent=true', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(2, $arr['data']['status_id']);
        $this->assertEquals(0, $arr['data']['amount']);
        $this->assertEquals(0, $arr['data']['balance']);


    }

    public function testInvoicePaymentLinkMutation()
    {


        $s = Subscription::factory()
            ->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);


        $s2 = Subscription::factory()
        ->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);


        $r = Invoice::factory()
        ->create(['company_id' => $this->company->id, 'user_id' => $this->user->id,'client_id' => $this->client->id]);

        $rr = $r->service()->setPaymentLink($s->hashed_id)->save();

        $this->assertEquals($s->id, $rr->subscription_id);

        $data = [
            'subscription_id' => $s2->hashed_id,
            'action' => 'set_payment_link',
            'ids' => [$r->hashed_id],
        ];

        $response = $this->withHeaders([
        'X-API-SECRET' => config('ninja.api_secret'),
        'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/invoices/bulk', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $r = $r->fresh();

        $this->assertEquals($s2->id, $r->subscription_id);



    }


    public function testPostNewInvoiceWithProjectButNoClient()
    {

        $p = Project::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $invoice = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'project_id' => $p->hashed_id
            // 'client_id' => $this->encodePrimaryKey($this->client->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/invoices/', $invoice)
            ->assertStatus(422);

    }


    public function testInvoiceGetDatesBetween()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?date_range=date,2023-01-01,2023-01-01', )
        ->assertStatus(200);
    }

    public function testInvoiceGetDatesBetween2()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?date_range=date', )
        ->assertStatus(200);
    }

    public function testInvoiceGetDatesBetween3()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?date_range=x', )
        ->assertStatus(200);
    }

    public function testInvoiceGetDatesBetween4()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?date_range=date,2023223123,312312321', )
        ->assertStatus(200);
    }

    public function testInvoiceGetDatesBetween5()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?date_range=date,x,23423', )
        ->assertStatus(200);
    }

    public function testInvoiceGetDatesBetween6()
    {
        Invoice::factory()->count(10)->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'date' => '1971-01-02',
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?date_range=1971-01-01,1971-01-03', )
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(10, $arr['data']);
    }

    public function testInvoiceGetPaidReversedInvoice()
    {
        $this->invoice->service()->handleReversal()->save();

        $this->assertEquals(6, $this->invoice->fresh()->status_id);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?status_id=6', )
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(1, $arr['data']);
    }

    public function testInvoiceGetPaidInvoices()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices?client_status=paid', )
        ->assertStatus(200);
    }

    public function testInvoiceArchiveAction()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices/'.$this->invoice->hashed_id.'/archive', )
        ->assertStatus(200);
    }


    public function testMarkingDeletedInvoiceAsSent()
    {
        Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
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
        });

        $client = Client::all()->first();

        $invoice = Invoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);
        $invoice->status_id = Invoice::STATUS_DRAFT;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;
        $invoice->tax_rate1 = 0;
        $invoice->tax_rate2 = 0;
        $invoice->tax_rate3 = 0;
        $invoice->discount = 0;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();

        $this->assertEquals(Invoice::STATUS_DRAFT, $invoice->status_id);
        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals(0, $invoice->balance);

        $invoice_repository = new InvoiceRepository();
        $invoice = $invoice_repository->delete($invoice);

        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals(0, $invoice->balance);
        $this->assertTrue($invoice->is_deleted);

        $invoice->service()->markSent()->save();

        $this->assertEquals(0, $invoice->balance);
    }

    public function testInvoiceList()
    {
        Client::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id])->each(function ($c) {
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
        });

        $client = Client::all()->first();

        Invoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices');

        $response->assertStatus(200);
    }

    public function testInvoiceRESTEndPoints()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id).'/edit');

        $response->assertStatus(200);

        $invoice_update = [
            'tax_name1' => 'dippy',
        ];

        $this->assertNotNull($this->invoice);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id), $invoice_update)
            ->assertStatus(200);
    }

    public function testPostNewInvoice()
    {
        $invoice = [
            'status_id' => 1,
            'number' => 'dfdfd',
            'discount' => 0,
            'is_amount_discount' => 1,
            'po_number' => '3434343',
            'public_notes' => 'notes',
            'is_deleted' => 0,
            'custom_value1' => 0,
            'custom_value2' => 0,
            'custom_value3' => 0,
            'custom_value4' => 0,
            'status' => 1,
            'client_id' => $this->encodePrimaryKey($this->client->id),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(200);

        $arr = $response->json();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/invoices/'.$arr['data']['id'], $invoice)
            ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice)
            ->assertStatus(302);
    }

    public function testDeleteInvoice()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/invoices/'.$this->encodePrimaryKey($this->invoice->id));

        $response->assertStatus(200);
    }

    public function testUniqueNumberValidation()
    {
        /* stub a invoice in the DB that we will use to test against later */
        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'number' => 'test',
        ]);

        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $data)
        ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('dude', $arr['data']['number']);

        /*test validation fires*/
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'test',
        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->put('/api/v1/invoices/'.$arr['data']['id'], $data)
            ->assertStatus(302);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            // nlog('inside update invoice validator');
            // nlog($message);
            $this->assertNotNull($message);
        }

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'style',
        ];

        /* test number passed validation*/
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/invoices/'.$arr['data']['id'], $data)
            ->assertStatus(200);

        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'style',
        ];

        /* Make sure we can UPDATE using the same number*/
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/invoices/'.$arr['data']['id'], $data)
            ->assertStatus(200);
    }

    public function testClientedDeletedAttemptingToCreateInvoice()
    {
        /* Test fire new invoice */
        $data = [
            'client_id' => $this->client->hashed_id,
            'number' => 'dude',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $data)
        ->assertStatus(200);
    }
}

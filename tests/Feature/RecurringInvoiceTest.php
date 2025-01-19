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

use Carbon\Carbon;
use Tests\TestCase;
use App\Models\Client;
use App\Utils\Helpers;
use App\Models\Product;
use Tests\MockAccountData;
use App\Models\Subscription;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use App\Factory\InvoiceItemFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use App\Factory\RecurringInvoiceFactory;
use Database\Factories\SubscriptionFactory;
use App\Jobs\RecurringInvoice\UpdateRecurring;
use App\Factory\InvoiceToRecurringInvoiceFactory;
use App\Factory\RecurringInvoiceToInvoiceFactory;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * 
 *  App\Http\Controllers\RecurringInvoiceController
 */
class RecurringInvoiceTest extends TestCase
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

        $this->withoutMiddleware(
            ThrottleRequests::class
        );

        $this->makeTestData();
    }

    public function testUniqueNumber()
    {
       
        $data = [
            'client_id' => $this->client->hashed_id,
            'frequency_id' => 5,
            'next_send_date' => now()->addMonth()->format('Y-m-d'),
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertNotNull($arr['data']['number']);

        $data['number'] = $arr['data']['number'];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices', $data)
        ->assertStatus(422);
        
    }

    public function testBulkUpdatesTaxes()
    {
        RecurringInvoice::factory(5)->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'vendor_id' => $this->vendor->id,
        ]);

        $ri = RecurringInvoice::query()
                            ->where('company_id', $this->company->id)
                            ->where('client_id', $this->client->id)
                            ->where('vendor_id', $this->vendor->id);

        $this->assertCount(5, $ri->get());

        $data = [
            'action' => 'bulk_update',
            'ids' => $ri->get()->pluck('hashed_id'),
            'column' => 'tax1',
            'new_value' => 'GST||10',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        $response->assertStatus(200);


        $ri->cursor()->each(function ($e){
            $this->assertEquals('GST', $e->tax_name1);
            $this->assertEquals(10, $e->tax_rate1);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $ri->get()->pluck('hashed_id'),
            'column' => 'custom_value1',
            'new_value' => 'CUSTOMCUSTOM123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        $response->assertStatus(200);

        $ri->cursor()->each(function ($e) {
            $this->assertEquals('CUSTOMCUSTOM123', $e->custom_value1);
        });

      
        $data = [
            'action' => 'bulk_update',
            'ids' => $ri->get()->pluck('hashed_id'),
            'column' => 'footer',
            'new_value' => 'testfooter',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        $response->assertStatus(200);

        $ri->cursor()->each(function ($e) {
            $this->assertEquals('testfooter', $e->footer);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $ri->get()->pluck('hashed_id'),
            'column' => 'uses_inclusive_taxes',
            'new_value' => true,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        $response->assertStatus(200);

        $ri->cursor()->each(function ($e) {
            $this->assertTrue((bool)$e->uses_inclusive_taxes);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $ri->get()->pluck('hashed_id'),
            'column' => 'private_notes',
            'new_value' => 'TESTEST123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        $response->assertStatus(200);

        $ri->cursor()->each(function ($e) {
            $this->assertEquals('TESTEST123', $e->private_notes);
        });

        $data = [
            'action' => 'bulk_update',
            'ids' => $ri->get()->pluck('hashed_id'),
            'column' => 'public_notes',
            'new_value' => 'TESTEST123',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices/bulk', $data);

        $response->assertStatus(200);

        $ri->cursor()->each(function ($e) {
            $this->assertEquals('TESTEST123', $e->private_notes);
        });



    }






    public function testDateValidations()
    {
        $data = [
            'client_id' => $this->client->hashed_id,
            'frequency_id' => 5,
            'next_send_date' => '0001-01-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/recurring_invoices', $data)
          ->assertStatus(422);

    }

    public function testLinkingSubscription()
    {
        $s = Subscription::factory()
        ->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);


        $s2 = Subscription::factory()
        ->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);


        $r = RecurringInvoice::factory()
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
       ])->postJson('/api/v1/recurring_invoices/bulk', $data)
       ->assertStatus(200);

        $arr = $response->json();

        $r = $r->fresh();

        $this->assertEquals($s2->id, $r->subscription_id);


    }

    public function testStartDate()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->task_id = $this->encodePrimaryKey($this->task->id);
        $item->expense_id = $this->encodePrimaryKey($this->expense->id);
        $item->notes = "Hello this is the month of :MONTH";

        $line_items[] = $item;


        $data = [
            'frequency_id' => 1,
            'status_id' => 1,
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
            'line_items' => $line_items,
            'remaining_cycles' => -1,
            'date' => '0001-01-01',
            'due_date' => '0001-01-01',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('0001-01-01', $arr['data']['date']);

    }

    public function testNextSendDateCatch()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->task_id = $this->encodePrimaryKey($this->task->id);
        $item->expense_id = $this->encodePrimaryKey($this->expense->id);
        $item->notes = "Hello this is the month of :MONTH";

        $line_items[] = $item;


        $data = [
            'frequency_id' => 1,
            'status_id' => 2,
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
            'line_items' => $line_items,
            'remaining_cycles' => -1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(now()->startOfDay(), $arr['data']['next_send_date']);

    }

    public function testBulkIncreasePriceWithJob()
    {

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        (new UpdateRecurring([$recurring_invoice->id], $this->company, $this->user, 'increase_prices', 10))->handle();

        $recurring_invoice->refresh();

        $this->assertEquals(11, $recurring_invoice->amount);

    }

    public function testBulkUpdateWithJob()
    {
        $p = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 20,
            'price' => 20,
            'product_key' => 'pink',
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        (new UpdateRecurring([$recurring_invoice->id], $this->company, $this->user, 'update_prices'))->handle();

        $recurring_invoice->refresh();

        $this->assertEquals(20, $recurring_invoice->amount);

    }

    public function testBulkUpdatePrices()
    {
        $p = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 10,
            'price' => 10,
            'product_key' => 'pink',
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];
        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        $this->assertEquals(10, $recurring_invoice->amount);

        $p->cost = 20;
        $p->price = 20;
        $p->save();

        $recurring_invoice->service()->updatePrice();

        $recurring_invoice->refresh();

        $this->assertEquals(20, $recurring_invoice->amount);

        $recurring_invoice->service()->increasePrice(10);

        $recurring_invoice->refresh();

        $this->assertEquals(22, $recurring_invoice->amount);


    }

    public function testBulkUpdateMultiPrices()
    {
        $p1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 10,
            'price' => 10,
            'product_key' => 'pink',
        ]);

        $p2 = Product::factory()->create([
           'company_id' => $this->company->id,
           'user_id' => $this->user->id,
           'cost' => 20,
           'price' => 20,
           'product_key' => 'floyd',
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $line_items[] = [
            'product_key' => 'floyd',
            'notes' => 'test',
            'cost' => 20,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];

        $line_items[] = [
            'product_key' => 'pink',
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ];

        $recurring_invoice->line_items = $line_items;

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        $this->assertEquals(30, $recurring_invoice->amount);

        $p1->cost = 20;
        $p1->price = 20;
        $p1->save();

        $p2->cost = 40;
        $p2->price = 40;
        $p2->save();

        $recurring_invoice->service()->updatePrice();

        $recurring_invoice->refresh();

        $this->assertEquals(60, $recurring_invoice->amount);

        $recurring_invoice->service()->increasePrice(10);

        $recurring_invoice->refresh();

        $this->assertEquals(66, $recurring_invoice->amount);

        $recurring_invoice->service()->increasePrice(1);

        $recurring_invoice->refresh();

        $this->assertEquals(66.66, $recurring_invoice->amount);

    }


    public function testRecurringGetStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?client_status=active')
          ->assertStatus(200);
    }


    public function testPostRecurringInvoiceWithPlaceholderVariables()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->task_id = $this->encodePrimaryKey($this->task->id);
        $item->expense_id = $this->encodePrimaryKey($this->expense->id);
        $item->notes = "Hello this is the month of :MONTH";

        $line_items[] = $item;


        $data = [
            'frequency_id' => 1,
            'status_id' => 1,
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
            'line_items' => $line_items,
            'remaining_cycles' => -1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $data)
            ->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals(RecurringInvoice::STATUS_DRAFT, $arr['data']['status_id']);

        $notes = end($arr['data']['line_items'])['notes'];

        $this->assertTrue(str_contains($notes, ':MONTH'));
    }


    public function testPostRecurringInvoice()
    {
        $data = [
            'frequency_id' => 1,
            'status_id' => 1,
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
            'line_items' => $this->buildLineItems(),
            'remaining_cycles' => -1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $data)
            ->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals(RecurringInvoice::STATUS_DRAFT, $arr['data']['status_id']);
    }

    public function testPostRecurringInvoiceWithStartAndStop()
    {
        $data = [
            'frequency_id' => 1,
            'status_id' => 1,
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
            'line_items' => $this->buildLineItems(),
            'remaining_cycles' => -1,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices?start=true', $data)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(RecurringInvoice::STATUS_ACTIVE, $arr['data']['status_id']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_invoices/'.$arr['data']['id'].'?stop=true', $data)
            ->assertStatus(200);

        $arr = $response->json();
        $this->assertEquals(RecurringInvoice::STATUS_PAUSED, $arr['data']['status_id']);
    }

    public function testRecurringInvoiceList()
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

        RecurringInvoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices');

        $response->assertStatus(200);
    }

    public function testRecurringInvoiceRESTEndPoints()
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

        $client = Client::query()->orderBy('id', 'DESC')->first();

        RecurringInvoice::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);

        $RecurringInvoice = RecurringInvoice::query()->where('user_id', $this->user->id)->orderBy('id', 'DESC')->first();
        $RecurringInvoice->save();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id).'/edit');

        $response->assertStatus(200);

        $RecurringInvoice_update = [
            'status_id' => RecurringInvoice::STATUS_DRAFT,
            'client_id' => $this->encodePrimaryKey($RecurringInvoice->client_id),
            'number' => 'customnumber',
        ];

        $this->assertNotNull($RecurringInvoice);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id), $RecurringInvoice_update)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('customnumber', $arr['data']['number']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->put('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id), $RecurringInvoice_update)
            ->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/recurring_invoices/', $RecurringInvoice_update)
            ->assertStatus(302);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->delete('/api/v1/recurring_invoices/'.$this->encodePrimaryKey($RecurringInvoice->id));

        $response->assertStatus(200);
    }

    public function testSubscriptionIdPassesToInvoice()
    {
        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->subscription_id = 10;
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(10, $invoice->subscription_id);
    }

    public function testRecurringDatePassesToInvoice()
    {
        $noteText = "Hello this is for :MONTH_AFTER";
        $recurringDate = \Carbon\Carbon::now()->timezone($this->client->timezone()->name)->subDays(10);

        $item = InvoiceItemFactory::create();
        $item->cost = 10;
        $item->notes = $noteText;

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);


        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = $recurringDate;
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = $recurringDate;
        $recurring_invoice->line_items = [$item];
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->subscription_id = 10;
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->invoice->client);

        $expectedNote = Helpers::processReservedKeywords($noteText, $this->invoice->client, $recurringDate);

        $this->assertEquals($expectedNote, $invoice->line_items[0]->notes);
    }

    public function testSubscriptionIdPassesToInvoiceIfNull()
    {
        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(null, $invoice->subscription_id);
    }

    public function testSubscriptionIdPassesToInvoiceIfNothingSet()
    {
        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $this->user->id;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = \Carbon\Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $invoice = RecurringInvoiceToInvoiceFactory::create($recurring_invoice, $this->client);

        $this->assertEquals(null, $invoice->subscription_id);
    }

    public function testFilterProductKey()
    {
        $p1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 10,
            'price' => 10,
            'product_key' => $this->faker->unique()->word(),
        ]);

        $p2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 20,
            'price' => 20,
            'product_key' => $this->faker->unique()->word(),
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $recurring_invoice->line_items = [[
            'product_key' => $p1->product_key,
            'notes' => 'test',
            'cost' => 20,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]];

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        $recurring_invoice2 = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice2->client_id = $this->client->id;
        $recurring_invoice2->line_items = [[
            'product_key' => $p2->product_key,
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]];

        $recurring_invoice2->calc()->getInvoice()->service()->start()->save()->fresh();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?product_key=' . $this->faker->unique()->word())
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('0', $arr['meta']['pagination']['total']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?product_key=' . $p1->product_key)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('1', $arr['meta']['pagination']['total']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?product_key=' . $p1->product_key .',' . $p2->product_key)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('2', $arr['meta']['pagination']['total']);
    }

    public function testFilterFrequency()
    {
        $p1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 10,
            'price' => 10,
            'product_key' => $this->faker->word,
        ]);

        $p2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 20,
            'price' => 20,
            'product_key' => $this->faker->word,
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->frequency_id = RecurringInvoice::FREQUENCY_TWO_WEEKS;
        $recurring_invoice->client_id = $this->client->id;
        $recurring_invoice->line_items = [[
            'product_key' => $p1->product_key,
            'notes' => 'test',
            'cost' => 20,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]];

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        $recurring_invoice2 = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice2->frequency_id = RecurringInvoice::FREQUENCY_ANNUALLY;
        $recurring_invoice2->client_id = $this->client->id;
        $recurring_invoice2->line_items = [[
            'product_key' => $p2->product_key,
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]];

        $recurring_invoice2->calc()->getInvoice()->service()->start()->save()->fresh();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?frequency_id=' . RecurringInvoice::FREQUENCY_SIX_MONTHS)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('0', $arr['meta']['pagination']['total']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?frequency_id=' . RecurringInvoice::FREQUENCY_TWO_WEEKS)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('1', $arr['meta']['pagination']['total']);
    }

    public function testFilterNextDateBetween()
    {
        $p1 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 10,
            'price' => 10,
            'product_key' => $this->faker->word,
        ]);

        $p2 = Product::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'cost' => 20,
            'price' => 20,
            'product_key' => $this->faker->word,
        ]);

        $recurring_invoice = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice->client_id = $this->client->id;
        $recurring_invoice->next_send_date = Carbon::now()->subDays(7)->format('Y-m-d H:i:s');
        $recurring_invoice->line_items = [[
            'product_key' => $p1->product_key,
            'notes' => 'test',
            'cost' => 20,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]];

        $recurring_invoice->calc()->getInvoice()->service()->start()->save()->fresh();

        $recurring_invoice2 = RecurringInvoiceFactory::create($this->company->id, $this->user->id);
        $recurring_invoice2->next_send_date = Carbon::now()->subDays(2)->format('Y-m-d H:i:s');
        $recurring_invoice2->client_id = $this->client->id;
        $recurring_invoice2->line_items = [[
            'product_key' => $p2->product_key,
            'notes' => 'test',
            'cost' => 10,
            'quantity' => 1,
            'tax_name1' => '',
            'tax_rate1' => 0,
            'tax_name2' => '',
            'tax_rate2' => 0,
            'tax_name3' => '',
            'tax_rate3' => 0,
        ]];

        $recurring_invoice2->calc()->getInvoice()->service()->start()->save()->fresh();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?next_send_between=2020-01-01 00:00:00|2020-01-01 00:00:10')
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('0', $arr['meta']['pagination']['total']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/recurring_invoices?next_send_between=' . $recurring_invoice->next_send_date . '|' . $recurring_invoice->next_send_date)
            ->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('1', $arr['meta']['pagination']['total']);
    }
}

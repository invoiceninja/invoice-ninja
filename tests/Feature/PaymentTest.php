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

use App\DataMapper\ClientSettings;
use App\Factory\ClientFactory;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\PaymentController
 */
class PaymentTest extends TestCase
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
        // $this->withoutExceptionHandling();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testNullExchangeRateHandling()
    {
            
        $data = [
            'amount' => 0,
            'applied' => 0,
            'archived_at' => 0,
            'assigned_user_id' => null,
            'client_contact_id' => null,
            'client_id' => $this->client->hashed_id,
            'company_gateway_id' => null,
            'created_at' => 0,
            'credits' => [],
            'currency_id' => null,
            'custom_value1' => null,
            'custom_value2' => null,
            'custom_value3' => null,
            'custom_value4' => null,
            'date' => '2024-11-19',
            'documents' => [],
            'exchange_currency_id' => '2',
            'exchange_rate' => null,
            'gateway_type_id' => null,
            'id' => null,
            'idempotency_key' => '1e05f3b2474afce706c5d3f82c3441a9ba3d68c413ea97b8d12e8abab0cbb938',
            'invitation_id' => null,
            'invoices' => [],
            'is_deleted' => false,
            'is_manual' => false,
            'number' => null,
            'paymentables' => [],
            'private_notes' => null,
            'project_id' => null,
            'refunded' => 0,
            'status_id' => '1',
            'transaction_id' => null,
            'transaction_reference' => null,
            'type_id' => null,
            'updated_at' => 0,
            'user_id' => $this->user->hashed_id,
            'vendor_id' => null,
        ];

        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals(1, $arr['data']['exchange_rate']);
    }

    public function testNegativePaymentPaidToDate()
    {
        
        $c = Client::factory()->create([
           'user_id' => $this->user->id,
           'company_id' => $this->company->id,
       ]);

       $this->assertEquals(0, $c->balance);
       $this->assertEquals(0, $c->paid_to_date);
       $this->assertEquals(0, $c->credit_balance);
       $this->assertEquals(0, $c->payment_balance);

        $data = [
            'amount' => -500,
            'client_id' => $c->hashed_id,
            'invoices' => [
            ],
            'credits' => [
            ],
            'date' => '2020/12/11',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);
        
        $p = $response->json()['data'];

        $payment = Payment::find($this->decodePrimaryKey($p['id']));

        $this->assertEquals(-500, $payment->amount);
        $this->assertEquals(0, $payment->refunded);
        $this->assertEquals(0, $payment->applied);

        $c = $c->fresh();

        $this->assertEquals(0, $c->balance);
        $this->assertEquals(-500, $c->paid_to_date);
        $this->assertEquals(0, $c->credit_balance);
        $this->assertEquals(0, $c->payment_balance);

        $p = $payment->service()->deletePayment()->save();

        $c = $c->fresh();

        $this->assertEquals(0, $c->balance);
        $this->assertEquals(0, $c->paid_to_date);
        $this->assertEquals(0, $c->credit_balance);
        $this->assertEquals(0, $c->payment_balance);



    }

    public function testNullPaymentAmounts()    
    {

        $data = [
            'amount' => "null",
            'client_id' => "null",
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => "null",
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $this->invoice->hashed_id,
                    'amount' => "null",
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => 'xx',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(422);

    }


    public function testIdempotencyTrigger()
    {

        $data = [
            'amount' => 5,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => 'xx',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        sleep(1);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(422);

    }


    public function testInvoicesValidationProp()
    {

        $data = [
            'amount' => 5,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id:' => $this->invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => \Illuminate\Support\Str::uuid()->toString()
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(422);

    }

    public function testClientIdValidation()
    {
        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Payment::STATUS_COMPLETED,
            'amount' => 100
        ]);


        $data = [
            'date' => now()->addDay()->format('Y-m-d')
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$p->hashed_id, $data);

        $response->assertStatus(200);

        $data = [
            'date' => now()->addDay()->format('Y-m-d'),
            'client_id' => $this->client->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$p->hashed_id, $data);

        $response->assertStatus(200);

        $c = Client::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
        ]);

        $data = [
            'date' => now()->addDay()->format('Y-m-d'),
            'client_id' => $c->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$p->hashed_id, $data);

        $response->assertStatus(422);

    }

    public function testNegativeAppliedAmounts()
    {
        $p = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Payment::STATUS_COMPLETED,
            'amount' => 100
        ]);

        $i = Invoice::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Invoice::STATUS_SENT,
        ]);

        $i->calc()->getInvoice()->service()->markSent()->save();

        $this->assertGreaterThan(0, $i->balance);


        $data = [
            'amount' => 5,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => \Illuminate\Support\Str::uuid()->toString()
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $payment_id = $response->json()['data']['id'];

        $payment = Payment::find($this->decodePrimaryKey($payment_id));

        $this->assertNotNull($payment);

        $data = [
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => -5,
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => \Illuminate\Support\Str::uuid()->toString()
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$payment_id, $data);

        $response->assertStatus(422);

    }

    public function testCompletedPaymentLogic()
    {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Payment::STATUS_COMPLETED,
            'amount' => 100
        ]);

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => sha1(time()).\Illuminate\Support\Str::uuid()->toString()

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$payment->hashed_id, $data);

        $response->assertStatus(200);

    }

    public function testPendingPaymentLogic()
    {
        $payment = Payment::factory()->create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'status_id' => Payment::STATUS_PENDING,
            'amount' => 100
        ]);

        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $this->client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $this->invoice->hashed_id,
                    'amount' => $this->invoice->amount,
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => 'dsjafhajklsfhlaksjdhlkajsdjdfjdfljasdfhkjlsafhljfkfhsjlfhiuwayerfiuwaskjgbzmvnjzxnjcbgfkjhdgfoiwwrasdfasdfkashjdfkaskfjdasfda'

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$payment->hashed_id, $data);

        $response->assertStatus(422);

    }

    public function testPaymentGetBetweenQuery1()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?date_range=date,2023-01-01,2023-02-01');

        $response->assertStatus(200);
    }

    public function testPaymentGetBetweenQuery2()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?date_range=');

        $response->assertStatus(200);
    }

    public function testPaymentGetBetweenQuery3()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?date_range=1,1,1,1,1');

        $response->assertStatus(200);
    }

    public function testPaymentGetBetweenQuery4()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?date_range=date,34343,34343434343');

        $response->assertStatus(200);
    }

    public function testPaymentGetBetweenQuery5()
    {
        Payment::factory()->count(10)->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
            'date' => '2023-01-02',
        ]);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?date_range=date,2023-01-01,2023-01-03');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(10, $arr['data']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?date_range=date,2053-10-01,2053-10-03');

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertCount(0, $arr['data']);

    }

    public function testPaymentGetClientStatus()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?client_status=completed');

        $response->assertStatus(200);
    }

    public function testGetPaymentMatchList()
    {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments?match_transactions=true')
          ->assertStatus(200);
    }

    public function testStorePaymentIdempotencyKeyIllegalLength()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_Taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        $data = [
            'amount' => $invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $invoice->amount,
                ],
            ],
            'date' => '2020/12/11',
            'idempotency_key' => 'dsjafhajklsfhlaksjdhlkajsdjdfjdfljasdfhkjlsafhljfkfhsjlfhiuwayerfiuwaskjgbzmvnjzxnjcbgfkjhdgfoiwwrasdfasdfkashjdfkaskfjdasfda'

        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(422);

        // $this->assertFalse($response);
    }


    public function testPaymentList()
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

        Payment::factory()->create(
            ['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $client->id]
        );

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments');

        $response->assertStatus(200);
    }

    public function testPaymentRESTEndPoints()
    {
        $Payment = Payment::factory()->create(['user_id' => $this->user->id, 'company_id' => $this->company->id, 'client_id' => $this->client->id]);
        $Payment->name = \Illuminate\Support\Str::random(54);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments/'.$this->encodePrimaryKey($Payment->id));

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$this->encodePrimaryKey($Payment->id), $Payment->toArray());

        $response->assertStatus(200);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->get('/api/v1/payments/'.$this->encodePrimaryKey($Payment->id).'/edit');

        $response->assertStatus(200);
    }

    public function testStorePaymentWithoutClientId()
    {
        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_Taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        $data = [
            'amount' => $invoice->amount,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $invoice->amount,
                ],
            ],
            'date' => '2020/12/11',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);
        // } catch (ValidationException $e) {
        //     $message = json_decode($e->validator->getMessageBag(), 1);

        $response->assertStatus(422);

        // $this->assertTrue(array_key_exists('client_id', $message));
        // }
    }

    public function testStorePaymentWithClientId()
    {
        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_SENT;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_Taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();

        $data = [
            'amount' => $invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        // try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices,paymentables', $data);
        // } catch (ValidationException $e) {
        // $message = json_decode($e->validator->getMessageBag(), 1);
        // $this->assertNotNull($message);
        // }



        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $payment_id = $arr['data']['id'];

            $payment = Payment::with('invoices')->find($this->decodePrimaryKey($payment_id));

            $this->assertNotNull($payment);
            $this->assertNotNull($payment->invoices());
            $this->assertEquals(1, $payment->invoices->count());
        }
    }

    public function testStorePaymentWithNoInvoiecs()
    {
        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_SENT;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();

        $data = [
            'amount' => $invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => '',
            'date' => '2020/12/12',

        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $response->assertStatus(200);

    }

    public function testPartialPaymentAmount()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        /** @var \App\Models\Invoice $invoice */
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->partial = 2.0;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $pivot_invoice = $payment->invoices()->first();
        $this->assertEquals($pivot_invoice->pivot->amount, 2);
        $this->assertEquals($pivot_invoice->partial, 0);
        $this->assertEquals($pivot_invoice->amount, 10.0000);
        $this->assertEquals($pivot_invoice->balance, 8.0000);

    }

    public function testPaymentGreaterThanPartial()
    {
        $invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->setRelation('company', $this->company);
        $client->save();

        $client_contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $client->setRelation('contacts', $client_contact);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->partial = 5.0;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->company->setRelation('company', $this->company);
        $invoice->company->setRelation('client', $client);
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();
        $invoice->is_deleted = false;
        $invoice->save();

        $data = [
            'amount' => 6.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 6.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        // try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);
        // } catch (ValidationException $e) {
        // $message = json_decode($e->validator->getMessageBag(), 1);
        // }

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $invoice = $payment->invoices()->first();

        $this->assertEquals($invoice->partial, 0);
        $this->assertEquals($invoice->balance, 4);
    }

    public function testPaymentLessThanPartialAmount()
    {
        $invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'send_email' => true,
        ]);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->partial = 5.0;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

        $invoice = $payment->invoices()->first();

        $this->assertEquals($invoice->partial, 3);
        $this->assertEquals($invoice->balance, 8);
    }

    public function testPaymentValidationAmount()
    {
        $invoice = null;

        $client = ClientFactory::create($this->company->id, $this->user->id);
        $client->save();

        $contact = ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'send_email' => true,
        ]);

        $client->setRelation('contact', $contact);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->partial = 5.0;
        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $invoice->setRelation('client', $client);

        $data = [
            'amount' => 1.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        // try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);
        // } catch (ValidationException $e) {
        // $message = json_decode($e->validator->getMessageBag(), 1);
        $response->assertStatus(422);

        // $this->assertTrue(array_key_exists('amount', $message));
        // }
    }

    public function testPaymentChangesBalancesCorrectly()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 2.0,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        // try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);
        // } catch (ValidationException $e) {
        // $message = json_decode($e->validator->getMessageBag(), 1);

        // $this->assertTrue(array_key_exists('amount', $message));
        // }

        // if ($response) {
        $response->assertStatus(200);

        $invoice = Invoice::find($this->decodePrimaryKey($invoice->hashed_id));

        $this->assertEquals($invoice->balance, 8);

        $payment = $invoice->payments()->first();

        $this->assertEquals($payment->applied, 2);
        // }
    }

    public function testUpdatePaymentValidationWorks()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->amount = 10;
        $payment->client_id = $client->id;
        $payment->date = now();
        $payment->save();

        $data = [
            'amount' => 2.0,
            'client_id' => $client->hashed_id,
            'invoices' => [],
            'date' => '2019/12/12',
        ];

        $response = false;

        // try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$this->encodePrimaryKey($payment->id), $data);
        // } catch (ValidationException $e) {
        // $message = json_decode($e->validator->getMessageBag(), 1);

        // $this->assertTrue(array_key_exists('invoices', $message));
        // }/

        // if ($response) {
        $response->assertStatus(200);
        // }
    }

    public function testUpdatePaymentValidationPasses()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $payment = PaymentFactory::create($this->company->id, $this->user->id);
        $payment->amount = 10;
        $payment->client_id = $client->id;
        $payment->date = now();
        $payment->number = $client->getNextPaymentNumber($client, $payment);
        $payment->save();

        $data = [
            'amount' => 10.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        // try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->putJson('/api/v1/payments/'.$this->encodePrimaryKey($payment->id), $data);
        // } catch (ValidationException $e) {
        // $message = json_decode($e->validator->getMessageBag(), 1);
        // \Log::error(print_r($e->validator->getMessageBag(), 1));

        // $this->assertTrue(array_key_exists('invoices', $message));
        // }

        // if ($response) {
        $response->assertStatus(422);
        // }
    }

    public function testDoublePaymentTestWithInvalidAmounts()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 15.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        // try {
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);
        // } catch (ValidationException $e) {
        // $message = json_decode($e->validator->getMessageBag(), 1);
        // \Log::error(print_r($e->validator->getMessageBag(), 1));
        // }

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals($payment->amount, 15);
        $this->assertEquals($payment->applied, 10);

        $invoice = null;
        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 15.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = false;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->putJson('/api/v1/payments/'.$this->encodePrimaryKey($payment->id), $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);

            $this->assertTrue(array_key_exists('invoices', $message));
        }
    }

    public function testDoublePaymentTestWithValidAmounts()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 20.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals($payment->amount, 20);
        $this->assertEquals($payment->applied, 10);

    }

    public function testStorePaymentWithNoAmountField()
    {
        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_SENT;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_Taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();

        $data = [
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = null;

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        if ($response) {
            $arr = $response->json();
            $response->assertStatus(200);

            $payment_id = $arr['data']['id'];

            $this->assertEquals($invoice->amount, $arr['data']['amount']);

            $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

            $this->assertNotNull($payment);
            $this->assertNotNull($payment->invoices());
            $this->assertEquals(1, $payment->invoices()->count());
        }
    }

    public function testStorePaymentWithZeroAmountField()
    {
        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_SENT;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_Taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();

        $data = [
            'amount' => 0,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        try {
            $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/payments?include=invoices', $data);
        } catch (ValidationException $e) {
            $message = json_decode($e->validator->getMessageBag(), 1);
            $this->assertNotNull($message);
        }

        $response->assertStatus(200);

        $arr = $response->json();
        $payment_id = $arr['data']['id'];
        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $this->assertEquals(round($payment->amount, 2), $invoice->amount);

        $this->assertEquals(round($payment->applied, 2), $invoice->amount);
    }

    public function testPaymentForInvoicesFromDifferentClients()
    {
        $client1 = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client1->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $client2 = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client2->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice1 = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice1->client_id = $client1->id;
        $invoice1->status_id = Invoice::STATUS_SENT;

        $invoice1->line_items = $this->buildLineItems();
        $invoice1->uses_inclusive_Taxes = false;

        $invoice1->save();

        $invoice_calc = new InvoiceSum($invoice1);
        $invoice_calc->build();

        $invoice1 = $invoice_calc->getInvoice();
        $invoice1->save();

        $invoice2 = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice2->client_id = $client2->id;
        $invoice2->status_id = Invoice::STATUS_SENT;

        $invoice2->line_items = $this->buildLineItems();
        $invoice2->uses_inclusive_Taxes = false;

        $invoice2->save();

        $invoice_calc = new InvoiceSum($invoice2);
        $invoice_calc->build();

        $invoice2 = $invoice_calc->getInvoice();
        $invoice2->save();

        $data = [
            'amount' => $invoice1->amount + $invoice2->amount,
            'client_id' => $client1->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice1->hashed_id,
                    'amount' => $invoice1->amount,
                ],
                [
                    'invoice_id' => $invoice2->hashed_id,
                    'amount' => $invoice2->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $response->assertStatus(422);

    }

    public function testPaymentWithSameInvoiceMultipleTimes()
    {
        $client1 = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client1->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice1 = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice1->client_id = $client1->id;
        $invoice1->status_id = Invoice::STATUS_SENT;

        $invoice1->line_items = $this->buildLineItems();
        $invoice1->uses_inclusive_Taxes = false;

        $invoice1->save();

        $invoice_calc = new InvoiceSum($invoice1);
        $invoice_calc->build();

        $invoice1 = $invoice_calc->getInvoice();
        $invoice1->save();

        $data = [
            'amount' => $invoice1->amount,
            'client_id' => $client1->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice1->hashed_id,
                    'amount' => 1,
                ],
                [
                    'invoice_id' => $invoice1->hashed_id,
                    'amount' => 1,
                ],
            ],
            'date' => '2020/12/12',

        ];


        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->postJson('/api/v1/payments?include=invoices', $data);

        $response->assertStatus(422);


    }

    public function testStorePaymentWithCredits()
    {
        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_SENT;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice()->service()->markSent()->save();
        $this->assertEquals(10, $invoice->amount);
        $this->assertEquals(10, $invoice->balance);

        $credit = CreditFactory::create($this->company->id, $this->user->id);
        $credit->client_id = $client->id;
        $credit->status_id = Credit::STATUS_SENT;

        $credit->line_items = $this->buildLineItems();
        $credit->uses_inclusive_taxes = false;

        $credit->save();

        $credit_calc = new InvoiceSum($credit);
        $credit_calc->build();

        $credit = $credit_calc->getCredit()->service()->markSent()->save(); //$10 credit

        $this->assertEquals(10, $credit->amount);
        $this->assertEquals(10, $credit->balance);

        $data = [
            'amount' => $invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 5,
                ],
            ],
            'credits' => [
                [
                    'credit_id' => $credit->hashed_id,
                    'amount' => 5,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $payment = Payment::find($this->decodePrimaryKey($payment_id));

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

    }

    public function testStorePaymentExchangeRate()
    {
        $settings = ClientSettings::defaults();
        $settings->currency_id = '2';

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);

        $client->settings = $settings;
        $client->save();

        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->status_id = Invoice::STATUS_SENT;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_Taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();

        $data = [
            'amount' => $invoice->amount,
            'client_id' => $client->hashed_id,
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => $invoice->amount,
                ],
            ],
            'date' => '2020/12/12',

        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments?include=invoices', $data);

        $arr = $response->json();
        $response->assertStatus(200);

        $payment_id = $arr['data']['id'];

        $payment = Payment::find($this->decodePrimaryKey($payment_id));

        $this->assertNotNull($payment);
        $this->assertNotNull($payment->invoices());
        $this->assertEquals(1, $payment->invoices()->count());

    }

    public function testPaymentActionArchive()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $invoice->line_items = $this->buildLineItems();
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $data = [
            'amount' => 20.0,
            'client_id' => $this->encodePrimaryKey($client->id),
            'invoices' => [
                [
                    'invoice_id' => $this->encodePrimaryKey($invoice->id),
                    'amount' => 10,
                ],
            ],
            'date' => '2019/12/12',
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $payment_id = $arr['data']['id'];

        $payment = Payment::whereId($this->decodePrimaryKey($payment_id))->first();

        $data = [
            'ids' => [$this->encodePrimaryKey($payment->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=archive', $data);

        $arr = $response->json();

        $this->assertGreaterThan(0, $arr['data'][0]['archived_at']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=restore', $data);

        $arr = $response->json();

        $this->assertEquals(0, $arr['data'][0]['archived_at']);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=delete', $data);

        $arr = $response->json();

        $this->assertEquals(1, $arr['data'][0]['is_deleted']);
    }

    public function testDeleteRefundedPayment()
    {
        $invoice = null;

        $client = Client::factory()->create(['company_id' => $this->company->id, 'user_id' => $this->user->id]);
        ClientContact::factory()->create([
            'user_id' => $this->user->id,
            'client_id' => $client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
        ]);


        $invoice = InvoiceFactory::create($this->company->id, $this->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->cost = 10;
        $item->product_key = 'test';
        $item->notes = 'test';
        $item->custom_value1 = '';
        $item->custom_value2 = '';
        $item->custom_value3 = '';
        $item->custom_value4 = '';

        $line_items[] = $item;

        $invoice->line_items = $line_items;
        $invoice->uses_inclusive_taxes = false;

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();
        $invoice->save();
        $invoice->service()->markSent()->createInvitations()->save();

        $this->assertEquals(10, $invoice->balance);
        $this->assertEquals(10, $invoice->client->fresh()->balance);

        $invoice = $invoice->service()->markPaid()->save();

        $this->assertEquals(0, $invoice->balance);
        $this->assertEquals(0, $invoice->client->balance);

        $this->assertTrue($invoice->payments()->exists());

        $payment = $invoice->payments()->first();

        $data = [
            'id' => $this->encodePrimaryKey($payment->id),
            'amount' => 10,
            'date' => '2021/12/12',
            'invoices' => [
                [
                    'invoice_id' => $invoice->hashed_id,
                    'amount' => 10,
                ],
            ],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/refund', $data);


        $arr = $response->json();

        $response->assertStatus(200);

        $this->assertEquals(10, $invoice->fresh()->balance);
        $this->assertEquals(10, $invoice->fresh()->balance);

        $data = [
            'ids' => [$this->encodePrimaryKey($payment->id)],
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments/bulk?action=delete', $data);

        $this->assertEquals(10, $invoice->fresh()->balance);
        $this->assertEquals(10, $invoice->fresh()->balance);
    }

    public function testUniquePaymentNumbers()
    {
        $data = [
            'amount' => $this->invoice->amount,
            'client_id' => $this->client->hashed_id,
            'date' => '2020/12/12',
            'number' => 'duplicate',
        ];
        sleep(1);

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $data);

        $response->assertStatus(200);
        sleep(1);
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/payments', $data);

        $response->assertStatus(422);
    }
}

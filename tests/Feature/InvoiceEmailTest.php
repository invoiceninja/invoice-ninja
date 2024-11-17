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

use App\Jobs\Entity\EmailEntity;
use App\Models\SystemLog;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Jobs\Invoice\EmailInvoice
 */
class InvoiceEmailTest extends TestCase
{
    use MockAccountData;
    use DatabaseTransactions;
    use GeneratesCounter;

    public $faker;

    protected function setUp(): void
    {
        parent::setUp();

        Session::start();

        $this->faker = \Faker\Factory::create();

        Model::reguard();

        $this->makeTestData();

        // $this->withoutExceptionHandling();

    }

    public function testInvalidEmailParsing()
    {
        $email = 'illegal@example.com';

        $this->assertTrue(strpos($email, '@example.com') !== false);
    }

    public function testEntityValidation()
    {
        $data = [
            "body" => "hey what's up",
            "entity" => 'blergen',
            "entity_id" => $this->invoice->hashed_id,
            "subject" => 'Reminder $number',
            "template" => "email_template_invoice"
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/emails', $data);

        $response->assertStatus(422);

    }


    public function testClientEmailHistory()
    {
        $system_log = new SystemLog();
        $system_log->company_id = $this->company->id;
        $system_log->client_id = $this->client->id;
        $system_log->category_id = SystemLog::CATEGORY_MAIL;
        $system_log->event_id = SystemLog::EVENT_MAIL_SEND;
        $system_log->type_id = SystemLog::TYPE_WEBHOOK_RESPONSE;
        $system_log->log = [
            'history' => [
                'entity_id' => $this->invoice->hashed_id,
                'entity' => 'invoice',
                'subject' => 'Invoice #1',
                'events' => [
                    [
                        'recipient' => 'bob@gmail.com',
                        'status' => 'Delivered',
                        'delivery_message' => 'A message that was deliveryed',
                        'server' => 'email.mx.com',
                        'server_ip' => '127.0.0.1',
                        'date' => \Carbon\Carbon::parse('2023-10-10')->format('Y-m-d H:m:s') ?? '',
                    ],
                ],
            ]
        ];

        $system_log->save();


        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/emails/clientHistory/'.$this->client->hashed_id);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('invoice', $arr[0]['entity']);

        $count = SystemLog::where('client_id', $this->client->id)
                ->where('category_id', SystemLog::CATEGORY_MAIL)
                ->orderBy('id', 'DESC')
                ->count();

        $this->assertEquals(1, $count);
    }

    public function testEntityEmailHistory()
    {
        $system_log = new SystemLog();
        $system_log->company_id = $this->company->id;
        $system_log->client_id = $this->client->id;
        $system_log->category_id = SystemLog::CATEGORY_MAIL;
        $system_log->event_id = SystemLog::EVENT_MAIL_SEND;
        $system_log->type_id = SystemLog::TYPE_WEBHOOK_RESPONSE;
        $system_log->log = [
            'history' => [
                'entity_id' => $this->invoice->hashed_id,
                'entity' => 'invoice',
                'subject' => 'Invoice #1',
                'events' => [
                    [
                        'recipient' => 'bob@gmail.com',
                        'status' => 'Delivered',
                        'delivery_message' => 'A message that was deliveryed',
                        'server' => 'email.mx.com',
                        'server_ip' => '127.0.0.1',
                        'date' => \Carbon\Carbon::parse('2023-10-10')->format('Y-m-d H:m:s') ?? '',
                    ],
                ],
            ]
        ];

        $system_log->save();

        $data = [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/emails/entityHistory/', $data);

        $response->assertStatus(200);

        $arr = $response->json();

        $this->assertEquals('invoice', $arr[0]['entity']);
        $this->assertEquals($this->invoice->hashed_id, $arr[0]['entity_id']);

        $count = SystemLog::where('company_id', $this->company->id)
                ->where('category_id', SystemLog::CATEGORY_MAIL)
                ->whereJsonContains('log->history->entity_id', $this->invoice->hashed_id)
                ->count();

        $this->assertEquals(1, $count);

    }


    public function testTemplateValidation()
    {
        $data = [
            "body" => "hey what's up",
            "entity" => 'invoice',
            "entity_id" => $this->invoice->hashed_id,
            "subject" => 'Reminder $number',
            "template" => "first_custom"
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/emails', $data);


        $response->assertStatus(422);

    }

    public function test_cc_email_implementation()
    {
        $data = [
            'template' => 'email_template_invoice',
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id,
            'cc_email' => 'jj@gmail.com'
        ];

        $response = false;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/emails', $data);


        $response->assertStatus(200);

    }

    public function test_initial_email_send_emails()
    {
        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;

        $client_settings = $this->client->settings;
        $client_settings->email_style = 'dark';
        $this->client->settings = $client_settings;
        $this->client->save();

        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        Bus::fake();


        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);
                Bus::assertDispatched(EmailEntity::class);
            }
        });

    }

    public function testTemplateThemes()
    {
        $settings = $this->company->settings;
        $settings->email_style = 'light';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;
        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();

        Bus::fake();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);


                Bus::assertDispatched(EmailEntity::class);

            }
        });

        $settings = $this->company->settings;
        $settings->email_style = 'dark';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;

        $client_settings = $this->client->settings;
        $client_settings->email_style = 'dark';
        $this->client->settings = $client_settings;
        $this->client->save();

        $this->invoice->setRelation('client', $this->client);
        $this->invoice->save();
        Bus::fake();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);


                Bus::assertDispatched(EmailEntity::class);

            }
        });

        $settings = $this->company->settings;
        $settings->email_style = 'plain';

        $this->company->settings = $settings;
        $this->company->save();

        $this->invoice->date = now();
        $this->invoice->due_date = now()->addDays(7);
        $this->invoice->number = $this->getNextInvoiceNumber($this->client, $this->invoice);

        $this->invoice->client_id = $this->client->id;
        $this->invoice->setRelation('client', $this->client);

        $this->invoice->save();
        Bus::fake();

        $this->invoice->invitations->each(function ($invitation) {
            if ($invitation->contact->send_email && $invitation->contact->email) {
                EmailEntity::dispatch($invitation, $invitation->company);


                Bus::assertDispatched(EmailEntity::class);

            }
        });

        $this->assertTrue(true);
    }
}

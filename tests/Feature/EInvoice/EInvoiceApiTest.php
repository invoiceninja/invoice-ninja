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

namespace Tests\Feature\EInvoice;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class EInvoiceApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class
        );
    }

    public function testRetrySendRoute()
    {
        $data = [
            'entity' => 'invoice',
            'entity_id' => $this->invoice->hashed_id
        ];
        
        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/peppol/send', $data);

        $response->assertStatus(200);

    }

    public function testValidationOnRoutes()
    {
                
        $data = [
            'entity' => 'invoiceBLAH',
            'entity_id' => $this->invoice->hashed_id
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/peppol/send', $data);

        $response->assertStatus(422);

    }

    public function testValidationOnRoutes2()
    {
                
        $data = [
            'entity' => 'invoice',
            'entity_id' => 'ddf8hjdfh8'
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->postJson('/api/v1/einvoice/peppol/send', $data);

        $response->assertStatus(422);

    }
}
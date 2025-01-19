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
class PeppolApiTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        if (!config('ninja.storecove_api_key')) {
            $this->markTestSkipped('Storecove API key not set');
        }

        $this->makeTestData();

        $this->withoutMiddleware(
            ThrottleRequests::class,
        );
    }

    public function testGeneratingToken()
    {
        if (! class_exists(\Modules\Admin\Http\Controllers\EInvoiceTokenController::class)) {
            $this->markTestSkipped('Admin module not installed');
        }

        config(['ninja.environment' => 'selfhost']);

        /**
         * @var \App\Models\CompanyUser $user
         */
        $user = $this->user;

        $current = $user->account->e_invoicing_token;

        $this->assertNull($current);

        $this
            ->withHeaders([
                'X-API-TOKEN' => $this->token,
            ])
            ->post('/api/v1/einvoice/token/update')
            ->assertSuccessful()
        ;

        $user->refresh();

        $this->assertNotEquals($current, $user->account->e_invoicing_token);
    }

    public function testHealthCheck()
    {
        if (! class_exists(\Modules\Admin\Http\Controllers\EInvoiceTokenController::class)) {
            $this->markTestSkipped('Admin module not installed');
        }

        config(['ninja.environment' => 'selfhost']);

        $this
            ->withHeaders([
                'X-API-TOKEN' => $this->token,
            ])
            ->get('/api/v1/einvoice/health_check')
            ->assertStatus(status: 422)
        ;

        $this
            ->withHeaders([
                'X-API-TOKEN' => $this->token,
            ])
            ->post('/api/v1/einvoice/token/update')
            ->assertSuccessful()
        ;

        $this
            ->withHeaders([
                'X-API-TOKEN' => $this->token,
            ])
            ->get('/api/v1/einvoice/health_check')
            ->assertSuccessful()
        ;
    }
}

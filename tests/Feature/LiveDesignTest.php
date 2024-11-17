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

use App\Models\Design;
use App\Models\InvoiceInvitation;
use App\Utils\HtmlEngine;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\PreviewController
 */
class LiveDesignTest extends TestCase
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

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }
    }

    public function testSyntheticInvitations()
    {
        $this->assertGreaterThanOrEqual(1, $this->client->contacts->count());

        $ii = InvoiceInvitation::factory()
        ->for($this->invoice)
        ->for($this->client->contacts->first(), 'contact')
        ->for($this->company)
        ->for($this->user)
        ->make();

        $this->assertInstanceOf(InvoiceInvitation::class, $ii);

        $engine = new HtmlEngine($ii);

        $this->assertNotNull($engine);

        $data = $engine->generateLabelsAndValues();

        $this->assertIsArray($data);

    }

    public function testDesignRoute200()
    {
        $data = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array)$this->company->settings,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/live_design/', $data);

        $response->assertStatus(200);
    }

    public function testDesignWithCustomDesign()
    {

        $d = Design::find(1);


        $data = [
            'entity_type' => 'invoice',
            'settings_type' => 'company',
            'settings' => (array)$this->company->settings,
            'design' => (array)$d->design,
        ];

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/live_design/', $data);

        $response->assertStatus(200);

    }
}

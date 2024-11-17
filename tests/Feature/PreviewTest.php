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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 *  App\Http\Controllers\PreviewController
 */
class PreviewTest extends TestCase
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

    public function testPreviewRoute()
    {
        $data = $this->getData();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/preview/', $data);

        $response->assertStatus(200);
    }

    public function testPurchaseOrderPreviewRoute()
    {
        $data = $this->getData();

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/preview/purchase_order', $data);

        $response->assertStatus(200);
    }

    public function testPurchaseOrderPreviewHtmlResponse()
    {
        $data = $this->getData();

        $response = $this->withHeaders([
                'X-API-SECRET' => config('ninja.api_secret'),
                'X-API-TOKEN' => $this->token,
            ])->post('/api/v1/preview/purchase_order?html=true', $data);

        $response->assertStatus(200);
    }


    public function testPreviewHtmlResponse()
    {
        $data = $this->getData();

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/preview?html=true', $data);

        $response->assertStatus(200);
    }

    private function getData()
    {
        $data =
            [
                'entity_type' => 'invoice',
                'entity_id' => '',
                'design' => [
                    'name' => '',
                    'design' => [
                        'includes' => '</style>',
                        'header' => '<div id="header"></div>',
                        'body' => '<div id="body">',
                        'product' => '',
                        'task' => '',
                        'footer' => '<div id="footer">$entity_footer</div>',
                    ],
                ],
                'is_custom' => 1,
            ];

        return $data;
    }
}

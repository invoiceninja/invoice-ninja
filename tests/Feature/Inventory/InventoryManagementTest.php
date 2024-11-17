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

namespace Tests\Feature\Inventory;

use App\DataMapper\InvoiceItem;
use App\Models\Invoice;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Str;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class InventoryManagementTest extends TestCase
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

    public function testInventoryMovements()
    {
        $product = Product::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'in_stock_quantity' => 100,
            'stock_notification' => true,
            'stock_notification_threshold' => 99,
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $this->user->id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $invoice->company->track_inventory = true;
        $invoice->push();

        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = 1;
        $invoice_item->product_key = $product->product_key;
        $invoice_item->notes = $product->notes;
        $invoice_item->quantity = 10;
        $invoice_item->cost = 100;

        $line_items[] = $invoice_item;
        $invoice->line_items = $line_items;
        $invoice->number = Str::random(16);

        $invoice->client_id = $this->client->hashed_id;

        $invoice_array = $invoice->toArray();
        $invoice_array['client_id'] = $this->client->hashed_id;

        $response = $this->withHeaders([
            'X-API-SECRET' => config('ninja.api_secret'),
            'X-API-TOKEN' => $this->token,
        ])->post('/api/v1/invoices/', $invoice_array)
        ->assertStatus(200);

        $product = $product->fresh();

        $this->assertEquals(90, $product->in_stock_quantity);

        $data = $response->json();

        $invoice = Invoice::find($this->decodePrimaryKey($data['data']['id']));

        $invoice->service()->markDeleted()->save();
        $invoice->is_deleted = true;
        $invoice->save();

        $this->assertEquals(100, $product->fresh()->in_stock_quantity);

        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($data['data']['id']));

        $invoice->service()->handleRestore()->save();

        $this->assertEquals(90, $product->fresh()->in_stock_quantity);
    }
}

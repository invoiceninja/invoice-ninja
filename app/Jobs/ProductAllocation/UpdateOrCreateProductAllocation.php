<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\ProductAllocation;

use App\Libraries\MultiDB;
use App\Models\Product;
use App\Models\ProductAllocation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class UpdateOrCreateProductAllocation implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $line_items;

    public $invoice;

    public $company;

    public $deleteWhenMissingModels = true;

    /**
     * Create a new job instance.
     *
     * @param $line_items
     * @param $invoice
     * @param $company
     */
    public function __construct($line_items, $invoice, $company)
    {
        $this->line_items = $line_items;

        $this->invoice = $invoice;

        $this->company = $company;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDB($this->company->db);

        if (strval($this->invoice->client->getSetting('currency_id')) != strval($this->company->settings->currency_id)) {
            return;
        }

        //only update / create products + allocations - not tasks or gateway fees
        $updateable_products = collect($this->line_items)->filter(function ($item) {
            return $item->type_id == 1;
        });

        $product_ids = [];

        /** @var \App\DataMapper\InvoiceItem $item */
        foreach ($updateable_products as $item) {
            if (empty($item->product_key) || (isset($item->product_allocation_ids) && count($item->product_allocation_ids) > 0)) {
                continue;
            }

            $product = Product::withTrashed()->firstOrNew(['product_key' => $item->product_key, 'company_id' => $this->invoice->company->id]);

            $productAllocation = ProductAllocation::withTrashed()->firstOrNew([
                'product_id' => $product->id,
                'company_id' => $this->invoice->company->id,
                'invoice_id' => $this->invoice->id,
                'invoice_aggregation_key' => 'invoice-product-mapper',
            ]);
            $productAllocation->recurring_id = $this->invoice->recurring_id ?? null;
            $productAllocation->project_id = $this->invoice->project_id ?? null;
            $productAllocation->subscription_id = $this->invoice->subscription_id ?? null;

            // aggregate quantity of all items with same product_key & none linked product_allocation_ids
            $productAllocation->quantity = $updateable_products->filter(function ($i) use ($item) {
                return $i->product_key == $item->product_key && !(isset($item->product_allocation_ids) && count($item->product_allocation_ids) > 0);
            })->sum('quantity');

            // skip, when not required
            if ($productAllocation->quantity == 0) {
                continue;
            }

            // add to array of valid product_ids of mappers for this invoice
            $product_ids[] = $product->id;

            // save
            $product->saveQuietly();
            $productAllocation->saveQuietly();

        }

        // remove all invalid mappers
        $productAllocation = ProductAllocation::withTrashed()
            ->whereNotIn('product_id', $product_ids)
            ->where('company_id', $this->invoice->company->id)
            ->where('invoice_id', $this->invoice->id)
            ->where('invoice_aggregation_key', 'invoice-product-mapper')
            ->delete();
    }

    public function failed($exception = null)
    {
        info('update create failed with = ');
        nlog($exception->getMessage());
    }
}

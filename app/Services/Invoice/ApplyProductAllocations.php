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

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;

class ApplyProductAllocations extends AbstractService
{
    use GeneratesCounter;

    /**
     * Summary of __construct
     * @param \App\Models\Invoice $invoice
     * @param \App\Models\ProductAllocation[] $product_allocations
     */
    public function __construct(private Invoice $invoice, private array $product_allocations)
    {
    }

    public function run()
    {
        /*Don't double pay*/
        if ($this->invoice->status_id != Invoice::STATUS_DRAFT) { // TODO: @turbo124 how to check for the draft status depending on the user/company config
            throw new \Exception('Invoice is not in draft status.');
        }

        // filter to only use unapplied product allocations
        $invoice = $this->invoice;
        $items = array_filter($this->product_allocations, function ($product_allocation) use ($invoice) {
            // Loop through $items and check if the 'id' exists in any item's 'product_allocation_ids'
            foreach ($invoice->line_items as $item) {
                if (in_array($product_allocation['id'], explode(',', $item['product_allocation_ids']))) {
                    return false; // Filter out this property
                }
            }
            return true; // Keep this property
        });

        // aggregate to reduce invoice rows
        /**
         * @var \App\Models\ProductAllocation[] $aggregatedItems
         */
        $aggregatedItems = [];
        foreach ($items as $item) {
            // Create a unique key based on the properties you want to group by
            $key = $item->product()->product_key . '|' . $item->invoice_aggregation_key;

            if (isset($aggregatedItems[$key])) {
                $aggregatedItems[$key]['quantity'] += $item->quantity;
                $aggregatedItems[$key]['product_allocation_ids'][] = $item->id;
                $aggregatedItems[$key]['public_notes'] = array_unique(
                    array_merge($aggregatedItems[$key]['public_notes'], [$item['public_notes']])
                );
            } else {
                $aggregatedItems[$key] = $item;
            }
        }
        $aggregatedItems = array_values($aggregatedItems);

        // apply to line_items
        foreach ($aggregatedItems as $aggregatedItem) {
            $item = new InvoiceItem();
            $item->quantity = $aggregatedItem['quantity'];
            $item->cost = $aggregatedItem->product()->cost;
            $item->product_key = $aggregatedItem->product()->product_key;
            $item->line_total = round($item->cost * $item->quantity, 2);
            $item->notes = $aggregatedItem['public_notes'];
            $item->custom_value1 = $aggregatedItem->custom_value1;
            $item->custom_value2 = $aggregatedItem->custom_value2;
            $item->custom_value3 = $aggregatedItem->custom_value3;
            $item->custom_value4 = $aggregatedItem->custom_value4;
            $item->product_allocation_ids = $aggregatedItem['product_allocation_ids'];
            $item->type_id = '1';

            $this->invoice->line_items[] = $item;
        }

        $this->invoice->saveQuietly();

        return $this->invoice;
    }
}

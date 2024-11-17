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

namespace App\Services\PurchaseOrder;

use App\Models\Product;
use App\Models\PurchaseOrder;

class PurchaseOrderInventory
{
    public function __construct(private PurchaseOrder $purchase_order)
    {
    }

    public function run()
    {
        $line_items = $this->purchase_order->line_items;

        foreach ($line_items as $item) {
            $p = Product::query()->where('product_key', $item->product_key)->where('company_id', $this->purchase_order->company_id)->first();

            if (!$p) {
                $p = new Product();
                $p->user_id = $this->purchase_order->user_id;
                $p->company_id = $this->purchase_order->company_id;
                $p->project_id = $this->purchase_order->project_id;
                $p->vendor_id = $this->purchase_order->vendor_id;
                $p->product_key = $item->product_key;
                $p->notes = $item->notes ?? '';
                $p->cost = $item->cost ?? 0;
                $p->quantity = $item->quantity ?? 0;
                $p->custom_value1 = $item->custom_value1 ?? '';
                $p->custom_value2 = $item->custom_value2 ?? '';
                $p->custom_value3 = $item->custom_value3 ?? '';
                $p->custom_value4 = $item->custom_value4 ?? '';
            }

            $p->in_stock_quantity += $item->quantity;
            $p->saveQuietly();
        }

        $this->purchase_order->status_id = PurchaseOrder::STATUS_RECEIVED;
        $this->purchase_order->save();

        return $this->purchase_order;
    }
}

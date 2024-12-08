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

namespace App\Export\Decorators;

use App\Models\PurchaseOrder;

class PurchaseOrderDecorator extends Decorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $purchase_order = false;

        if ($entity instanceof PurchaseOrder) {
            $purchase_order = $entity;
        } elseif ($entity->purchase_order) {
            $purchase_order = $entity->purchase_order;
        }

        if ($purchase_order && method_exists($this, $key)) {
            return $this->{$key}($purchase_order);
        } elseif ($purchase_order->{$key} ?? false) {
            return $purchase_order->{$key} ?? '';
        }

        return '';

    }

    public function status(PurchaseOrder $purchase_order)
    {
        return $purchase_order->stringStatus($purchase_order->status_id);
    }

    public function currency_id(PurchaseOrder $purchase_order)
    {
        return $purchase_order->currency ? $purchase_order->currency->code : $purchase_order->company->currency()->code;
    }

}

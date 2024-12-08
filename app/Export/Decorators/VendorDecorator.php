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

use App\Models\Vendor;

class VendorDecorator extends Decorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $vendor = false;

        if ($entity instanceof Vendor) {
            $vendor = $entity;
        } elseif ($entity->vendor) {
            $vendor = $entity->vendor;
        }

        if ($vendor && method_exists($this, $key)) {
            return $this->{$key}($vendor);
        } elseif ($vendor->{$key} ?? false) {
            return $vendor->{$key} ?? '';
        }

        return '';

    }

    public function country_id(Vendor $vendor)
    {
        return $vendor->country ? $vendor->country->name : '';
    }

    public function name(Vendor $vendor)
    {
        return $vendor->present()->name();
    }

    public function currency(Vendor $vendor)
    {
        return $vendor->currency_id ? $vendor->currency()->code : $vendor->company->currency()->code;
    }

    public function classification(Vendor $vendor)
    {
        ctrans("texts.{$vendor->classification}") ?? '';
    }

    public function status(Vendor $vendor)
    {
        if ($vendor->is_deleted) {
            return ctrans('texts.deleted');
        }

        if ($vendor->deleted_at) {
            return ctrans('texts.archived');
        }

        return ctrans('texts.active');
    }

}

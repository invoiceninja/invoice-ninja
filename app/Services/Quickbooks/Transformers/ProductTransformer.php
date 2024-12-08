<?php

/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

/**
 * Class ProductTransformer.
 */
class ProductTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb()
    {

    }

    public function transform(mixed $data): array
    {

        return [
            'id' => data_get($data, 'Id.value', null),
            'product_key' => data_get($data, 'Name', data_get($data, 'FullyQualifiedName', '')),
            'notes' => data_get($data, 'Description', ''),
            'cost' => data_get($data, 'PurchaseCost', 0),
            'price' => data_get($data, 'UnitPrice', 0),
            'in_stock_quantity' => data_get($data, 'QtyOnHand', 0),
        ];

    }

}

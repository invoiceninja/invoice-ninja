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

namespace App\Import\Transformer\Csv;

use App\Import\Transformer\BaseTransformer;

/**
 * Class ProductTransformer.
 */
class ProductTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return array
     */
    public function transform($data)
    {
        return [
            'company_id' => $this->company->id,
            'product_key' => $this->getString($data, 'product.product_key'),
            'notes' => $this->getString($data, 'product.notes'),
            'cost' => $this->getFloat($data, 'product.cost'),
            'price' => $this->getFloat($data, 'product.price'),
            'quantity' => $this->getFloat($data, 'product.quantity'),
            'tax_name1' => $this->getString($data, 'product.tax_name1'),
            'tax_rate1' => $this->getFloat($data, 'product.tax_rate1'),
            'tax_name2' => $this->getString($data, 'product.tax_name2'),
            'tax_rate2' => $this->getFloat($data, 'product.tax_rate2'),
            'tax_name3' => $this->getString($data, 'product.tax_name3'),
            'tax_rate3' => $this->getFloat($data, 'product.tax_rate3'),
            'custom_value1' => $this->getString($data, 'product.custom_value1'),
            'custom_value2' => $this->getString($data, 'product.custom_value2'),
            'custom_value3' => $this->getString($data, 'product.custom_value3'),
            'custom_value4' => $this->getString($data, 'product.custom_value4'),
            'product_image' => $this->getString($data, 'product.image_url'),
            'in_stock_quantity' => $this->getFloat($data, 'product.in_stock_quantity'),
            'tax_id' => $this->getNumber($data, 'product.tax_category', 1)
        ];
    }
}

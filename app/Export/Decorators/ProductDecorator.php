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

use App\Models\Product;

class ProductDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $product = false;

        if ($entity instanceof Product) {
            $product = $entity;
        } elseif ($entity->product) {
            $product = $entity->product;
        }

        if ($product && method_exists($this, $key)) {
            return $this->{$key}($product);
        } elseif ($product->{$key} ?? false) {
            return $product->{$key} ?? '';
        }

        return '';

    }

    /*
        public const PRODUCT_TYPE_PHYSICAL = 1;
        public const PRODUCT_TYPE_SERVICE = 2;
        public const PRODUCT_TYPE_DIGITAL = 3;
        public const PRODUCT_TYPE_SHIPPING = 4;
        public const PRODUCT_TYPE_EXEMPT = 5;
        public const PRODUCT_TYPE_REDUCED_TAX = 6;
        public const PRODUCT_TYPE_OVERRIDE_TAX = 7;
        public const PRODUCT_TYPE_ZERO_RATED = 8;
        public const PRODUCT_TYPE_REVERSE_TAX = 9;
    */
    public function tax_category(Product $product)
    {

        $category = ctrans('texts.physical_goods');

        match($product->tax_id) {
            1 => $category =  ctrans('texts.physical_goods'),
            2 => $category = ctrans('texts.services'),
            3 => $category =  ctrans('texts.digital_products'),
            4 => $category = ctrans('texts.shipping'),
            5 => $category =  ctrans('texts.tax_exempt'),
            6 => $category =  ctrans('texts.reduced_tax'),
            7 => $category =  ctrans('texts.override_tax'),
            8 => $category =  ctrans('texts.zero_rated'),
            9 => $category =  ctrans('texts.reverse_tax'),
            default => $category =  ctrans('texts.physical_goods'),
        };

        return $category;
    }

}

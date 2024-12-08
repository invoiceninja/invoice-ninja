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

namespace App\DataMapper\Tax\US;

use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\Models\Product;

/**
 * The rules apply US => US taxes using the tax calculator.
 *
 * US => Foreign taxes we check the product types still for exemptions, and we all back to the client country tax rate.
 */
class Rule extends BaseRule implements RuleInterface
{
    /** @var string $seller_region */
    public string $seller_region = 'US';

    /**
     * Initializes the rules and builds any required data.
     *
     * @return self
     */
    public function init(): self
    {
        $this->calculateRates();

        return $this;
    }

    /**
     * Override tax class, we use this when we do not modify the input taxes
     *
     * @param  mixed $item
     * @return self
     */
    public function override($item): self
    {

        $this->tax_rate1 = $item->tax_rate1;
        $this->tax_name1 = $item->tax_name1;
        $this->tax_rate2 = $item->tax_rate2;
        $this->tax_name2 = $item->tax_name2;
        $this->tax_rate3 = $item->tax_rate3;
        $this->tax_name3 = $item->tax_name3;

        return $this;

    }

    /**
     * Sets the correct tax rate based on the product type.
     *
     * @param  mixed $item
     * @return self
     */
    public function taxByType($item): self
    {

        match(intval($item->tax_id)) {
            Product::PRODUCT_TYPE_EXEMPT => $this->taxExempt($item),
            Product::PRODUCT_TYPE_DIGITAL => $this->taxDigital($item),
            Product::PRODUCT_TYPE_SERVICE => $this->taxService($item),
            Product::PRODUCT_TYPE_SHIPPING => $this->taxShipping($item),
            Product::PRODUCT_TYPE_PHYSICAL => $this->taxPhysical($item),
            Product::PRODUCT_TYPE_REDUCED_TAX => $this->taxReduced($item),
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $this->override($item),
            Product::PRODUCT_TYPE_ZERO_RATED => $this->zeroRated($item),
            default => $this->default($item),
        };

        return $this;
    }

    /**
     * Sets the tax as exempt (0)
     * @param  mixed $item
     *
     * @return self
     */
    public function taxExempt($item): self
    {
        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

    /**
     * Calculates the tax rate for a digital product
     * @param  mixed $item
     *
     * @return self
     */
    public function taxDigital($item): self
    {
        $this->default($item);

        return $this;
    }

    /**
     * Calculates the tax rate for a service product
     * @param  mixed $item
     *
     * @return self
     */
    public function taxService($item): self
    {
        if (in_array($this->tax_data?->txbService, ['Y','L'])) {
            $this->default($item);
        } else {
            $this->taxExempt($item);
        }

        return $this;
    }

    /**
     * Calculates the tax rate for a shipping product
     * @param  mixed $item
     *
     * @return self
     */
    public function taxShipping($item): self
    {
        if ($this->tax_data?->txbFreight == 'Y') {
            return $this->default($item);
        }

        $this->tax_rate1 = 0;
        $this->tax_name1 = '';

        return $this;
    }

    /**
     * Calculates the tax rate for a physical product
     * @param  mixed $item
     *
     * @return self
     */
    public function taxPhysical($item): self
    {
        $this->default($item);

        return $this;
    }

    /**
     * Calculates the tax rate for an undefined product uses the default tax rate for the client county
     *
     * @return self
     */
    public function default($item): self
    {

        if ($this->tax_data?->stateSalesTax == 0) {

            $this->tax_rate1 = 0;
            $this->tax_name1 = '';

            return $this;
        }

        $this->tax_rate1 = $this->tax_data->taxSales * 100;
        $this->tax_name1 = "Sales Tax";

        return $this;
    }

    public function zeroRated($item): self
    {

        $this->tax_rate1 = 0;
        $this->tax_name1 = "{$this->tax_data->geoState} Zero Rated Tax";

        return $this;

    }

    /**
     * Calculates the tax rate for a reduced tax product
     *
     * @return self
     */
    public function taxReduced($item): self
    {
        $this->default($item);

        return $this;
    }

    /**
     * Calculates the tax rate for a reverse tax product
     *
     * @return self
     */
    public function reverseTax($item): self
    {
        $this->default($item);

        return $this;
    }

    /**
     * Calculates the tax rates to be applied
     *
     * @return self
     */
    public function calculateRates(): self
    {
        return $this;
    }

}

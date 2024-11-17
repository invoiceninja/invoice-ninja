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

namespace App\DataMapper\Tax\AU;

use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\Models\Product;

class Rule extends BaseRule implements RuleInterface
{
    /** @var string $seller_region */
    public string $seller_region = 'AU';

    /** @var bool $consumer_tax_exempt */
    public bool $consumer_tax_exempt = false;

    /** @var bool $business_tax_exempt */
    public bool $business_tax_exempt = false;

    /** @var bool $eu_business_tax_exempt */
    public bool $eu_business_tax_exempt = true;

    /** @var bool $foreign_business_tax_exempt */
    public bool $foreign_business_tax_exempt = false;

    /** @var bool $foreign_consumer_tax_exempt */
    public bool $foreign_consumer_tax_exempt = false;

    /** @var float $tax_rate */
    public float $tax_rate = 0;

    /** @var float $reduced_tax_rate */
    public float $reduced_tax_rate = 0;

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
     * Sets the correct tax rate based on the product type.
     *
     * @param  mixed $item
     * @return self
     */
    public function taxByType($item): self
    {

        if ($this->client->is_tax_exempt || !property_exists($item, 'tax_id')) {
            return $this->taxExempt($item);
        }

        match(intval($item->tax_id)) {
            Product::PRODUCT_TYPE_EXEMPT => $this->taxExempt($item),
            Product::PRODUCT_TYPE_DIGITAL => $this->taxDigital($item),
            Product::PRODUCT_TYPE_SERVICE => $this->taxService($item),
            Product::PRODUCT_TYPE_SHIPPING => $this->taxShipping($item),
            Product::PRODUCT_TYPE_PHYSICAL => $this->taxPhysical($item),
            Product::PRODUCT_TYPE_REDUCED_TAX => $this->taxReduced($item),
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $this->override($item),
            Product::PRODUCT_TYPE_ZERO_RATED => $this->zeroRated($item),
            Product::PRODUCT_TYPE_REVERSE_TAX => $this->reverseTax($item),
            default => $this->default($item),
        };

        return $this;
    }

    /**
     * Calculates the tax rate for a reduced tax product
     *
     * @return self
     */
    public function reverseTax($item): self
    {
        $this->tax_rate1 = 10;
        $this->tax_name1 = 'GST';

        return $this;
    }

    /**
     * Calculates the tax rate for a reduced tax product
     *
     * @return self
     */
    public function taxReduced($item): self
    {
        $this->tax_rate1 = $this->reduced_tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    /**
     * Calculates the tax rate for a zero rated tax product
     *
     * @return self
     */
    public function zeroRated($item): self
    {
        $this->tax_rate1 = 0;
        $this->tax_name1 = 'GST';

        return $this;
    }


    /**
     * Calculates the tax rate for a tax exempt product
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
     *
     * @return self
     */
    public function taxDigital($item): self
    {

        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    /**
     * Calculates the tax rate for a service product
     *
     * @return self
     */
    public function taxService($item): self
    {

        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    /**
     * Calculates the tax rate for a shipping product
     *
     * @return self
     */
    public function taxShipping($item): self
    {

        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    /**
     * Calculates the tax rate for a physical product
     *
     * @return self
     */
    public function taxPhysical($item): self
    {

        $this->tax_rate1 = $this->tax_rate;
        $this->tax_name1 = 'GST';

        return $this;
    }

    /**
     * Calculates the tax rate for a default product
     *
     * @return self
     */
    public function default($item): self
    {

        $this->tax_name1 = '';
        $this->tax_rate1 = 0;

        return $this;
    }

    /**
     * Calculates the tax rate for an override product
     *
     * @return self
     */
    public function override($item): self
    {
        return $this;
    }

    /**
     * Calculates the tax rates based on the client's location.
     *
     * @return self
     */
    public function calculateRates(): self
    {
        if ($this->client->is_tax_exempt) {

            $this->tax_rate = 0;
            $this->reduced_tax_rate = 0;

            return $this;
        }

        $this->tax_rate = $this->client->company->tax_data->regions->AU->subregions->{$this->client->company->country()->iso_3166_2}->tax_rate ?? 0;
        $this->reduced_tax_rate = $this->client->company->tax_data->regions->AU->subregions->{$this->client->company->country()->iso_3166_2}->tax_rate ?? 0;

        return $this;

    }

}

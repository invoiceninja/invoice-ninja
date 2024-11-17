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

namespace App\DataMapper\Tax\GB;

use App\DataMapper\Tax\BaseRule;
use App\DataMapper\Tax\RuleInterface;
use App\Models\Product;

class Rule extends BaseRule implements RuleInterface
{
    /** @var string $seller_region */
    public string $seller_region = 'UK';

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

    public string $tax_name1 = 'VAT';

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

        
        if ($this->client->is_tax_exempt || !property_exists($item, 'tax_id') || (isset($item->type_id) && $item->type_id == '5')) {
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
        $this->tax_rate1 = 0;
        $this->tax_name1 = 'VAT';

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
        $this->tax_name1 = 'VAT';

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
        $this->tax_name1 = 'VAT';

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
        $this->tax_name1 = 'VAT';

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
        $this->tax_name1 = 'VAT';

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
        $this->tax_name1 = 'VAT';

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
        $this->tax_name1 = 'VAT';

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
        
        $this->tax_rate1 = $item->tax_rate1;
        $this->tax_name1 = $item->tax_name1;
        $this->tax_rate2 = $item->tax_rate2;
        $this->tax_name2 = $item->tax_name2;
        $this->tax_rate3 = $item->tax_rate3;
        $this->tax_name3 = $item->tax_name3;

        return $this;
    }

    /**
    * Calculates the tax rates based on the client's location.
    *
    * Internal (UK) Sales:
    * - Standard rate: 20%
    * - Reduced rate: 5%
    * - Zero rate: 0% (still VAT registered but charge no VAT)
    * - Exempt: No VAT registration required
    *
    * External Sales:
    * 1. To EU Businesses (B2B):
    *    - Zero-rated (0%)
    *    - Reverse charge applies (customer pays VAT in their country)
    *    - Must validate EU VAT number
    *    - Must report in EC Sales List
    *
    * 2. To EU Consumers (B2C):
    *    - Charge UK VAT rate (20%)
    *    - Unless distance selling threshold exceeded in destination country
    *    - Then must register for VAT in that country
    *
    * 3. To Non-EU (Rest of World):
    *    - Zero-rated (0%)
    *    - Export documentation required
    *
    * Special Cases:
    * - Northern Ireland (GB-NIR): Follows EU VAT rules for goods but UK rules for services
    * - Channel Islands: Outside UK & EU VAT area
    * - Digital Services: Special rules apply (check MOSS registration)
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

        // GB => GB sales
        if($this->client_subregion == 'GB') {

            $this->tax_name = $this->client->company->tax_data->regions->UK->subregions->GB->tax_name;
            $this->tax_rate = $this->client->company->tax_data->regions->UK->subregions->GB->tax_rate ?? 0;

            return $this;
        }
        
        $is_over_threshold = $this->client->company->tax_data->regions->EU->has_sales_above_threshold ?? false;

        //GB => EU sales - Reverse Charge
        if (in_array($this->client_subregion, $this->eu_country_codes) && !in_array($this->client->classification, ['','individual'])) {

            $this->tax_name = 'VAT';
            $this->tax_rate = 0;

            return $this;
        }
        elseif(in_array($this->client_subregion, $this->eu_country_codes) && $is_over_threshold) {
            
            $this->tax_name = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->tax_name;
            $this->tax_rate = $this->client->company->tax_data->regions->EU->subregions->{$this->client->country->iso_3166_2}->tax_rate ?? 0;
            
            return $this;
        }
        
        // must be tax exempt at this point

            $this->tax_name = 'VAT';
            $this->tax_rate = 0;
        
        return $this;

    }

}

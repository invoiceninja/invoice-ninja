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

use App\Models\Client;
use App\Models\Vendor;
use App\Models\Company;

/**
 * Class BaseTransformer.
 */
class BaseTransformer
{
    public function __construct(public Company $company)
    {
    }

    public function resolveCountry(string $iso_3_code): string
    {
        /** @var \App\Models\Country $country */
        $country = app('countries')->first(function ($c) use ($iso_3_code) {

            /** @var \App\Models\Country $c */
            return $c->iso_3166_3 == $iso_3_code || $c->name == $iso_3_code;
        });

        return $country ? (string) $country->id : $this->company->settings->country_id;
    }

    public function resolveCurrency(string $currency_code): string
    {

        /** @var \App\Models\Currency $currency */
        $currency = app('currencies')->first(function ($c) use ($currency_code) {

            /** @var \App\Models\Currency $c */
            return $c->code == $currency_code;
        });

        return $currency ? (string) $currency->id : $this->company->settings->currency_id;
    }

    public function getShipAddrCountry($data, $field)
    {
        return is_null(($c = $this->getString($data, $field))) ? null : $this->getCountryId($c);
    }

    public function getBillAddrCountry($data, $field)
    {
        return is_null(($c = $this->getString($data, $field))) ? null : $this->getCountryId($c);
    }

    public function getClientId($customer_reference_id): ?int
    {
        $client = Client::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    // ->where('number', $customer_reference_id)
                    ->where('sync->qb_id', $customer_reference_id)
                    ->first();

        return $client ? $client->id : null;
    }

    public function getVendorId($customer_reference_id): ?int
    {
        $vendor = Vendor::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('number', $customer_reference_id)
                    ->first();

        return $vendor ? $vendor->id : null;
    }
}

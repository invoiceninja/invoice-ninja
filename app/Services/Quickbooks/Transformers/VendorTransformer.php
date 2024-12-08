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
 * Class VendorTransformer.
 */
class VendorTransformer extends BaseTransformer
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

        nlog($data);
        $contact = [
                'first_name' => data_get($data, 'GivenName'),
                'last_name' => data_get($data, 'FamilyName'),
                'phone' => data_get($data, 'PrimaryPhone.FreeFormNumber'),
                'email' => data_get($data, 'PrimaryEmailAddr.Address'),
            ];

        $vendor = [
            'number' => data_get($data, 'Id.value'),
            'name' => data_get($data, 'DisplayName'),
            'address1' => data_get($data, 'BillAddr.Line1'),
            'address2' => data_get($data, 'BillAddr.Line2'),
            'city' => data_get($data, 'BillAddr.City'),
            'state' => data_get($data, 'BillAddr.CountrySubDivisionCode'),
            'postal_code' => data_get($data, 'BillAddr.PostalCode'),
            'country_id' => $this->resolveCountry(data_get($data, 'BillAddr.CountryCode', data_get($data, 'BillAddr.Country', ''))),
            'website' => data_get($data, 'WebAddr.URI'),
            'vat_number' => data_get($data, 'TaxIdentifier'),
            'currency_id' => $this->resolveCurrency(data_get($data, 'CurrencyRef.value', '')),
        ];


        $new_vendor_merge = [
            'vendor_hash' => data_get($data, 'V4IDPseudonym', \Illuminate\Support\Str::random(32)),
        ];


        return [$vendor, $contact, $new_vendor_merge];

    }

}

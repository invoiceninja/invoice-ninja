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

namespace App\Http\Requests\EInvoice\Peppol;

use App\Models\Country;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AddTaxIdentifierRequest extends FormRequest
{
    public static array $vat_regex_patterns = [
        'GB' => '/^GB(\d{9}|\d{12})$/',            // Great Britain
        'DE' => '/^DE\d{9}$/',                     // Germany
        'AT' => '/^ATU\d{8}$/',                    // Austria
        'BE' => '/^BE[0-1]\d{9}$/',                // Belgium
        'BG' => '/^BG\d{9,10}$/',                  // Bulgaria
        'CY' => '/^CY\d{8}[A-Z]$/',                // Cyprus
        'HR' => '/^HR\d{11}$/',                    // Croatia
        'DK' => '/^DK\d{8}$/',                     // Denmark
        'ES' => '/^ES[A-Z0-9]\d{7}[A-Z0-9]$/',     // Spain
        'EE' => '/^EE\d{9}$/',                     // Estonia
        'FI' => '/^FI\d{8}$/',                     // Finland
        'FR' => '/^FR[A-Z0-9]{2}\d{9}$/',          // France
        'EL' => '/^EL\d{9}$/',                     // Greece
        'HU' => '/^HU\d{8}$/',                     // Hungary
        'IE' => '/^IE\d{7}[A-WYZ][A-Z]?$/',        // Ireland
        'IT' => '/^IT\d{11}$/',                    // Italy
        'IS' => '/^IS\d{10}|IS[\dA-Z]{6}$/',       // Iceland
        'LV' => '/^LV\d{11}$/',                    // Latvia
        'LT' => '/^LT(\d{9}|\d{12})$/',            // Lithuania
        'LU' => '/^LU\d{8}$/',                     // Luxembourg
        'MT' => '/^MT\d{8}$/',                     // Malta
        'NL' => '/^NL\d{9}B\d{2}$/',               // Netherlands
        'NO' => '/^NO\d{9}MVA$/',                     // Norway
        'PL' => '/^PL\d{10}$/',                    // Poland
        'PT' => '/^PT\d{9}$/',                     // Portugal
        'CZ' => '/^CZ\d{8,10}$/',                  // Czech Republic
        'RO' => '/^RO\d{2,10}$/',                  // Romania
        'SK' => '/^SK\d{10}$/',                    // Slovakia
        'SI' => '/^SI\d{8}$/',                     // Slovenia
        'SE' => '/^SE\d{12}$/',                    // Sweden
    ];

    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (config('ninja.app_env') == 'local') {
            return true;
        }

        return $user->account->isPaid() && $user->isAdmin() && $user->company()->legal_entity_id != null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $user = auth()->user();
        $company = $user->company();

        return [
            'country' => ['required', 'bail', Rule::in(array_keys(self::$vat_regex_patterns)), function ($attribute, $value, $fail) use ($company) {
                if ($this->country_id == $company->country()->id) {
                    $fail(ctrans('texts.country_not_supported'));
                }
            }],
            'vat_number' => [
               'required',
               'string',
               'bail',
               function ($attribute, $value, $fail) use ($company) {
                   if ($this->country && isset(self::$vat_regex_patterns[$this->country])) {
                       if (!preg_match(self::$vat_regex_patterns[$this->country], $value)) {
                           $fail(ctrans('texts.invalid_vat_number'));
                       }
                   }
                   if ($company->settings->classification == 'individual') {
                       $fail("Individuals cannot register additional VAT numbers, only business entities");
                   }
               },
            ]
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['country'])) {
            $country = $this->country();
            $input['country'] = $country->iso_3166_2;
            $input['country_id'] = $country->id;
        }

        $this->replace($input);

    }

    public function country(): Country
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        return $countries->first(function ($c) {
            return $this->country == $c->id;
        });
    }


}

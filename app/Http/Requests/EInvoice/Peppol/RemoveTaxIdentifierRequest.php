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

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RemoveTaxIdentifierRequest extends FormRequest
{
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
        /** @var \App\Models\Company $company **/
        $company = auth()->user()->company();
        $tax_data = $company->tax_data;

        return [
            'country' => ['required', 'bail', Rule::in(array_keys(AddTaxIdentifierRequest::$vat_regex_patterns))],
            'vat_number' => ['required', function ($attribute, $value, $fail) use ($company, $tax_data) {
                if ($company->settings->classification == 'individual') {
                    $fail("Individuals cannot register additional VAT numbers, only business entities");
                }

                $country = $this->country;

                $vat = $this->input('region') === 'GB'
                    ? data_get($tax_data->regions->UK->subregions, "{$country}.vat_number")
                    : data_get($tax_data->regions->EU->subregions, "{$country}.vat_number");

                if ($vat === null) {
                    $fail('VAT number not found.');
                }
            }],
        ];
    }
}

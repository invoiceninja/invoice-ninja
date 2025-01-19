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
use Illuminate\Auth\Access\AuthorizationException;
use App\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;

class StoreEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /**
         * @var \App\Models\User
         */
        $user = auth()->user();

        if (config('ninja.app_env') == 'local') {
            return true;
        }

        return $user->account->isPaid() && $user->isAdmin() &&
            $user->company()->legal_entity_id === null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'party_name' => ['required', 'string'],
            'line1' => ['required', 'string'],
            'line2' => ['nullable', 'string'],
            'city' => ['required', 'string'],
            'country' => ['required', 'bail', Rule::in(array_keys(AddTaxIdentifierRequest::$vat_regex_patterns))],
            'zip' => ['required', 'string'],
            'county' => ['required', 'string'],
            'acts_as_receiver' => ['required', 'bool'],
            'acts_as_sender' => ['required', 'bool'],
            'tenant_id' => ['required'],
            'classification' => ['required', 'in:business,individual'],
            'vat_number' => [Rule::requiredIf(fn () => $this->input('classification') !== 'individual')],
            'id_number' => [Rule::requiredIf(fn () => $this->input('classification') === 'individual')],
        ];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            message: ctrans('texts.peppol_not_paid_message'),
        );
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['country'])) {
            $country = $this->country();
            $input['country'] = $country->iso_3166_2;
            $input['country_id'] = $country->id;
        }

        $input['acts_as_receiver'] = $input['acts_as_receiver'] ?? true;
        $input['acts_as_sender'] = $input['acts_as_sender'] ?? true;
        
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

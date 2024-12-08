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

namespace App\Http\Requests\ClientPortal;

use App\Utils\Ninja;
use App\Models\Account;
use App\Models\Company;
use App\Libraries\MultiDB;
use Illuminate\Validation\Rule;
use Illuminate\Foundation\Http\FormRequest;
use App\Http\ValidationRules\Turnstile\Turnstile;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        $rules = [];

        foreach ($this->company()->client_registration_fields as $field) {
            if ($field['visible'] ?? true) {
                $rules[$field['key']] = $field['required'] ? ['bail','required'] : ['sometimes'];
            }
        }

        foreach ($rules as $field => $properties) {
            if ($field === 'email') {
                $rules[$field] = array_merge($rules[$field], ['email:rfc,dns', 'max:191', Rule::unique('client_contacts')->where('company_id', $this->company()->id)]);
            }

            if ($field === 'current_password') {
                $rules[$field] = array_merge($rules[$field], ['string', 'min:6', 'confirmed']);
            }
        }

        if ($this->company()->settings->client_portal_terms || $this->company()->settings->client_portal_privacy_policy) {
            $rules['terms'] = ['required'];
        }

        $rules['cf-turnstile-response'] = ['sometimes', new Turnstile];

        return $rules;
    }

    public function company()
    {
        //this should be all we need, the rest SHOULD be redundant because of our Middleware
        if ($this->key) {
            return Company::query()->where('company_key', $this->key)->first();
        }

        if ($this->company_key) {
            return Company::query()->where('company_key', $this->company_key)->firstOrFail();
        }

        if (! $this->route()->parameter('company_key') && Ninja::isSelfHost()) {
            $company = Account::query()->first()->default_company;

            if (! $company->client_can_register) {
                abort(403, 'This page is restricted');
            }

            return $company;
        }

        if (Ninja::isHosted()) {
            $subdomain = explode('.', $this->getHost())[0];

            $query = [
                'subdomain' => $subdomain,
                'portal_mode' => 'subdomain',
            ];

            if ($company = MultiDB::findAndSetDbByDomain($query)) {
                return $company;
            }

            $query = [
                'portal_domain' => $this->getSchemeAndHttpHost(),
                'portal_mode' => 'domain',
            ];

            if ($company = MultiDB::findAndSetDbByDomain($query)) {
                return $company;
            }
        }

        abort(400, 'Register request not found.');
    }
}

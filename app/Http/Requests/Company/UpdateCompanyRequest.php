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

namespace App\Http\Requests\Company;

use App\Utils\Ninja;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;
use App\DataMapper\CompanySettings;
use App\Http\Requests\EInvoice\Peppol\AddTaxIdentifierRequest;
use App\Http\ValidationRules\ValidSettingsRule;
use App\Http\ValidationRules\Company\ValidSubdomain;
use App\Http\ValidationRules\Company\ValidExpenseMailbox;
use App\Http\ValidationRules\EInvoice\ValidCompanyScheme;

class UpdateCompanyRequest extends Request
{
    use MakesHash;

    private array $protected_input = [
        'client_portal_privacy_policy',
        'client_portal_terms',
        'portal_custom_footer',
        'portal_custom_css',
        'portal_custom_head'
    ];
    
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        return $user->can('edit', $this->company);
    }

    public function rules()
    {
        
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $input = $this->all();

        $rules = [];

        $rules['company_logo'] = 'mimes:jpeg,jpg,png,gif|max:10000'; // max 10000kb
        $rules['settings'] = new ValidSettingsRule();
        $rules['industry_id'] = 'integer|nullable';
        $rules['size_id'] = 'integer|nullable';
        $rules['country_id'] = 'integer|nullable';
        $rules['work_email'] = 'email|nullable';
        $rules['matomo_id'] = 'nullable|integer';
        $rules['e_invoice_certificate_passphrase'] = 'sometimes|nullable';
        $rules['e_invoice_certificate'] = 'sometimes|nullable|file|mimes:p12,pfx,pem,cer,crt,der,txt,p7b,spc,bin';

        $rules['smtp_host'] = 'sometimes|string|nullable';
        $rules['smtp_port'] = 'sometimes|integer|nullable';
        $rules['smtp_encryption'] = 'sometimes|string|nullable';
        $rules['smtp_local_domain'] = 'sometimes|string|nullable';
        // $rules['smtp_verify_peer'] = 'sometimes|string';

        $rules['e_invoice'] = ['sometimes', 'nullable', new ValidCompanyScheme()];

        if (isset($input['portal_mode']) && ($input['portal_mode'] == 'domain' || $input['portal_mode'] == 'iframe')) {
            $rules['portal_domain'] = 'bail|nullable|sometimes|url';
        }

        if (Ninja::isHosted()) {
            $rules['subdomain'] = ['nullable', 'regex:/^[a-zA-Z0-9-]+[a-zA-Z0-9]$/', new ValidSubdomain()];
        }

        $rules['expense_mailbox'] = ['sometimes','email', 'nullable', new ValidExpenseMailbox(), Rule::unique('companies')->ignore($this->company->id)];
        $rules['expense_mailbox_active'] = ['sometimes','boolean'];
        $rules['inbound_mailbox_allow_company_users'] = ['sometimes','boolean'];
        $rules['inbound_mailbox_allow_vendors'] = ['sometimes','boolean'];
        $rules['inbound_mailbox_allow_clients'] = ['sometimes','boolean'];
        $rules['inbound_mailbox_allow_unknown'] = ['sometimes','boolean'];
        $rules['inbound_mailbox_whitelist'] = ['sometimes', 'string', 'nullable', 'regex:/^[\w\-\.\+]+@([\w-]+\.)+[\w-]{2,4}(,[\w\-\.\+]+@([\w-]+\.)+[\w-]{2,4})*$/'];
        $rules['inbound_mailbox_blacklist'] = ['sometimes', 'string', 'nullable', 'regex:/^[\w\-\.\+]+@([\w-]+\.)+[\w-]{2,4}(,[\w\-\.\+]+@([\w-]+\.)+[\w-]{2,4})*$/'];

        $rules['settings.vat_number'] = [
                'nullable',
                'string',
                'bail',
                'sometimes',
                Rule::requiredIf(function () use ($user) {
                    return $this->input('settings.e_invoice_type') === 'PEPPOL' && $user->company()->settings->classification != 'individual';
                }),
                function ($attribute, $value, $fail) {
                    $country_code = $this->getCountryCode();
                    if ($country_code && isset(AddTaxIdentifierRequest::$vat_regex_patterns[$country_code]) && $this->input('settings.e_invoice_type') === 'PEPPOL') {
                        if (!preg_match(AddTaxIdentifierRequest::$vat_regex_patterns[$country_code], $value)) {
                            $fail(ctrans('texts.invalid_vat_number'));
                        }
                    }
                },
            ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['portal_domain']) && strlen($input['portal_domain']) > 1) {
            $input['portal_domain'] = $this->addScheme($input['portal_domain']);
            $input['portal_domain'] = rtrim(strtolower($input['portal_domain']), "/");
        }

        // /** Disabled on the hosted platform */
        // if (isset($input['expense_mailbox']) && Ninja::isHosted() && !($this->company->account->isPaid() && $this->company->account->plan == 'enterprise')) {
        //     unset($input['expense_mailbox']);
        // }

        if (isset($input['settings'])) {
            $input['settings'] = (array) $this->filterSaveableSettings($input['settings']);
        }

        if (isset($input['subdomain']) && $this->company->subdomain == $input['subdomain']) {
            unset($input['subdomain']);
        }

        if (isset($input['e_invoice_certificate_passphrase']) && empty($input['e_invoice_certificate_passphrase'])) {
            unset($input['e_invoice_certificate_passphrase']);
        }

        if (isset($input['smtp_username']) && strlen(str_replace("*", "", $input['smtp_username'])) < 2) {
            unset($input['smtp_username']);
        }

        if (isset($input['smtp_password']) && strlen(str_replace("*", "", $input['smtp_password'])) < 2) {
            unset($input['smtp_password']);
        }

        if (isset($input['smtp_port'])) {
            $input['smtp_port'] = (int) $input['smtp_port'];
        }

        if (isset($input['smtp_verify_peer']) && is_string($input['smtp_verify_peer'])) {
            $input['smtp_verify_peer'] == 'true' ? true : false;
        }

        if (isset($input['e_invoice']) && is_array($input['e_invoice'])) {
            //ensure it is normalized first!
            $input['e_invoice'] = $this->company->filterNullsRecursive($input['e_invoice']);
        }

        $this->replace($input);
    }


    private function getCountryCode()
    {
        return auth()->user()->company()->country()->iso_3166_2;
    }

    /**
     * For the hosted platform, we restrict the feature settings.
     *
     * This method will trim the company settings object
     * down to the free plan setting properties which
     * are saveable
     *
     * @param  object $settings
     * @return \stdClass $settings
     */
    private function filterSaveableSettings($settings)
    {
        $account = $this->company->account;

        if (Ninja::isHosted()) {
            foreach ($this->protected_input as $protected_var) {
                $settings[$protected_var] = str_replace("script", "", $settings[$protected_var]);
            }
        }

        if (isset($settings['email_style_custom'])) {
            $settings['email_style_custom'] = str_replace(['{!!', '!!}', '{{', '}}', '@checked', '@dd', '@dump', '@if', '@if(', '@endif', '@isset', '@unless', '@auth', '@empty', '@guest', '@env', '@section', '@switch', '@foreach', '@while', '@include', '@each', '@once', '@push', '@use', '@forelse', '@verbatim', '<?php', '@php', '@for', '@class', '</sc', '<sc', 'html;base64', '@elseif', '@else', '@endunless', '@endisset', '@endempty', '@endauth', '@endguest', '@endproduction', '@endenv', '@hasSection', '@endhasSection', '@sectionMissing', '@endsectionMissing', '@endfor', '@endforeach', '@empty', '@endforelse', '@endwhile', '@continue', '@break', '@includeIf', '@includeWhen', '@includeUnless', '@includeFirst', '@component', '@endcomponent', '@endsection', '@yield', '@show', '@append', '@overwrite', '@stop', '@extends', '@endpush', '@stack', '@prepend', '@endprepend', '@slot', '@endslot', '@endphp', '@method', '@csrf', '@error', '@enderror', '@json', '@endverbatim', '@inject'], '', $settings['email_style_custom']);
        }

        if (isset($settings['company_logo']) && strlen($settings['company_logo'] ?? '') > 2) {
            $settings['company_logo'] = $this->forceScheme($settings['company_logo']);
        }

        if (!$account->isFreeHostedClient()) {
            return $settings;
        }

        $saveable_casts = CompanySettings::$free_plan_casts;

        foreach ($settings as $key => $value) {
            if (!array_key_exists($key, $saveable_casts)) {
                unset($settings->{$key});
            }
        }

        return $settings;
    }

    
    /**
     * forceScheme
     *
     * @param  string $url
     * @return string
     */
    private function forceScheme(string $url): string 
    {
        return stripos($url, 'http') !== false ? $url : "https://{$url}";
    }

    /**
     * addScheme
     *
     * @param  string $url
     * @param  string $scheme
     * @return string
     */
    private function addScheme(string $url, $scheme = 'https://'): string
    {
        if (Ninja::isHosted()) {
            $url = str_replace('http://', '', $url);
            $url = parse_url($url, PHP_URL_SCHEME) === null ? $scheme . $url : $url;
        }

        return rtrim($url, '/');
    }
}

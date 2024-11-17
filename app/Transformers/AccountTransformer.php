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

namespace App\Transformers;

use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

/**
 * Class AccountTransformer.
 */
class AccountTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected array $defaultIncludes = [
        //'default_company',
        //'user',
        //'company_users'
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'default_company',
        'company_users',
        'companies',
    ];

    /**
     * @param Account $account
     *
     *
     * @return array
     */
    public function transform(Account $account)
    {
        return [
            'id' => (string) $this->encodePrimaryKey($account->id),
            'key' => (string) $account->key,
            'default_url' => config('ninja.app_url'),
            'plan' => $account->getPlan(),
            'plan_term' => (string) $account->plan_term,
            'plan_started' => (string) $account->plan_started,
            'plan_paid' => (string) $account->plan_paid,
            'plan_expires' => (string) $account->plan_expires,
            'user_agent' => (string) $account->user_agent,
            'payment_id' => (string) $this->encodePrimaryKey($account->payment_id),
            'trial_started' => (string) $account->trial_started,
            'trial_plan' => (string) $account->trial_plan,
            'plan_price' => (float) $account->plan_price,
            'num_users' => (int) $account->num_users,
            'utm_source' => (string) $account->utm_source,
            'utm_medium' => (string) $account->utm_medium,
            'utm_content' => (string) $account->utm_content,
            'utm_term' => (string) $account->utm_term,
            'referral_code' => (string) $account->referral_code,
            'latest_version' => (string) trim($account->latest_version),
            'current_version' => (string) config('ninja.app_version'),
            'updated_at' => (int) $account->updated_at,
            'archived_at' => (int) $account->deleted_at,
            'report_errors' => (bool) $account->report_errors,
            'debug_enabled' => (bool) config('ninja.debug_enabled'),
            'is_docker' => (bool) config('ninja.is_docker'),
            'is_scheduler_running' => Ninja::isHosted() ? (bool) true : (bool) $account->is_scheduler_running, //force true for hosted 03/01/2022
            'default_company_id' => (string) $this->encodePrimaryKey($account->default_company_id),
            'disable_auto_update' => (bool) config('ninja.disable_auto_update'),
            'emails_sent' => (int) $account->emailsSent(),
            'email_quota' => (int) $account->getDailyEmailLimit(),
            'is_migrated' => (bool) $account->is_migrated,
            'hosted_client_count' => (int) $account->hosted_client_count,
            'hosted_company_count' => (int) $account->hosted_company_count,
            'is_hosted' => (bool) Ninja::isHosted(),
            'set_react_as_default_ap' => (bool) $account->set_react_as_default_ap,
            'trial_days_left' => Ninja::isHosted() ? (int) $account->getTrialDays() : 0,
            'account_sms_verified' => (bool) $account->account_sms_verified,
            'has_iap_plan' => (bool)$account->inapp_transaction_id,
            'tax_api_enabled' => (bool) config('services.tax.zip_tax.key') ? true : false,
            'nordigen_enabled' => (bool) (config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key')) ? true : false,
            'upload_extensions' => (string) "png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx,webp,xml,zip,csv,ods,odt,odp,".config('ninja.upload_extensions'),
            'e_invoice_quota' => (int) $account->e_invoice_quota,
        ];
    }

    public function includeCompanyUsers(Account $account)
    {
        $transformer = new CompanyUserTransformer($this->serializer);

        return $this->includeCollection($account->company_users, $transformer, CompanyUser::class);
    }

    public function includeDefaultCompany(Account $account)
    {
        $transformer = new CompanyTransformer($this->serializer);

        return $this->includeItem($account->default_company, $transformer, Company::class);
    }

    public function includeUser(Account $account)
    {
        $transformer = new UserTransformer($this->serializer);

        return $this->includeItem(auth()->user(), $transformer, User::class);
    }
}

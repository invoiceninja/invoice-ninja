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

namespace App\Models;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Ninja\EmailQuotaExceeded;
use App\Mail\Ninja\GmailTokenInvalid;
use App\Models\Presenters\AccountPresenter;
use App\Notifications\Ninja\EmailQuotaNotification;
use App\Notifications\Ninja\GmailCredentialNotification;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use DateTime;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Laracasts\Presenter\PresentableTrait;

/**
 * App\Models\Account
 *
 * @property int $id
 * @property int $email_quota
 * @property string|null $plan
 * @property string|null $plan_term
 * @property string|null $plan_started
 * @property string|null $plan_paid
 * @property string|null $plan_expires
 * @property string|null $user_agent
 * @property string|null $key
 * @property int|null $payment_id
 * @property int $default_company_id
 * @property string|null $trial_started
 * @property string|null $trial_plan
 * @property string|null $plan_price
 * @property int $num_users
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property string $latest_version
 * @property int $report_errors
 * @property string|null $referral_code
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property bool $is_scheduler_running
 * @property int|null $trial_duration
 * @property bool $is_onboarding
 * @property object|array|null $onboarding
 * @property bool $is_migrated
 * @property string|null $platform
 * @property int|null $hosted_client_count
 * @property int|null $hosted_company_count
 * @property string|null $inapp_transaction_id
 * @property bool $set_react_as_default_ap
 * @property bool $is_flagged
 * @property bool $is_verified_account
 * @property string|null $account_sms_verification_code
 * @property string|null $account_sms_verification_number
 * @property bool $account_sms_verified
 * @property string|null $bank_integration_account_id
 * @property bool $is_trial
 * @property int $e_invoice_quota
 * @property-read int|null $bank_integrations_count
 * @property-read int|null $companies_count
 * @property-read int|null $company_users_count
 * @property-read \App\Models\Company|null $default_company
 * @property-read mixed $hashed_id
 * @property-read \App\Models\Payment|null $payment
 * @property-read int|null $users_count
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\AccountFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Account newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Account newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Account query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Account first()
 * @method static \Illuminate\Database\Eloquent\Builder|Account count()
 * @method static \Illuminate\Database\Eloquent\Builder|Account where($query)
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankIntegration> $bank_integrations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Company> $companies
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyUser> $company_users
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users

 * @mixin \Eloquent
 */
class Account extends BaseModel
{
    use PresentableTrait;
    use MakesHash;

    private $free_plan_email_quota = 20;

    private $paid_plan_email_quota = 300;

    /**
     * @var string
     */
    protected $presenter = AccountPresenter::class;

    protected $fillable = [
        // 'plan',
        // 'plan_term',
        // 'plan_price',
        // 'plan_paid',
        // 'plan_started',
        // 'plan_expires',
        // 'num_users',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'user_agent',
        'platform',
        'set_react_as_default_ap',
        'inapp_transaction_id',
        'e_invoicing_token',
    ];

    protected $casts = [
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'onboarding' => 'object',
        'set_react_as_default_ap' => 'bool',
        'promo_expires' => 'date',
        'discount_expires' => 'date',
    ];

    public const PLAN_FREE = 'free';
    public const PLAN_PRO = 'pro';
    public const PLAN_ENTERPRISE = 'enterprise';
    public const PLAN_WHITE_LABEL = 'white_label';
    public const PLAN_TERM_MONTHLY = 'month';
    public const PLAN_TERM_YEARLY = 'year';

    public const FEATURE_TASKS = 'tasks';
    public const FEATURE_EXPENSES = 'expenses';
    public const FEATURE_QUOTES = 'quotes';
    public const FEATURE_PURCHASE_ORDERS = 'purchase_orders';
    public const FEATURE_CUSTOMIZE_INVOICE_DESIGN = 'custom_designs';
    public const FEATURE_DIFFERENT_DESIGNS = 'different_designs';
    public const FEATURE_EMAIL_TEMPLATES_REMINDERS = 'template_reminders';
    public const FEATURE_INVOICE_SETTINGS = 'invoice_settings';
    public const FEATURE_CUSTOM_EMAILS = 'custom_emails';
    public const FEATURE_PDF_ATTACHMENT = 'pdf_attachments';
    public const FEATURE_MORE_INVOICE_DESIGNS = 'more_invoice_designs';
    public const FEATURE_REPORTS = 'reports';
    public const FEATURE_BUY_NOW_BUTTONS = 'buy_now_buttons';
    public const FEATURE_API = 'api';
    public const FEATURE_CLIENT_PORTAL_PASSWORD = 'client_portal_password';
    public const FEATURE_CUSTOM_URL = 'custom_url';
    public const FEATURE_MORE_CLIENTS = 'more_clients';
    public const FEATURE_WHITE_LABEL = 'white_label';
    public const FEATURE_REMOVE_CREATED_BY = 'remove_created_by';
    public const FEATURE_USERS = 'users'; // Grandfathered for old Pro users
    public const FEATURE_DOCUMENTS = 'documents';
    public const FEATURE_USER_PERMISSIONS = 'permissions';
    public const FEATURE_SUBSCRIPTIONS = 'subscriptions';

    public const RESULT_FAILURE = 'failure';
    public const RESULT_SUCCESS = 'success';

    public function getEntityType()
    {
        return self::class;
    }

    public function users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(User::class)->withTrashed();
    }

    public function default_company(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Company::class, 'id', 'default_company_id');
    }

    public function payment(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Payment::class)->withTrashed();
    }

    public function companies(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Company::class);
    }

    public function bank_integrations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(BankIntegration::class);
    }

    public function company_users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompanyUser::class);
    }

    /**
     * Returns the owner of the Account - not a HasMany relation
     * @return \App\Models\User | bool
     */
    public function owner()
    {
        return $this->hasMany(CompanyUser::class)->where('is_owner', true)->first() ? $this->hasMany(CompanyUser::class)->where('is_owner', true)->first()->user : false;
    }

    public function tokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompanyToken::class)->withTrashed();
    }

    public function getPlan()
    {
        if (Carbon::parse($this->plan_expires)->lt(now())) {
            return '';
        }

        return $this->plan ?: '';
    }

    public function hasFeature($feature)
    {
        $plan_details = $this->getPlanDetails();
        $self_host = !Ninja::isNinja();

        switch ($feature) {
            case self::FEATURE_TASKS:
            case self::FEATURE_EXPENSES:
            case self::FEATURE_QUOTES:
            case self::FEATURE_PURCHASE_ORDERS:
                return true;

            case self::FEATURE_CUSTOMIZE_INVOICE_DESIGN:
            case self::FEATURE_DIFFERENT_DESIGNS:
            case self::FEATURE_EMAIL_TEMPLATES_REMINDERS:
            case self::FEATURE_INVOICE_SETTINGS:
            case self::FEATURE_CUSTOM_EMAILS:
            case self::FEATURE_PDF_ATTACHMENT:
            case self::FEATURE_MORE_INVOICE_DESIGNS:
            case self::FEATURE_REPORTS:
            case self::FEATURE_BUY_NOW_BUTTONS:
            case self::FEATURE_API:
            case self::FEATURE_CLIENT_PORTAL_PASSWORD:
            case self::FEATURE_CUSTOM_URL:
                return $self_host || !empty($plan_details);

                // Pro; No trial allowed, unless they're trialing enterprise with an active pro plan
            case self::FEATURE_MORE_CLIENTS:
                return $self_host || !empty($plan_details) && (!$plan_details['trial'] || !empty($this->getPlanDetails(false, false)));

                // White Label
            case self::FEATURE_WHITE_LABEL:
                if (!$self_host && $plan_details && !$plan_details['expires']) {
                    return false;
                }
                // Fallthrough
                // no break
            case self::FEATURE_REMOVE_CREATED_BY:
                return !empty($plan_details); // A plan is required even for self-hosted users

                // Enterprise; No Trial allowed; grandfathered for old pro users
            case self::FEATURE_USERS: // Grandfathered for old Pro users
                if ($plan_details && $plan_details['trial']) {
                    // Do they have a non-trial plan?
                    $plan_details = $this->getPlanDetails(false, false);
                }

                return $self_host || !empty($plan_details) && ($plan_details['plan'] == self::PLAN_ENTERPRISE);

                // Enterprise; No Trial allowed
            case self::FEATURE_DOCUMENTS:
            case self::FEATURE_USER_PERMISSIONS:
                return $self_host || !empty($plan_details) && $plan_details['plan'] == self::PLAN_ENTERPRISE && !$plan_details['trial'];

            default:
                return false;
        }
    }

    public function isPaid(): bool
    {
        return Ninja::isNinja() ? $this->isPaidHostedClient() : $this->hasFeature(self::FEATURE_WHITE_LABEL);
    }

    public function isPremium(): bool
    {
        // return true;
        return Ninja::isHosted() && $this->isPaidHostedClient() && !$this->isTrial() && Carbon::createFromTimestamp($this->created_at)->diffInMonths() > 2;
    }

    public function isPaidHostedClient(): bool
    {
        if (!Ninja::isNinja()) {
            return false;
        }

        // 09-03-2023 - winds forward expiry checks to ensure we don't cut off users prior to billing cycle being commenced
        if ($this->plan_expires && Carbon::parse($this->plan_expires)->lt(now()->subHours(12))) {
            return false;
        }

        return $this->plan == 'pro' || $this->plan == 'enterprise';
    }

    public function isFreeHostedClient(): bool
    {
        if (!Ninja::isNinja()) {
            return false;
        }

        if ($this->plan_expires && Carbon::parse($this->plan_expires)->lt(now()->subHours(12))) {
            return true;
        }

        return $this->plan == 'free' || is_null($this->plan) || empty($this->plan);
    }

    public function isEnterpriseClient(): bool
    {
        if (!Ninja::isNinja()) {
            return false;
        }

        return $this->plan == 'enterprise';
    }

    public function isEnterprisePaidClient(): bool
    {
        if (! Ninja::isNinja()) {
            return false;
        }

        return $this->isEnterpriseClient() && $this->isPaid();
    }

    public function isProClient(): bool
    {
        if (! Ninja::isNinja()) {
            return false;
        }

        return $this->plan == 'pro';
    }

    public function isProPaidClient(): bool
    {
        if (! Ninja::isNinja()) {
            return false;
        }

        return $this->isProClient() && $this->isPaid();
    }

    public function isNewHostedAccount()
    {
        return Ninja::isHosted() && Carbon::createFromTimestamp($this->created_at)->diffInWeeks() <= 2;
    }

    public function isTrial(): bool
    {
        if (!Ninja::isNinja()) {
            return false;
        }

        return !$this->plan_paid && $this->trial_started && Carbon::parse($this->trial_started)->addDays(14)->gte(now()->subHours(12));

    }

    public function startTrial($plan): void
    {
        if (!Ninja::isNinja()) {
            return;
        }

        if ($this->trial_started && $this->trial_started != '0000-00-00') {
            return;
        }

        $this->trial_plan = $plan;
        $this->trial_started = now();
        $this->save();
    }

    public function getPlanDetails($include_inactive = false, $include_trial = true)
    {
        $plan = $this->plan;
        $price = $this->plan_price;
        $trial_plan = $this->trial_plan;

        if ((!$plan || $plan == self::PLAN_FREE) && (!$trial_plan || !$include_trial)) {
            return null;
        }

        $trial_active = false;
        $trial_expires = false;
        $trial_started = false;

        //14 day trial
        $duration = 60 * 60 * 24 * 14;

        if ($trial_plan && $include_trial) {
            $trial_started = $this->trial_started;
            $trial_expires = Carbon::parse($this->trial_started)->addSeconds($duration);

            if ($trial_expires->greaterThan(now())) {
                $trial_active = true;
            }
        }

        $plan_active = false;
        $plan_expires = false;

        if ($plan) {
            if ($this->plan_expires == null) {
                $plan_active = true;
            } else {
                $plan_expires = Carbon::parse($this->plan_expires);
                if ($plan_expires->greaterThan(now())) {
                    $plan_active = true;
                }
            }
        }

        if (!$include_inactive && !$plan_active && !$trial_active) {
            return null;
        }

        // Should we show plan details or trial details?
        if (($plan && !$trial_plan) || !$include_trial) {
            $use_plan = true;
        } elseif (!$plan && $trial_plan) {
            $use_plan = false;
        } else {
            // There is both a plan and a trial
            if (!empty($plan_active) && empty($trial_active)) {
                $use_plan = true;
            } elseif (empty($plan_active) && !empty($trial_active)) {
                $use_plan = false;
            } elseif (!empty($plan_active) && !empty($trial_active)) {
                // Both are active; use whichever is a better plan
                if ($plan == self::PLAN_ENTERPRISE) {
                    $use_plan = true;
                } elseif ($trial_plan == self::PLAN_ENTERPRISE) {
                    $use_plan = false;
                } else {
                    // They're both the same; show the plan
                    $use_plan = true;
                }
            } else {
                // Neither are active; use whichever expired most recently
                $use_plan = $plan_expires >= $trial_expires;
            }
        }

        if ($use_plan) {
            return [
                'account_id' => $this->id,
                'num_users' => $this->num_users,
                'plan_price' => $price,
                'trial' => false,
                'plan' => $plan,
                'started' => $this->plan_started ? DateTime::createFromFormat('Y-m-d', $this->plan_started) : false,
                'expires' => $plan_expires,
                'paid' => $this->plan_paid ? DateTime::createFromFormat('Y-m-d', $this->plan_paid) : false,
                'term' => $this->plan_term,
                'active' => $plan_active,
            ];
        } else {
            return [
                'account_id' => $this->id,
                'num_users' => 1,
                'plan_price' => 0,
                'trial' => true,
                'plan' => $trial_plan,
                'started' => $trial_started,
                'expires' => $trial_expires,
                'active' => $trial_active,
            ];
        }
    }

    public function getDailyEmailLimit()
    {
        if ($this->is_flagged) {
            return 0;
        }

        if ($this->email_quota) {
            return (int)$this->email_quota;
        }

        if (Carbon::createFromTimestamp($this->created_at)->diffInWeeks() <= 1) {
            return 20;
        }

        if (Carbon::createFromTimestamp($this->created_at)->diffInWeeks() <= 2 && !$this->payment_id) {
            return 20;
        }

        if ($this->isPaid()) {
            $multiplier = $this->plan == 'enterprise' ? 2 : 1.2;

            $limit = $this->paid_plan_email_quota;
            $limit += Carbon::createFromTimestamp($this->created_at)->diffInMonths() * (20 * $multiplier);
        } else {
            $limit = $this->free_plan_email_quota;
            $limit += Carbon::createFromTimestamp($this->created_at)->diffInMonths() * 1.5;
        }

        return min($limit, 1000);
    }

    public function emailsSent()
    {
        if (is_null(Cache::get("email_quota" . $this->key))) {
            return 0;
        }

        return Cache::get("email_quota" . $this->key);
    }

    public function emailQuotaExceeded(): bool
    {
        if (is_null(Cache::get("email_quota" . $this->key))) {
            return false;
        }

        try {
            if (Cache::get("email_quota" . $this->key) > $this->getDailyEmailLimit()) {
                if (is_null(Cache::get("throttle_notified:{$this->key}"))) {
                    App::forgetInstance('translator');
                    $t = app('translator');
                    $t->replace(Ninja::transformTranslations($this->companies()->first()->settings));

                    $nmo = new NinjaMailerObject();
                    $nmo->mailable = new EmailQuotaExceeded($this->companies()->first());
                    $nmo->company = $this->companies()->first();
                    $nmo->settings = $this->companies()->first()->settings;
                    $nmo->to_user = $this->companies()->first()->owner();
                    NinjaMailerJob::dispatch($nmo, true);

                    Cache::put("throttle_notified:{$this->key}", true, 60 * 60 * 24);

                    if (config('ninja.notification.slack')) {
                        $this->companies()->first()->notification(new EmailQuotaNotification($this))->ninja();
                    }
                }

                return true;
            }
        } catch (\Exception $e) {
            \Sentry\captureMessage("I encountered an error with email quotas for account {$this->key} - defaulting to SEND");
        }

        return false;
    }

    public function gmailCredentialNotification(): bool
    {
        nlog("checking if gmail credential notification has already been sent");

        if (is_null(Cache::get($this->key))) {
            return false;
        }

        nlog("Sending notification");

        try {
            if (is_null(Cache::get("gmail_credentials_notified:{$this->key}"))) {
                App::forgetInstance('translator');
                $t = app('translator');
                $t->replace(Ninja::transformTranslations($this->companies()->first()->settings));

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new GmailTokenInvalid($this->companies()->first());
                $nmo->company = $this->companies()->first();
                $nmo->settings = $this->companies()->first()->settings;
                $nmo->to_user = $this->companies()->first()->owner();
                NinjaMailerJob::dispatch($nmo, true);

                Cache::put("gmail_credentials_notified:{$this->key}", true, 60 * 24);

                if (config('ninja.notification.slack')) {
                    $this->companies()->first()->notification(new GmailCredentialNotification($this))->ninja();
                }
            }

            return true;
        } catch (\Exception $e) {
            \Sentry\captureMessage("I encountered an error with sending with gmail for account {$this->key}");
        }

        return false;
    }

    public function resolveRouteBinding($value, $field = null)
    {
        if (is_numeric($value)) {
            throw new ModelNotFoundException("Record with value {$value} not found");
        }

        return $this
            ->where('id', $this->decodePrimaryKey($value))
            ->firstOrFail();
    }

    public function getTrialDays()
    {
        if ($this->payment_id || $this->is_migrated) {
            return 0;
        }

        $plan_expires = Carbon::parse($this->plan_expires);

        if ($plan_expires->gt(now())) {
            $diff = intval(abs($plan_expires->diffInDays()));

            if ($diff > 14) {
                return 0;
            }

            return $diff;
        }

        return 0;
    }
}

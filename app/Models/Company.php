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

use App\Casts\EncryptedCast;
use App\DataMapper\CompanySettings;
use App\DataMapper\QuickbooksSettings;
use App\Models\Presenters\CompanyPresenter;
use App\Services\Company\CompanyService;
use App\Services\Notification\NotificationService;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\CompanySettingsSaver;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Laracasts\Presenter\PresentableTrait;

/**
 * App\Models\Company
 *
 * @property int $id
 * @property int $account_id
 * @property int|null $industry_id
 * @property string|null $ip
 * @property string $company_key
 * @property bool $convert_products
 * @property bool $fill_products
 * @property bool $update_products
 * @property bool $show_product_details
 * @property bool $client_can_register
 * @property bool $custom_surcharge_taxes1
 * @property bool $custom_surcharge_taxes2
 * @property bool $custom_surcharge_taxes3
 * @property bool $custom_surcharge_taxes4
 * @property int $show_product_cost
 * @property int $enabled_tax_rates
 * @property int $enabled_modules
 * @property int $enable_product_cost
 * @property int $enable_product_quantity
 * @property int $default_quantity
 * @property string|null $subdomain
 * @property string|null $db
 * @property int|null $size_id
 * @property string|null $first_day_of_week
 * @property string|null $first_month_of_year
 * @property string $portal_mode
 * @property string|null $portal_domain
 * @property int $enable_modules
 * @property object $custom_fields
 * @property \App\DataMapper\CompanySettings|\stdClass $settings
 * @property string $slack_webhook_url
 * @property string $google_analytics_key
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int $enabled_item_tax_rates
 * @property bool $is_large
 * @property int $enable_shop_api
 * @property string $default_auto_bill
 * @property string $custom_value1
 * @property string $custom_value2
 * @property string $custom_value3
 * @property string $custom_value4
 * @property bool $mark_expenses_invoiceable
 * @property bool $mark_expenses_paid
 * @property bool $invoice_expense_documents
 * @property bool $auto_start_tasks
 * @property bool $invoice_task_timelog
 * @property bool $invoice_task_documents
 * @property bool $show_tasks_table
 * @property bool $is_disabled
 * @property bool $default_task_is_date_based
 * @property bool $enable_product_discount
 * @property bool $calculate_expense_tax_by_amount
 * @property bool $expense_inclusive_taxes
 * @property int $session_timeout
 * @property bool $oauth_password_required
 * @property int $invoice_task_datelog
 * @property int $default_password_timeout
 * @property bool $show_task_end_date
 * @property bool $markdown_enabled
 * @property bool $use_comma_as_decimal_place
 * @property bool $report_include_drafts
 * @property array|null $client_registration_fields
 * @property bool $convert_rate_to_client
 * @property bool $markdown_email_enabled
 * @property bool $stop_on_unpaid_recurring
 * @property bool $use_quote_terms_on_conversion
 * @property int $enable_applying_payments
 * @property bool $track_inventory
 * @property int $inventory_notification_threshold
 * @property int $stock_notification
 * @property string|null $matomo_url
 * @property int|null $matomo_id
 * @property int $enabled_expense_tax_rates
 * @property int $invoice_task_project
 * @property int $report_include_deleted
 * @property int $invoice_task_lock
 * @property int $convert_payment_currency
 * @property int $convert_expense_currency
 * @property int $notify_vendor_when_paid
 * @property int $invoice_task_hours
 * @property string|null $expense_mailbox
 * @property boolean $expense_mailbox_active
 * @property bool $inbound_mailbox_allow_company_users
 * @property bool $inbound_mailbox_allow_vendors
 * @property bool $inbound_mailbox_allow_clients
 * @property bool $inbound_mailbox_allow_unknown
 * @property string|null $inbound_mailbox_whitelist
 * @property string|null $inbound_mailbox_blacklist
 * @property string|null $e_invoice_certificate_passphrase
 * @property string|null $e_invoice_certificate
 * @property int $deleted_at
 * @property string|null $smtp_username
 * @property string|null $smtp_password
 * @property string|null $smtp_host
 * @property int|null $smtp_port
 * @property string|null $smtp_encryption
 * @property string|null $smtp_local_domain
 * @property \App\DataMapper\QuickbooksSettings|null $quickbooks
 * @property boolean $smtp_verify_peer
 * @property int|null $legal_entity_id
 * @property-read \App\Models\Account $account
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $all_activities
 * @property-read int|null $all_activities_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $all_documents
 * @property-read int|null $all_documents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankIntegration> $bank_integrations
 * @property-read int|null $bank_integrations_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransactionRule> $bank_transaction_rules
 * @property-read int|null $bank_transaction_rules_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\BankTransaction> $bank_transactions
 * @property-read int|null $bank_transactions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $client_contacts
 * @property-read int|null $client_contacts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientGatewayToken> $client_gateway_tokens
 * @property-read int|null $client_gateway_tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Client> $clients
 * @property-read int|null $clients_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyGateway> $company_gateways
 * @property-read int|null $company_gateways_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyUser> $company_users
 * @property-read int|null $company_users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $contacts
 * @property-read int|null $contacts_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read int|null $credits_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Design> $designs
 * @property-read int|null $designs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ExpenseCategory> $expense_categories
 * @property-read int|null $expense_categories_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read int|null $expenses_count
 * @property-read mixed $company_id
 * @property-read mixed $hashed_id
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupSetting> $group_settings
 * @property-read int|null $group_settings_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\GroupSetting> $groups
 * @property-read int|null $groups_count
 * @property-read \App\Models\Industry|null $industry
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read int|null $invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $ledger
 * @property-read int|null $ledger_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentTerm> $payment_terms
 * @property-read int|null $payment_terms_count
 * @property-read \App\Models\PaymentType|null $payment_type
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read int|null $payments_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Product> $products
 * @property-read int|null $products_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read int|null $projects_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PurchaseOrder> $purchase_orders
 * @property-read int|null $purchase_orders_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @property-read int|null $quotes_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringExpense> $recurring_expenses
 * @property-read int|null $recurring_expenses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurring_invoices
 * @property-read int|null $recurring_invoices_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Scheduler> $schedulers
 * @property-read int|null $schedulers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Subscription> $subscriptions
 * @property-read int|null $subscriptions_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $system_log_relation
 * @property-read int|null $system_log_relation_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $system_logs
 * @property-read int|null $system_logs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Scheduler> $task_schedulers
 * @property-read int|null $task_schedulers_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaskStatus> $task_statuses
 * @property-read int|null $task_statuses_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read int|null $tasks_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\TaxRate> $tax_rates
 * @property-read int|null $tax_rates_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens
 * @property-read int|null $tokens_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyToken> $tokens_hashed
 * @property-read int|null $tokens_hashed_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Design> $user_designs
 * @property-read int|null $user_designs_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\PaymentTerm> $user_payment_terms
 * @property-read int|null $user_payment_terms_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users
 * @property-read int|null $users_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Vendor> $vendors
 * @property-read int|null $vendors_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Webhook> $webhooks
 * @method static \Illuminate\Database\Eloquent\Builder|Company where($query)
 * @method static \Illuminate\Database\Eloquent\Builder|Company find($query)
 * @property-read int|null $webhooks_count
 * @property int $calculate_taxes
 * @property mixed $tax_data
 * @method \App\Models\User|null owner()
 * @mixin \Eloquent
 */
class Company extends BaseModel
{
    use PresentableTrait;
    use MakesHash;
    use CompanySettingsSaver;
    use AppSetup;
    use \Awobaz\Compoships\Compoships;

    /** @var CompanyPresenter */
    protected $presenter = CompanyPresenter::class;

    protected array $tax_coverage_countries = [
        'US',
        // //EU countries
        'AT', // Austria
        'BE', // Belgium
        'BG', // Bulgaria
        'CY', // Cyprus
        'CZ', // Czech Republic
        'DE', // Germany
        'DK', // Denmark
        'EE', // Estonia
        'ES', // Spain
        'FI', // Finland
        'FR', // France
        'GR', // Greece
        'HR', // Croatia
        'HU', // Hungary
        'IE', // Ireland
        'IT', // Italy
        'LT', // Lithuania
        'LU', // Luxembourg
        'LV', // Latvia
        'MT', // Malta
        'NL', // Netherlands
        'PL', // Poland
        'PT', // Portugal
        'RO', // Romania
        'SE', // Sweden
        'SI', // Slovenia
        'SK', // Slovakia
        // //EU Countries
        'AU', // Australia
    ];

    protected $fillable = [
        'invoice_task_item_description',
        'invoice_task_project_header',
        'invoice_task_hours',
        'markdown_enabled',
        'calculate_expense_tax_by_amount',
        'invoice_expense_documents',
        'invoice_task_documents',
        'show_tasks_table',
        'mark_expenses_invoiceable',
        'mark_expenses_paid',
        'enabled_item_tax_rates',
        'fill_products',
        'industry_id',
        'subdomain',
        'size_id',
        'custom_fields',
        'enable_product_cost',
        'enable_product_quantity',
        'enabled_modules',
        'default_quantity',
        'enabled_tax_rates',
        'portal_mode',
        'portal_domain',
        'convert_products',
        'update_products',
        'custom_surcharge_taxes1',
        'custom_surcharge_taxes2',
        'custom_surcharge_taxes3',
        'custom_surcharge_taxes4',
        'show_product_details',
        'first_day_of_week',
        'first_month_of_year',
        'slack_webhook_url',
        'google_analytics_key',
        'matomo_url',
        'matomo_id',
        'client_can_register',
        'enable_shop_api',
        'invoice_task_timelog',
        'auto_start_tasks',
        'is_disabled',
        'default_task_is_date_based',
        'enable_product_discount',
        'expense_inclusive_taxes',
        'session_timeout',
        'oauth_password_required',
        'invoice_task_datelog',
        'default_password_timeout',
        'show_task_end_date',
        'use_comma_as_decimal_place',
        'report_include_drafts',
        'client_registration_fields',
        'convert_rate_to_client',
        'markdown_email_enabled',
        'stop_on_unpaid_recurring',
        'use_quote_terms_on_conversion',
        'enable_applying_payments',
        'track_inventory',
        'inventory_notification_threshold',
        'stock_notification',
        'enabled_expense_tax_rates',
        'invoice_task_project',
        'report_include_deleted',
        'invoice_task_lock',
        'convert_payment_currency',
        'convert_expense_currency',
        'notify_vendor_when_paid',
        'calculate_taxes',
        'tax_data',
        'e_invoice_certificate_passphrase',
        'expense_mailbox_active',
        'expense_mailbox',
        'inbound_mailbox_allow_company_users',
        'inbound_mailbox_allow_vendors',
        'inbound_mailbox_allow_clients',
        'inbound_mailbox_allow_unknown',
        'inbound_mailbox_whitelist',
        'inbound_mailbox_blacklist',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_local_domain',
        'smtp_verify_peer',
        // 'e_invoice',
        // 'e_invoicing_token',
    ];

    protected $hidden = [
        'id',
        'db',
        'ip',
        'smtp_username',
        'smtp_password',
        'quickbooks',
    ];

    protected $casts = [
        'is_proforma' => 'bool',
        'country_id' => 'string',
        'custom_fields' => 'object',
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'client_registration_fields' => 'array',
        'tax_data' => 'object',
        'origin_tax_data' => 'object',
        'e_invoice_certificate_passphrase' => EncryptedCast::class,
        'smtp_username' => 'encrypted',
        'smtp_password' => 'encrypted',
        'e_invoice' => 'object',
        'quickbooks' => QuickbooksSettings::class,
        'smtp_port' => 'int',
    ];

    protected $with = [];

    // public static $modules = [
    //     self::ENTITY_RECURRING_INVOICE => 1,
    //     self::ENTITY_CREDIT => 2,
    //     self::ENTITY_QUOTE => 4,
    //     self::ENTITY_TASK => 8,
    //     self::ENTITY_EXPENSE => 16,
    //     self::ENTITY_PROJECT => 32,
    //     self::ENTITY_VENDOR => 64,
    //     self::ENTITY_TICKET => 128,
    //     self::ENTITY_PROPOSAL => 256,
    //     self::ENTITY_RECURRING_EXPENSE => 512,
    //     self::ENTITY_RECURRING_TASK => 1024,
    //     self::ENTITY_RECURRING_QUOTE => 2048,
    // ];

    public function shouldCalculateTax()
    {
        return $this->calculate_taxes && in_array($this->getSetting('country_id'), $this->tax_coverage_countries);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function schedulers(): HasMany
    {
        return $this->hasMany(Scheduler::class);
    }

    public function task_schedulers(): HasMany
    {
        return $this->hasMany(Scheduler::class);
    }

    public function all_documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function ledger(): HasMany
    {
        return $this->hasMany(CompanyLedger::class);
    }

    public function bank_integrations(): HasMany
    {
        return $this->hasMany(BankIntegration::class)->withTrashed();
    }

    public function bank_transactions(): HasMany
    {
        return $this->hasMany(BankTransaction::class)->withTrashed();
    }

    public function bank_transaction_rules(): HasMany
    {
        return $this->hasMany(BankTransactionRule::class);
    }

    public function getCompanyIdAttribute()
    {
        return $this->encodePrimaryKey($this->id);
    }

    public function account(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function client_contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function users(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(User::class, CompanyUser::class, 'company_id', 'id', 'id', 'user_id')->withTrashed();
    }

    public function expense_categories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class)->withTrashed();
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->withTrashed();
    }

    public function purchase_orders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class)->withTrashed();
    }

    public function task_statuses(): HasMany
    {
        return $this->hasMany(TaskStatus::class)->withTrashed();
    }

    public function clients(): HasMany
    {
        return $this->hasMany(Client::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->withTrashed();
    }

    public function webhooks(): HasMany
    {
        return $this->hasMany(Webhook::class);
    }

    /**
     * @return HasMany
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function vendor_contacts(): HasMany
    {
        return $this->hasMany(VendorContact::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class)->withTrashed();
    }

    public function all_activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function activities(): HasMany
    {   
        return $this->hasMany(Activity::class)->orderBy('id', 'DESC')->take(50);     
    }

    /**
     * @return HasMany
     */
    public function contacts()
    {
        return $this->hasMany(ClientContact::class);
    }

    public function groups()
    {
        return $this->hasMany(GroupSetting::class);
    }

    /**
     * @return HasMany
     */
    public function invoices()
    {
        return $this->hasMany(Invoice::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function recurring_invoices()
    {
        return $this->hasMany(RecurringInvoice::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function recurring_expenses()
    {
        return $this->hasMany(RecurringExpense::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function quotes()
    {
        return $this->hasMany(Quote::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function credits()
    {
        return $this->hasMany(Credit::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function company_gateways()
    {
        return $this->hasMany(CompanyGateway::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function tax_rates()
    {
        return $this->hasMany(TaxRate::class)->withTrashed();
    }

    /**
     * @return HasMany
     */
    public function products()
    {
        return $this->hasMany(Product::class)->withTrashed();
    }

    public function country()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        return $countries->first(function ($item) {
            return $item->id == $this->getSetting('country_id');
        });
    }

    public function group_settings()
    {
        return $this->hasMany(GroupSetting::class)->withTrashed();
    }

    public function timezone()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\TimeZone> */
        $timezones = app('timezones');

        return $timezones->first(function ($item) {
            return $item->id == $this->settings->timezone_id;
        });

    }

    public function designs()
    {
        return $this->hasMany(Design::class)->whereCompanyId($this->id)->orWhere('company_id', null);
    }

    public function user_designs()
    {
        return $this->hasMany(Design::class);
    }

    public function payment_terms()
    {
        return $this->hasMany(PaymentTerm::class)->whereCompanyId($this->id)->orWhere('company_id', null);
    }

    public function user_payment_terms()
    {
        return $this->hasMany(PaymentTerm::class);
    }

    public function language()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        $language = $languages->first(function ($item) {
            return $item->id == $this->settings->language_id;
        });

        return $language ?? $languages->first();
    }

    public function getLocale()
    {
        return isset($this->settings->language_id) && $this->language() ? $this->language()->locale : config('ninja.i18n.locale');
    }

    public function getLogo(): ?string
    {
        return $this->settings->company_logo ?: null;
    }

    public function locale()
    {
        return $this->getLocale();
    }

    public function setLocale()
    {
        App::setLocale($this->getLocale());
    }

    public function getSetting($setting)
    {
        //todo $this->setting ?? false
        if (property_exists($this->settings, $setting) != false) {
            return $this->settings->{$setting};
        }

        $cs = CompanySettings::defaults();

        if (property_exists($cs, $setting) != false) {
            return $cs->{$setting};
        }

        return null;
    }

    public function currency()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        return $currencies->first(function ($item) {
            return $item->id == $this->settings->currency_id;
        });
    }

    /**
     * @return BelongsTo
     */
    public function industry()
    {
        return $this->belongsTo(Industry::class);
    }

    /**
     * @return BelongsTo
     */
    public function payment_type()
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class)->withTrashed();
    }

    public function payments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Payment::class)->withTrashed();
    }

    public function tokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompanyToken::class);
    }

    public function client_gateway_tokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientGatewayToken::class);
    }

    public function system_logs(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SystemLog::class)->orderBy('id', 'DESC')->take(100);
    }

    public function system_log_relation(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SystemLog::class)->orderBy('id', 'DESC');
    }

    public function tokens_hashed(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompanyToken::class);
    }

    public function company_users(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompanyUser::class)->withTrashed();
    }

    public function invoice_invitations(): HasMany
    {
        return $this->hasMany(InvoiceInvitation::class);
    }

    public function quote_invitations(): HasMany
    {
        return $this->hasMany(QuoteInvitation::class);
    }

    public function credit_invitations(): HasMany
    {
        return $this->hasMany(CreditInvitation::class);
    }

    public function purchase_order_invitations(): HasMany
    {
        return $this->hasMany(PurchaseOrderInvitation::class);
    }

    /**
     * @return \App\Models\User|null
     */
    public function owner(): ?User
    {
        return $this->company_users()->withTrashed()->where('is_owner', true)->first()?->user;
    }

    public function credit_rules()
    {
        return BankTransactionRule::query()
            ->where('company_id', $this->id)
            ->where('applies_to', 'CREDIT')
            ->get();
    }

    public function debit_rules()
    {
        return BankTransactionRule::query()
            ->where('company_id', $this->id)
            ->where('applies_to', 'DEBIT')
            ->get();
    }

    public function resolveRouteBinding($value, $field = null)
    {
        return $this->where('id', $this->decodePrimaryKey($value))
            ->where('account_id', auth()->user()->account_id)
            ->firstOrFail();
    }

    public function domain(): string
    {
        if (Ninja::isHosted()) {
            if ($this->portal_mode == 'domain' && strlen($this->portal_domain) > 3) {
                return $this->portal_domain;
            }

            return "https://{$this->subdomain}." . config('ninja.app_domain');
        }

        return config('ninja.app_url');
    }

    public function notification(Notification $notification)
    {
        return new NotificationService($this, $notification);
    }

    public function routeNotificationForSlack($notification): string
    {
        return $this->slack_webhook_url;
    }

    public function file_path(): string
    {
        return $this->company_key . '/';
    }

    public function rBits()
    {
        $user = $this->owner();
        $data = [];

        $data[] = $this->createRBit('business_name', 'user', ['business_name' => $this->present()->name()]);
        $data[] = $this->createRBit('industry_code', 'user', ['industry_detail' => $this->industry ? $this->industry->name : '']);
        $data[] = $this->createRBit('comment', 'partner_database', ['comment_text' => 'Logo image not present']);
        $data[] = $this->createRBit('business_description', 'user', ['business_description' => $this->present()->size()]);

        $data[] = $this->createRBit('person', 'user', ['name' => $user->present()->getFullName()]);
        $data[] = $this->createRBit('email', 'user', ['email' => $user->email]);
        $data[] = $this->createRBit('phone', 'user', ['phone' => $user->phone]);
        $data[] = $this->createRBit('website_uri', 'user', ['uri' => $this->settings->website]);
        $data[] = $this->createRBit('external_account', 'partner_database', ['is_partner_account' => 'yes', 'account_type' => 'Invoice Ninja', 'create_time' => time()]);

        return $data;
    }

    private function createRBit($type, $source, $properties)
    {
        $data = new \stdClass();
        $data->receive_time = time();
        $data->type = $type;
        $data->source = $source;
        $data->properties = new \stdClass();

        foreach ($properties as $key => $val) {
            $data->properties->$key = $val;
        }

        return $data;
    }

    public function utc_offset(): int
    {
        $offset = 0;
        $timezone = $this->timezone();

        date_default_timezone_set('GMT');
        $date = new \DateTime("now", new \DateTimeZone($timezone->name));
        $offset = $date->getOffset();

        return $offset;
    }

    public function timezone_offset(): int
    {
        $offset = 0;

        $entity_send_time = $this->getSetting('entity_send_time');

        if ($entity_send_time == 0) {
            return 0;
        }

        $timezone = $this->timezone();

        date_default_timezone_set('GMT');
        $date = new \DateTime("now", new \DateTimeZone($timezone->name));
        $offset -= $date->getOffset();

        $offset += ($entity_send_time * 3600);

        return $offset;
    }

    public function translate_entity()
    {
        return ctrans('texts.company');
    }

    public function date_format()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\DateFormat> */
        $date_formats = app('date_formats');

        return $date_formats->first(function ($item) {
            return $item->id == $this->getSetting('date_format_id');
        })->format;
    }

    public function getInvoiceCert()
    {
        if ($this->e_invoice_certificate) {
            return base64_decode($this->e_invoice_certificate);
        }

        return false;
    }

    public function getSslPassPhrase()
    {
        return $this->e_invoice_certificate_passphrase;
    }

    public function service(): CompanyService
    {
        return new CompanyService($this);
    }

    public function isPeppolSender()
    {
        return Ninja::isHosted() && $this->account->isPaid() && $this->account->isEnterpriseClient() && $this->account->e_invoice_quota > 0 && $this->settings->e_invoice_type == 'PEPPOL' && $this->tax_data->acts_as_sender;
    }

}

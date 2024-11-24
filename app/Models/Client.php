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

use Laravel\Scout\Searchable;
use App\DataMapper\ClientSync;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\MakesHash;
use App\DataMapper\FeesAndLimits;
use App\Models\Traits\Excludable;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use Illuminate\Support\Facades\App;
use App\Services\Client\ClientService;
use App\Utils\Traits\GeneratesCounter;
use Laracasts\Presenter\PresentableTrait;
use App\Models\Presenters\ClientPresenter;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Utils\Traits\ClientGroupSettingsSaver;
use App\Libraries\Currency\Conversion\CurrencyApi;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Contracts\Translation\HasLocalePreference;

/**
 * App\Models\Client
 *
 * @property int $id
 * @property int $company_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property string|null $name
 * @property string|null $website
 * @property string|null $private_notes
 * @property string|null $public_notes
 * @property string|null $client_hash
 * @property string|null $logo
 * @property string|null $phone
 * @property string|null $routing_id
 * @property float $balance
 * @property float $paid_to_date
 * @property float $credit_balance
 * @property int|null $last_login
 * @property int|null $industry_id
 * @property int|null $size_id
 * @property object|array|null $e_invoice
 * @property string|null $address1
 * @property string|null $address2
 * @property string|null $city
 * @property string|null $state
 * @property string|null $postal_code
 * @property int|null $country_id
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $shipping_address1
 * @property string|null $shipping_address2
 * @property string|null $shipping_city
 * @property string|null $shipping_state
 * @property string|null $shipping_postal_code
 * @property int|null $shipping_country_id
 * @property object|null $settings
 * @property object|null $group_settings
 * @property object|null $sync
 * @property bool $is_deleted
 * @property int|null $group_settings_id
 * @property string|null $vat_number
 * @property string|null $number
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $id_number
 * @property-read mixed $hashed_id
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\Country|null $country
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $contacts
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientGatewayToken> $gateway_tokens
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\ClientContact> $primary_contact
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Project> $projects
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringExpense> $recurring_expenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurring_invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\SystemLog> $system_logs
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringInvoice> $recurring_invoices
 * @method static \Illuminate\Database\Eloquent\Builder|Client exclude($columns)
 * @method static \Database\Factories\ClientFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Client filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Client without()
 * @method static \Illuminate\Database\Eloquent\Builder|Client find()
 * @method static \Illuminate\Database\Eloquent\Builder|Client select()
 * @property string $payment_balance
 * @property mixed $tax_data
 * @property bool $is_tax_exempt
 * @property bool $has_valid_vat_number
 * @mixin \Eloquent
 */
class Client extends BaseModel implements HasLocalePreference
{
    use PresentableTrait;
    use MakesHash;
    use SoftDeletes;
    use Filterable;
    use GeneratesCounter;
    use AppSetup;
    use ClientGroupSettingsSaver;
    use Excludable;
    use Searchable;

    protected $presenter = ClientPresenter::class;

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'company_id',
        'last_login',
    ];

    protected $fillable = [
        'assigned_user_id',
        'name',
        'website',
        'private_notes',
        'industry_id',
        'size_id',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
        'settings',
        'vat_number',
        'id_number',
        'group_settings_id',
        'public_notes',
        'phone',
        'number',
        'routing_id',
        'is_tax_exempt',
        'has_valid_vat_number',
        'classification',
    ];

    protected $with = [
        'gateway_tokens',
        'documents',
        'contacts.company',
    ];

    protected $casts = [
        'is_deleted' => 'boolean',
        'country_id' => 'string',
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'last_login' => 'timestamp',
        'tax_data' => 'object',
        'e_invoice' => 'object',
        'sync' => ClientSync::class,
    ];

    protected $touches = [];

    /**
     * Whitelisted fields for using from query parameters on subscriptions request.
     *
     * @var string[]
     */
    public static $subscriptions_fillable = [
        'assigned_user_id',
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
        'payment_terms',
        'vat_number',
        'id_number',
        'public_notes',
        'phone',
        'routing_id',
    ];

    public static array $bulk_update_columns = [
        'public_notes',
        'industry_id',
        'size_id',
        'country_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
    ];

    public function toSearchableArray()
    {

        $locale = $this->locale();
        App::setLocale($locale);

        $name = ctrans('texts.client') . " | " . $this->present()->name();

        if (strlen($this->vat_number ?? '') > 1) {
            $name .= " | ". $this->vat_number;
        }

        return [
            'id' => $this->id,
            'name' => $name,
            'is_deleted' => $this->is_deleted,
            'hashed_id' => $this->hashed_id,
            'number' => $this->number,
            'id_number' => $this->id_number,
            'vat_number' => $this->vat_number,
            'balance' => $this->balance,
            'paid_to_date' => $this->paid_to_date,
            'phone' => $this->phone,
            'address1' => $this->address1,
            'address2' => $this->address2,
            'city' => $this->city,
            'state' => $this->state,
            'postal_code' => $this->postal_code,
            'website' => $this->website,
            'private_notes' => $this->private_notes,
            'public_notes' => $this->public_notes,
            'shipping_address1' => $this->shipping_address1,
            'shipping_address2' => $this->shipping_address2,
            'shipping_city' => $this->shipping_city,
            'shipping_state' => $this->shipping_state,
            'shipping_postal_code' => $this->shipping_postal_code,
            'custom_value1' => $this->custom_value1,
            'custom_value2' => $this->custom_value2,
            'custom_value3' => $this->custom_value3,
            'custom_value4' => $this->custom_value4,
            'company_key' => $this->company->company_key,
        ];
    }

    public function getScoutKey()
    {
        return $this->hashed_id;
    }

    // public function getScoutKeyName()
    // {
    //     return 'hashed_id';
    // }

    public function getEntityType()
    {
        return self::class;
    }

    public function ledger(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CompanyLedger::class)->orderBy('id', 'desc');
    }

    public function company_ledger(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function gateway_tokens(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(ClientGatewayToken::class)->orderBy('is_default', 'DESC');
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class)->withTrashed();
    }

    public function projects(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Project::class)->withTrashed();
    }

    /**
     * Retrieves the specific payment token per
     * gateway - per payment method.
     *
     * Allows the storage of multiple tokens
     * per client per gateway per payment_method
     *
     * @param  int $company_gateway_id  The company gateway ID
     * @param  int $payment_method_id   The payment method ID
     * @return ClientGatewayToken       The client token record
     */
    public function gateway_token($company_gateway_id, $payment_method_id)
    {
        return $this->gateway_tokens()
                    ->whereCompanyGatewayId($company_gateway_id)
                    ->whereGatewayTypeId($payment_method_id)
                    ->first();
    }

    public function credits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Credit::class)->withTrashed();
    }

    public function purgeable_activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activity::class)->where('company_id', $this->company_id)->take(50)->orderBy('id', 'desc');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(ClientContact::class)->orderBy('is_primary', 'desc');
    }

    public function primary_contact(): HasMany
    {
        return $this->hasMany(ClientContact::class)->where('is_primary', true);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class)->withTrashed();
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class)->withTrashed();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class)->withTrashed();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->withTrashed();
    }

    public function recurring_invoices(): HasMany
    {
        return $this->hasMany(RecurringInvoice::class)->withTrashed();
    }

    public function recurring_expenses(): HasMany
    {
        return $this->hasMany(RecurringExpense::class)->withTrashed();
    }

    public function shipping_country(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Country::class, 'shipping_country_id', 'id');
    }

    public function system_logs(): HasMany
    {
        return $this->hasMany(SystemLog::class)->take(50)->orderBy('id', 'desc');
    }

    public function timezone(): Timezone
    {
        return Timezone::find($this->getSetting('timezone_id'));
    }

    public function language()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Language> */
        $languages = app('languages');

        return $languages->first(function ($item) {
            return $item->id == $this->getSetting('language_id');
        });
    }

    public function industry(): BelongsTo
    {
        return $this->belongsTo(Industry::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(Size::class);
    }

    public function locale(): string
    {
        if (! $this->language()) {
            return 'en';
        }

        return $this->language()->locale ?: 'en';
    }

    public function date_format()
    {
        /** @var \Illuminate\Support\Collection<DateFormat> */
        $date_formats = app('date_formats');

        return $date_formats->first(function ($item) {
            return $item->id == $this->getSetting('date_format_id');
        })->format;
    }

    public function currency()
    {

        /** @var \Illuminate\Support\Collection<Currency> */
        $currencies = app('currencies');

        return $currencies->first(function ($item) {
            return $item->id == $this->getSetting('currency_id');
        });
    }

    public function service(): ClientService
    {
        return new ClientService($this);
    }

    public function updateBalance($amount): ClientService
    {
        return $this->service()->updateBalance($amount);
    }

    /**
     * Returns the entire filtered set
     * of settings which have been merged from
     * Client > Group > Company levels.
     *
     * @return \stdClass stdClass object of settings
     */
    public function getMergedSettings(): object
    {
        if ($this->group_settings !== null) {
            $group_settings = ClientSettings::buildClientSettings($this->group_settings->settings, $this->settings);

            return ClientSettings::buildClientSettings($this->company->settings, $group_settings);
        }

        return CompanySettings::setProperties(ClientSettings::buildClientSettings($this->company->settings, $this->settings));
    }

    /**
     * Returns a single setting
     * which cascades from
     * Client > Group > Company.
     *
     * @param  string $setting The Setting parameter
     * @return mixed          The setting requested
     */
    public function getSetting($setting): mixed
    {
        /*Client Settings*/
        if ($this->settings && property_exists($this->settings, $setting) && isset($this->settings->{$setting})) {
            /*need to catch empty string here*/
            if (is_string($this->settings->{$setting}) && (iconv_strlen($this->settings->{$setting}) >= 1)) {
                return $this->settings->{$setting};
            } elseif (is_bool($this->settings->{$setting})) {
                return $this->settings->{$setting};
            } elseif (is_int($this->settings->{$setting})) {
                return $this->settings->{$setting};
            } elseif (is_float($this->settings->{$setting})) {
                return $this->settings->{$setting};
            }
        }

        /*Group Settings*/
        if ($this->group_settings && (property_exists($this->group_settings->settings, $setting) !== false) && (isset($this->group_settings->settings->{$setting}) !== false)) {
            return $this->group_settings->settings->{$setting};
        }

        /*Company Settings*/ elseif ((property_exists($this->company->settings, $setting) !== false) && (isset($this->company->settings->{$setting}) !== false)) {
            return $this->company->settings->{$setting};
        } elseif (property_exists(CompanySettings::defaults(), $setting)) {
            return CompanySettings::defaults()->{$setting};
        }

        return '';

    }

    public function getSettingEntity($setting)
    {
        /*Client Settings*/
        if ($this->settings && (property_exists($this->settings, $setting) !== false) && (isset($this->settings->{$setting}) !== false)) {
            /*need to catch empty string here*/
            if (is_string($this->settings->{$setting}) && (iconv_strlen($this->settings->{$setting}) >= 1)) {
                return $this;
            }
        }

        /*Group Settings*/
        if ($this->group_settings && (property_exists($this->group_settings->settings, $setting) !== false) && (isset($this->group_settings->settings->{$setting}) !== false)) {
            return $this->group_settings;
        }

        /*Company Settings*/
        if ((property_exists($this->company->settings, $setting) != false) && (isset($this->company->settings->{$setting}) !== false)) {
            return $this->company;
        }

        throw new \Exception('Could not find a settings object', 1);
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function group_settings(): BelongsTo
    {
        return $this->belongsTo(GroupSetting::class);
    }

    /**
     * Returns the first Credit Card Gateway.
     *
     * @return null|CompanyGateway The Priority Credit Card gateway
     */
    public function getCreditCardGateway(): ?CompanyGateway
    {
        $pms = $this->service()->getPaymentMethods(-1);

        foreach ($pms as $pm) {
            if ($pm['gateway_type_id'] == GatewayType::CREDIT_CARD) {

                $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                if ($cg->gateway_key == '80af24a6a691230bbec33e930ab40666') { //ensure we don't attempt to authorize paypal platform - yet.
                    continue;
                }

                if ($cg && is_object($cg->fees_and_limits) && ! property_exists($cg->fees_and_limits, strval(GatewayType::CREDIT_CARD))) {
                    $fees_and_limits = $cg->fees_and_limits;
                    $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits();
                    $cg->fees_and_limits = $fees_and_limits;
                    $cg->save();
                }

                if ($cg && is_object($cg->fees_and_limits) && $cg->fees_and_limits->{GatewayType::CREDIT_CARD}->is_enabled) {
                    return $cg;
                }
            }
        }

        return null;
    }

    public function getBACSGateway(): ?CompanyGateway
    {
        $pms = $this->service()->getPaymentMethods(-1);

        foreach ($pms as $pm) {
            if ($pm['gateway_type_id'] == GatewayType::BACS) {
                $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                if ($cg && ! property_exists($cg->fees_and_limits, GatewayType::BACS)) { //@phpstan-ignore-line
                    $fees_and_limits = $cg->fees_and_limits;
                    $fees_and_limits->{GatewayType::BACS} = new FeesAndLimits();
                    $cg->fees_and_limits = $fees_and_limits;
                    $cg->save();
                }

                if ($cg && $cg->fees_and_limits->{GatewayType::BACS}->is_enabled) {
                    return $cg;
                }
            }
        }

        return null;
    }

    public function getACSSGateway(): ?CompanyGateway
    {
        $pms = $this->service()->getPaymentMethods(-1);

        foreach ($pms as $pm) {
            if ($pm['gateway_type_id'] == GatewayType::ACSS) {
                $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                if ($cg && ! property_exists($cg->fees_and_limits, GatewayType::ACSS)) { //@phpstan-ignore-line
                    $fees_and_limits = $cg->fees_and_limits;
                    $fees_and_limits->{GatewayType::ACSS} = new FeesAndLimits();
                    $cg->fees_and_limits = $fees_and_limits;
                    $cg->save();
                }

                if ($cg && $cg->fees_and_limits->{GatewayType::ACSS}->is_enabled) {
                    return $cg;
                }
            }
        }

        return null;
    }


    //todo refactor this  - it is only searching for existing tokens
    public function getBankTransferGateway(): ?CompanyGateway
    {
        $pms = $this->service()->getPaymentMethods(-1);

        if ($this->currency()->code == 'USD' && in_array(GatewayType::BANK_TRANSFER, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::BANK_TRANSFER) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && ! property_exists($cg->fees_and_limits, GatewayType::BANK_TRANSFER)) { //@phpstan-ignore-line
                        $fees_and_limits = $cg->fees_and_limits;
                        $fees_and_limits->{GatewayType::BANK_TRANSFER} = new FeesAndLimits();
                        $cg->fees_and_limits = $fees_and_limits;
                        $cg->save();
                    }

                    if ($cg && $cg->fees_and_limits->{GatewayType::BANK_TRANSFER}->is_enabled) {
                        return $cg;
                    }
                }
            }
        }

        if ($this->currency()->code == 'EUR' && (in_array(GatewayType::BANK_TRANSFER, array_column($pms, 'gateway_type_id')) || in_array(GatewayType::SEPA, array_column($pms, 'gateway_type_id')))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::SEPA) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::SEPA}->is_enabled) {
                        return $cg;
                    }
                }
            }
        }

        if (in_array(GatewayType::DIRECT_DEBIT, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::DIRECT_DEBIT) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::DIRECT_DEBIT}->is_enabled) {
                        return $cg;
                    }
                }
            }
        }

        if (in_array($this->currency()->code, ['CAD','USD']) && in_array(GatewayType::ACSS, array_column($pms, 'gateway_type_id'))) {
            // if ($this->currency()->code == 'CAD' && in_array(GatewayType::ACSS, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::ACSS) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::ACSS}->is_enabled) {
                        return $cg;
                    }
                }
            }
        }


        if (in_array($this->currency()->code, ['GBP']) && in_array(GatewayType::BACS, array_column($pms, 'gateway_type_id'))) {
            // if ($this->currency()->code == 'CAD' && in_array(GatewayType::ACSS, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::BACS) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::BACS}->is_enabled) {
                        return $cg;
                    }
                }
            }
        }

        return null;
    }

    public function getBankTransferMethodType()
    {


        $pms = $this->service()->getPaymentMethods(-1);

        if ($this->currency()->code == 'USD' && in_array(GatewayType::BANK_TRANSFER, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::BANK_TRANSFER) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && ! property_exists($cg->fees_and_limits, GatewayType::BANK_TRANSFER)) { //@phpstan-ignore-line
                        $fees_and_limits = $cg->fees_and_limits;
                        $fees_and_limits->{GatewayType::BANK_TRANSFER} = new FeesAndLimits();
                        $cg->fees_and_limits = $fees_and_limits;
                        $cg->save();
                    }

                    if ($cg && $cg->fees_and_limits->{GatewayType::BANK_TRANSFER}->is_enabled) {
                        return GatewayType::BANK_TRANSFER;
                    }
                }
            }
        }

        if ($this->currency()->code == 'EUR' && (in_array(GatewayType::BANK_TRANSFER, array_column($pms, 'gateway_type_id')) || in_array(GatewayType::SEPA, array_column($pms, 'gateway_type_id'))  || in_array(GatewayType::DIRECT_DEBIT, array_column($pms, 'gateway_type_id')))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::SEPA) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::SEPA}->is_enabled) {
                        return GatewayType::SEPA;
                    } elseif ($cg && $cg->fees_and_limits->{GatewayType::BANK_TRANSFER}->is_enabled) {
                        return GatewayType::BANK_TRANSFER;
                    } elseif ($cg && $cg->fees_and_limits->{GatewayType::DIRECT_DEBIT}->is_enabled) {
                        return GatewayType::DIRECT_DEBIT;
                    }

                }
            }
        }

        if (in_array(GatewayType::DIRECT_DEBIT, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::DIRECT_DEBIT) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::DIRECT_DEBIT}->is_enabled) {
                        return GatewayType::DIRECT_DEBIT;
                    }
                }
            }
        }

        if (in_array($this->currency()->code, ['CAD','USD']) && in_array(GatewayType::ACSS, array_column($pms, 'gateway_type_id'))) {
            // if ($this->currency()->code == 'CAD' && in_array(GatewayType::ACSS, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::ACSS) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::ACSS}->is_enabled) {
                        return GatewayType::ACSS;
                    }
                }
            }
        }


        if (in_array($this->currency()->code, ['GBP']) && in_array(GatewayType::BACS, array_column($pms, 'gateway_type_id'))) {
            // if ($this->currency()->code == 'CAD' && in_array(GatewayType::ACSS, array_column($pms, 'gateway_type_id'))) {
            foreach ($pms as $pm) {
                if ($pm['gateway_type_id'] == GatewayType::BACS) {
                    $cg = CompanyGateway::query()->find($pm['company_gateway_id']);

                    if ($cg && $cg->fees_and_limits->{GatewayType::BACS}->is_enabled) {
                        return GatewayType::BACS;
                    }
                }
            }
        }

        return null;




        // if ($this->currency()->code == 'USD') {
        //     return GatewayType::BANK_TRANSFER;
        // }

        // if ($this->currency()->code == 'EUR') {
        //     return GatewayType::SEPA;
        // }

        // //Special handler for GoCardless
        // if($this->currency()->code == 'CAD' && ($this->getBankTransferGateway()->gateway_key == 'b9886f9257f0c6ee7c302f1c74475f6c') ?? false) {
        //     return GatewayType::DIRECT_DEBIT;
        // }

        // if (in_array($this->currency()->code, ['EUR', 'GBP','DKK','SEK','AUD','NZD','USD'])) {
        //     return GatewayType::DIRECT_DEBIT;
        // }

        // if(in_array($this->currency()->code, ['CAD'])) {
        //     return GatewayType::ACSS;
        // }
    }

    public function getCurrencyCode(): string
    {
        if ($this->currency()) {
            return $this->currency()->code;
        }

        return 'USD';
    }

    public function validGatewayForAmount($fees_and_limits_for_payment_type, $amount): bool
    {
        if (isset($fees_and_limits_for_payment_type)) {
            $fees_and_limits = $fees_and_limits_for_payment_type;
        } else {
            return true;
        }

        if ((property_exists($fees_and_limits, 'min_limit')) && $fees_and_limits->min_limit !== null && $fees_and_limits->min_limit != -1 && $amount < $fees_and_limits->min_limit) {
            return false;
        }

        if ((property_exists($fees_and_limits, 'max_limit')) && $fees_and_limits->max_limit !== null && $fees_and_limits->max_limit != -1 && $amount > $fees_and_limits->max_limit) {
            return false;
        }

        return true;
    }

    public function preferredLocale()
    {
        $this->language()->locale ?? 'en';
    }

    public function backup_path(): string
    {
        return $this->company->company_key.'/'.$this->client_hash.'/backups';
    }

    public function invoice_filepath($invitation): string
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/invoices/';
    }
    public function e_document_filepath($invitation): string
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/e_invoice/';
    }

    public function quote_filepath($invitation): string
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/quotes/';
    }

    public function credit_filepath($invitation): string
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/credits/';
    }

    public function recurring_invoice_filepath($invitation): string
    {
        $contact_key = $invitation->contact->contact_key;

        return $this->company->company_key.'/'.$this->client_hash.'/'.$contact_key.'/recurring_invoices/';
    }

    public function company_filepath(): string
    {
        return $this->company->company_key.'/';
    }

    public function document_filepath(): string
    {
        return $this->company->company_key.'/documents/';
    }

    public function setCompanyDefaults($data, $entity_name): array
    {
        $defaults = [];

        $terms = &$data['terms'];
        $footer = &$data['footer'];

        if (empty($terms)) {
            $defaults['terms'] = $this->getSetting($entity_name.'_terms');
        } elseif ($terms) {
            $defaults['terms'] = $data['terms'];
        }

        if (empty($footer)) {
            $defaults['footer'] = $this->getSetting($entity_name.'_footer');
        } elseif ($footer) {
            $defaults['footer'] = $data['footer'];
        }

        if (strlen($this->public_notes ?? '') >= 1) {
            $defaults['public_notes'] = $this->public_notes;
        }

        $exchange_rate = new CurrencyApi();
        $defaults['exchange_rate'] = 1 / $exchange_rate->exchangeRate($this->getSetting('currency_id'), $this->company->settings->currency_id);

        return $defaults;
    }

    public function setExchangeRate()
    {

        $converter = new CurrencyApi();

        return 1 / $converter->convert(1, $this->currency()->id, $this->company->settings->currency_id);

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

        if ($entity_send_time == 0) { //Send UTC time
            return 0;
        } elseif ($entity_send_time == 24) { // Step back a few seconds to ensure we do not send exactly at hour 24 as that will be the next day - technically.
            $offset -= 10;
        }

        $offset -= $this->company->utc_offset();

        $offset += ($entity_send_time * 3600);

        return $offset;
    }

    public function transaction_event()
    {
        $client = $this->fresh();

        return [
            'client_id' => $client->id,
            'client_balance' => $client->balance ?: 0,
            'client_paid_to_date' => $client->paid_to_date ?: 0,
            'client_credit_balance' => $client->credit_balance ?: 0,
        ];
    }

    public function translate_entity(): string
    {
        return ctrans('texts.client');
    }

    public function portalUrl(bool $use_react_url): string
    {
        return $use_react_url ? config('ninja.react_url'). "/#/clients/{$this->hashed_id}" : config('ninja.app_url');
    }

    /**
     * peppolSendingEnabled
     *
     * Determines the sending status of the company
     *
     * @return bool
     */
    public function peppolSendingEnabled(): bool
    {
        return $this->getSetting('e_invoice_type') == 'PEPPOL' && $this->company->peppolSendingEnabled();

    }
}

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

use App\Events\Payment\PaymentWasRefunded;
use App\Events\Payment\PaymentWasVoided;
use App\Services\Ledger\LedgerService;
use App\Services\Payment\PaymentService;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\Inviteable;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Payment\Refundable;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Payment
 *
 * @property int $id
 * @property int $company_id
 * @property int $client_id
 * @property int $category_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int|null $user_id
 * @property int|null $assigned_user_id
 * @property int|null $client_contact_id
 * @property int|null $invitation_id
 * @property int|null $company_gateway_id
 * @property int|null $gateway_type_id
 * @property int|null $type_id
 * @property int $status_id
 * @property float $amount
 * @property float $refunded
 * @property float $applied
 * @property string|null $date
 * @property string|null $transaction_reference
 * @property string|null $payer_id
 * @property string|null $number
 * @property string|null $private_notes
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property bool $is_deleted
 * @property bool $is_manual
 * @property float $exchange_rate
 * @property int $currency_id
 * @property int|null $exchange_currency_id
 * @property \App\Models\Paymentable $paymentable
 * @property object|null $meta
 * @property array|null $refund_meta
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $transaction_id
 * @property string|null $idempotency_key
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read \App\Models\CompanyGateway|null $company_gateway
 * @property-read \App\Models\ClientContact|null $contact
 * @property-read \App\Models\Currency|null $currency
 * @property-read \App\Models\Currency|null $exchange_currency
 * @property-read \App\Models\GatewayType|null $gateway_type
 * @property-read mixed $hashed_id
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\PaymentType|null $type
 * @property-read \App\Models\User|null $user
 * @property-read \App\Models\Vendor|null $vendor
 * @method static \Database\Factories\PaymentFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Payment filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Payment withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment>|\Illuminate\Support\Collection $paymentables
 * @mixin \Eloquent
 */
class Payment extends BaseModel
{
    use MakesHash;
    use Filterable;
    use SoftDeletes;
    use Refundable;
    use Inviteable;

    public const STATUS_PENDING = 1;

    public const STATUS_CANCELLED = 2;

    public const STATUS_FAILED = 3;

    public const STATUS_COMPLETED = 4;

    public const STATUS_PARTIALLY_REFUNDED = 5;

    public const STATUS_REFUNDED = 6;

    public const TYPE_CREDIT_CARD = 1;

    public const TYPE_BANK_TRANSFER = 2;

    public const TYPE_PAYPAL = 3;

    public const TYPE_CRYPTO = 4;

    public const TYPE_DWOLLA = 5;

    public const TYPE_CUSTOM1 = 6;

    public const TYPE_ALIPAY = 7;

    public const TYPE_SOFORT = 8;

    public const TYPE_SEPA = 9;

    public const TYPE_GOCARDLESS = 10;

    public const TYPE_APPLE_PAY = 11;

    public const TYPE_CUSTOM2 = 12;

    public const TYPE_CUSTOM3 = 13;

    public const TYPE_TOKEN = 'token';

    protected $fillable = [
        'assigned_user_id',
        'client_id',
        'type_id',
        'amount',
        'date',
        'transaction_reference',
        'number',
        'exchange_currency_id',
        'exchange_rate',
        'private_notes',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'category_id',
        'idempotency_key',
    ];

    protected $casts = [
        'exchange_rate' => 'float',
        'settings' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_deleted' => 'bool',
        'meta' => 'object',
        'refund_meta' => 'array',
    ];

    protected $with = [
        'paymentables',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function company_gateway(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CompanyGateway::class)->withTrashed();
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function contact(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(ClientContact::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function invoices(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(Invoice::class, 'paymentable')->withTrashed()->withPivot('amount', 'refunded', 'deleted_at')->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function credits(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphedByMany(Credit::class, 'paymentable')->withTrashed()->withPivot('amount', 'refunded', 'deleted_at')->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function company_ledger(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(PaymentType::class);
    }

    public function currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function transaction(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(BankTransaction::class)->withTrashed();
    }

    public function exchange_currency(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Currency::class, 'exchange_currency_id', 'id');
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function translatedType(): string
    {
        if (! $this->type_id) {
            return '';
        }

        $pt = new PaymentType();

        return $pt->name($this->type_id);
    }

    public function gateway_type(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(GatewayType::class);
    }

    public function paymentables(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Paymentable::class);
    }

    public function formattedAmount()
    {
        return Number::formatMoney($this->amount, $this->client);
    }

    public function formatAmount(float $amount): string
    {
        return Number::formatMoney($amount, $this->client);
    }

    public function clientPaymentDate()
    {
        if (! $this->date) {
            return '';
        }

        $date_format = DateFormat::find($this->client->getSetting('date_format_id'));

        return $this->createClientDate($this->date, $this->client->timezone()->name)->format($date_format->format);
    }

    public function badgeForStatus(): string
    {
        switch ($this->status_id) {
            case self::STATUS_PENDING:
                return '<h6><span class="badge badge-secondary">'.ctrans('texts.payment_status_1').'</span></h6>';
            case self::STATUS_CANCELLED:
                return '<h6><span class="badge badge-warning text-white">'.ctrans('texts.payment_status_2').'</span></h6>';
            case self::STATUS_FAILED:
                return '<h6><span class="badge badge-danger">'.ctrans('texts.payment_status_3').'</span></h6>';
            case self::STATUS_COMPLETED:

                if ($this->amount > $this->applied) {
                    return '<h6><span class="badge badge-info">' . ctrans('texts.partially_unapplied') . '</span></h6>';
                }

                return '<h6><span class="badge badge-info">'.ctrans('texts.payment_status_4').'</span></h6>';
            case self::STATUS_PARTIALLY_REFUNDED:
                return '<h6><span class="badge badge-success">'.ctrans('texts.payment_status_5').'</span></h6>';
            case self::STATUS_REFUNDED:
                return '<h6><span class="badge badge-primary">'.ctrans('texts.payment_status_6').'</span></h6>';
            default:
                return '';
        }
    }

    public static function stringStatus(int $status): string
    {
        switch ($status) {
            case self::STATUS_PENDING:
                return ctrans('texts.payment_status_1');
            case self::STATUS_CANCELLED:
                return ctrans('texts.payment_status_2');
            case self::STATUS_FAILED:
                return ctrans('texts.payment_status_3');
            case self::STATUS_COMPLETED:
                return ctrans('texts.payment_status_4');
            case self::STATUS_PARTIALLY_REFUNDED:
                return ctrans('texts.payment_status_5');
            case self::STATUS_REFUNDED:
                return ctrans('texts.payment_status_6');
            default:
                return '';
        }
    }

    public function ledger(): LedgerService
    {
        return new LedgerService($this);
    }

    public function service(): PaymentService
    {
        return new PaymentService($this);
    }

    /**
     * $data = [
            'id' => $payment->id,
            'amount' => 10,
            'invoices' => [
                [
                    'invoice_id' => $invoice->id,
                    'amount' => 10,
                ],
            ],
            'date' => '2020/12/12',
            'gateway_refund' => false,
            'email_receipt' => false,
        ];
     *
     * @param array $data
     * @return self
     */
    public function refund(array $data): self
    {
        return $this->service()->refundPayment($data);
    }

    /**
     * @return float
     */
    public function getCompletedAmount(): float
    {
        return $this->amount - $this->refunded;
    }

    public function recordRefund($amount = null)
    {
        //do i need $this->isRefunded() here?
        if ($this->isVoided()) {
            return false;
        }

        //if no refund specified
        if (! $amount) {
            $amount = $this->amount;
        }

        $new_refund = min($this->amount, $this->refunded + $amount);
        $refund_change = $new_refund - $this->refunded;

        if ($refund_change) {
            $this->refunded = $new_refund;
            $this->status_id = $this->refunded == $this->amount ? self::STATUS_REFUNDED : self::STATUS_PARTIALLY_REFUNDED;
            $this->save();

            event(new PaymentWasRefunded($this, $refund_change, $this->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }

        return true;
    }

    public function isVoided()
    {
        return $this->status_id == self::STATUS_CANCELLED;
    }

    public function isPartiallyRefunded()
    {
        return $this->status_id == self::STATUS_PARTIALLY_REFUNDED;
    }

    public function isRefunded()
    {
        return $this->status_id == self::STATUS_REFUNDED;
    }

    public function setStatus($status)
    {
        $this->status_id = $status;
        $this->save();
    }

    public function markVoided()
    {
        if ($this->isVoided() || $this->isPartiallyRefunded() || $this->isRefunded()) {
            return false;
        }

        $this->refunded = $this->amount;
        $this->status_id = self::STATUS_CANCELLED;
        $this->save();

        event(new PaymentWasVoided($this, $this->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
    }

    public function getLink(): string
    {

        if (Ninja::isHosted()) {
            $domain = $this->company->domain();
        } else {
            $domain = strlen($this->company->portal_domain) > 5 ? $this->company->portal_domain : config('ninja.app_url');
        }

        return $domain.'/client/payment/'.$this->client->contacts()->first()->contact_key.'/'.$this->hashed_id.'?next=/client/payments/'.$this->hashed_id;
    }

    public function transaction_event()
    {
        $payment = $this->fresh();

        return [
            'payment_id' => $payment->id,
            'payment_amount' => $payment->amount ?: 0,
            'payment_applied' => $payment->applied ?: 0,
            'payment_refunded' => $payment->refunded ?: 0,
            'payment_status' => $payment->status_id ?: 1,
            'paymentables' => $payment->paymentables->toArray(),
            'payment_request' => [],
        ];
    }

    public function translate_entity(): string
    {
        return ctrans('texts.payment');
    }

    public function portalUrl($use_react_url): string
    {
        return $use_react_url ? config('ninja.react_url')."/#/payments/{$this->hashed_id}/edit" : config('ninja.app_url');
    }

    public function setRefundMeta(array $data)
    {
        $tmp_meta = $this->refund_meta ?? [];
        $tmp_meta[] = $data;

        $this->refund_meta = $tmp_meta;
    }
}

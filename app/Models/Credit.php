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

use App\Utils\Number;
use Laravel\Scout\Searchable;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\Helpers\Invoice\InvoiceSum;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\MakesReminders;
use App\Services\Credit\CreditService;
use App\Services\Ledger\LedgerService;
use App\Utils\Traits\MakesInvoiceValues;
use Laracasts\Presenter\PresentableTrait;
use App\Models\Presenters\CreditPresenter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * App\Models\Credit
 *
 * @property int $id
 * @property object|null $e_invoice
 * @property int $client_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int $status_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int|null $recurring_id
 * @property int|null $design_id
 * @property int|null $invoice_id
 * @property string|null $number
 * @property float $discount
 * @property bool $is_amount_discount
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $last_sent_date
 * @property string|null $due_date
 * @property bool $is_deleted
 * @property array|null $line_items
 * @property object|null $backup
 * @property string|null $footer
 * @property string|null $public_notes
 * @property string|null $private_notes
 * @property string|null $terms
 * @property string|null $tax_name1
 * @property float $tax_rate1
 * @property string|null $tax_name2
 * @property float $tax_rate2
 * @property string|null $tax_name3
 * @property float $tax_rate3
 * @property string $total_taxes
 * @property bool $uses_inclusive_taxes
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $next_send_date
 * @property string|null $custom_surcharge1
 * @property string|null $custom_surcharge2
 * @property string|null $custom_surcharge3
 * @property string|null $custom_surcharge4
 * @property int $custom_surcharge_tax1
 * @property int $custom_surcharge_tax2
 * @property int $custom_surcharge_tax3
 * @property int $custom_surcharge_tax4
 * @property float $exchange_rate
 * @property float $amount
 * @property float $balance
 * @property float|null $partial
 * @property string|null $partial_due_date
 * @property string|null $last_viewed
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $reminder1_sent
 * @property string|null $reminder2_sent
 * @property string|null $reminder3_sent
 * @property string|null $reminder_last_sent
 * @property float $paid_to_date
 * @property int|null $subscription_id
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property int|null $activities_count
 * @property \App\Models\User|null $assigned_user
 * @property \App\Models\Client $client
 * @property \App\Models\Company $company
 * @property \App\Models\CreditInvitation $invitation
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property int|null $company_ledger_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property int|null $documents_count
 * @property mixed $hashed_id
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property int|null $history_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditInvitation> $invitations
 * @property int|null $invitations_count
 * @property \App\Models\Invoice|null $invoice
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property int|null $invoices_count
 * @property \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property int|null $payments_count
 * @property \App\Models\Project|null $project
 * @property \App\Models\User $user
 * @property \App\Models\Client $client
 * @property \App\Models\Vendor|null $vendor
 * @property-read mixed $pivot
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CreditInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Invoice> $invoices
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 *
 * @mixin \Eloquent
 */
class Credit extends BaseModel
{
    use MakesHash;
    use Filterable;
    use SoftDeletes;
    use PresentableTrait;
    use MakesInvoiceValues;
    use MakesReminders;
    use Searchable;
    
    protected $presenter = CreditPresenter::class;

    protected $fillable = [
        'number',
        'discount',
        'po_number',
        'date',
        'due_date',
        'terms',
        'public_notes',
        'private_notes',
        'tax_name1',
        'tax_rate1',
        'tax_name2',
        'tax_rate2',
        'tax_name3',
        'tax_rate3',
        'is_amount_discount',
        'partial',
        'partial_due_date',
        'project_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'line_items',
        'client_id',
        'footer',
        'custom_surcharge1',
        'custom_surcharge2',
        'custom_surcharge3',
        'custom_surcharge4',
        'design_id',
        'assigned_user_id',
        'exchange_rate',
        'subscription_id',
        'vendor_id',
    ];

    protected $casts = [
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_amount_discount' => 'bool',
        'e_invoice' => 'object',

    ];

    protected $touches = [];

    public const STATUS_DRAFT = 1;

    public const STATUS_SENT = 2;

    public const STATUS_PARTIAL = 3;

    public const STATUS_APPLIED = 4;

    public function toSearchableArray()
    {
        $locale = $this->company->locale();
        App::setLocale($locale);

        return [
            'id' => $this->id,
            'name' => ctrans('texts.credit') . " " . $this->number . " | " . $this->client->present()->name() .  ' | ' . Number::formatMoney($this->amount, $this->company) . ' | ' . $this->translateDate($this->date, $this->company->date_format(), $locale),
            'hashed_id' => $this->hashed_id,
            'number' => $this->number,
            'is_deleted' => $this->is_deleted,
            'amount' => (float) $this->amount,
            'balance' => (float) $this->balance,
            'due_date' => $this->due_date,
            'date' => $this->date,
            'custom_value1' => (string)$this->custom_value1,
            'custom_value2' => (string)$this->custom_value2,
            'custom_value3' => (string)$this->custom_value3,
            'custom_value4' => (string)$this->custom_value4,
            'company_key' => $this->company->company_key,
            'po_number' => (string)$this->po_number,
        ];
    }

    public function getScoutKey()
    {
        return $this->hashed_id;
    }

    public function getEntityType()
    {
        return self::class;
    }

    public function getDateAttribute($value)
    {
        return $this->dateMutator($value);
    }

    public function getDueDateAttribute($value)
    {
        return $value ? $this->dateMutator($value) : null;
    }

    public function getPartialDueDateAttribute($value)
    {
        return $this->dateMutator($value);
    }

    public function assigned_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function history(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activity::class)->where('company_id', $this->company_id)->where('client_id', $this->client_id)->orderBy('id', 'DESC')->take(50);
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(CreditInvitation::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    /**
     * The invoice which the credit has been created from.
     */
    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function company_ledger()
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function ledger(): \App\Services\Ledger\LedgerService
    {
        return new LedgerService($this);
    }

    /**
     * The invoice/s which the credit has
     * been applied to.
     */
    public function invoices(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Invoice::class)->using(Paymentable::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function payments()
    {
        return $this->morphToMany(Payment::class, 'paymentable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Access the invoice calculator object.
     *
     * @return InvoiceSumInclusive | InvoiceSum The invoice calculator object getters
     */
    public function calc(): InvoiceSumInclusive | InvoiceSum
    {
        $credit_calc = null;

        if ($this->uses_inclusive_taxes) {
            $credit_calc = new InvoiceSumInclusive($this);
        } else {
            $credit_calc = new InvoiceSum($this);
        }

        return $credit_calc->build();
    }

    public function service(): \App\Services\Credit\CreditService
    {
        return new CreditService($this);
    }

    /**
     * @param float $balance_adjustment
     */
    public function updateBalance($balance_adjustment)
    {
        if ($this->is_deleted) {
            return;
        }

        $balance_adjustment = floatval($balance_adjustment);

        $this->balance = $this->balance + $balance_adjustment;

        if ($this->balance == 0) {
            $this->status_id = self::STATUS_APPLIED;
            $this->saveQuietly();

            return;
        }

        $this->saveQuietly();
    }

    public function setStatus($status)
    {
        $this->status_id = $status;
        $this->saveQuietly();
    }

    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->saveQuietly();
            }
        });
    }

    public function transaction_event()
    {
        $credit = $this->fresh();

        return [
            'credit_id' => $credit->id,
            'credit_amount' => $credit->amount ?: 0,
            'credit_balance' => $credit->balance ?: 0,
            'credit_status' => $credit->status_id ?: 1,
        ];
    }

    public function translate_entity(): string
    {
        return ctrans('texts.credit');
    }

    public static function stringStatus(int $status): string
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return ctrans('texts.draft');
            case self::STATUS_SENT:
                return ctrans('texts.sent');
            case self::STATUS_PARTIAL:
                return ctrans('texts.partial');
            case self::STATUS_APPLIED:
                return ctrans('texts.applied');
            default:
                return ctrans('texts.sent');
        }
    }
}

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

use App\Utils\Ninja;
use Laravel\Scout\Searchable;
use Illuminate\Support\Carbon;
use App\DataMapper\InvoiceSync;
use App\Utils\Traits\MakesDates;
use App\Helpers\Invoice\InvoiceSum;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\MakesReminders;
use App\Utils\Traits\NumberFormatter;
use App\Services\Ledger\LedgerService;
use App\Services\Invoice\InvoiceService;
use App\Utils\Traits\MakesInvoiceValues;
use App\Events\Invoice\InvoiceWasEmailed;
use Laracasts\Presenter\PresentableTrait;
use App\Models\Presenters\EntityPresenter;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Utils\Traits\Invoice\ActionsInvoice;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Events\Invoice\InvoiceReminderWasEmailed;
use App\Utils\Number;

/**
 * App\Models\Invoice
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
 * @property string|null $number
 * @property float $discount
 * @property bool $is_amount_discount
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $last_sent_date
 * @property string|null $due_date
 * @property bool $is_deleted
 * @property object|array|string $line_items
 * @property object|null $backup
 * @property object|null $sync
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
 * @property float $total_taxes
 * @property bool $uses_inclusive_taxes
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property string|null $next_send_date
 * @property float|null $custom_surcharge1
 * @property float|null $custom_surcharge2
 * @property float|null $custom_surcharge3
 * @property float|null $custom_surcharge4
 * @property bool $custom_surcharge_tax1
 * @property bool $custom_surcharge_tax2
 * @property bool $custom_surcharge_tax3
 * @property bool $custom_surcharge_tax4
 * @property float $exchange_rate
 * @property float $amount
 * @property float $balance
 * @property float|null $partial
 * @property string|null|\Carbon\Carbon $partial_due_date
 * @property string|null $last_viewed
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $reminder1_sent
 * @property string|null $reminder2_sent
 * @property string|null $reminder3_sent
 * @property string|null $reminder_last_sent
 * @property bool $auto_bill_enabled
 * @property float $paid_to_date
 * @property int|null $subscription_id
 * @property int $auto_bill_tries
 * @property bool $is_proforma
 * @property-read int|null $activities_count
 * @property \App\Models\User|null $assigned_user
 * @property \App\Models\Client $client
 * @property \App\Models\InvoiceInvitation $invitation
 * @property-read \App\Models\Company $company
 * @property-read int|null $company_ledger_count
 * @property-read int|null $credits_count
 * @property \App\Models\Design|null $design
 * @property-read int|null $documents_count
 * @property-read \App\Models\Expense|null $expense
 * @property-read int|null $expenses_count
 * @property-read mixed $balance_due
 * @property-read mixed $hashed_id
 * @property-read mixed $status
 * @property-read mixed $total
 * @property-read int|null $history_count
 * @property-read int|null $invitations_count
 * @property-read int|null $payments_count
 * @property-read mixed $pivot
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\RecurringInvoice|null $recurring_invoice
 * @property-read \App\Models\Subscription|null $subscription
 * @property-read \App\Models\Task|null $task
 * @property-read int|null $tasks_count
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\CompanyLedger> $company_ledger
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Credit> $credits
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Expense> $expenses
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\InvoiceInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Payment> $payments
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Task> $tasks
 * @property object|null $tax_data
 * @mixin \Eloquent
 */
class Invoice extends BaseModel
{
    use SoftDeletes;
    use Filterable;
    use NumberFormatter;
    use MakesDates;
    use PresentableTrait;
    use MakesInvoiceValues;
    use MakesReminders;
    use ActionsInvoice;
    use Searchable;

    protected $presenter = EntityPresenter::class;

    protected $touches = [];

    protected $hidden = [
        'id',
        'private_notes',
        'user_id',
        'client_id',
        'company_id',
    ];

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
        'auto_bill_enabled',
        'uses_inclusive_taxes',
        'vendor_id',
    ];

    protected $casts = [
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_deleted' => 'bool',
        'is_amount_discount' => 'bool',
        'tax_data' => 'object',
        'partial_due_date' => 'date:Y-m-d',
        'custom_surcharge_tax1' => 'bool',
        'custom_surcharge_tax2' => 'bool',
        'custom_surcharge_tax3' => 'bool',
        'custom_surcharge_tax4' => 'bool',
        'e_invoice' => 'object',
        'sync' => InvoiceSync::class,

    ];

    protected $with = [];

    protected $appends = [
        'hashed_id',
        'status',
    ];

    public const STATUS_DRAFT = 1;

    public const STATUS_SENT = 2;

    public const STATUS_PARTIAL = 3;

    public const STATUS_PAID = 4;

    public const STATUS_CANCELLED = 5;

    public const STATUS_REVERSED = 6;

    public const STATUS_OVERDUE = -1; //status < 4 || < 3 && !is_deleted && !trashed() && due_date < now()

    public const STATUS_UNPAID = -2; //status < 4 || < 3 && !is_deleted && !trashed()

    public function toSearchableArray()
    {
        $locale = $this->company->locale();
        App::setLocale($locale);

        return [
            'name' => ctrans('texts.invoice') . " " . $this->number . " | " . $this->client->present()->name() .  ' | ' . Number::formatMoney($this->amount, $this->company) . ' | ' . $this->translateDate($this->date, $this->company->date_format(), $locale),
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

    // public function getPartialDueDateAttribute($value)
    // {
    //     return $value ? $this->dateMutator($value) : null;
    // }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function design(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Design::class);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function recurring_invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(RecurringInvoice::class, 'recurring_id', 'id')->withTrashed();
    }

    public function assigned_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(InvoiceInvitation::class);
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function subscription(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Subscription::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function payments(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(Payment::class, 'paymentable')->withTrashed()->withPivot('amount', 'refunded', 'deleted_at')->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphToMany
     */
    public function net_payments(): \Illuminate\Database\Eloquent\Relations\MorphToMany
    {
        return $this->morphToMany(Payment::class, 'paymentable')->withTrashed()->where('is_deleted', 0)->withPivot('amount', 'refunded', 'deleted_at')->withTimestamps();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function company_ledger(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(CompanyLedger::class, 'company_ledgerable');
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->where('company_id', $this->company_id)->where('client_id', $this->client_id)->orderBy('id', 'DESC')->take(50);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function history(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function credits(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Credit::class);
    }

    public function tasks(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function task(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Task::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function quote(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Quote::class);
    }

    public function expenses(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function expense(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Expense::class);
    }

    /**
     * Service entry points.
     *
     * @return InvoiceService
     */
    public function service(): InvoiceService
    {
        return new InvoiceService($this);
    }

    public function ledger()
    {
        return new LedgerService($this);
    }

    /* ---------------- */
    /* Settings getters */
    /* ---------------- */

    public function getStatusAttribute()
    {
        $due_date = $this->due_date ? Carbon::parse($this->due_date) : false;
        $partial_due_date = $this->partial_due_date ? Carbon::parse($this->partial_due_date) : false;

        if ($this->status_id == self::STATUS_SENT && $due_date && $due_date->gt(now())) {
            return self::STATUS_UNPAID;
        } elseif ($this->status_id == self::STATUS_PARTIAL && $partial_due_date && $partial_due_date->gt(now())) {
            return self::STATUS_PARTIAL;
        } elseif ($this->status_id == self::STATUS_SENT && $due_date && $due_date->lt(now())) {
            return self::STATUS_OVERDUE;
        } elseif ($this->status_id == self::STATUS_PARTIAL && $partial_due_date && $partial_due_date->lt(now())) {
            return self::STATUS_OVERDUE;
        } else {
            return $this->status_id;
        }
    }

    public function isPayable(): bool
    {
        if($this->is_deleted || $this->status_id == self::STATUS_PAID || $this->balance < 0) {
            return false;
        } elseif ($this->status_id == self::STATUS_DRAFT && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_SENT && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_PARTIAL && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_SENT && $this->is_deleted == false) {
            return true;
        } elseif ($this->status_id == self::STATUS_DRAFT && $this->is_deleted == false) {
            return true;
        } else {
            return false;
        }
    }

    public function isRefundable(): bool
    {
        if ($this->is_deleted) {
            return false;
        }

        if (($this->amount - $this->balance) == 0) {
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    public function isPartial(): bool
    {
        return $this->status_id >= self::STATUS_PARTIAL;
    }

    /**
     * @return bool
     */
    public function hasPartial(): bool
    {
        return ($this->partial && $this->partial > 0) === true;
    }

    public static function badgeForStatus(int $status): string
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
            case self::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';
            case self::STATUS_PARTIAL:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.partial').'</span></h5>';
            case self::STATUS_PAID:
                return '<h5><span class="badge badge-success">'.ctrans('texts.paid').'</span></h5>';
            case self::STATUS_CANCELLED:
                return '<h5><span class="badge badge-secondary">'.ctrans('texts.cancelled').'</span></h5>';
            case self::STATUS_OVERDUE:
                return '<h5><span class="badge badge-danger">'.ctrans('texts.overdue').'</span></h5>';
            case self::STATUS_UNPAID:
                return '<h5><span class="badge badge-warning text-white">'.ctrans('texts.unpaid').'</span></h5>';
            case self::STATUS_REVERSED:
                return '<h5><span class="badge badge-info">'.ctrans('texts.reversed').'</span></h5>';
            default:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.sent').'</span></h5>';

        }
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
            case self::STATUS_PAID:
                return ctrans('texts.paid');
            case self::STATUS_CANCELLED:
                return ctrans('texts.cancelled');
            case self::STATUS_OVERDUE:
                return ctrans('texts.overdue');
            case self::STATUS_UNPAID:
                return ctrans('texts.unpaid');
            case self::STATUS_REVERSED:
                return ctrans('texts.reversed');
            default:
                return ctrans('texts.sent');
        }
    }

    /**
     * Access the invoice calculator object.
     *
     * @return InvoiceSumInclusive | InvoiceSum The invoice calculator object getters
     */
    public function calc(): InvoiceSumInclusive | InvoiceSum
    {
        $invoice_calc = null;

        if ($this->uses_inclusive_taxes) {
            $invoice_calc = new InvoiceSumInclusive($this);
        } else {
            $invoice_calc = new InvoiceSum($this);
        }

        return $invoice_calc->build();
    }

    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->load('invoice');
                $invitation->sent_date = Carbon::now();
                $invitation->save();
            }
        });
    }

    /**
     * Filtering logic to determine
     * whether an invoice is locked
     * based on the current status of the invoice.
     * @return bool
     */
    public function isLocked(): bool
    {
        $locked_status = $this->client->getSetting('lock_invoices');

        switch ($locked_status) {
            case 'off':
                return false;
            case 'when_sent':
                return $this->status_id >= self::STATUS_SENT;
            case 'when_paid':
                return $this->status_id == self::STATUS_PAID || $this->status_id == self::STATUS_PARTIAL;
            case 'end_of_month':
                return \Carbon\Carbon::parse($this->date)->setTimezone($this->company->timezone()->name)->endOfMonth()->lte(now());
            default:
                return false;
        }
    }

    public function getBalanceDueAttribute()
    {
        return $this->balance;
    }

    public function getTotalAttribute()
    {
        return $this->calc()->getTotal();
    }

    public function getPayableAmount()
    {
        if ($this->partial > 0) {
            return $this->partial;
        }

        if ($this->balance > 0) {
            return $this->balance;
        }

        if ($this->status_id == 1) {
            return $this->amount;
        }

        return 0;
    }

    public function entityEmailEvent($invitation, $reminder_template, $template = '')
    {

        switch ($reminder_template) {
            case 'invoice':
                event(new InvoiceWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $template));
                break;
            case 'reminder1':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $reminder_template));
                break;
            case 'reminder2':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $reminder_template));
                break;
            case 'reminder3':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $reminder_template));
                break;
            case 'reminder_endless':
            case 'endless_reminder':
                event(new InvoiceReminderWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $reminder_template));
                break;
            case 'custom1':
            case 'custom2':
            case 'custom3':
                event(new InvoiceWasEmailed($invitation, $invitation->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $template));
                break;
            default:
                // code...
                break;
        }
    }

    public function transaction_event()
    {
        $invoice = $this->fresh();

        return [
            'invoice_id' => $invoice->id,
            'invoice_amount' => $invoice->amount ?: 0,
            'invoice_partial' => $invoice->partial ?: 0,
            'invoice_balance' => $invoice->balance ?: 0,
            'invoice_paid_to_date' => $invoice->paid_to_date ?: 0,
            'invoice_status' => $invoice->status_id ?: 1,
        ];
    }

    public function expense_documents()
    {
        $line_items = $this->line_items;

        $expense_ids = [];

        foreach ($line_items as $item) {
            if (property_exists($item, 'expense_id')) {
                $expense_ids[] = $item->expense_id;
            }
        }

        return Expense::query()->whereIn('id', $this->transformKeys($expense_ids))
                           ->where('invoice_documents', 1)
                           ->where('company_id', $this->company_id)
                           ->cursor();
    }

    public function task_documents()
    {
        $line_items = $this->line_items;

        $task_ids = [];

        foreach ($line_items as $item) {
            if (property_exists($item, 'task_id')) {
                $task_ids[] = $item->task_id;
            }
        }

        return Task::query()->whereIn('id', $this->transformKeys($task_ids))
                           ->whereHas('company', function ($query) {
                               $query->where('invoice_task_documents', 1);
                           })
                           ->where('company_id', $this->company_id)
                           ->cursor();
    }

    public function translate_entity()
    {
        return ctrans('texts.invoice');
    }

    public function taxTypeString($id): string
    {
        $tax_type  = '';

        match(intval($id)) {
            Product::PRODUCT_TYPE_PHYSICAL => $tax_type = ctrans('texts.physical_goods'),
            Product::PRODUCT_TYPE_SERVICE => $tax_type = ctrans('texts.services'),
            Product::PRODUCT_TYPE_DIGITAL => $tax_type = ctrans('texts.digital_products'),
            Product::PRODUCT_TYPE_SHIPPING => $tax_type = ctrans('texts.shipping'),
            Product::PRODUCT_TYPE_EXEMPT => $tax_type = ctrans('texts.tax_exempt'),
            Product::PRODUCT_TYPE_REDUCED_TAX => $tax_type = ctrans('texts.reduced_tax'),
            Product::PRODUCT_TYPE_OVERRIDE_TAX => $tax_type = ctrans('texts.override_tax'),
            Product::PRODUCT_TYPE_ZERO_RATED => $tax_type = ctrans('texts.zero_rated'),
            Product::PRODUCT_TYPE_REVERSE_TAX => $tax_type = ctrans('texts.reverse_tax'),
            default => $tax_type = ctrans('texts.physical_goods'),
        };

        return $tax_type;
    }

    // public function typeIdString($id)
    // {
    //     $type = '';
    //     match($id) {
    //         '1' => $type = ctrans('texts.product'),
    //         '2' => $type = ctrans('texts.service'),
    //         '3' => $type = ctrans('texts.gateway_fees'),
    //         '4' => $type = ctrans('texts.gateway_fees'),
    //         '5' => $type = ctrans('texts.late_fees'),
    //         '6' => $type = ctrans('texts.expense'),
    //         default => $type = ctrans('texts.product'),
    //     };

    //     return $type;

    // }

    public function reminderSchedule(): string
    {
        $reminder_schedule = '';
        $settings = $this->client->getMergedSettings();

        $send_email_enabled =  ctrans('texts.send_email') . " " .ctrans('texts.enabled');
        $send_email_disabled =  ctrans('texts.send_email') . " " .ctrans('texts.disabled');

        $sends_email_1 = $settings->enable_reminder1 ? $send_email_enabled : $send_email_disabled;
        $days_1 = $settings->num_days_reminder1 . " " . ctrans('texts.days');
        $schedule_1 = ctrans("texts.{$settings->schedule_reminder1}"); //after due date etc or disabled
        $label_1 = ctrans('texts.reminder1');

        $sends_email_2 = $settings->enable_reminder2 ? $send_email_enabled : $send_email_disabled;
        $days_2 = $settings->num_days_reminder2 . " " . ctrans('texts.days');
        $schedule_2 = ctrans("texts.{$settings->schedule_reminder2}"); //after due date etc or disabled
        $label_2 = ctrans('texts.reminder2');

        $sends_email_3 = $settings->enable_reminder3 ? $send_email_enabled : $send_email_disabled;
        $days_3 = $settings->num_days_reminder3 . " " . ctrans('texts.days');
        $schedule_3 = ctrans("texts.{$settings->schedule_reminder3}"); //after due date etc or disabled
        $label_3 = ctrans('texts.reminder3');

        $sends_email_endless = $settings->enable_reminder_endless ? $send_email_enabled : $send_email_disabled;
        $days_endless = \App\Models\RecurringInvoice::frequencyForKey($settings->endless_reminder_frequency_id);
        $label_endless = ctrans('texts.reminder_endless');

        if($schedule_1 == ctrans('texts.disabled') || $settings->schedule_reminder1 == 'disabled' || $settings->schedule_reminder1 == '') {
            $reminder_schedule .= "{$label_1}: " . ctrans('texts.disabled') ."<br>";
        } else {
            $reminder_schedule .= "{$label_1}: {$days_1} {$schedule_1} [{$sends_email_1}]<br>";
        }

        if($schedule_2 == ctrans('texts.disabled') || $settings->schedule_reminder2 == 'disabled' || $settings->schedule_reminder2 == '') {
            $reminder_schedule .= "{$label_2}: " . ctrans('texts.disabled') ."<br>";
        } else {
            $reminder_schedule .= "{$label_2}: {$days_2} {$schedule_2} [{$sends_email_2}]<br>";
        }

        if($schedule_3 == ctrans('texts.disabled') || $settings->schedule_reminder3 == 'disabled' || $settings->schedule_reminder3 == '') {
            $reminder_schedule .= "{$label_3}: " . ctrans('texts.disabled') ."<br>";
        } else {
            $reminder_schedule .= "{$label_3}: {$days_3} {$schedule_3} [{$sends_email_3}]<br>";
        }

        if($sends_email_endless == ctrans('texts.disabled') || $settings->endless_reminder_frequency_id == '0' || $settings->endless_reminder_frequency_id == '') {
            $reminder_schedule .= "{$label_endless}: " . ctrans('texts.disabled') ."<br>";
        } else {
            $reminder_schedule .= "{$label_endless}: {$days_endless} [{$sends_email_endless}]<br>";
        }


        return $reminder_schedule;
    }
}

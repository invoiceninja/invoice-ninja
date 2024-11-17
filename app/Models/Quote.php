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

use App\Helpers\Invoice\InvoiceSum;
use App\Helpers\Invoice\InvoiceSumInclusive;
use App\Models\Presenters\QuotePresenter;
use App\Services\Quote\QuoteService;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceValues;
use App\Utils\Traits\MakesReminders;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laracasts\Presenter\PresentableTrait;

/**
 * App\Models\Quote
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
 * @property bool $auto_bill_enabled
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $last_sent_date
 * @property string|null|Carbon $due_date
 * @property string|null $next_send_date
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
 * @property \Carbon\Carbon|null $partial_due_date
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
 * @property \App\Models\User|null $assigned_user
 * @property \App\Models\Client $client
 * @property \App\Models\Company $company
 * @property \App\Models\QuoteInvitation $invitation
 * @property-read mixed $balance_due
 * @property-read mixed $hashed_id
 * @property-read mixed $total
 * @property-read mixed $valid_until
 * @property-read int|null $invitations_count
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\QuoteInvitation|null $invitations
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Vendor|null $vendor
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\QuoteInvitation> $invitations
 * @mixin \Eloquent
 * @mixin \Illuminate\Database\Eloquent\Builder
 */
class Quote extends BaseModel
{
    use MakesHash;
    use MakesDates;
    use Filterable;
    use SoftDeletes;
    use MakesReminders;
    use PresentableTrait;
    use MakesInvoiceValues;

    protected $presenter = QuotePresenter::class;

    protected $touches = [];

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
        'uses_inclusive_taxes',
        'vendor_id',
    ];

    protected $casts = [
        // 'date' => 'date:Y-m-d',
        'tax_data' => 'object',
        'due_date' => 'date:Y-m-d',
        'partial_due_date' => 'date:Y-m-d',
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
        'is_deleted' => 'boolean',
        'is_amount_discount' => 'bool',
        'e_invoice' => 'object',
    ];

    public const STATUS_DRAFT = 1;

    public const STATUS_SENT = 2;

    public const STATUS_APPROVED = 3;

    public const STATUS_CONVERTED = 4;

    public const STATUS_EXPIRED = -1;

    public function getEntityType()
    {
        return self::class;
    }

    public function getDateAttribute($value)
    {
        return $this->dateMutator($value);
    }

    //    public function getDueDateAttribute($value)
    //    {
    //        return $value ? $this->dateMutator($value) : null;
    //    }

    // public function getPartialDueDateAttribute($value)
    // {
    //     return $this->dateMutator($value);
    // }

    public function getStatusIdAttribute($value)
    {
        if ($this->due_date && ! $this->is_deleted && $value == self::STATUS_SENT && Carbon::parse($this->due_date)->lte(now()->startOfDay())) {
            return self::STATUS_EXPIRED;
        }

        return $value;
    }

    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function vendor(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasManyThrough
     */
    public function history(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function activities(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Activity::class)->where('company_id', $this->company_id)->where('client_id', $this->client_id)->orderBy('id', 'DESC')->take(50);
    }

    public function user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function client(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function invoice(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    public function assigned_user(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function project(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function invitations(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(QuoteInvitation::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function documents(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * Access the quote calculator object.
     *
     * @return InvoiceSumInclusive | InvoiceSum The quote calculator object getters
     */
    public function calc(): InvoiceSumInclusive | InvoiceSum
    {
        $quote_calc = null;

        if ($this->uses_inclusive_taxes) {
            $quote_calc = new InvoiceSumInclusive($this);
        } else {
            $quote_calc = new InvoiceSum($this);
        }

        return $quote_calc->build();
    }

    /**
     * Updates Invites to SENT.
     */
    public function markInvitationsSent()
    {
        $this->invitations->each(function ($invitation) {
            if (! isset($invitation->sent_date)) {
                $invitation->sent_date = Carbon::now();
                $invitation->saveQuietly();
            }
        });
    }

    public function service(): QuoteService
    {
        return new QuoteService($this);
    }

    /**
     * @param int $status
     * @return string
     */
    public static function badgeForStatus(int $status): string
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
            case self::STATUS_SENT:
                return '<h5><span class="badge badge-primary">'.ctrans('texts.pending').'</span></h5>';
            case self::STATUS_APPROVED:
                return '<h5><span class="badge badge-success">'.ctrans('texts.approved').'</span></h5>';
            case self::STATUS_EXPIRED:
                return '<h5><span class="badge badge-danger">'.ctrans('texts.expired').'</span></h5>';
            case self::STATUS_CONVERTED:
                return '<h5><span class="badge badge-light">'.ctrans('texts.converted').'</span></h5>';
            default:
                return '<h5><span class="badge badge-light">'.ctrans('texts.draft').'</span></h5>';
        }
    }

    public static function stringStatus(int $status): string
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return ctrans('texts.draft');
            case self::STATUS_SENT:
                return ctrans('texts.sent');
            case self::STATUS_APPROVED:
                return ctrans('texts.approved');
            case self::STATUS_EXPIRED:
                return ctrans('texts.expired');
            case self::STATUS_CONVERTED:
                return ctrans('texts.converted');
            default:
                return ctrans('texts.draft');

        }
    }

    /**
     * Check if the quote has been approved.
     *
     * @return bool
     */
    public function isApproved(): bool
    {
        if ($this->status_id === $this::STATUS_APPROVED) {
            return true;
        }

        return false;
    }

    public function getValidUntilAttribute()
    {
        return $this->due_date;
    }

    public function getBalanceDueAttribute()
    {
        return $this->balance;
    }

    public function getTotalAttribute()
    {
        return $this->calc()->getTotal();
    }

    public function translate_entity(): string
    {
        return ctrans('texts.quote');
    }

    /**
     * calculateTemplate
     *
     * @param  string $entity_string
     * @return string
     */
    public function calculateTemplate(string $entity_string): string
    {

        $client = $this->client;

        if ($entity_string != 'quote') {
            return $entity_string;
        }

        if ($this->inReminderWindow(
            $client->getSetting('quote_schedule_reminder1'),
            $client->getSetting('quote_num_days_reminder1')
        ) && ! $this->reminder1_sent) {
            return 'reminder1';
            // } elseif ($this->inReminderWindow(
            //     $client->getSetting('schedule_reminder2'),
            //     $client->getSetting('num_days_reminder2')
            // ) && ! $this->reminder2_sent) {
            //     return 'reminder2';
            // } elseif ($this->inReminderWindow(
            //     $client->getSetting('schedule_reminder3'),
            //     $client->getSetting('num_days_reminder3')
            // ) && ! $this->reminder3_sent) {
            //     return 'reminder3';
            // } elseif ($this->checkEndlessReminder(
            //     $this->reminder_last_sent,
            //     $client->getSetting('endless_reminder_frequency_id')
            // )) {
            //     return 'endless_reminder';
        } else {
            return $entity_string;
        }

    }


    /**
     * @return bool
     */
    public function canRemind(): bool
    {
        if (in_array($this->status_id, [self::STATUS_DRAFT, self::STATUS_APPROVED, self::STATUS_CONVERTED]) || $this->is_deleted) {
            return false;
        }

        return true;

    }

}

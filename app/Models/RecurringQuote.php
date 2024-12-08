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
use App\Models\Presenters\RecurringQuotePresenter;
use App\Services\Recurring\RecurringService;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Recurring\HasRecurrence;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Laracasts\Presenter\PresentableTrait;

/**
 * Class for Recurring Quotes.
 *
 * @property int $id
 * @property int $client_id
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int|null $project_id
 * @property int|null $vendor_id
 * @property int $status_id
 * @property float $discount
 * @property bool $is_amount_discount
 * @property string|null $number
 * @property string|null $po_number
 * @property string|null $date
 * @property string|null $due_date
 * @property bool $is_deleted
 * @property array $line_items
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
 * @property float $total_taxes
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property float $amount
 * @property float $balance
 * @property string|null $last_viewed
 * @property int $frequency_id
 * @property int $design_id
 * @property string|null $last_sent_date
 * @property string|null $next_send_date
 * @property int|null $remaining_cycles
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string $auto_bill
 * @property bool $auto_bill_enabled
 * @property float $paid_to_date
 * @property float|null $custom_surcharge1
 * @property float|null $custom_surcharge2
 * @property float|null $custom_surcharge3
 * @property float|null $custom_surcharge4
 * @property int $custom_surcharge_tax1
 * @property int $custom_surcharge_tax2
 * @property int $custom_surcharge_tax3
 * @property int $custom_surcharge_tax4
 * @property string|null $due_date_days
 * @property float $exchange_rate
 * @property float|null $partial
 * @property string|null $partial_due_date
 * @property int|null $subscription_id
 * @property bool $uses_inclusive_taxes
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read int|null $activities_count
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client $client
 * @property-read \App\Models\Company $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read mixed $status
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read int|null $history_count
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringQuoteInvitation> $invitations
 * @property-read int|null $invitations_count
 * @property-read \App\Models\Project|null $project
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @property-read int|null $quotes_count
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\RecurringQuoteFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuote filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuote newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuote newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuote onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuote query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuote withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|RecurringQuote withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Activity> $activities
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Backup> $history
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\RecurringQuoteInvitation> $invitations
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Quote> $quotes
 * @mixin \Eloquent
 */
class RecurringQuote extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;
    use HasRecurrence;
    use PresentableTrait;

    protected $presenter = RecurringQuotePresenter::class;

    /**
     * Quote Statuses.
     */
    public const STATUS_DRAFT = 1;

    public const STATUS_ACTIVE = 2;

    public const STATUS_PAUSED = 3;

    public const STATUS_COMPLETED = 4;

    public const STATUS_PENDING = -1;

    /**
     * Quote Frequencies.
     */
    public const FREQUENCY_DAILY = 1;

    public const FREQUENCY_WEEKLY = 2;

    public const FREQUENCY_TWO_WEEKS = 3;

    public const FREQUENCY_FOUR_WEEKS = 4;

    public const FREQUENCY_MONTHLY = 5;

    public const FREQUENCY_TWO_MONTHS = 6;

    public const FREQUENCY_THREE_MONTHS = 7;

    public const FREQUENCY_FOUR_MONTHS = 8;

    public const FREQUENCY_SIX_MONTHS = 9;

    public const FREQUENCY_ANNUALLY = 10;

    public const FREQUENCY_TWO_YEARS = 11;

    public const FREQUENCY_THREE_YEARS = 12;

    public const RECURS_INDEFINITELY = -1;

    protected $fillable = [
        'client_id',
        'project_id',
        'number',
        'discount',
        'is_amount_discount',
        'po_number',
        'date',
        'due_date',
        'due_date_days',
        'line_items',
        'footer',
        'public_notes',
        'private_notes',
        'terms',
        'tax_name1',
        'tax_name2',
        'tax_name3',
        'tax_rate1',
        'tax_rate2',
        'tax_rate3',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'amount',
        'partial',
        'frequency_id',
        'next_send_date',
        'remaining_cycles',
        'auto_bill',
        'auto_bill_enabled',
        'design_id',
        'custom_surcharge1',
        'custom_surcharge2',
        'custom_surcharge3',
        'custom_surcharge4',
        'custom_surcharge_tax1',
        'custom_surcharge_tax2',
        'custom_surcharge_tax3',
        'custom_surcharge_tax4',
        'design_id',
        'assigned_user_id',
        'exchange_rate',
    ];

    protected $casts = [
        'settings' => 'object',
        'line_items' => 'object',
        'backup' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $appends = [
        'hashed_id',
        'status',
    ];

    protected $touches = [];

    public function getEntityType()
    {
        return self::class;
    }

    public function getDateAttribute($value)
    {
        if (! empty($value)) {
            return (new Carbon($value))->format('Y-m-d');
        }

        return $value;
    }

    public function getDueDateAttribute($value)
    {
        if (! empty($value)) {
            return (new Carbon($value))->format('Y-m-d');
        }

        return $value;
    }

    public function getPartialDueDateAttribute($value)
    {
        if (! empty($value)) {
            return (new Carbon($value))->format('Y-m-d');
        }

        return $value;
    }

    public function activities()
    {
        return $this->hasMany(Activity::class)->where('company_id', $this->company_id)->where('client_id', $this->client_id)->orderBy('id', 'DESC')->take(50);
    }

    public function history()
    {
        return $this->hasManyThrough(Backup::class, Activity::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    public function quotes()
    {
        return $this->hasMany(Quote::class, 'recurring_id', 'id')->withTrashed();
    }

    public function invitations()
    {
        return $this->hasMany(RecurringQuoteInvitation::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    public function getStatusAttribute()
    {
        if ($this->status_id == self::STATUS_ACTIVE && Carbon::parse($this->next_send_date)->isFuture()) {
            return self::STATUS_PENDING;
        } else {
            return $this->status_id;
        }
    }

    public function nextSendDate(): ?Carbon
    {
        if (! $this->next_send_date) {
            return null;
        }

        $offset = $this->client->timezone_offset();

        /*
        As we are firing at UTC+0 if our offset is negative it is technically firing the day before so we always need
        to add ON a day - a day = 86400 seconds
        */
        if ($offset < 0) {
            $offset += 86400;
        }

        switch ($this->frequency_id) {
            case self::FREQUENCY_DAILY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addDay()->addSeconds($offset);
            case self::FREQUENCY_WEEKLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeek()->addSeconds($offset);
            case self::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeeks(2)->addSeconds($offset);
            case self::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addWeeks(4)->addSeconds($offset);
            case self::FREQUENCY_MONTHLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
            case self::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
            case self::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(4)->addSeconds($offset);
            case self::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addMonthsNoOverflow(6)->addSeconds($offset);
            case self::FREQUENCY_ANNUALLY:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYear()->addSeconds($offset);
            case self::FREQUENCY_TWO_YEARS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYears(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_YEARS:
                return Carbon::parse($this->next_send_date)->startOfDay()->addYears(3)->addSeconds($offset);
            default:
                return null;
        }
    }

    public function nextDateByFrequency($date)
    {
        $offset = $this->client->timezone_offset();

        switch ($this->frequency_id) {
            case self::FREQUENCY_DAILY:
                return Carbon::parse($date)->startOfDay()->addDay()->addSeconds($offset);
            case self::FREQUENCY_WEEKLY:
                return Carbon::parse($date)->startOfDay()->addWeek()->addSeconds($offset);
            case self::FREQUENCY_TWO_WEEKS:
                return Carbon::parse($date)->startOfDay()->addWeeks(2)->addSeconds($offset);
            case self::FREQUENCY_FOUR_WEEKS:
                return Carbon::parse($date)->startOfDay()->addWeeks(4)->addSeconds($offset);
            case self::FREQUENCY_MONTHLY:
                return Carbon::parse($date)->startOfDay()->addMonthNoOverflow()->addSeconds($offset);
            case self::FREQUENCY_TWO_MONTHS:
                return Carbon::parse($date)->startOfDay()->addMonthsNoOverflow(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_MONTHS:
                return Carbon::parse($date)->startOfDay()->addMonthsNoOverflow(3)->addSeconds($offset);
            case self::FREQUENCY_FOUR_MONTHS:
                return Carbon::parse($date)->startOfDay()->addMonthsNoOverflow(4)->addSeconds($offset);
            case self::FREQUENCY_SIX_MONTHS:
                return Carbon::parse($date)->addMonthsNoOverflow(6)->addSeconds($offset);
            case self::FREQUENCY_ANNUALLY:
                return Carbon::parse($date)->startOfDay()->addYear()->addSeconds($offset);
            case self::FREQUENCY_TWO_YEARS:
                return Carbon::parse($date)->startOfDay()->addYears(2)->addSeconds($offset);
            case self::FREQUENCY_THREE_YEARS:
                return Carbon::parse($date)->startOfDay()->addYears(3)->addSeconds($offset);
            default:
                return null;
        }
    }

    public function remainingCycles(): int
    {
        if ($this->remaining_cycles == 0) {
            return 0;
        } elseif ($this->remaining_cycles == -1) {
            return -1;
        } else {
            return $this->remaining_cycles - 1;
        }
    }

    public function setCompleted(): void
    {
        $this->status_id = self::STATUS_COMPLETED;
        $this->next_send_date = null;
        $this->remaining_cycles = 0;
        $this->save();
    }

    public static function badgeForStatus(int $status)
    {
        switch ($status) {
            case self::STATUS_DRAFT:
                return '<h4><span class="badge badge-light">'.ctrans('texts.draft').'</span></h4>';
            case self::STATUS_PENDING:
                return '<h4><span class="badge badge-primary">'.ctrans('texts.pending').'</span></h4>';
            case self::STATUS_ACTIVE:
                return '<h4><span class="badge badge-primary">'.ctrans('texts.active').'</span></h4>';
            case self::STATUS_COMPLETED:
                return '<h4><span class="badge badge-success">'.ctrans('texts.status_completed').'</span></h4>';
            case self::STATUS_PAUSED:
                return '<h4><span class="badge badge-danger">'.ctrans('texts.paused').'</span></h4>';
            default:
                return '<h4><span class="badge badge-primary">'.ctrans('texts.pending').'</span></h4>';
        }
    }

    public static function frequencyForKey(int $frequency_id): string
    {
        switch ($frequency_id) {
            case self::FREQUENCY_DAILY:
                return ctrans('texts.freq_daily');
            case self::FREQUENCY_WEEKLY:
                return ctrans('texts.freq_weekly');
            case self::FREQUENCY_TWO_WEEKS:
                return ctrans('texts.freq_two_weeks');
            case self::FREQUENCY_FOUR_WEEKS:
                return ctrans('texts.freq_four_weeks');
            case self::FREQUENCY_MONTHLY:
                return ctrans('texts.freq_monthly');
            case self::FREQUENCY_TWO_MONTHS:
                return ctrans('texts.freq_two_months');
            case self::FREQUENCY_THREE_MONTHS:
                return ctrans('texts.freq_three_months');
            case self::FREQUENCY_FOUR_MONTHS:
                return ctrans('texts.freq_four_months');
            case self::FREQUENCY_SIX_MONTHS:
                return ctrans('texts.freq_six_months');
            case self::FREQUENCY_ANNUALLY:
                return ctrans('texts.freq_annually');
            case self::FREQUENCY_TWO_YEARS:
                return ctrans('texts.freq_two_years');
            default:
                return ctrans('texts.freq_weekly');

        }
    }

    /**
     * Access the invoice calculator object.
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

    /*
     * Important to note when playing with carbon dates - in order
     * not to modify the original instance, always use a `->copy()`
     *
     */
    public function recurringDates()
    {
        /* Return early if nothing to send back! */
        if ($this->status_id == self::STATUS_COMPLETED ||
            $this->remaining_cycles == 0 ||
            ! $this->next_send_date) {
            return [];
        }

        /* Endless - lets send 10 back*/
        $iterations = $this->remaining_cycles;

        if ($this->remaining_cycles == -1) {
            $iterations = 10;
        }

        $data = [];

        if (! Carbon::parse($this->next_send_date)) {
            return $data;
        }

        $next_send_date = Carbon::parse($this->next_send_date)->copy();

        for ($x = 0; $x < $iterations; $x++) {
            // we don't add the days... we calc the day of the month!!
            $next_due_date = $this->calculateDueDate($next_send_date->copy()->format('Y-m-d'));
            $next_due_date_string = $next_due_date ? $next_due_date->format('Y-m-d') : '';

            $next_send_date = Carbon::parse($next_send_date);

            $data[] = [
                'send_date' => $next_send_date->format('Y-m-d'),
                'due_date' => $next_due_date_string,
            ];

            /* Fixes the timeshift in case the offset is negative which cause a infinite loop due to UTC +0*/
            if ($this->client->timezone_offset() < 0) {
                $next_send_date = $this->nextDateByFrequency($next_send_date->addDay()->format('Y-m-d'));
            } else {
                $next_send_date = $this->nextDateByFrequency($next_send_date->format('Y-m-d'));
            }
        }

        return $data;
    }

    public function calculateDueDate($date)
    {
        switch ($this->due_date_days) {
            case 'terms':
                return $this->calculateDateFromTerms($date);
            default:
                return $this->setDayOfMonth($date, ($this->due_date_days ?? 1));
        }
    }

    /**
     * Calculates a date based on the client payment terms.
     *
     * @param  Carbon $date A given date
     * @return null|Carbon  The date
     */
    public function calculateDateFromTerms($date)
    {
        $new_date = Carbon::parse($date);

        $client_payment_terms = $this->client->getSetting('payment_terms');

        if ($client_payment_terms == '') {//no due date! return null;
            return null;
        }

        return $new_date->addDays((int)$client_payment_terms); //add the number of days in the payment terms to the date
    }

    /**
     * Service entry points.
     * @return RecurringService
     */
    public function service(): RecurringService
    {
        return new RecurringService($this);
    }

    public function translate_entity()
    {
        return ctrans('texts.recurring_quote');
    }
}

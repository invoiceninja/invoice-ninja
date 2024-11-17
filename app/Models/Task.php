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

use Carbon\CarbonInterval;
use App\Models\CompanyUser;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Libraries\Currency\Conversion\CurrencyApi;

/**
 * App\Models\Task
 *
 * @property int $id
 * @property string|null $hash
 * @property object|null $meta
 * @property int $user_id
 * @property int|null $assigned_user_id
 * @property int $company_id
 * @property int|null $client_id
 * @property int|null $invoice_id
 * @property int|null $project_id
 * @property int|null $status_id
 * @property int|null $status_sort_order
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property int|null $deleted_at
 * @property string|null $custom_value1
 * @property string|null $custom_value2
 * @property string|null $custom_value3
 * @property string|null $custom_value4
 * @property int|null $duration
 * @property string|null $description
 * @property bool $is_deleted
 * @property bool $is_running
 * @property string|null $time_log
 * @property string|null $number
 * @property float $rate
 * @property bool $invoice_documents
 * @property int $is_date_based
 * @property int|null $status_order
 * @property-read \App\Models\User|null $assigned_user
 * @property-read \App\Models\Client|null $client
 * @property-read \App\Models\Company|null $company
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @property-read int|null $documents_count
 * @property-read mixed $hashed_id
 * @property-read \App\Models\Invoice|null $invoice
 * @property-read \App\Models\Project|null $project
 * @property-read \App\Models\TaskStatus|null $status
 * @property-read \App\Models\User $user
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel exclude($columns)
 * @method static \Database\Factories\TaskFactory factory($count = null, $state = [])
 * @method static \Illuminate\Database\Eloquent\Builder|Task filter(\App\Filters\QueryFilters $filters)
 * @method static \Illuminate\Database\Eloquent\Builder|Task newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Task onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Task query()
 * @method static \Illuminate\Database\Eloquent\Builder|BaseModel scope()
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereAssignedUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereClientId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCompanyId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue1($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue2($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue3($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereCustomValue4($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereDuration($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereInvoiceDocuments($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereInvoiceId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereIsDateBased($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereIsDeleted($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereIsRunning($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereNumber($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereProjectId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereRate($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereStatusId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereStatusOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereStatusSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereTimeLog($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task whereUserId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Task withTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder|Task withoutTrashed()
 * @property-read \Illuminate\Database\Eloquent\Collection<int, \App\Models\Document> $documents
 * @mixin \Eloquent
 */
class Task extends BaseModel
{
    use MakesHash;
    use SoftDeletes;
    use Filterable;

    protected $fillable = [
        'client_id',
        'invoice_id',
        'project_id',
        'assigned_user_id',
        'custom_value1',
        'custom_value2',
        'custom_value3',
        'custom_value4',
        'description',
        'is_running',
        'time_log',
        'status_id',
        'status_sort_order', //deprecated
        'invoice_documents',
        'rate',
        'number',
        'is_date_based',
        'status_order',
        'hash',
        'meta',
    ];

    protected $casts = [
        'meta' => 'object',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'deleted_at' => 'timestamp',
    ];

    protected $with = [
        // 'project',
    ];

    protected $touches = ['project'];

    public function getEntityType()
    {
        return self::class;
    }

    /**
     * Get all of the users that belong to the team.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function company(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function documents()
    {
        return $this->morphMany(Document::class, 'documentable');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function assigned_user()
    {
        return $this->belongsTo(User::class, 'assigned_user_id', 'id')->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function client()
    {
        return $this->belongsTo(Client::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function status()
    {
        return $this->belongsTo(TaskStatus::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function invoice()
    {
        return $this->belongsTo(Invoice::class)->withTrashed();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function project()
    {
        return $this->belongsTo(Project::class)->withTrashed();
    }

    public function stringStatus(): string
    {
        if($this->invoice_id) {
            return '<h5><span class="badge badge-success">'.ctrans('texts.invoiced').'</span></h5>';
        }

        if($this->status) {
            return '<h5><span class="badge badge-primary">' . $this->status?->name ?? ''; //@phpstan-ignore-line
        }

        return '';

    }

    public function calcStartTime()
    {
        $parts = json_decode($this->time_log) ?: [];

        if (count($parts)) {
            return Carbon::createFromTimeStamp((int)$parts[0][0])->timestamp;
        } else {
            return null;
        }
    }

    public function getLastStartTime()
    {
        $parts = json_decode($this->time_log) ?: [];

        if (count($parts)) {
            $index = count($parts) - 1;

            return $parts[$index][0];
        } else {
            return '';
        }
    }

    public function calcDuration($start_time_cutoff = 0, $end_time_cutoff = 0)
    {
        $duration = 0;
        $parts = json_decode($this->time_log ?? '{}') ?: [];

        foreach ($parts as $part) {
            $start_time = $part[0];
            if (count($part) == 1 || ! $part[1]) {
                $end_time = time();
            } else {
                $end_time = $part[1];
            }

            if ($start_time_cutoff) {
                $start_time = max($start_time, $start_time_cutoff);
            }
            if ($end_time_cutoff) {
                $end_time = min($end_time, $end_time_cutoff);
            }

            $duration += max($end_time - $start_time, 0);
        }

        // return CarbonInterval::seconds(round($duration))->locale($this->company->locale())->cascade()->forHumans();
        return round($duration);
    }

    public function translate_entity()
    {
        return ctrans('texts.task');
    }

    public function getRate(): float
    {
        if($this->project && $this->project->task_rate > 0) {
            return $this->project->task_rate;
        }

        if($this->client) {
            return $this->client->getSetting('default_task_rate');
        }

        return $this->company->settings->default_task_rate ?? 0;
    }

    public function taskCompanyValue(): float
    {
        $client_currency = $this->client->getSetting('currency_id');
        $company_currency = $this->company->getSetting('currency_id');

        if($client_currency != $company_currency) {
            $converter = new CurrencyApi();
            return $converter->convert($this->taskValue(), $client_currency, $company_currency);
        }

        return $this->taskValue();

    }

    public function getQuantity(): float
    {
        return round(($this->calcDuration() / 3600) , 2);
    }

    public function taskValue(): float
    {
        return round(($this->calcDuration() / 3600) * $this->getRate(), 2);
    }

    public function processLogs()
    {

        return
        collect(json_decode($this->time_log, true))->map(function ($log) {

            $parent_entity = $this->client ?? $this->company;

            if($log[0]) {
                $log[0] = Carbon::createFromTimestamp((int)$log[0])->format($parent_entity->date_format().' H:i:s');
            }

            if($log[1] && $log[1] != 0) {
                $log[1] = Carbon::createFromTimestamp((int)$log[1])->format($parent_entity->date_format().' H:i:s');
            } else {
                $log[1] = ctrans('texts.running');
            }

            return $log;
        })->toArray();
    }

    public function description(): string
    {
        $parent_entity = $this->client ?? $this->company;
        $time_format = $parent_entity->getSetting('military_time') ? "H:i:s" : "h:i:s A";
        
        $task_description =  collect(json_decode($this->time_log, true))
            ->filter(function ($log) {
                $billable = $log[3] ?? false;
                return $billable || $this->company->settings->allow_billable_task_items;
            })
            ->map(function ($log) use($parent_entity, $time_format){
                $interval_description = $log[2] ?? '';
                $hours = ctrans('texts.hours');

                $parts = [];
                
                $parts[] = '<div class="task-time-details">';

                $date_time = [];
                
                if ($this->company->invoice_task_datelog) {
                    $date_time[] = Carbon::createFromTimestamp((int)$log[0])
                        ->setTimeZone($this->company->timezone()->name)
                        ->format($parent_entity->date_format());
                }

                if ($this->company->invoice_task_timelog) {
                    $date_time[] = Carbon::createFromTimestamp((int)$log[0])
                        ->setTimeZone($this->company->timezone()->name)
                        ->format($time_format) . " - " . 
                        Carbon::createFromTimestamp((int)$log[1])
                        ->setTimeZone($this->company->timezone()->name)
                        ->format($time_format);
                }

                if ($this->company->invoice_task_hours) {
                    $date_time[] = "{$this->getQuantity()} {$hours}";
                }

                $parts[] = implode(" • ", $date_time);

                if ($this->company->invoice_task_item_description && $this->company->settings->show_task_item_description && strlen($interval_description) > 1) {
                    $parts[] = $interval_description;
                }

                $parts[] = '</div>';

                return implode(PHP_EOL, $parts);
            })
            ->implode(PHP_EOL);

            $body = '';

            if($this->company->invoice_task_project && $this->project)
                $body = "## {$this->project->name}  \n";
            
            if(strlen($this->description) > 1)
                $body .= $this->description. " ";
            
            $body .= $task_description;

            return $body;
    }

    public function processLogsExpandedNotation()
    {

        return
        collect(json_decode($this->time_log, true))->map(function ($log) {

            $parent_entity = $this->client ?? $this->company;
            $logged = [];

            if($log[0] && $log[1] != 0) {
                $duration = $log[1] - $log[0];
            } else {
                $duration = 0;
            }

            if($log[0]) {
                $logged['start_date_raw'] = $log[0];
            }
            $logged['start_date'] = Carbon::createFromTimestamp((int)$log[0])->setTimeZone($this->company->timezone()->name)->format($parent_entity->date_format().' H:i:s');

            if($log[1] && $log[1] != 0) {
                $logged['end_date_raw'] = $log[1];
                $logged['end_date'] = Carbon::createFromTimestamp((int)$log[1])->setTimeZone($this->company->timezone()->name)->format($parent_entity->date_format().' H:i:s');
            } else {
                $logged['end_date_raw'] = 0;
                $logged['end_date'] = ctrans('texts.running');
            }

            $logged['description'] =  $log[2] ?? '';
            $logged['billable'] = $log[3] ?? false;
            $logged['duration_raw'] = $duration;
            $logged['duration'] = gmdate("H:i:s", $duration);

            return $logged;

        })->toArray();
    }

    public function assignedCompanyUser()
    {
        if(!$this->assigned_user_id) {
            return false;
        }

        return CompanyUser::where('company_id', $this->company_id)->where('user_id', $this->assigned_user_id)->first();
    }
}

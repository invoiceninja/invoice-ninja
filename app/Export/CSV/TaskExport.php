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

namespace App\Export\CSV;

use App\Models\Task;
use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Timezone;
use App\Libraries\MultiDB;
use App\Models\DateFormat;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Transformers\TaskTransformer;
use Illuminate\Database\Eloquent\Builder;

class TaskExport extends BaseExport
{
    private $entity_transformer;

    public string $date_key = 'calculated_start_date';

    private string $date_format = 'Y-m-d';

    public Writer $csv;

    private Decorator $decorator;

    private array $storage_array = [];

    private array $storage_item_array = [];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new TaskTransformer();
        $this->decorator = new Decorator();
    }

    public function init(): Builder
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->date_format = DateFormat::find($this->company->settings->date_format_id)->format;
        ksort($this->entity_keys);

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->task_report_keys);
            $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));
        }

        $query = Task::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id);

        if(!$this->input['include_deleted'] ?? false) {
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'tasks');

        $clients = &$this->input['client_id'];

        if($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        $document_attachments = &$this->input['document_email_attachment'];

        if($document_attachments) {
            $this->queueDocuments($query);
        }

        return $query;

    }

    public function run()
    {

        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
              ->each(function ($entity) {

                  /** @var \App\Models\Task $entity*/
                  $this->buildRow($entity);
              });

        $this->csv->insertAll($this->storage_array);

        return $this->csv->toString();
    }


    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $query->cursor()
                ->each(function ($resource) {

                    /** @var \App\Models\Task $resource*/
                    $this->buildRow($resource);

                    foreach($this->storage_array as $row) {
                        $this->storage_item_array[] = $this->processMetaData($row, $resource);
                    }

                    $this->storage_array = [];
                });

        return array_merge(['columns' => $header], $this->storage_item_array);
    }

    private function buildRow(Task $task)
    {
        $entity = [];
        $transformed_entity = $this->entity_transformer->transform($task);

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'task' && array_key_exists($parts[1], $transformed_entity)) {
                $entity[$key] = $transformed_entity[$parts[1]];
            } elseif (array_key_exists($key, $transformed_entity)) {
                $entity[$key] = $transformed_entity[$key];
            } elseif (in_array($key, ['task.start_date', 'task.end_date', 'task.duration', 'task.billable', 'task.item_notes', 'task.time_log'])) {
                $entity[$key] = '';
            } else {
                $entity[$key] = $this->decorator->transform($key, $task);
            }

        }

        $entity = $this->decorateAdvancedFields($task, $entity);

        $entity = $this->convertFloats($entity);

        if (is_null($task->time_log) || (is_array(json_decode($task->time_log, true)) && count(json_decode($task->time_log, true)) == 0)) {
            $this->storage_array[] = $entity;
        } else {
            $this->iterateLogs($task, $entity);
        }

    }

    private function iterateLogs(Task $task, array $entity)
    {
        $timezone = Timezone::find($task->company->settings->timezone_id);
        $timezone_name = 'America/New_York';

        if ($timezone) {
            $timezone_name = $timezone->name;
        }

        $logs = json_decode($task->time_log, true);

        $date_format_default = $this->date_format;

        foreach ($logs as $key => $item) {
            if (in_array('task.start_date', $this->input['report_keys']) || in_array('start_date', $this->input['report_keys'])) {
                $carbon_object = Carbon::createFromTimeStamp((int)$item[0])->setTimezone($timezone_name);
                $entity['task.start_date'] = $carbon_object->format($date_format_default);
                $entity['task.start_time'] = $carbon_object->format('H:i:s');
            }

            if ((in_array('task.end_date', $this->input['report_keys']) || in_array('end_date', $this->input['report_keys'])) && $item[1] > 0) {
                $carbon_object = Carbon::createFromTimeStamp((int)$item[1])->setTimezone($timezone_name);
                $entity['task.end_date'] = $carbon_object->format($date_format_default);
                $entity['task.end_time'] = $carbon_object->format('H:i:s');
            }

            if ((in_array('task.end_date', $this->input['report_keys']) || in_array('end_date', $this->input['report_keys'])) && $item[1] == 0) {
                $entity['task.end_date'] = ctrans('texts.is_running');
                $entity['task.end_time'] = ctrans('texts.is_running');
            }

            $seconds = $task->calcDuration();
            $time_log_entry = (isset($item[1]) && $item[1] != 0) ? $item[1] - $item[0] : ctrans('texts.is_running');

            if (in_array('task.duration', $this->input['report_keys']) || in_array('duration', $this->input['report_keys'])) {
                $entity['task.duration'] = $seconds;
            }

            if (in_array('task.time_log', $this->input['report_keys']) || in_array('time_log', $this->input['report_keys'])) {
                $entity['task.time_log'] = $time_log_entry;
            }

            if (in_array('task.time_log_duration_words', $this->input['report_keys']) || in_array('time_log_duration_words', $this->input['report_keys'])) {
                $entity['task.time_log_duration_words'] =  is_int($time_log_entry) ? CarbonInterval::seconds($time_log_entry)->locale($this->company->locale())->cascade()->forHumans() : $time_log_entry;
            }

            if (in_array('task.duration_words', $this->input['report_keys']) || in_array('duration_words', $this->input['report_keys'])) {
                $entity['task.duration_words'] =  $seconds > 86400 ? CarbonInterval::seconds($seconds)->locale($this->company->locale())->cascade()->forHumans() : now()->startOfDay()->addSeconds($seconds)->format('H:i:s');
            }

            if (in_array('task.billable', $this->input['report_keys']) || in_array('billable', $this->input['report_keys'])) {
                $entity['task.billable'] = isset($item[3]) && $item[3] == 'true' ? ctrans('texts.yes') : ctrans('texts.no');
            }

            if (in_array('task.item_notes', $this->input['report_keys']) || in_array('item_notes', $this->input['report_keys'])) {
                $entity['task.item_notes'] = isset($item[2]) ? (string)$item[2] : '';
            }

            
            $this->storage_array[] = $entity;

            $entity['task.start_date'] = '';
            $entity['task.start_time'] = '';
            $entity['task.end_date'] = '';
            $entity['task.end_time'] = '';
            $entity['task.duration'] = '';
            $entity['task.duration_words'] = '';
            $entity['task.time_log'] = '';
            $entity['task.time_log_duration_words'] = '';
            $entity['task.billable'] = '';
            $entity['task.item_notes'] = '';

        }

    }

    /**
     * Add Task Status Filter
     *
     * @param  Builder $query
     * @param  string $status
     * @return Builder
     */
    protected function addTaskStatusFilter(Builder $query, string $status): Builder
    {
        /** @var array $status_parameters */
        $status_parameters = explode(',', $status);

        if (in_array('all', $status_parameters) || count($status_parameters) == 0) {
            return $query;
        }

        if (in_array('invoiced', $status_parameters)) {
            $query->whereNotNull('invoice_id');
        }

        if (in_array('uninvoiced', $status_parameters)) {
            $query->whereNull('invoice_id');
        }

        return $query;

    }

    private function decorateAdvancedFields(Task $task, array $entity): array
    {
        if (in_array('task.status_id', $this->input['report_keys'])) {
            $entity['task.status_id'] = $task->status()->exists() ? $task->status->name : '';
        }

        if (in_array('task.project_id', $this->input['report_keys'])) {
            $entity['task.project_id'] = $task->project()->exists() ? $task->project->name : '';
        }

        if (in_array('task.user_id', $this->input['report_keys'])) {
            $entity['task.user_id'] = $task->user ? $task->user->present()->name() : '';
        }

        if (in_array('task.assigned_user_id', $this->input['report_keys'])) {
            $entity['task.assigned_user_id'] = $task->assigned_user ? $task->assigned_user->present()->name() : '';
        }


        return $entity;
    }
}

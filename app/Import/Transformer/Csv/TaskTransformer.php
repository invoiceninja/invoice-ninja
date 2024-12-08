<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Csv;

use App\Import\Transformer\BaseTransformer;
use App\Models\TaskStatus;

/**
 * Class TaskTransformer.
 */
class TaskTransformer extends BaseTransformer
{
    private int $stubbed_timestamp = 0;
    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($task_items_data)
    {
        $this->stubbed_timestamp = time();

        if (count($task_items_data) == count($task_items_data, COUNT_RECURSIVE)) {
            $task_data = $task_items_data;
        } else {
            $task_data = reset($task_items_data);
        }

        $clientId = $this->getClient(
            $this->getString($task_data, 'client.name'),
            $this->getString($task_data, 'client.email')
        );

        $projectId = $task_data['project.name'] ?? '';

        $transformed = [
            'company_id' => $this->company->id,
            'number' => $this->getString($task_data, 'task.number'),
            'user_id' => $this->getString($task_data, 'task.user_id'),
            'rate' => $this->getFloat($task_data, 'task.rate'),
            'client_id' => $clientId,
            'project_id' => $this->getProjectId($projectId, $clientId),
            'description' => $this->getString($task_data, 'task.description'),
            'status_id' => $this->getTaskStatusId($task_data),
            'custom_value1' => $this->getString($task_data, 'task.custom_value1'),
            'custom_value2' => $this->getString($task_data, 'task.custom_value2'),
            'custom_value3' => $this->getString($task_data, 'task.custom_value3'),
            'custom_value4' => $this->getString($task_data, 'task.custom_value4'),
        ];

        if (count($task_items_data) == count($task_items_data, COUNT_RECURSIVE)) {
            $transformed['time_log'] = json_encode([$this->parseLog($task_items_data)]);
            return $transformed;
        }

        $time_log = collect($task_items_data)
                            ->map(function ($item) {
                                return $this->parseLog($item);

                            })->toJson();

        $transformed['time_log'] = $time_log;

        return $transformed;
    }

    private function parseLog($item)
    {
        $start_date = false;
        $end_date = false;

        $notes = $item['task.notes'] ?? '';

        if (isset($item['task.billable']) && is_string($item['task.billable']) && in_array($item['task.billable'], ['yes', 'true', '1', 'TRUE', 'YES'])) {
            $is_billable = true;
        } elseif (isset($item['task.billable']) && is_bool($item['task.billable'])) {
            $is_billable = $item['task.billable'];
        } else {
            $is_billable = true;
        }

        if (isset($item['task.start_date'])) {
            $start_date = $this->resolveStartDate($item);
            $end_date = $this->resolveEndDate($item);
        } elseif (isset($item['task.duration'])) {
            $duration =  strtotime($item['task.duration']) - strtotime('TODAY');
            $start_date = $this->stubbed_timestamp;
            $end_date = $this->stubbed_timestamp + $duration;
            $this->stubbed_timestamp;
        } else {
            return '';
        }

        return [$start_date, $end_date, $notes, $is_billable];
    }

    private function resolveStartDate($item)
    {

        $stub_start_date = $item['task.start_date'];
        $stub_start_date .= isset($item['task.start_time']) ? " ".$item['task.start_time'] : '';

        try {

            $stub_start_date = \Carbon\Carbon::parse($stub_start_date);
            $this->stubbed_timestamp = $stub_start_date->timestamp;

            return $stub_start_date->timestamp;
        } catch (\Exception $e) {
            nlog("fall back failed too" . $e->getMessage());
            // return $this->stubbed_timestamp;
        }


        try {

            $stub_start_date = \Carbon\Carbon::createFromFormat($this->company->date_format(), $stub_start_date);
            $this->stubbed_timestamp = $stub_start_date->timestamp;
        } catch (\Exception $e) {
            nlog($e->getMessage());
            return $this->stubbed_timestamp;
        }


    }

    private function resolveEndDate($item)
    {

        $stub_end_date = isset($item['task.end_date']) ? $item['task.end_date'] : $item['task.start_date'];
        $stub_end_date .= isset($item['task.end_time']) ? " ".$item['task.end_time'] : '';

        try {

            $stub_end_date = \Carbon\Carbon::parse($stub_end_date);

            if ($stub_end_date->timestamp == $this->stubbed_timestamp) {
                $this->stubbed_timestamp;
                return $this->stubbed_timestamp;
            }

            $this->stubbed_timestamp = $stub_end_date->timestamp;
            return $stub_end_date->timestamp;
        } catch (\Exception $e) {
            nlog($e->getMessage());

            // return $this->stubbed_timestamp;
        }



        try {

            $stub_end_date = \Carbon\Carbon::createFromFormat($this->company->date_format(), $stub_end_date);
            $this->stubbed_timestamp = $stub_end_date->timestamp;
        } catch (\Exception $e) {
            nlog("fall back failed too" . $e->getMessage());
            return $this->stubbed_timestamp;
        }




    }

    private function getTaskStatusId($item): ?int
    {
        if (isset($item['task.status'])) {
            $name = strtolower(trim($item['task.status']));

            $ts = TaskStatus::query()->where('company_id', $this->company->id)
                ->where('is_deleted', false)
                ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                    strtolower(str_replace(' ', '', $name)),
                ])
                ->first();

            if ($ts) {
                return $ts->id;
            }
        }

        return TaskStatus::where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->orderBy('status_order', 'asc')
            ->first()->id ?? null;

    }

}

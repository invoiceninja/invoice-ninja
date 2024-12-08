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

namespace App\Repositories;

use App\Models\Task;
use App\Models\TaskStatus;

/**
 * Class for task status repository.
 */
class TaskStatusRepository extends BaseRepository
{
    public function delete($task_status)
    {
        /** @var \App\Models\TaskStatus $ts **/
        $ts = TaskStatus::query()->where('company_id', $task_status->company_id)
                                 ->first();

        $new_status = $ts ? $ts->id : null;

        Task::query()->where('status_id', $task_status->id)
        ->where('company_id', $task_status->company_id)
        ->update(['status_id' => $new_status]);


        parent::delete($task_status);

        return $task_status;
    }

    public function archive($task_status)
    {
        $task_status = TaskStatus::withTrashed()
                                 ->where('id', $task_status->id)
                                 ->where('company_id', $task_status->company_id)
                                 ->first();

        $new_status = $task_status ? $task_status->id : null;

        Task::withTrashed()
            ->where('status_id', $task_status->id)
            ->where('company_id', $task_status->company_id)
            ->update(['status_id' => $new_status]);


        parent::archive($task_status);

        return $task_status;
    }

    public function reorder(TaskStatus $task_status)
    {

        TaskStatus::query()->where('company_id', $task_status->company_id)
                    ->where('id', '!=', $task_status->id)
                    ->orderByRaw('ISNULL(status_order), status_order ASC')
                    ->cursor()
                    ->each(function ($ts, $key) use ($task_status) {

                        if ($ts->status_order < $task_status->status_order) {
                            $ts->status_order--;
                            $ts->save();
                        } elseif ($ts->status_order >= $task_status->status_order) {
                            $ts->status_order++;
                            $ts->save();
                        } else {
                            $ts->status_order = 99999;
                            $ts->save();
                        }

                    });


        TaskStatus::query()->where('company_id', $task_status->company_id)
                ->orderByRaw('ISNULL(status_order), status_order ASC')
                ->cursor()
                ->each(function ($ts, $key) {
                    $ts->status_order = $key + 1;
                    $ts->save();
                });

    }
}

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

use App\Factory\TaskFactory;
use App\Jobs\Task\TaskAssigned;
use App\Models\Task;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

/**
 * App\Repositories\TaskRepository.
 */
class TaskRepository extends BaseRepository
{
    use GeneratesCounter;

    public $new_task = true;

    private $completed = true;

    private bool $task_round_up = true;

    private int $task_round_to_nearest = 1;

    /**
     * Saves the task and its contacts.
     *
     * @param      array                         $data    The data
     * @param      \App\Models\Task              $task  The task
     *
     * @return     task|null  task Object
     */
    public function save(array $data, Task $task): ?Task
    {
        if ($task->id) {
            $this->new_task = false;
        }

        if (!is_numeric($task->rate) && !isset($data['rate'])) {
            $data['rate'] = 0;
        }

        $task->fill($data);
        $task->saveQuietly();

        if (isset($data['assigned_user_id']) && $data['assigned_user_id'] != $task->assigned_user_id) {
            TaskAssigned::dispatch($task, $task->company->db)->delay(2);
        }

        $this->init($task);

        if ($this->new_task && ! $task->status_id) {
            $task->status_id = $this->setDefaultStatus($task);
        }

        if ($this->new_task && (!$task->rate || $task->rate <= 0)) {
            $task->rate = $task->getRate();
        }

        $task->number = empty($task->number) || ! array_key_exists('number', $data) ? $this->trySaving($task) : $data['number'];

        if (isset($data['description'])) {
            $task->description = trim($data['description']);
        }

        //todo i can't set it - i need to calculate it.
        if (isset($data['status_order'])) {
            $task->status_order = $data['status_order'];
        }

        /*V4 override*/
        if (! empty($data['time_details'])) {
            $timeLog = [];
            foreach ($data['time_details'] as $detail) {
                $startTime = strtotime($detail['start_datetime'] ?? now()->format('Y-m-d'));
                $endTime = false;
                if (! empty($detail['end_datetime'])) {
                    $endTime = strtotime($detail['end_datetime']);
                } else {
                    $duration = 0;
                    if (! empty($detail['duration_seconds'])) {
                        $duration += $detail['duration_seconds'];
                    }
                    if (! empty($detail['duration_minutes'])) {
                        $duration += $detail['duration_minutes'] * 60;
                    }
                    if (! empty($detail['duration_hours'])) {
                        $duration += $detail['duration_hours'] * 60 * 60;
                    }
                    if ($duration) {
                        $endTime = $startTime + $duration;
                    }
                }
                $timeLog[] = [$startTime, $endTime];
                if (! $endTime) {
                    $data['is_running'] = true;
                }
            }
            $data['time_log'] = json_encode($timeLog);
        }

        if (isset($data['time_log'])) {
            $time_log = json_decode($data['time_log']);
        } elseif ($task->time_log) {
            $time_log = json_decode($task->time_log);
        } else {
            $time_log = [];
        }

        $key_values = array_column($time_log, 0);

        if (count($key_values) > 0) {
            array_multisort($key_values, SORT_ASC, $time_log);
        }

        foreach ($time_log as $key => $value) {

            if (is_array($time_log[$key]) && count($time_log[$key]) >= 2) {
                $time_log[$key][1] = $this->roundTimeLog($time_log[$key][0], $time_log[$key][1]);
            }

        }

        if (isset($data['action'])) {
            if ($data['action'] == 'start') {
                $task->is_running = true;
                $time_log[] = [strtotime('now'), false];
            } elseif ($data['action'] == 'resume') {
                $task->is_running = true;
                $time_log[] = [strtotime('now'), false];
            } elseif ($data['action'] == 'stop' && $task->is_running) {
                $time_log[count($time_log) - 1][1] = time();
                $task->is_running = false;
            } elseif ($data['action'] == 'offline') {
                $task->is_running = $data['is_running'] ? true : false;
            }
        } elseif (isset($data['is_running'])) {
            $task->is_running = $data['is_running'] ? true : false;
        }

        $task->calculated_start_date = $this->harvestStartDate($time_log, $task);

        if (isset(end($time_log)[1])) {
            $task->is_running = end($time_log)[1] == 0;
        }

        $task->time_log = json_encode($time_log);

        $task->saveQuietly();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $task);
        }

        $this->calculateProjectDuration($task);

        return $task;
    }

    private function harvestStartDate($time_log, $task)
    {

        if (isset($time_log[0][0])) {
            return \Carbon\Carbon::createFromTimestamp((int)$time_log[0][0])->addSeconds($task->company->utc_offset());
        }

        return null;

    }

    /**
     * Store tasks in bulk.
     *
     * @param array $task
     * @return Task|null
     */
    public function create($task): ?Task
    {
        /** @var \App\Models\User $user **/
        $user = auth()->user();

        return $this->save(
            $task,
            TaskFactory::create($user->company()->id, $user->id)
        );
    }

    private function setDefaultStatus(Task $task)
    {
        $first_status = $task->company->task_statuses()
                              ->whereNull('deleted_at')
                              ->orderBy('id', 'asc')
                              ->first();

        if ($first_status) {
            return $first_status->id;
        }

        return null;
    }

    /**
     * Sorts the task status order IF the old status has changed between requests
     *
     * @param  Task     $new_task The new Task model
     * @return void
     */
    public function sortStatuses($new_task)
    {
        if (! $new_task->project()->exists()) {
            return;
        }

        $index = $new_task->status_order;

        $tasks = $new_task->project->tasks->reject(function ($task) use ($new_task) {
            return $task->id == $new_task->id;
        });

        $sorted_tasks = $tasks->filter(function ($task, $key) use ($index) {
            return $key < $index;
        })->push($new_task)->merge($tasks->filter(function ($task, $key) use ($index) {
            return $key >= $index;
        }))->each(function ($item, $key) {
            $item->status_order = $key;
            $item->saveQuietly();
        });
    }

    public function start(Task $task)
    {
        //do no allow an task to be restarted if it has been invoiced
        if ($task->invoice_id) {
            return;
        }

        if (strlen($task->time_log) < 5) {
            $log = [];

            $start_time = time();

            $log = array_merge($log, [[$start_time, 0]]);
            $task->time_log = json_encode($log);
            $task->calculated_start_date = \Carbon\Carbon::createFromTimestamp($start_time)->addSeconds($task->company->utc_offset());

            $task->saveQuietly();
        }

        $log = json_decode($task->time_log, true);

        $last = end($log);

        if (is_array($last) && $last[1] !== 0) { // this line is a disaster
            $new = [time(), 0];

            $log = array_merge($log, [$new]);
            $task->time_log = json_encode($log);
            $task->is_running = true;
            $task->saveQuietly();
        }

        $this->calculateProjectDuration($task);

        return $task;
    }

    public function roundTimeLog(int $start_time, int $end_time): int
    {
        if (in_array($this->task_round_to_nearest, [0,1]) || $end_time == 0) {
            return $end_time;
        }

        $interval = $end_time - $start_time;

        if ($this->task_round_up) {
            return $start_time + (int)ceil($interval / $this->task_round_to_nearest) * $this->task_round_to_nearest;
        }

        if ($interval <= $this->task_round_to_nearest) {
            return $start_time;
        }

        return $start_time + (int)floor($interval / $this->task_round_to_nearest) * $this->task_round_to_nearest;

    }

    public function stop(Task $task)
    {
        $this->init($task);

        $log = json_decode($task->time_log, true);

        $last = end($log);

        if (is_array($last) && $last[1] === 0) {
            $last[1] = $this->roundTimeLog($last[0], time());

            array_pop($log);
            $log = array_merge($log, [$last]);//check at this point, it may be prepending here.

            $task->time_log = json_encode($log);
            $task->is_running = false;
            $task->saveQuietly();
        }

        $this->calculateProjectDuration($task);

        return $task;

    }

    public function triggeredActions($request, $task)
    {
        if ($request->has('start') && $request->input('start') == 'true') {
            $task = $this->start($task);
        }

        if ($request->has('stop') && $request->input('stop') == 'true') {
            $task = $this->stop($task);
        }

        return $task;
    }

    private function init(Task $task): self
    {

        $this->task_round_up = $task->client ? $task->client->getSetting('task_round_up') : $task->company->getSetting('task_round_up');
        $this->task_round_to_nearest = $task->client ? $task->client->getSetting('task_round_to_nearest') : $task->company->getSetting('task_round_to_nearest');

        return $this;

    }

    private function trySaving(Task $task)
    {
        $x = 1;

        do {
            try {
                $task->number = $this->getNextTaskNumber($task);
                $task->saveQuietly();
                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 50) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);

        return $task->number;
    }

    private function calculateProjectDuration(Task $task)
    {

        if ($task->project) {

            $duration = 0;

            $task->project->tasks()->withTrashed()->where('is_deleted', 0)->each(function ($task) use (&$duration) {

                if (is_iterable(json_decode($task->time_log))) {

                    foreach (json_decode($task->time_log) as $log) {

                        if (!is_array($log)) {
                            continue;
                        }

                        $start_time = $log[0];
                        $end_time = $log[1] == 0 ? time() : $log[1];

                        $duration += $end_time - $start_time;

                    }
                }

            });

            $task->project->current_hours = (int) round(($duration / 60 / 60), 0);
            $task->push();
        }
    }

    /**
     * @param $entity
     */
    public function restore($task)
    {
        if (!$task->trashed()) {
            return;
        }

        parent::restore($task);

        $this->calculateProjectDuration($task);

    }

    /**
     * @param $entity
     */
    public function delete($task)
    {
        if ($task->is_deleted) {
            return;
        }

        parent::delete($task);

        $this->calculateProjectDuration($task);

    }

}

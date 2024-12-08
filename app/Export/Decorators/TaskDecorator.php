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

namespace App\Export\Decorators;

use App\Models\DateFormat;
use App\Models\Task;
use App\Models\Timezone;
use Carbon\Carbon;

class TaskDecorator extends Decorator implements DecoratorInterface
{
    //@todo - we do not handle iterating through the timelog here.
    public function transform(string $key, mixed $entity): mixed
    {
        $task = false;
        if ($entity instanceof Task) {
            $task = $entity;
        } elseif ($entity->task) {
            $task = $entity->task;
        }

        if ($task && method_exists($this, $key)) {
            return $this->{$key}($task);
        } elseif ($task && $task->{$key} ?? false) {
            return $task->{$key};
        }

        return '';

    }

    public function start_date(Task $task)
    {

        $timezone = Timezone::find($task->company->settings->timezone_id);
        $timezone_name = 'America/New_York';

        if ($timezone) {
            $timezone_name = $timezone->name;
        }

        $logs = json_decode($task->time_log, true);

        $date_format_default = 'Y-m-d';

        $date_format = DateFormat::find($task->company->settings->date_format_id);

        if ($date_format) {
            $date_format_default = $date_format->format;
        }

        if (is_array($logs)) {
            $item = $logs[0];
            return Carbon::createFromTimeStamp((int)$item[0])->setTimezone($timezone_name)->format($date_format_default);
        }

        return '';

    }

    public function end_date(Task $task)
    {

        $timezone = Timezone::find($task->company->settings->timezone_id);
        $timezone_name = 'America/New_York';

        if ($timezone) {
            $timezone_name = $timezone->name;
        }

        $logs = json_decode($task->time_log, true);

        $date_format_default = 'Y-m-d';

        $date_format = DateFormat::find($task->company->settings->date_format_id);

        if ($date_format) {
            $date_format_default = $date_format->format;
        }

        if (is_array($logs)) {
            $item = $logs[1];
            return Carbon::createFromTimeStamp((int)$item[1])->setTimezone($timezone_name)->format($date_format_default);
        }

        return '';

    }

    /**
     * billable
     *
     * @todo
     */
    public function billable(Task $task)
    {
        return '';
    }

    /**
     * items_notes
     * @todo
     */
    public function items_notes(Task $task)
    {
        return '';
    }

    public function duration(Task $task)
    {
        return $task->calcDuration();
    }

    public function status_id(Task $task)
    {
        return $task->status()->exists() ? $task->status->name : '';
    }

    public function project_id(Task $task)
    {
        return $task->project()->exists() ? $task->project->name : '';
    }

    public function assigned_user_id(Task $task)
    {
        return $task->assigned_user ? $task->assigned_user->present()->name() : '';
    }

    public function user_id(Task $task)
    {
        return $task->user ? $task->user->present()->name() : '';
    }
}

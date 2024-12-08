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

namespace App\Services\Project;

use App\Models\Project;
use Illuminate\Support\Carbon;

class ProjectService
{

    public function __construct(public Project $project)
    {
    }

    public function timelineData()
    {

        // Example data
        $project_start = Carbon::parse($this->project->created_at)->addSeconds($this->project->company->timezone_offset());
        $project_due = Carbon::parse($this->project->due_date);
        $budgeted_hours = $this->project->budgeted_hours;
        $project_duration = $project_start->diffInDays($project_due) + 1; 
        $average_daily_hours = $budgeted_hours / $project_duration;

        $task_query = $this->project
                            ->tasks()
                            ->orderBy('calculated_start_date','asc');


        $average_data = $task_query
                            ->get()
                            ->map(function ($task){

            return [
                    'date' => $task->calculated_start_date ?? \Carbon\Carbon::parse($task->created_at)->format('Y-m-d'), 
                    'hours_used' => $task->calcDuration()/60/60,
                ];
        });

        $last_task = $task_query->latest()->first();

        $next_date = \Carbon\Carbon::parse($last_task->calculated_start_date ?? now()->format('Y-m-d'));

        do{

            $next_date->addDay();

            $average_data->push([
                'date' => $next_date->format('Y-m-d'), 
                'hours_used' => 0,    
            ]);

        }
        while($next_date->lt($project_due));

        return [
            'project_start' => $project_start->toDateString(),
            'project_due' => $project_due->toDateString(),
            'hours_used' => $this->project->current_hours,
            'average_data' => $average_data,
        ];
        

    }

    /**
     * Saves the project.
     * @return \App\Models\Project
     */
    public function save(): ?Project
    {
        $this->project->saveQuietly();

        return $this->project;
    }
}

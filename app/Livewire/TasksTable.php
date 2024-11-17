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

namespace App\Livewire;

use App\Libraries\MultiDB;
use App\Models\Task;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class TasksTable extends Component
{
    use WithSorting;
    use WithPagination;

    public $per_page = 10;

    public $company_id;

    public $db;

    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function render()
    {
        $query = Task::query()
            ->where('company_id', $this->company_id)
            ->where('is_deleted', false)
            ->where('client_id', auth()->guard('contact')->user()->client_id);

        if (auth()->guard('contact')->user()->client->getSetting('show_all_tasks_client_portal') === 'invoiced') {
            $query = $query->whereNotNull('invoice_id');
        }

        if (auth()->guard('contact')->user()->client->getSetting('show_all_tasks_client_portal') === 'uninvoiced') {
            $query = $query->whereNull('invoice_id');
        }

        $query = $query
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.tasks-table', [
            'tasks' => $query,
            'show_item_description' => auth()->guard('contact')->user()->client->getSetting("show_task_item_description"),
        ]);
    }
}

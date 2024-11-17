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

use App\Models\Task;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Company;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use Livewire\Component;
use App\Models\Document;
use App\Libraries\MultiDB;
use Livewire\WithPagination;
use App\Models\RecurringInvoice;
use App\Utils\Traits\WithSorting;
use Livewire\Attributes\Computed;

class DocumentsTable extends Component
{
    use WithPagination;
    use WithSorting;

    public int $client_id;

    public int $per_page = 10;

    public string $tab = 'documents';

    public string $db;

    protected $query;

    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->query = $this->documents();
    }

    #[Computed()]
    public function client()
    {
        return Client::withTrashed()->find($this->client_id);
    }

    public function render()
    {
        $this->updateResources(request()->tab ?: $this->tab);

        return render('components.livewire.documents-table', [
            'documents' => $this->query
                ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
                ->withTrashed()
                ->paginate($this->per_page),
        ]);
    }

    public function updateResources(string $resource)
    {
        $this->tab = $resource;

        switch ($resource) {
            case 'documents':
                $this->query = $this->documents();
                break;

            case 'credits':
                $this->query = $this->credits();
                break;

            case 'expenses':
                // $this->query = $this->expenses();
                break;

            case 'invoices':
                $this->query = $this->invoices();
                break;

            case 'payments':
                $this->query = $this->payments();
                break;

            case 'projects':
                $this->query = $this->projects();
                break;

            case 'quotes':
                $this->query = $this->quotes();
                break;

            case 'recurringInvoices':
                $this->query = $this->recurringInvoices();
                break;

            case 'tasks':
                $this->query = $this->tasks();
                break;

            default:
                $this->query = $this->documents();
                break;
        }
    }

    protected function documents()
    {
        return $this->client()->documents()
            ->where('is_public', true);
    }

    protected function credits()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [Credit::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }

    protected function expenses()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [Expense::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }

    protected function invoices()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [Invoice::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }

    protected function payments()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [Payment::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }

    protected function projects()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [Project::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }

    protected function quotes()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [Quote::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }

    protected function recurringInvoices()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [RecurringInvoice::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }

    protected function tasks()
    {
        return Document::query()
            ->where('is_public', true)
            ->whereHasMorph('documentable', [Task::class], function ($query) {
                $query->where('client_id', $this->client()->id);
            });
    }
}

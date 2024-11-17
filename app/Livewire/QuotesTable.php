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
use App\Models\Company;
use App\Models\Quote;
use Livewire\Component;
use Livewire\WithPagination;

class QuotesTable extends Component
{
    use WithPagination;

    public int $per_page = 10;

    public array $status = [];

    public string $sort = 'status_id';

    public bool $sort_asc = true;

    public int $company_id;

    public string $db;

    public function mount()
    {
        MultiDB::setDb($this->db);
    }


    public function sortBy($field)
    {
        $this->sort === $field
            ? $this->sort_asc = ! $this->sort_asc
            : $this->sort_asc = true;

        $this->sort = $field;
    }

    public function render()
    {
        $query = Quote::query()
            ->with('client.contacts', 'company')
            ->orderBy($this->sort, $this->sort_asc ? 'asc' : 'desc');

        if (count($this->status) > 0) {
            /* Special filter for expired*/
            if (in_array('-1', $this->status)) {
                $query->where(function ($query) {
                    $query->whereDate('due_date', '<=', now()->startOfDay())
                          ->whereNotNull('due_date')
                          ->where('status_id', '<>', Quote::STATUS_CONVERTED);
                });
            }

            if (in_array('2', $this->status)) {
                $query->where(function ($query) {
                    $query->whereDate('due_date', '>=', now()->startOfDay())
                          ->orWhereNull('due_date');
                })->where('status_id', Quote::STATUS_SENT);
            }

            if (in_array('3', $this->status)) {
                $query->whereIn('status_id', [Quote::STATUS_APPROVED, Quote::STATUS_CONVERTED]);
            }
        }

        $query = $query
            ->where('company_id', auth()->guard('contact')->user()->company_id)
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->where('is_deleted', 0)
            ->where('status_id', '<>', Quote::STATUS_DRAFT)
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.quotes-table', [
            'quotes' => $query,
        ]);
    }
}

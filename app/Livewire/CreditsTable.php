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
use App\Models\Credit;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class CreditsTable extends Component
{
    use WithPagination;
    use WithSorting;

    public int $per_page = 10;

    public string $db;

    public int $company_id;

    public function mount()
    {
        MultiDB::setDb($this->db);

    }

    public function render()
    {

        $query = Credit::query()
            ->where('company_id', auth()->guard('contact')->user()->company_id)
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->where('status_id', '<>', Credit::STATUS_DRAFT)
            ->where('is_deleted', 0)
            ->where(function ($query) {
                $query->whereDate('due_date', '>=', now())
                      ->orWhereNull('due_date');
            })
            // ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->when($this->sort_field == 'number', function ($q){
                $q->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . ($this->sort_asc ? 'desc' : 'asc'));
            })
            ->when($this->sort_field != 'number', function ($q){
                $q->orderBy($this->sort_field, ($this->sort_asc ? 'desc' : 'asc'));
            })
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.credits-table', [
            'credits' => $query,
        ]);
    }
}

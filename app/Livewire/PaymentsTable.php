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
use App\Models\Payment;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentsTable extends Component
{
    use WithSorting;
    use WithPagination;

    public int $per_page = 10;

    public int $company_id;

    public string $db;

    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function render()
    {
        $query = Payment::query()
            ->with('type', 'client', 'invoices')
            ->where('company_id', auth()->guard('contact')->user()->company_id)
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->whereIn('status_id', [Payment::STATUS_FAILED, Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED])
            // ->orderBy($this->sort_field, $this->sort_asc ? 'desc' : 'asc')
            ->when($this->sort_field == 'number', function ($q){
                $q->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . ($this->sort_asc ? 'desc' : 'asc'));
            })
            ->when($this->sort_field != 'number', function ($q){
                $q->orderBy($this->sort_field, ($this->sort_asc ? 'desc' : 'asc'));
            })
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.payments-table', [
            'payments' => $query,
        ]);
    }
}

<?php

namespace App\Livewire;

use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class PaymentMethodsTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;

    public int $client_id;

    public string $db;

    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function render()
    {
        $query = ClientGatewayToken::query()
            ->with('gateway_type')
            ->where('company_id', auth()->guard('contact')->user()->company_id)
            ->where('client_id', auth()->guard('contact')->user()->client_id)
            ->whereHas('gateway', function ($query) {
                $query->where('is_deleted', 0)
                       ->where('deleted_at', null);
            })
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.payment-methods-table', [
            'payment_methods' => $query,
        ]);
    }
}

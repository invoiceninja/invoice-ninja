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
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class PurchaseOrdersTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;

    public $status = [];

    public $db;

    public function mount()
    {
        MultiDB::setDb($this->db);

        $this->sort_asc = false;

        $this->sort_field = 'date';
    }

    public function render()
    {
        $local_status = [];

        $query = PurchaseOrder::query()
            ->with('vendor.contacts')
            ->where('company_id', auth()->guard('vendor')->user()->company_id)
            ->whereIn('status_id', [PurchaseOrder::STATUS_SENT, PurchaseOrder::STATUS_ACCEPTED])
            ->where('is_deleted', false)
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc');

        if (in_array('sent', $this->status)) {
            $local_status[] = PurchaseOrder::STATUS_SENT;
        }

        if (in_array('accepted', $this->status)) {
            $local_status[] = PurchaseOrder::STATUS_ACCEPTED;
        }

        if (count($local_status) > 0) {
            $query = $query->whereIn('status_id', array_unique($local_status));
        }

        $query = $query
            ->where('vendor_id', auth()->guard('vendor')->user()->vendor_id)
            // ->where('status_id', '<>', Invoice::STATUS_DRAFT)
            // ->where('status_id', '<>', Invoice::STATUS_CANCELLED)
            ->withTrashed()
            ->paginate($this->per_page);

        return render('components.livewire.purchase-orders-table', [
            'purchase_orders' => $query,
        ]);
    }
}

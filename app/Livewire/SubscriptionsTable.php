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
use App\Models\RecurringInvoice;
use App\Utils\Traits\WithSorting;
use Livewire\Component;
use Livewire\WithPagination;

class SubscriptionsTable extends Component
{
    use WithPagination;
    use WithSorting;

    public $per_page = 10;

    public $company_id;

    public $db;
    
    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function render()
    {
        $query = RecurringInvoice::query()
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('company_id', $this->company_id)
            ->whereNotNull('subscription_id')
            ->where('is_deleted', false)
            ->whereIn('status_id', [RecurringInvoice::STATUS_ACTIVE, RecurringInvoice::STATUS_PAUSED])
            ->withTrashed()
            ->orderBy($this->sort_field, $this->sort_asc ? 'asc' : 'desc')
            ->paginate($this->per_page);

        return render('components.livewire.subscriptions-table', [
            'recurring_invoices' => $query,
        ]);
    }
}

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
use Livewire\Component;

class RecurringInvoiceCancellation extends Component
{
    public $invoice_id;

    public $db;

    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    public function render()
    {
        return render('components.livewire.recurring-invoice-cancellation');
    }

    public function processCancellation()
    {

        MultiDB::setDb($this->db);

        $ri = RecurringInvoice::withTrashed()->find($this->invoice_id);

        if ($ri->subscription) {
            return $ri->subscription->service()->handleCancellation($ri);
        }

        return redirect()->route('client.recurring_invoices.request_cancellation', ['recurring_invoice' => $ri->hashed_id]);
    }
}

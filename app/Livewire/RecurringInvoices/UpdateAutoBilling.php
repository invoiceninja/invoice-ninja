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

namespace App\Livewire\RecurringInvoices;

use App\Models\Invoice;
use Livewire\Component;
use App\Libraries\MultiDB;
use App\Models\RecurringInvoice;
use Livewire\Attributes\Computed;

class UpdateAutoBilling extends Component
{
    public $invoice_id;

    public $db;

    public function mount()
    {
        MultiDB::setDb($this->db);
    }

    #[Computed]
    public function invoice()
    {
        return RecurringInvoice::withTrashed()->find($this->invoice_id);
    }

    public function updateAutoBilling(): void
    {
        $invoice = $this->invoice();

        if ($invoice->auto_bill == 'optin' || $invoice->auto_bill == 'optout') {
            $invoice->auto_bill_enabled = ! $invoice->auto_bill_enabled;
            $invoice->saveQuietly();

            Invoice::withTrashed()
                        ->where('company_id', $invoice->company_id)
                        ->where('recurring_id', $invoice->id)
                        ->whereIn('status_id', [2,3])
                        ->where('is_deleted', 0)
                        ->where('balance', '>', 0)
                        ->update(['auto_bill_enabled' => $invoice->auto_bill_enabled]);
        }
    }

    public function render()
    {
        return render('components.livewire.recurring-invoices-switch-autobilling');
    }
}

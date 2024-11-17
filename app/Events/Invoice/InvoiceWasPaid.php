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

namespace App\Events\Invoice;

use App\Models\BaseModel;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\Invoice\Broadcasting\DefaultResourceBroadcast;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Queue\SerializesModels;

/**
 * Class InvoiceWasPaid.
 */
class InvoiceWasPaid implements ShouldBroadcast
{
    use SerializesModels, DefaultResourceBroadcast, InteractsWithSockets;

    /**
     * Create a new event instance.
     *
     * @param Invoice $invoice
     * @param Company $company
     * @param Payment $payment
     * @param array $event_vars
     */
    public function __construct(public Invoice $invoice, public Payment $payment, public Company $company, public array $event_vars)
    {
        $this->dontBroadcastToCurrentUser();
    }

    public function broadcastModel(): BaseModel
    {
        return $this->invoice;
    }
}

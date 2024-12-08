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

namespace App\Services\Subscription;

use App\Models\Subscription;
use App\Repositories\RecurringInvoiceRepository;
use App\Services\AbstractService;

class ZeroCostProduct extends AbstractService
{
    private $subscription;

    private $data;

    /**
    * $data = [
    *     'email' => $this->email ?? $this->contact->email,
    *     'quantity' => $this->quantity,
    *     'contact_id' => $this->contact->id,
    *     'client_id' => $this->contact->client->id,
    * ];
     */
    public function __construct(Subscription $subscription, array $data)
    {
        $this->subscription = $subscription;

        $this->data = $data;
    }

    public function run()
    {
        //create a zero dollar invoice.

        $invoice = $this->subscription->service()->createInvoice($this->data);

        $invoice = $invoice->service()
                           ->markPaid()
                           ->save();

        $redirect_url = "/client/invoices/{$invoice->hashed_id}";

        //create a recurring zero dollar invoice attached to this subscription.

        if (strlen($this->subscription->recurring_product_ids) >= 1) {
            $recurring_invoice = $this->subscription->service()->convertInvoiceToRecurring($this->data['client_id']);
            $recurring_invoice_repo = new RecurringInvoiceRepository();

            $recurring_invoice->next_send_date = now();
            $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
            $recurring_invoice->next_send_date = now();
            $recurring_invoice->next_send_date_client = now();
            $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
            $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();

            /* Start the recurring service */
            $recurring_invoice->service()
                              ->start()
                              ->save();

            $invoice->recurring_id = $recurring_invoice->id;
            $invoice->save();

            $context = [
                'context' => 'recurring_purchase',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'invoice' => $invoice->hashed_id,
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
                'contact' => auth()->guard('contact')->user()->hashed_id,
                'redirect_url' => "/client/recurring_invoices/{$recurring_invoice->hashed_id}",
            ];

            if (isset($this->data['campaign'])) {
                $context['campaign'] = $this->data['campaign'];
            }

            return $context;
        }

        return ['redirect_url' => $redirect_url];
    }
}

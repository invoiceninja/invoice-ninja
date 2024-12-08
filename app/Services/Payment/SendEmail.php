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

namespace App\Services\Payment;

use App\Jobs\Payment\EmailPayment;
use App\Models\ClientContact;
use App\Models\Payment;
use Illuminate\Database\QueryException;

class SendEmail
{
    public function __construct(public Payment $payment, public ?ClientContact $contact)
    {
    }

    /**
     * Builds the correct template to send.
     */
    public function run()
    {
        $this->payment->load('company', 'invoices');

        if (!$this->contact) {

            if ($invoice = $this->payment->invoices->first() ?? false) {

                $invitation =
                $invoice
                    ->invitations()
                    ->whereHas("contact", function ($q) {
                        $q->where('send_email', true)->orderBy("is_primary", 'ASC');
                    })
                    ->first();

                if ($invitation) {
                    $this->contact = $invitation->contact;
                } else {
                    $this->contact = $this->payment->client->contacts()->orderBy('send_email', 'desc')->orderBy('is_primary', 'desc')->first();
                }
            }

        }

        EmailPayment::dispatch($this->payment, $this->payment->company, $this->contact);

    }
}

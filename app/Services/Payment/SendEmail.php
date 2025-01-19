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
        
        EmailPayment::dispatch($this->payment, $this->payment->company, $this->contact);

    }
}

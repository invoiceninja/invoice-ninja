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

namespace App\Mail\EInvoice\Peppol;

use Illuminate\Mail\Mailable;

class CreditsExhausted extends Mailable
{
    public function __construct(
        public string $email,
        public bool $is_hosted,
    ) {
        //
    }

    public function build(): self
    {
        return $this
            ->to($this->email)
            ->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.notification_no_credits'))
            ->text('email.einvoice.peppol.credits_exhausted_text')
            ->view('email.einvoice.peppol.credits_exhausted')
            ->with([
                //
            ]);
    }
}

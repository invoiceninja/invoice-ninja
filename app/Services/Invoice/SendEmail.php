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

namespace App\Services\Invoice;

use App\Utils\Ninja;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Models\ClientContact;
use App\Jobs\Entity\EmailEntity;
use App\Services\AbstractService;
use App\Events\Invoice\InvoiceWasEmailed;

class SendEmail extends AbstractService
{
    public function __construct(protected Invoice $invoice, protected $reminder_template = null, protected ?ClientContact $contact = null)
    {
    }

    /**
     * Builds the correct template to send.
     */
    public function run()
    {
        if (! $this->reminder_template) {
            $this->reminder_template = $this->invoice->calculateTemplate('invoice');
        }

        $this->invoice->invitations->each(function ($invitation) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {
                EmailEntity::dispatch($invitation->withoutRelations(), $invitation->company->db, $this->reminder_template)->delay(10);
            }
        });

        if ($this->invoice->invitations->count() >= 1) {
            event(new InvoiceWasEmailed($this->invoice->invitations->first(), $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), $this->reminder_template ?? 'invoice'));
            $this->invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");

        }

    }
}

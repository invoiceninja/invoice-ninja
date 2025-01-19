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
use App\Services\Email\Email;
use App\Jobs\Entity\EmailEntity;
use App\Services\AbstractService;
use App\Services\Email\EmailObject;
use App\Events\General\EntityWasEmailed;
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

        $base_template = $this->reminder_template;

        $this->reminder_template = $this->resolveTemplateString($this->reminder_template);

        $this->invoice->service()->markSent()->save();

        $this->invoice->invitations->filter(function ($invitation){
            return (! $invitation->contact->trashed() && $invitation->contact->email);
        })->each(function ($invitation) use($base_template) {
            
                $mo = new EmailObject();
                $mo->entity_id = $invitation->invoice_id;
                $mo->template = $this->reminder_template; 
                $mo->email_template_body = $this->reminder_template;
                $mo->email_template_subject = str_replace("template", "subject", $this->reminder_template);

                $mo->entity_class = get_class($invitation->invoice);
                $mo->invitation_id = $invitation->id;
                $mo->client_id = $invitation->contact->client_id ?? null;
                $mo->vendor_id = $invitation->contact->vendor_id ?? null;

                Email::dispatch($mo, $invitation->company);

                $this->invoice->entityEmailEvent($invitation, $base_template, $base_template);

        });

        event(new EntityWasEmailed($this->invoice->invitations->first(), $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'invoice'));

        $this->invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");

    }


    private function resolveTemplateString(string $template): string
    {

        return match ($template) {
            'reminder1' => 'email_template_reminder1',
            'reminder2' => 'email_template_reminder2',
            'reminder3' => 'email_template_reminder3',
            'endless_reminder' => 'email_template_reminder_endless',
            'custom1' => 'email_template_custom1',
            'custom2' => 'email_template_custom2',
            'custom3' => 'email_template_custom3',
            default => "email_template_{$template}",
        };

    }

}

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

namespace App\Services\Quote;

use App\Models\Webhook;
use App\Models\ClientContact;
use App\Services\Email\Email;
use App\Jobs\Entity\EmailEntity;
use App\Models\Quote;
use App\Services\Email\EmailObject;

class SendEmail
{
    public function __construct(public Quote $quote, public ?string $reminder_template = null, protected ?ClientContact $contact = null)
    {
    }

    /**
     * Builds the correct template to send.
     * @return void
     */
    public function run()
    {

        if(in_array($this->reminder_template, ["email_quote_template_reminder1","reminder1"]))
            $this->reminder_template = "email_quote_template_reminder1";
        else    
            $this->reminder_template = "email_template_".$this->quote->calculateTemplate('quote');

        $this->quote->service()->markSent()->save();

        $this->quote->invitations->each(function ($invitation) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {

                //@refactor 2024-11-10
                $mo = new EmailObject();
                $mo->entity_id = $invitation->quote_id;
                $mo->template = $this->reminder_template; //full template name in use
                $mo->email_template_body = $this->reminder_template;
                $mo->email_template_subject = str_replace("template", "subject", $this->reminder_template);

                $mo->entity_class = get_class($invitation->quote);
                $mo->invitation_id = $invitation->id;
                $mo->client_id = $invitation->contact->client_id ?? null;
                $mo->vendor_id = $invitation->contact->vendor_id ?? null;

                Email::dispatch($mo, $invitation->company);

            }
        });

        $this->quote->sendEvent(Webhook::EVENT_SENT_QUOTE, "client");

    }
}

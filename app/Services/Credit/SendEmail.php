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

namespace App\Services\Credit;

use App\Utils\Ninja;
use App\Models\Webhook;
use App\Models\ClientContact;
use App\Jobs\Entity\EmailEntity;
use App\Events\Credit\CreditWasEmailed;
use App\Events\General\EntityWasEmailed;

class SendEmail
{
    public $credit;

    protected $reminder_template;

    protected $contact;

    public function __construct($credit, $reminder_template = null, ClientContact $contact = null)
    {
        $this->credit = $credit;

        $this->reminder_template = $reminder_template;

        $this->contact = $contact;
    }

    /**
     * Builds the correct template to send.
     */
    public function run()
    {
        if (! $this->reminder_template) {
            $this->reminder_template = $this->credit->calculateTemplate('credit');
        }

        $this->credit->service()->markSent()->save();

        $this->credit->invitations->load('contact.client.country', 'credit.client.country', 'credit.company')->each(function ($invitation)  {

            $mo = new \App\Services\Email\EmailObject();
            $mo->entity_id = $this->credit->id;
            $mo->entity_class = \App\Models\Credit::class;
            $mo->invitation_id = $invitation->id;
            $mo->client_id = $invitation->contact->client_id ?? null;
            $mo->vendor_id = $invitation->contact->vendor_id ?? null;
            $mo->settings = $invitation->contact->client->getMergedSettings();
            $mo->email_template_body = 'email_template_credit';
            $mo->email_template_subject = 'email_subject_credit';

            if (! $invitation->contact->trashed() && $invitation->contact->email) {
                \App\Services\Email\Email::dispatch($mo, $invitation->company);
                $this->credit->entityEmailEvent($invitation, 'credit', 'credit');
            }
        });

        event(new EntityWasEmailed($this->credit->invitations->first(), $this->credit->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'credit'));

        if ($this->credit->invitations->count() >= 1) {
            event(new CreditWasEmailed($this->credit->invitations->first(), $this->credit->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'credit'));
            $this->credit->sendEvent(Webhook::EVENT_SENT_CREDIT, "client");

        }

    }
}

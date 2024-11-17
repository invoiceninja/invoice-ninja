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

        $this->credit->invitations->each(function ($invitation) {
            if (! $invitation->contact->trashed() && $invitation->contact->email) {
                EmailEntity::dispatch($invitation->withoutRelations(), $invitation->company->db, $this->reminder_template)->delay(2);
            }
        });

        if ($this->credit->invitations->count() >= 1) {
            event(new CreditWasEmailed($this->credit->invitations->first(), $this->credit->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null), 'credit'));
            $this->credit->sendEvent(Webhook::EVENT_SENT_CREDIT, "client");

        }

    }
}

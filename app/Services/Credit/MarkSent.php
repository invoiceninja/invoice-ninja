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

use App\Events\Credit\CreditWasMarkedSent;
use App\Models\Credit;
use App\Models\Webhook;
use App\Utils\Ninja;

class MarkSent
{
    private $client;

    private $credit;

    public function __construct($client, $credit)
    {
        $this->client = $client;
        $this->credit = $credit;
    }

    public function run($fire_event = false)
    {
        /* Return immediately if status is not draft */
        if ($this->credit->status_id != Credit::STATUS_DRAFT) {
            return $this->credit;
        }

        $this->credit->markInvitationsSent();

        $this->credit
             ->service()
             ->setStatus(Credit::STATUS_SENT)
             ->applyNumber()
             ->adjustBalance($this->credit->amount)
            //  ->deletePdf()
             ->save();

        $this->client
             ->service()
             ->adjustCreditBalance($this->credit->amount)
             ->save();

        event(new CreditWasMarkedSent($this->credit, $this->credit->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        if ($fire_event) {

            event('eloquent.updated: App\Models\Credit', $this->credit);
            $this->credit->sendEvent(Webhook::EVENT_SENT_CREDIT);

        }

        return $this->credit;

    }
}

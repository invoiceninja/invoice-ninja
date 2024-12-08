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

use App\Events\Quote\QuoteWasMarkedSent;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Webhook;
use App\Utils\Ninja;
use Carbon\Carbon;

class MarkSent
{
    public function __construct(private Client $client, private Quote $quote)
    {
    }

    public function run($first_event = false)
    {
        /* Return immediately if status is not draft */
        if ($this->quote->status_id != Quote::STATUS_DRAFT) {
            return $this->quote;
        }

        $this->quote->markInvitationsSent();

        if ($this->quote->due_date != '' || $this->client->getSetting('valid_until') == '') {
        } else {
            $this->quote->due_date = Carbon::parse($this->quote->date)->addDays((int)$this->client->getSetting('valid_until'));
        }

        $this->quote
             ->service()
             ->setStatus(Quote::STATUS_SENT)
             ->applyNumber()
             ->setReminder()
             ->save();

        event(new QuoteWasMarkedSent($this->quote, $this->quote->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        if ($first_event) {

            event('eloquent.updated: App\Models\Quote', $this->quote);
            $this->quote->sendEvent(Webhook::EVENT_SENT_QUOTE, "client");
        }

        return $this->quote;

    }
}

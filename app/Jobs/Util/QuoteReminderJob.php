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

namespace App\Jobs\Util;

use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Carbon;
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Jobs\Entity\EmailEntity;
use App\Utils\Traits\MakesDates;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\MakesReminders;
use Illuminate\Support\Facades\Auth;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Events\Quote\QuoteReminderWasEmailed;

class QuoteReminderJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesReminders;
    use MakesDates;

    public $tries = 1;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        set_time_limit(0);

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            nrlog("Sending quote reminders on ".now()->format('Y-m-d h:i:s'));

            Quote::query()
                 ->where('is_deleted', 0)
                 ->whereIn('status_id', [Invoice::STATUS_SENT])
                 ->whereNull('deleted_at')
                 ->where('next_send_date', '<=', now()->toDateTimeString())
                 ->whereHas('client', function ($query) {
                     $query->where('is_deleted', 0)
                           ->where('deleted_at', null);
                 })
                 ->whereHas('company', function ($query) {
                     $query->where('is_disabled', 0);
                 })
                 ->with('invitations')->chunk(50, function ($quotes) {
                     foreach ($quotes as $quote) {
                         $this->sendReminderForQuote($quote);
                     }

                     sleep(1);
                 });
        } else {
            //multiDB environment, need to

            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                nrlog("Sending quote reminders on db {$db} ".now()->format('Y-m-d h:i:s'));

                Quote::query()
                     ->where('is_deleted', 0)
                     ->whereIn('status_id', [Invoice::STATUS_SENT])
                     ->whereNull('deleted_at')
                     ->where('next_send_date', '<=', now()->toDateTimeString())
                     ->whereHas('client', function ($query) {
                         $query->where('is_deleted', 0)
                               ->where('deleted_at', null);
                     })
                     ->whereHas('company', function ($query) {
                         $query->where('is_disabled', 0);
                     })

                     ->with('invitations')->chunk(50, function ($quotes) {

                         foreach ($quotes as $quote) {
                             $this->sendReminderForQuote($quote);
                         }

                         sleep(1);
                     });
            }
        }
    }

    private function sendReminderForQuote(Quote $quote)
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($quote->client->getMergedSettings()));
        App::setLocale($quote->client->locale());

        if ($quote->canRemind()) {
            //Attempts to prevent duplicates from sending
            if ($quote->reminder_last_sent && Carbon::parse($quote->reminder_last_sent)->startOfDay()->eq(now()->startOfDay())) {
                nrlog("caught a duplicate reminder for quote {$quote->number}");
                return;
            }

            $reminder_template = $quote->calculateTemplate('quote');
            nrlog("#{$quote->number} => reminder template = {$reminder_template}");
            $quote->service()->touchReminder($reminder_template)->save();

            $enabled_reminder = 'enable_quote_'.$reminder_template;
            
            if (in_array($reminder_template, ['reminder1', 'reminder2', 'reminder3', 'reminder_endless', 'endless_reminder']) &&
        $quote->client->getSetting($enabled_reminder) &&
        $quote->client->getSetting('send_reminders') &&
        (Ninja::isSelfHost() || $quote->company->account->isPaidHostedClient())) {
                $quote->invitations->each(function ($invitation) use ($quote, $reminder_template) {
                    if ($invitation->contact && !$invitation->contact->trashed() && $invitation->contact->email) {
                        EmailEntity::dispatch($invitation->withoutRelations(), $invitation->company->db, $reminder_template);
                        nrlog("Firing reminder email for quote {$quote->number} - {$reminder_template}");
                        $quote->entityEmailEvent($invitation, $reminder_template);
                        $quote->sendEvent(Webhook::EVENT_REMIND_QUOTE, "client");
                    }
                });
            }
            $quote->service()->setReminder()->save();
        } else {
            $quote->next_send_date = null;
            $quote->save();
        }
    }

}

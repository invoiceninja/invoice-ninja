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

namespace App\Jobs\Ninja;

use App\DataMapper\InvoiceItem;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Jobs\Entity\EmailEntity;
use App\Jobs\Util\WebhookHandler;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesReminders;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;

//@DEPRECATED
class SendReminders implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesDates;
    use MakesReminders;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        nlog('Sending reminders '.Carbon::now()->format('Y-m-d h:i:s'));

        if (! config('ninja.db.multi_db_enabled')) {
            $this->sendReminderEmails();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->sendReminderEmails();
            }
        }
    }

    private function sendReminderEmails()
    {
        $invoices = Invoice::where('is_deleted', 0)
                           ->where('balance', '>', 0)
                           ->whereDate('next_send_date', '<=', now()->startOfDay())
                           ->whereNotNull('next_send_date')
                           ->with('client')
                           ->cursor();

        //we only need invoices that are payable
        $invoices->filter(function ($invoice) {
            return $invoice->isPayable();
        })->each(function ($invoice) {
            $reminder_template = $invoice->calculateTemplate('invoice');

            nlog("hitting a reminder for {$invoice->number} with template {$reminder_template}");

            if (in_array($reminder_template, ['reminder1', 'reminder2', 'reminder3', 'endless_reminder'])) {
                $this->sendReminder($invoice, $reminder_template);
                WebhookHandler::dispatch(Webhook::EVENT_REMIND_INVOICE, $invoice, $invoice->company);
            }
        });
    }

    private function checkSendSetting($invoice, $template)
    {
        switch ($template) {
            case 'reminder1':
                return $invoice->client->getSetting('enable_reminder1');
                break;
            case 'reminder2':
                return $invoice->client->getSetting('enable_reminder2');
                break;
            case 'reminder3':
                return $invoice->client->getSetting('enable_reminder3');
                break;
            case 'endless_reminder':
                return $invoice->client->getSetting('enable_reminder_endless');
                break;
            default:
                return false;
                break;
        }
    }

    /**
     * Create a collection of all possible reminder dates
     * and pass back the first one in chronology
     *
     * @param  Invoice $invoice
     * @return Carbon $date
     */
    private function calculateNextSendDate($invoice)
    {
        $dates = collect();

        $settings = $invoice->client->getMergedSettings();

        $set_reminder1 = false;
        $set_reminder2 = false;
        $set_reminder3 = false;

        if ((int) $settings->schedule_reminder1 > 0) {
            $next_reminder_date = $this->calculateScheduledDate($invoice, $settings->schedule_reminder1, (int) $settings->num_days_reminder1);

            if ($next_reminder_date && $next_reminder_date->gt(Carbon::parse($invoice->last_sent_date)));
            $dates->push($next_reminder_date);

            if (! $invoice->reminder1_sent) {
                $set_reminder1 = true;
            }
        }

        if ((int) $settings->num_days_reminder2 > 0) {
            $next_reminder_date = $this->calculateScheduledDate($invoice, $settings->schedule_reminder2, (int) $settings->num_days_reminder2);

            if ($next_reminder_date && $next_reminder_date->gt(Carbon::parse($invoice->last_sent_date)));
            $dates->push($next_reminder_date);

            if (! $invoice->reminder2_sent) {
                $set_reminder2 = true;
            }
        }

        if ((int) $settings->num_days_reminder3 > 0) {
            $next_reminder_date = $this->calculateScheduledDate($invoice, $settings->schedule_reminder3, (int) $settings->num_days_reminder3);

            if ($next_reminder_date && $next_reminder_date->gt(Carbon::parse($invoice->last_sent_date)));
            $dates->push($next_reminder_date);

            if (! $invoice->reminder3_sent) {
                $set_reminder3 = true;
            }
        }

        //If all the available reminders have fired, we then start to fire the endless reminders
        if ((int) $settings->endless_reminder_frequency_id > 0 && ! $set_reminder1 && ! $set_reminder2 && ! $set_reminder3) {
            $dates->push($this->addTimeInterval($invoice->last_sent_date, (int) $settings->endless_reminder_frequency_id));
        }

        //order the dates ascending and get first one
        return $dates->sort()->first();
    }

    /**
     * Helper method which switches values based on the $schedule_reminder
     * @param  Invoice $invoice
     * @param  string $schedule_reminder
     * @param  int $num_days_reminder
     * @return Carbon  $date
     */
    private function calculateScheduledDate($invoice, $schedule_reminder, $num_days_reminder): ?Carbon
    {
        $offset = $invoice->client->timezone_offset();

        switch ($schedule_reminder) {
            case 'after_invoice_date':
                return Carbon::parse($invoice->date)->addDays((int)$num_days_reminder)->startOfDay()->addSeconds($offset);
                break;
            case 'before_due_date':
                return Carbon::parse($invoice->due_date)->subDays((int)$num_days_reminder)->startOfDay()->addSeconds($offset);
                break;
            case 'after_due_date':
                return Carbon::parse($invoice->due_date)->addDays((int)$num_days_reminder)->startOfDay()->addSeconds($offset);
                break;
            default:
                return null;
                break;
        }
    }

    /**
     * Sends the reminder and/or late fee for the invoice.
     *
     * @param  Invoice $invoice
     * @param  string $template
     * @return void
     */
    private function sendReminder($invoice, $template): void
    {
        $invoice = $this->calcLateFee($invoice, $template);

        $invoice->invitations->each(function ($invitation) use ($template, $invoice) {
            //only send if enable_reminder setting is toggled to yes
            if ($this->checkSendSetting($invoice, $template) && $invoice->company->account->hasFeature(Account::FEATURE_EMAIL_TEMPLATES_REMINDERS)) {
                nlog('firing email');

                EmailEntity::dispatch($invitation->withoutRelations(), $invitation->company->db, $template)->delay(10);
                event(new InvoiceWasEmailed($invoice->invitations->first(), $invoice->company, Ninja::eventVars(), $template));
                $invoice->sendEvent(Webhook::EVENT_REMIND_INVOICE, "client");
            }
        });

        $invoice->last_sent_date = now();
        $invoice->next_send_date = $this->calculateNextSendDate($invoice);

        if (in_array($template, ['reminder1', 'reminder2', 'reminder3'])) {
            $invoice->{$template.'_sent'} = now();
        }
        $invoice->service()->touchReminder($template)->save();

    }

    /**
     * Calculates the late if - if any - and rebuilds the invoice
     *
     * @param  Invoice $invoice
     * @param  string $template
     * @return Invoice
     */
    private function calcLateFee($invoice, $template): Invoice
    {
        $late_fee_amount = 0;
        $late_fee_percent = 0;

        switch ($template) {
            case 'reminder1':
                $late_fee_amount = $invoice->client->getSetting('late_fee_amount1');
                $late_fee_percent = $invoice->client->getSetting('late_fee_percent1');
                break;
            case 'reminder2':
                $late_fee_amount = $invoice->client->getSetting('late_fee_amount2');
                $late_fee_percent = $invoice->client->getSetting('late_fee_percent2');
                break;
            case 'reminder3':
                $late_fee_amount = $invoice->client->getSetting('late_fee_amount3');
                $late_fee_percent = $invoice->client->getSetting('late_fee_percent3');
                break;
            case 'endless_reminder':
                $late_fee_amount = $invoice->client->getSetting('late_fee_endless_amount');
                $late_fee_percent = $invoice->client->getSetting('late_fee_endless_percent');
                break;
            default:
                $late_fee_amount = 0;
                $late_fee_percent = 0;
                break;
        }

        return $this->setLateFee($invoice, $late_fee_amount, $late_fee_percent);
    }

    /**
     * Applies the late fee to the invoice line items
     *
     * @param Invoice $invoice
     * @param float $amount  The fee amount
     * @param float $percent The fee percentage amount
     *
     * @return Invoice
     */
    private function setLateFee($invoice, $amount, $percent): Invoice
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($invoice->client->getMergedSettings()));
        App::setLocale($invoice->client->locale());

        $temp_invoice_balance = $invoice->balance;

        if ($amount <= 0 && $percent <= 0) {
            return $invoice;
        }

        $fee = $amount;

        if ($invoice->partial > 0) {
            $fee += round($invoice->partial * $percent / 100, 2);
        } else {
            $fee += round($invoice->balance * $percent / 100, 2);
        }

        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = '5';
        $invoice_item->product_key = trans('texts.fee');
        $invoice_item->notes = ctrans('texts.late_fee_added', ['date' => $this->translateDate(now()->startOfDay(), $invoice->client->date_format(), $invoice->client->locale())]);
        $invoice_item->quantity = 1;
        $invoice_item->cost = $fee;

        $invoice_items = $invoice->line_items;
        $invoice_items[] = $invoice_item;

        $invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $invoice = $invoice->calc()->getInvoice();

        $invoice->client->service()->calculateBalance();

        // $invoice->client->service()->updateBalance($invoice->balance - $temp_invoice_balance)->save();
        $invoice->ledger()->updateInvoiceBalance($invoice->balance - $temp_invoice_balance, "Late Fee Adjustment for invoice {$invoice->number}");

        return $invoice;
    }
}

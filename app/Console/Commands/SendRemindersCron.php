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

namespace App\Console\Commands;

use App\DataMapper\InvoiceItem;
use App\Events\Invoice\InvoiceWasEmailed;
use App\Jobs\Entity\EmailEntity;
use App\Jobs\Util\WebhookHandler;
use App\Libraries\MultiDB;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Webhook;
use App\Utils\Ninja;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesReminders;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\App;

//@deprecated 27-11-2022 - only ever should be used for testing
class SendRemindersCron extends Command
{
    use MakesReminders;
    use MakesDates;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:send-reminders';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Force send all reminders';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     */
    public function handle()
    {
        Invoice::where('next_send_date', '<=', now()->toDateTimeString())
                ->whereNull('invoices.deleted_at')
                ->where('invoices.is_deleted', 0)
                ->whereIn('invoices.status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                ->where('invoices.balance', '>', 0)
                 ->whereHas('client', function ($query) {
                     $query->where('is_deleted', 0)
                           ->where('deleted_at', null);
                 })
                 ->whereHas('company', function ($query) {
                     $query->where('is_disabled', 0);
                 })

                 ->with('invitations')->cursor()->each(function ($invoice) {
                     if ($invoice->isPayable()) {
                         $reminder_template = $invoice->calculateTemplate('invoice');
                         $invoice->service()->touchReminder($reminder_template)->save();
                         $invoice = $this->calcLateFee($invoice, $reminder_template);

                         //check if this reminder needs to be emailed
                         if (in_array($reminder_template, ['reminder1', 'reminder2', 'reminder3']) && $invoice->client->getSetting('enable_'.$reminder_template)) {
                             $invoice->invitations->each(function ($invitation) use ($invoice, $reminder_template) {
                                 EmailEntity::dispatch($invitation->withoutRelations(), $invitation->company->db, $reminder_template);
                                 nlog("Firing reminder email for invoice {$invoice->number}");
                             });

                             if ($invoice->invitations->count() > 0) {
                                //  event(new InvoiceWasEmailed($invoice->invitations->first(), $invoice->company, Ninja::eventVars(), $reminder_template));
                                 $invoice->entityEmailEvent($invoice->invitations->first(), $reminder_template);
                             }
                         }
                         $invoice->service()->setReminder()->save();
                     } else {
                         $invoice->next_send_date = null;
                         $invoice->save();
                     }
                 });
    }

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
        $invoice_item->product_key = ctrans('texts.fee');
        $invoice_item->notes = ctrans('texts.late_fee_added', ['date' => $this->translateDate(now()->startOfDay(), $invoice->client->date_format(), $invoice->client->locale())]);
        $invoice_item->quantity = 1;
        $invoice_item->cost = $fee;

        $invoice_items = $invoice->line_items;
        $invoice_items[] = $invoice_item;

        $invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $invoice->calc()->getInvoice()->save();
        $invoice = $invoice->fresh();

        /* Refresh the client here to ensure the balance is fresh */
        $client = $invoice->client;
        $client = $client->fresh();

        $client->service()->calculateBalance();
        $invoice->ledger()->updateInvoiceBalance($invoice->balance - $temp_invoice_balance, "Late Fee Adjustment for invoice {$invoice->number}");

        return $invoice;
    }

    private function webHookOverdueInvoices()
    {
        if (! config('ninja.db.multi_db_enabled')) {
            $this->executeWebhooks();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->executeWebhooks();
            }
        }
    }

    private function webHookExpiredQuotes()
    {
    }

    private function executeWebhooks()
    {
        $invoices = Invoice::where('is_deleted', 0)
                          ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                          ->where('balance', '>', 0)
                          ->whereDate('due_date', '<=', now()->subDays(1)->startOfDay())
                          ->cursor();

        $invoices->each(function ($invoice) {
            WebhookHandler::dispatch(Webhook::EVENT_LATE_INVOICE, $invoice, $invoice->company);
        });

        $quotes = Quote::where('is_deleted', 0)
                          ->where('status_id', Quote::STATUS_SENT)
                          ->whereDate('due_date', '<=', now()->subDays(1)->startOfDay())
                          ->cursor();

        $quotes->each(function ($quote) {
            WebhookHandler::dispatch(Webhook::EVENT_EXPIRED_QUOTE, $quote, $quote->company);
        });
    }
}

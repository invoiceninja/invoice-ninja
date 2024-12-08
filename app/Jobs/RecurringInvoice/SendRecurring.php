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

namespace App\Jobs\RecurringInvoice;

use Carbon\Carbon;
use App\Utils\Ninja;
use App\Models\Invoice;
use App\Models\Webhook;
use App\Jobs\Cron\AutoBill;
use Illuminate\Bus\Queueable;
use App\Utils\Traits\MakesHash;
use App\Jobs\Entity\EmailEntity;
use App\Models\RecurringInvoice;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Facades\LightLogs;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\Invoice\InvoiceWasCreated;
use App\Factory\InvoiceInvitationFactory;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Factory\RecurringInvoiceToInvoiceFactory;
use App\DataMapper\Analytics\SendRecurringFailure;

class SendRecurring implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use GeneratesCounter;
    use MakesHash;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param RecurringInvoice $recurring_invoice
     * @param string $db
     */
    public function __construct(public RecurringInvoice $recurring_invoice, public string $db = 'db-ninja-01')
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        // Generate Standard Invoice
        $invoice = RecurringInvoiceToInvoiceFactory::create($this->recurring_invoice, $this->recurring_invoice->client);

        $date = date('Y-m-d'); //@todo this will always pull UTC date.
        $invoice->date = $date;

        nlog("Recurring Invoice Date Set on Invoice = {$invoice->date} - ". now()->format('Y-m-d'));

        $invoice->due_date = $this->recurring_invoice->calculateDueDate($date);
        $invoice->recurring_id = $this->recurring_invoice->id;
        $invoice->saveQuietly();

        if ($invoice->client->getSetting('auto_email_invoice')) {
            $invoice = $invoice->service()
                               ->markSent()
                               ->applyNumber()
                               ->fillDefaults(true)
                               ->adjustInventory()
                               ->save();
        } else {
            $invoice = $invoice->service()
                               ->fillDefaults(true)
                               ->save();
        }

        if ($this->recurring_invoice->auto_bill == 'always') {
            $invoice->auto_bill_enabled = true;
            $invoice->saveQuietly();
        } elseif ($this->recurring_invoice->auto_bill == 'optout' || $this->recurring_invoice->auto_bill == 'optin') {
        } elseif ($this->recurring_invoice->auto_bill == 'off') {
            $invoice->auto_bill_enabled = false;
            $invoice->saveQuietly();
        }

        $invoice = $this->createRecurringInvitations($invoice);

        /* Set next date here to prevent a recurring loop forming */
        $this->recurring_invoice->next_send_date = $this->recurring_invoice->nextSendDate();
        $this->recurring_invoice->next_send_date_client = $this->recurring_invoice->nextSendDateClient();
        $this->recurring_invoice->remaining_cycles = $this->recurring_invoice->remainingCycles();
        $this->recurring_invoice->last_sent_date = now();

        /* Set completed if we don't have any more cycles remaining*/
        if ($this->recurring_invoice->remaining_cycles == 0) {
            $this->recurring_invoice->setCompleted();
        }

        $this->recurring_invoice->save();

        event('eloquent.created: App\Models\Invoice', $invoice);
        event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars()));

        //auto bill, BUT NOT DRAFTS!!
        if ($invoice->auto_bill_enabled && $invoice->client->getSetting('auto_bill_date') == 'on_send_date' && $invoice->client->getSetting('auto_email_invoice')) {
            nlog("attempting to autobill {$invoice->number}");
            AutoBill::dispatch($invoice->id, $this->db, true)->delay(rand(1, 2));

            //04-08-2023 edge case to support where online payment notifications are not enabled
            if (!$invoice->client->getSetting('client_online_payment_notification')) {
                $this->sendRecurringEmails($invoice);
                $invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");
            }
        } elseif ($invoice->auto_bill_enabled && $invoice->client->getSetting('auto_bill_date') == 'on_due_date' && $invoice->client->getSetting('auto_email_invoice') && ($invoice->due_date && Carbon::parse($invoice->due_date)->startOfDay()->lte(now()->startOfDay()))) {
            nlog("attempting to autobill {$invoice->number}");
            AutoBill::dispatch($invoice->id, $this->db, true)->delay(rand(1, 2));

            //04-08-2023 edge case to support where online payment notifications are not enabled
            if (!$invoice->client->getSetting('client_online_payment_notification')) {
                $this->sendRecurringEmails($invoice);
                $invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");
            }

        } elseif ($invoice->client->getSetting('auto_email_invoice')) {
            $this->sendRecurringEmails($invoice);
            $invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");
        }

    }

    /**
     * Sends the recurring invoice emails to
     * the designated contacts
     *
     * @param Invoice $invoice
     * @return void
     */
    private function sendRecurringEmails(Invoice $invoice): void
    {
        //Admin notification for recurring invoice sent.
        if ($invoice->invitations->count() >= 1) {
            $invoice->entityEmailEvent($invoice->invitations->first(), 'invoice', 'email_template_invoice');
        }

        $invoice->invitations->each(function ($invitation) use ($invoice) {
            if ($invitation->contact && ! $invitation->contact->trashed() && strlen($invitation->contact->email) >= 1 && $invoice->client->getSetting('auto_email_invoice')) {
                try {
                    EmailEntity::dispatch($invitation->withoutRelations(), $invoice->company->db, 'invoice')->delay(rand(1, 2));
                } catch (\Exception $e) {
                    nlog($e->getMessage());
                }

                nlog("Firing email for invoice {$invoice->number}");
            }
        });

    }

    /**
     * Only create the invitations that are defined on the recurring invoice.
     * @param  Invoice $invoice
     * @return Invoice $invoice
     */
    private function createRecurringInvitations($invoice): Invoice
    {
        if ($this->recurring_invoice->invitations->count() == 0) {
            $this->recurring_invoice = $this->recurring_invoice->service()->createInvitations()->save();
            // $this->recurring_invoice = $this->recurring_invoice->fresh();
        }

        $this->recurring_invoice->invitations->each(function ($recurring_invitation) use ($invoice) {
            $ii = InvoiceInvitationFactory::create($invoice->company_id, $invoice->user_id);
            $ii->key = $this->createDbHash($invoice->company->db);
            $ii->invoice_id = $invoice->id;
            $ii->client_contact_id = $recurring_invitation->client_contact_id;
            $ii->save();
        });

        return $invoice->fresh();
    }

    public function failed($exception = null)
    {
        nlog('the job failed');

        $job_failure = new SendRecurringFailure();
        $job_failure->string_metric5 = get_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->send();

        nlog($exception->getMessage());
    }
}

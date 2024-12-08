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

use App\Events\Invoice\InvoiceWasArchived;
use App\Jobs\EDocument\CreateEDocument;
use App\Jobs\Entity\CreateRawPdf;
use App\Jobs\Inventory\AdjustProductInventory;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\CompanyGateway;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Task;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

class InvoiceService
{
    use MakesHash;

    public function __construct(public Invoice $invoice)
    {
    }

    /**
     * Marks as invoice as paid
     * and executes child sub functions.
     * @return $this InvoiceService object
     */
    public function markPaid(?string $reference = null)
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new MarkPaid($this->invoice, $reference))->run();

        return $this;
    }

    /**
     * applyPaymentAmount
     *
     * @param  float $amount
     * @param  ?string $reference
     * @return self
     */
    public function applyPaymentAmount($amount, ?string $reference = null): self
    {
        $this->invoice = (new ApplyPaymentAmount($this->invoice, $amount, $reference))->run();

        return $this;
    }

    /**
     * Applies the invoice number.
     * @return $this InvoiceService object
     */
    public function applyNumber()
    {
        $this->invoice = (new ApplyNumber($this->invoice->client, $this->invoice))->run();

        return $this;
    }

    /**
     * Sets the exchange rate on the invoice if the client currency
     * is different to the company currency.
     */
    public function setExchangeRate($force = false)
    {
        if ($this->invoice->exchange_rate != 1 || $force) {
            return $this;
        }

        $client_currency = $this->invoice->client->getSetting('currency_id');
        $company_currency = $this->invoice->company->settings->currency_id;

        if ($company_currency != $client_currency) {
            $exchange_rate = new CurrencyApi();

            $this->invoice->exchange_rate = 1 / $exchange_rate->exchangeRate($client_currency, $company_currency, now());
        }

        return $this;
    }

    /**
     * Applies the recurring invoice number.
     * @return $this InvoiceService object
     */
    public function applyRecurringNumber()
    {
        $this->invoice = (new ApplyRecurringNumber($this->invoice->client, $this->invoice))->run();

        return $this;
    }

    /**
     * Apply a payment amount to an invoice.
     *
     * *** does not create a paymentable ****
     * @param  Payment $payment        The Payment
     * @param  float   $payment_amount The Payment amount
     * @return InvoiceService          Parent class object
     */
    public function applyPayment(Payment $payment, float $payment_amount)
    {
        $this->invoice = $this->markSent()->save();

        $this->invoice = (new ApplyPayment($this->invoice, $payment, $payment_amount))->run();

        return $this;
    }

    public function addGatewayFee(CompanyGateway $company_gateway, $gateway_type_id, float $amount)
    {
        $this->invoice = (new AddGatewayFee($company_gateway, $gateway_type_id, $this->invoice, $amount))->run();

        return $this;
    }

    /**
     * Update an invoice balance.
     *
     * @param  float $balance_adjustment The amount to adjust the invoice by
     * a negative amount will REDUCE the invoice balance, a positive amount will INCREASE
     * the invoice balance
     *
     * @return InvoiceService                     Parent class object
     */
    public function updateBalance($balance_adjustment, bool $is_draft = false)
    {
        if ((bool) $this->invoice->is_deleted !== false) {
            nlog($this->invoice->number.' is deleted returning');

            return $this;
        }

        $this->invoice->balance += $balance_adjustment;

        if (round($this->invoice->balance, 2) == 0 && ! $is_draft) {
            $this->invoice->status_id = Invoice::STATUS_PAID;
        }

        if ((int) $this->invoice->balance == 0) {
            $this->invoice->next_send_date = null;
        }

        return $this;
    }

    public function updatePaidToDate($adjustment)
    {
        $this->invoice->paid_to_date += $adjustment;

        return $this;
    }

    public function createInvitations()
    {
        $this->invoice = (new CreateInvitations($this->invoice))->run();

        return $this;
    }

    public function markSent($fire_event = false)
    {

        $this->invoice->loadMissing(['client' => function ($q) {
            $q->without('documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }]);

        $this->invoice = (new MarkSent($this->invoice->client, $this->invoice))->run($fire_event);

        $this->setExchangeRate();

        return $this;
    }

    public function getInvoicePdf($contact = null)
    {
        return (new GetInvoicePdf($this->invoice, $contact))->run();
    }

    public function getRawInvoicePdf($contact = null)
    {
        $invitation = $contact ? $this->invoice->invitations()->where('contact_id', $contact->id)->first() : $this->invoice->invitations()->first();

        return (new CreateRawPdf($invitation))->handle();
    }

    public function getInvoiceDeliveryNote(Invoice $invoice, \App\Models\ClientContact $contact = null)
    {
        return (new GenerateDeliveryNote($invoice, $contact))->run();
    }

    public function getEInvoice($contact = null)
    {
        return (new CreateEDocument($this->invoice))->handle();
    }

    public function getEDocument($contact = null)
    {
        return $this->getEInvoice($contact);
    }

    public function sendEmail($contact = null)
    {
        $send_email = new SendEmail($this->invoice, null, $contact);

        return $send_email->run();
    }

    public function handleReversal()
    {
        $this->invoice = (new HandleReversal($this->invoice))->run();

        return $this;
    }

    public function handleCancellation()
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new HandleCancellation($this->invoice))->run();

        return $this;
    }

    public function markDeleted()
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new MarkInvoiceDeleted($this->invoice))->run();

        return $this;
    }

    public function handleRestore()
    {
        $this->invoice = (new HandleRestore($this->invoice))->run();

        return $this;
    }

    public function reverseCancellation()
    {
        $this->removeUnpaidGatewayFees();

        $this->invoice = (new HandleCancellation($this->invoice))->reverse();

        return $this;
    }

    public function triggeredActions($request)
    {
        $this->invoice = (new TriggeredActions($this->invoice->load('invitations'), $request))->run();

        return $this;
    }

    public function autoBill()
    {
        (new AutoBillInvoice($this->invoice, $this->invoice->company->db))->run();

        return $this;
    }

    public function markViewed()
    {
        $this->invoice->last_viewed = Carbon::now()->format('Y-m-d H:i');

        return $this;
    }

    /* One liners */
    public function setDueDate()
    {
        if ($this->invoice->due_date != '' || $this->invoice->client->getSetting('payment_terms') == '') {
            return $this;
        }

        //12-10-2022
        if ($this->invoice->partial > 0 && !$this->invoice->partial_due_date) {
            $this->invoice->partial_due_date = Carbon::parse($this->invoice->date)->addDays((int)$this->invoice->client->getSetting('payment_terms'));
        } else {
            $this->invoice->due_date = Carbon::parse($this->invoice->date)->addDays((int)$this->invoice->client->getSetting('payment_terms'));
        }

        return $this;
    }

    /**
     * Reset the reminders if only the
     * partial has been paid.
     *
     * We can _ONLY_ call this _IF_ a partial
     * amount has been paid, otherwise we end up wiping
     * all reminders regardless
     *
     * @return self
     */
    public function checkReminderStatus(): self
    {

        if ($this->invoice->partial == 0) {
            $this->invoice->partial_due_date = null;
        }

        if ($this->invoice->partial == 0 && $this->invoice->balance > 0) {
            $this->invoice->reminder1_sent = null;
            $this->invoice->reminder2_sent = null;
            $this->invoice->reminder3_sent = null;

            $this->setReminder();
        }

        return $this;
    }

    public function setReminder($settings = null)
    {
        $this->invoice = (new UpdateReminder($this->invoice, $settings))->run();

        return $this;
    }

    public function setStatus($status)
    {
        $this->invoice->status_id = $status;

        return $this;
    }

    public function setCalculatedStatus()
    {
        if (round($this->invoice->balance, 2) == 0) {
            $this->setStatus(Invoice::STATUS_PAID);
        } elseif ($this->invoice->balance > 0 && $this->invoice->balance < $this->invoice->amount) {
            $this->setStatus(Invoice::STATUS_PARTIAL);
        } elseif ($this->invoice->balance < 0 || $this->invoice->balance > 0) {
            $this->invoice->status_id = Invoice::STATUS_SENT;
        }

        return $this;
    }

    public function updateStatus()
    {
        if ($this->invoice->status_id == Invoice::STATUS_DRAFT) {
            return $this;
        }

        if (round($this->invoice->balance, 2) == 0) {
            $this->invoice->status_id = Invoice::STATUS_PAID;
        } elseif ($this->invoice->balance > 0 && $this->invoice->balance < $this->invoice->amount) {
            $this->invoice->status_id = Invoice::STATUS_PARTIAL;
        } elseif ($this->invoice->balance < 0 || $this->invoice->balance > 0) {
            $this->invoice->status_id = Invoice::STATUS_SENT;
        }

        return $this;
    }

    public function toggleFeesPaid()
    {
        $this->invoice->line_items = collect($this->invoice->line_items)
                                     ->map(function ($item) {
                                         if ($item->type_id == '3') {
                                             $item->type_id = '4';
                                         }

                                         return $item;
                                     })->toArray();

        $this->deleteEInvoice();

        return $this;
    }

    public function deletePdf()
    {
        $this->invoice->load('invitations');

        //30-06-2023
        $this->invoice->invitations->each(function ($invitation) {
            try {
                // if (Storage::disk(config('filesystems.default'))->exists($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf')) {
                Storage::disk(config('filesystems.default'))->delete($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf');
                // }

                // if (Ninja::isHosted() && Storage::disk('public')->exists($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf')) {
                if (Ninja::isHosted()) {
                    Storage::disk('public')->delete($this->invoice->client->invoice_filepath($invitation).$this->invoice->numberFormatter().'.pdf');
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        });

        return $this;
    }

    public function deleteEInvoice()
    {
        $this->invoice->load('invitations');

        $this->invoice->invitations->each(function ($invitation) {
            try {
                // if (Storage::disk(config('filesystems.default'))->exists($this->invoice->client->e_invoice_filepath($invitation).$this->invoice->getFileName("xml"))) {
                Storage::disk(config('filesystems.default'))->delete($this->invoice->client->e_document_filepath($invitation).$this->invoice->getFileName("xml"));
                // }

                // if (Ninja::isHosted() && Storage::disk('public')->exists($this->invoice->client->e_invoice_filepath($invitation).$this->invoice->getFileName("xml"))) {
                if (Ninja::isHosted()) {
                    Storage::disk('public')->delete($this->invoice->client->e_document_filepath($invitation).$this->invoice->getFileName("xml"));
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        });

        return $this;
    }

    public function removeUnpaidGatewayFees()
    {
        $balance = $this->invoice->balance;

        //return early if type three does not exist.
        if ($this->invoice->status_id == Invoice::STATUS_PAID || ! collect($this->invoice->line_items)->contains('type_id', 3)) {
            return $this;
        }

        $pre_count = count((array)$this->invoice->line_items);

        $items = collect((array)$this->invoice->line_items)
                    ->filter(function ($item) {
                        return $item->type_id != '3';
                    })->toArray();

        $this->invoice->line_items = array_values($items);

        $this->invoice = $this->invoice->calc()->getInvoice();

        /* 24-03-2022 */
        $new_balance = $this->invoice->balance;

        $post_count = count($this->invoice->line_items);
        nlog("pre count = {$pre_count} post count = {$post_count}");

        if ((int) $pre_count != (int) $post_count) {
            $adjustment = $balance - $new_balance;

            $this->invoice
            ->ledger()
            ->updateInvoiceBalance($adjustment * -1, 'Adjustment for removing gateway fee');

            $this->invoice->client->service()->calculateBalance();

        }

        return $this;
    }

    /*Set partial value and due date to null*/
    public function clearPartial()
    {
        $this->invoice->partial = null;
        $this->invoice->partial_due_date = null;

        return $this;
    }

    /*Update the partial amount of a invoice*/
    public function updatePartial($amount)
    {
        $this->invoice->partial += $amount;

        return $this;
    }

    /*When a reminder is sent we want to touch the dates they were sent*/
    public function touchReminder(string $reminder_template)
    {
        nrlog(now()->format('Y-m-d h:i:s') . " INV #{$this->invoice->number} : Touching Reminder => {$reminder_template}");
        switch ($reminder_template) {
            case 'reminder1':
                $this->invoice->reminder1_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            case 'reminder2':
                $this->invoice->reminder2_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            case 'reminder3':
                $this->invoice->reminder3_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            case 'endless_reminder':
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
            default:
                $this->invoice->reminder1_sent = now();
                $this->invoice->reminder_last_sent = now();
                $this->invoice->last_sent_date = now();
                break;
        }

        return $this;
    }

    public function linkEntities()
    {
        //set all task.invoice_ids = 0
        $this->invoice->tasks()->update(['invoice_id' => null]);

        //set all tasks.invoice_ids = x with the current  line_items
        $tasks = collect($this->invoice->line_items)->map(function ($item) {
            if (isset($item->task_id)) {
                $item->task_id = $this->decodePrimaryKey($item->task_id);
            }

            if (isset($item->expense_id)) {
                $item->expense_id = $this->decodePrimaryKey($item->expense_id);
            }

            return $item;
        });

        Task::query()->whereIn('id', $tasks->pluck('task_id'))->update(['invoice_id' => $this->invoice->id]);
        Expense::query()->whereIn('id', $tasks->pluck('expense_id'))->update(['invoice_id' => $this->invoice->id]);

        return $this;
    }

    public function fillDefaults(bool $is_recurring = false)
    {
        $this->invoice->load('client.company');

        $settings = $this->invoice->client->getMergedSettings();

        if (! $this->invoice->design_id) {
            $this->invoice->design_id = intval($this->decodePrimaryKey($settings->invoice_design_id));
        }

        if (! isset($this->invoice->footer) || empty($this->invoice->footer)) {
            $this->invoice->footer = $settings->invoice_footer;
        }

        if (! isset($this->invoice->terms) || empty($this->invoice->terms)) {
            $this->invoice->terms = $settings->invoice_terms;
        }

        if (! isset($this->invoice->public_notes) || empty($this->invoice->public_notes)) {
            $this->invoice->public_notes = $this->invoice->client->public_notes;
        }

        /* If client currency differs from the company default currency, then insert the client exchange rate on the model.*/
        if (! isset($this->invoice->exchange_rate) && $this->invoice->client->currency()->id != (int) $this->invoice->company->settings->currency_id) {
            $this->invoice->exchange_rate = $this->invoice->client->setExchangeRate();
        }

        if (!$is_recurring && $this->invoice->client->getSetting('auto_bill_standard_invoices')) {
            $this->invoice->auto_bill_enabled = true;
        }

        if ($settings->counter_number_applied == 'when_saved') {
            $this->invoice->service()->applyNumber()->save();
        }

        return $this;
    }

    public function workFlow()
    {
        if ($this->invoice->status_id == Invoice::STATUS_PAID && $this->invoice->client->getSetting('auto_archive_invoice')) {
            /* Throws: Payment amount xxx does not match invoice totals. */

            if ($this->invoice->trashed()) {
                return $this;
            }

            $this->invoice->delete();

            event(new InvoiceWasArchived($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }

        if ($this->invoice->status_id == Invoice::STATUS_CANCELLED && $this->invoice->client->getSetting('auto_archive_invoice_cancelled')) {
            /* Throws: Payment amount xxx does not match invoice totals. */

            if ($this->invoice->trashed()) {
                return $this;
            }

            $this->invoice->delete();

            event(new InvoiceWasArchived($this->invoice, $this->invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        }

        return $this;
    }

    public function adjustInventory($old_invoice = [])
    {
        if ($this->invoice->company->track_inventory) {
            (new AdjustProductInventory($this->invoice->company, $this->invoice, $old_invoice))->handle();
        }

        return $this;
    }

    public function setPaymentLink(string $subscription_id): self
    {

        $sub_id = $this->decodePrimaryKey($subscription_id);

        if (Subscription::withTrashed()->where('id', $sub_id)->where('company_id', $this->invoice->company_id)->exists()) {
            $this->invoice->subscription_id = $sub_id;
        }

        return $this;

    }

    /**
     * Saves the invoice.
     * @return Invoice object
     */
    public function save(): ?Invoice
    {
        $this->invoice->saveQuietly();

        return $this->invoice->fresh();
    }
}

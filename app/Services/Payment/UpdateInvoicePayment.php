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

namespace App\Services\Payment;

use App\Events\Invoice\InvoiceWasUpdated;
use App\Factory\RecurringInvoiceFactory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\RecurringInvoice;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class UpdateInvoicePayment
{
    use MakesHash;

    public $payment;

    public $payment_hash;

    public function __construct(Payment $payment, PaymentHash $payment_hash)
    {
        $this->payment = $payment;
        $this->payment_hash = $payment_hash;
    }

    public function run()
    {
        $paid_invoices = $this->payment_hash->invoices();

        $invoices = Invoice::query()->whereIn('id', $this->transformKeys(array_column($paid_invoices, 'invoice_id')))->withTrashed()->get();

        $client = $this->payment->client;

        if ($client->trashed()) {
            $client->restore();
        }

        collect($paid_invoices)->each(function ($paid_invoice) use ($invoices, $client) {
            $invoice = $invoices->first(function ($inv) use ($paid_invoice) {
                return $paid_invoice->invoice_id == $inv->hashed_id;
            });

            if ($invoice->trashed()) {
                $invoice->restore();
            }

            // $has_partial = $invoice->hasPartial();

            if ($invoice->id == $this->payment_hash->fee_invoice_id) {
                $paid_amount = $paid_invoice->amount + $this->payment_hash->fee_total;
            } else {
                $paid_amount = $paid_invoice->amount;
            }

            $client->service()->updatePaidToDate($paid_amount); //always use the payment->amount

            $has_partial = $invoice->hasPartial();

            /* Need to determine here is we have an OVER payment - if YES only apply the max invoice amount */
            if ($paid_amount > $invoice->partial && $paid_amount > $invoice->balance) {
                $paid_amount = $invoice->balance;
            }

            $client->service()->updateBalance($paid_amount * -1); //only ever use the amount applied to the invoice

            /*Improve performance here - 26-01-2022 - also change the order of events for invoice first*/
            //caution what if we amount paid was less than partial - we wipe it!
            $invoice->balance -= $paid_amount;
            $invoice->paid_to_date += $paid_amount;
            $invoice->saveQuietly();

            $invoice = $invoice->service()
                               ->clearPartial()
                               ->updateStatus()
                               ->workFlow()
                               ->save();

            if ($has_partial) {
                $invoice->service()->checkReminderStatus()->save();
            }

            if ($invoice->is_proforma) {
                //keep proforma's hidden
                if (property_exists($this->payment_hash->data, 'pre_payment') && $this->payment_hash->data->pre_payment == "1") {
                    $invoice->payments()->each(function ($p) {
                        $p->pivot->forceDelete();
                        $p->invoices()->each(function ($i) {
                            $i->pivot->forceDelete();
                        });
                    });


                    $invoice
                    ->ledger()
                    ->updateInvoiceBalance($paid_amount * -1, "Prepayment Balance Adjustment");

                    $invoice->is_deleted = true;
                    $invoice->deleted_at = now();
                    $invoice->saveQuietly();

                    if (property_exists($this->payment_hash->data, 'is_recurring') && $this->payment_hash->data->is_recurring == "1") {
                        $recurring_invoice = RecurringInvoiceFactory::create($invoice->company_id, $invoice->user_id);
                        $recurring_invoice->client_id = $invoice->client_id;
                        $recurring_invoice->line_items = $invoice->line_items;
                        $recurring_invoice->frequency_id = $this->payment_hash->data->frequency_id ?: RecurringInvoice::FREQUENCY_MONTHLY;
                        $recurring_invoice->date = now();
                        $recurring_invoice->remaining_cycles = $this->payment_hash->data->remaining_cycles;
                        $recurring_invoice->auto_bill = 'always';
                        $recurring_invoice->auto_bill_enabled =  true;
                        $recurring_invoice->due_date_days = 'on_receipt';
                        $recurring_invoice->next_send_date = now()->format('Y-m-d');
                        $recurring_invoice->next_send_date_client = now()->format('Y-m-d');
                        $recurring_invoice->amount = $invoice->amount;
                        $recurring_invoice->balance = $invoice->amount;
                        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
                        $recurring_invoice->is_proforma = true;

                        $recurring_invoice->saveQuietly();
                        $recurring_invoice->next_send_date =  $recurring_invoice->nextSendDate();
                        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
                        $recurring_invoice->service()->applyNumber()->save();
                    }

                    return;
                }

                if (strlen($invoice->number) > 1 && str_starts_with($invoice->number, "####")) {
                    $invoice->number = '';
                }

                $invoice->is_proforma = false;

                $invoice->service()
                        ->applyNumber()
                        ->save();
            }

            /* Updates the company ledger */
            $this->payment
                 ->ledger()
                 ->updatePaymentBalance($paid_amount * -1, "UpdateInvoicePayment");

            $pivot_invoice = $this->payment->invoices->first(function ($inv) use ($paid_invoice) {
                return $inv->hashed_id == $paid_invoice->invoice_id;
            });

            /*update paymentable record*/
            $pivot_invoice->pivot->amount = $paid_amount;
            $pivot_invoice->pivot->save();

            $this->payment->applied += $paid_amount;

        });

        /* Remove the event updater from within the loop to prevent race conditions */

        $this->payment->saveQuietly();

        $invoices->each(function ($invoice) {
            event('eloquent.updated: App\Models\Invoice', $invoice);
            event(new InvoiceWasUpdated($invoice, $invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        });

        return $this->payment->fresh();
    }
}

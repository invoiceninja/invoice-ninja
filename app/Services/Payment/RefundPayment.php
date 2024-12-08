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

use App\Exceptions\PaymentRefundFailed;
use App\Jobs\Payment\EmailRefundPayment;
use App\Models\Activity;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use App\Repositories\ActivityRepository;
use App\Utils\Ninja;
use stdClass;

class RefundPayment
{
    private float $total_refund = 0;

    private float $credits_used = 0;

    private bool $refund_failed = false;

    private string $refund_failed_message = '';

    public function __construct(public Payment $payment, public array $refund_data)
    {
    }

    public function run()
    {
        $this->payment = $this
                            ->calculateTotalRefund() //sets amount for the refund (needed if we are refunding multiple invoices in one payment)
                            ->updateCreditables() //return the credits first
                            ->processGatewayRefund() //process the gateway refund if needed
                            ->setStatus() //sets status of payment
                            ->updatePaymentables() //update the paymentable items
                            ->adjustInvoices()
                            ->save();

        if (array_key_exists('email_receipt', $this->refund_data) && $this->refund_data['email_receipt'] == 'true') {
            $contact = $this->payment->client->contacts()->whereNotNull('email')->first();
            EmailRefundPayment::dispatch($this->payment, $this->payment->company, $contact);
        }

        $is_gateway_refund = ($this->refund_data['gateway_refund'] !== false || $this->refund_failed || (isset($this->refund_data['via_webhook']) && $this->refund_data['via_webhook'] !== false)) ? ctrans('texts.yes') : ctrans('texts.no');
        $notes = ctrans('texts.refunded') . " : {$this->total_refund} - " . ctrans('texts.gateway_refund') . " : " . $is_gateway_refund;

        $this->createActivity($notes);
        $this->finalize();

        return $this->payment;
    }

    private function finalize(): self
    {
        if ($this->refund_failed) {
            throw new PaymentRefundFailed($this->refund_failed_message);
        }

        return $this;
    }

    /**
     * Process the refund through the gateway.
     *
     * $response
     * [
     *  'transaction_reference' => (string),
     *  'transaction_response' => (string),
     *  'success' => (bool),
     *  'description' => (string),
     *  'code' => (string),
     *  'payment_id' => (int),
     *  'amount' => (float),
     * ];
     *
     * @return $this
     * @throws PaymentRefundFailed
     */
    private function processGatewayRefund()
    {
        $net_refund = ($this->total_refund - $this->credits_used);

        if ($this->refund_data['gateway_refund'] !== false && $net_refund > 0) {
            if ($this->payment->company_gateway) {
                $response = $this->payment->company_gateway->driver($this->payment->client)->refund($this->payment, $net_refund);

                if ($response['amount'] ?? false) {
                    $net_refund = $response['amount'];
                }

                if ($response['voided'] ?? false) {
                    //When a transaction is voided - all invoices attached to the payment need to be reversed, this
                    //block prevents the edge case where a partial refund was attempted.
                    $this->refund_data['invoices'] = $this->payment->invoices->map(function ($invoice) {
                        return [
                            'date' => now()->addSeconds($invoice->client->timezone_offset())->format('Y-m-d'),
                            'invoice_id' => $invoice->id,
                            'amount' => $invoice->pivot->amount,
                        ];
                    })->toArray();
                }

                $this->payment->refunded += $net_refund;

                if ($response['success'] == false) {
                    $this->payment->save();
                    $this->refund_failed = true;
                    $this->refund_failed_message = $response['description'] ?? '';
                }
            }
        } else {
            $this->payment->refunded += $net_refund;
        }

        $this->payment->setRefundMeta($this->refund_data);

        return $this;
    }

    /**
     * Create the payment activity.
     *
     * @param  string $notes
     * @return $this
     */
    private function createActivity($notes)
    {
        $fields = new stdClass();
        $activity_repo = new ActivityRepository();

        $fields->payment_id = $this->payment->id;
        $fields->user_id = $this->payment->user_id;
        $fields->company_id = $this->payment->company_id;
        $fields->activity_type_id = Activity::REFUNDED_PAYMENT;
        $fields->client_id = $this->payment->client_id;
        // $fields->credit_id // TODO
        $fields->notes = $notes;

        if (isset($this->refund_data['invoices'])) {
            foreach ($this->refund_data['invoices'] as $invoice) {
                $fields->invoice_id = $invoice['invoice_id'];
                $activity_repo->save($fields, $this->payment, Ninja::eventVars(auth()->user() ? auth()->user()->id : null));
            }
        } else {
            $activity_repo->save($fields, $this->payment, Ninja::eventVars(auth()->user() ? auth()->user()->id : null));
        }

        return $this;
    }

    /**
     * Determine the amount of refund.
     *
     * @return $this
     */
    private function calculateTotalRefund()
    {
        if (array_key_exists('invoices', $this->refund_data) && count($this->refund_data['invoices']) > 0) {
            $this->total_refund = collect($this->refund_data['invoices'])->sum('amount');
        } else {
            $this->total_refund = $this->refund_data['amount'];
        }

        return $this;
    }

    /**
     * Set the payment status.
     */
    private function setStatus()
    {
        if ($this->total_refund == $this->payment->amount || floatval($this->payment->amount) == floatval($this->payment->refunded)) {
            $this->payment->status_id = Payment::STATUS_REFUNDED;
        } else {
            $this->payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
        }

        return $this;
    }

    /**
     * Update the paymentable records.
     *
     * @return $this
     */
    private function updatePaymentables()
    {
        if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0) {
            $this->payment->invoices->each(function ($paymentable_invoice) {
                collect($this->refund_data['invoices'])->each(function ($refunded_invoice) use ($paymentable_invoice) {
                    if ($refunded_invoice['invoice_id'] == $paymentable_invoice->id) {
                        $paymentable_invoice->pivot->refunded += $refunded_invoice['amount'];
                        $paymentable_invoice->pivot->save();
                    }
                });
            });
        }

        return $this;
    }

    /**
     * If credits have been bundled in this payment, we
     * need to reverse these.
     *
     * @return $this
     */
    private function updateCreditables()
    {

        if ($this->payment->credits()->exists()) {

            $amount_to_refund = $this->total_refund;

            //Adjust credits first!!!
            foreach ($this->payment->credits as $paymentable_credit) {
                $available_credit = $paymentable_credit->pivot->amount - $paymentable_credit->pivot->refunded;

                if ($available_credit > $amount_to_refund) {
                    $paymentable_credit->pivot->refunded += $amount_to_refund;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->service()
                                       ->setStatus(Credit::STATUS_SENT)
                                       ->adjustBalance($amount_to_refund)
                                       ->updatePaidToDate($amount_to_refund * -1)
                                       ->save();


                    $this->credits_used += $amount_to_refund;
                    $amount_to_refund = 0;

                } else {
                    $paymentable_credit->pivot->refunded += $available_credit;
                    $paymentable_credit->pivot->save();

                    $paymentable_credit->service()
                                       ->setStatus(Credit::STATUS_SENT)
                                       ->adjustBalance($available_credit)
                                       ->updatePaidToDate($available_credit * -1)
                                       ->save();

                    $this->credits_used += $available_credit;
                    $amount_to_refund -= $available_credit;

                }

                if ($amount_to_refund == 0) {
                    break;
                }
            }
        }

        return $this;
    }

    /**
     * Reverse the payments made on invoices.
     *
     * @return $this
     */
    private function adjustInvoices()
    {
        if (isset($this->refund_data['invoices']) && count($this->refund_data['invoices']) > 0) {
            foreach ($this->refund_data['invoices'] as $refunded_invoice) {
                $invoice = Invoice::withTrashed()->find($refunded_invoice['invoice_id']);

                if ($invoice->trashed()) {
                    $invoice->restore();
                }

                $invoice->service()
                        ->updateBalance($refunded_invoice['amount'])
                        ->updatePaidToDate($refunded_invoice['amount'] * -1)
                        ->save();

                $invoice->ledger()
                        ->updateInvoiceBalance(abs($refunded_invoice['amount']), "Refund of payment # {$this->payment->number}")
                        ->save();

                if ($invoice->amount == $invoice->balance) {
                    $invoice->service()->setStatus(Invoice::STATUS_SENT);
                } else {
                    $invoice->service()->setStatus(Invoice::STATUS_PARTIAL);
                }

                //26-10-2022 - disable autobill to prevent future billings;
                $invoice->auto_bill_enabled = false;

                $invoice->saveQuietly();

                //08-08-2023
                $client = $invoice->client
                                  ->service()
                                  ->updateBalanceAndPaidToDate($refunded_invoice['amount'], -1 * $refunded_invoice['amount'])
                                  ->save();

                if ($invoice->is_deleted) {
                    $invoice->delete();
                }
            }

        } else {
            //if we are refunding and no payments have been tagged, then we need to decrement the client->paid_to_date by the total refund amount.

            $client = $this->payment->client->fresh();

            if ($client->trashed()) {
                $client->restore();
            }

            $client->service()->updatePaidToDate(-1 * $this->total_refund)->save();
        }

        return $this;
    }

    /**
     * Saves the payment.
     *
     * @return Payment $payment
     */
    private function save()
    {
        $this->payment->save();

        return $this->payment;
    }
}

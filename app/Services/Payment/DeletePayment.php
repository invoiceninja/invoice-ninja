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

use App\Models\BankTransaction;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Contracts\Container\BindingResolutionException;

class DeletePayment
{
    private float $_paid_to_date_deleted = 0;

    /**
     * @param Payment $payment
     * @return void
     */
    public function __construct(public Payment $payment, private bool $update_client_paid_to_date)
    {
    }

    /**
     * @return Payment
     * @throws BindingResolutionException
     */
    public function run()
    {
        \DB::connection(config('database.default'))->transaction(function () {
            $this->payment = Payment::withTrashed()->where('id', $this->payment->id)->lockForUpdate()->first();

            if ($this->payment && !$this->payment->is_deleted) {
                $this->setStatus(Payment::STATUS_CANCELLED) //sets status of payment
                    ->updateCreditables() //return the credits first
                    ->adjustInvoices()
                    ->deletePaymentables()
                    ->cleanupPayment()
                    ->save();
            }
        }, 2);

        return $this->payment;
    }

    /** @return $this  */
    private function cleanupPayment()
    {

        $this->payment->is_deleted = true;
        $this->payment->delete();

        BankTransaction::query()->where('payment_id', $this->payment->id)->cursor()->each(function ($bt) {
            $bt->invoice_ids = null;
            $bt->payment_id = null;
            $bt->status_id = 1;
            $bt->save();
        });

        return $this;
    }

    /** @return $this  */
    private function deletePaymentables()
    {

        $this->payment->paymentables()
                ->each(function ($pp) {
                    $pp->forceDelete();
                });

        return $this;
    }

    /** @return $this  */
    private function adjustInvoices()
    {
        $this->_paid_to_date_deleted = 0;

        if ($this->payment->invoices()->exists()) {
            $this->payment->invoices()->each(function ($paymentable_invoice) {
                $net_deletable = $paymentable_invoice->pivot->amount - $paymentable_invoice->pivot->refunded;

                $this->_paid_to_date_deleted += $net_deletable;
                $paymentable_invoice = $paymentable_invoice->fresh();

                nlog("net deletable amount - refunded = {$net_deletable}");

                if ($paymentable_invoice->status_id == Invoice::STATUS_CANCELLED) {

                    $is_trashed = false;

                    if ($paymentable_invoice->trashed()) {
                        $is_trashed = true;
                        $paymentable_invoice->restore();
                    }

                    $paymentable_invoice->service()
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();

                    $this->payment
                         ->client
                         ->service()
                         ->updatePaidToDate($net_deletable * -1)
                         ->save();

                    if ($is_trashed) {
                        $paymentable_invoice->delete();
                    }

                } elseif (! $paymentable_invoice->is_deleted) {
                    $paymentable_invoice->restore();

                    $paymentable_invoice->service()
                                        ->updateBalance($net_deletable)
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();

                    $paymentable_invoice->ledger()
                                        ->updateInvoiceBalance($net_deletable, "Adjusting invoice {$paymentable_invoice->number} due to deletion of Payment {$this->payment->number}")
                                        ->save();

                    //@todo refactor
                    $this->payment
                         ->client
                         ->service()
                         ->updateBalanceAndPaidToDate($net_deletable, $net_deletable * -1)
                         ->save();

                    if ($paymentable_invoice->balance == $paymentable_invoice->amount) {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_SENT)->save();
                    } elseif ($paymentable_invoice->balance == 0) {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_PAID)->save();
                    } else {
                        $paymentable_invoice->service()->setStatus(Invoice::STATUS_PARTIAL)->save();
                    }
                } else {
                    $paymentable_invoice->restore();
                    $paymentable_invoice->service()
                                        ->updatePaidToDate($net_deletable * -1)
                                        ->save();
                    $paymentable_invoice->delete();

                }
            });
        }

        //sometimes the payment is NOT created properly, this catches the payment and prevents the paid to date reducing inappropriately.
        if ($this->update_client_paid_to_date) {

            $reduced_paid_to_date = $this->payment->amount < 0 ? $this->payment->amount * -1 : min(0, ($this->payment->amount - $this->payment->refunded - $this->_paid_to_date_deleted) * -1);

            // $reduced_paid_to_date = min(0, ($this->payment->amount - $this->payment->refunded - $this->_paid_to_date_deleted) * -1);

            $this->payment
                ->client
                ->service()
                ->updatePaidToDate($reduced_paid_to_date)
                ->save();
        }

        return $this;
    }

    /** @return $this  */
    private function updateCreditables()
    {
        if ($this->payment->credits()->exists()) {
            $this->payment->credits()->where('is_deleted', 0)->each(function ($paymentable_credit) {
                $multiplier = 1;

                if ($paymentable_credit->pivot->amount < 0) {
                    $multiplier = -1;
                }

                $paymentable_credit->service()
                                   ->updateBalance($paymentable_credit->pivot->amount * $multiplier * -1)
                                   ->updatePaidToDate($paymentable_credit->pivot->amount * $multiplier * -1)
                                   ->setStatus(Credit::STATUS_SENT)
                                   ->save();

                $client = $this->payment->client->fresh();

                $client
                ->service()
                ->adjustCreditBalance($paymentable_credit->pivot->amount)
                ->save();
            });
        }

        return $this;
    }

    /**
     * @param mixed $status
     * @return $this
     */
    private function setStatus($status)
    {
        $this->payment->status_id = Payment::STATUS_CANCELLED;

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

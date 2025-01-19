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

use App\Events\Invoice\InvoiceWasDeleted;
use App\Jobs\Inventory\AdjustProductInventory;
use App\Models\Invoice;
use App\Models\Quote;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;

class MarkInvoiceDeleted extends AbstractService
{
    use GeneratesCounter;

    private $adjustment_amount = 0;

    private $total_payments = 0;

    private $balance_adjustment = 0;

    public function __construct(public Invoice $invoice)
    {
    }

    public function run()
    {
        
        if ($this->invoice->company->track_inventory) {
            (new AdjustProductInventory($this->invoice->company, $this->invoice, []))->handleDeletedInvoice();
        }

        $this->cleanup()
             ->setAdjustmentAmount()
             ->deletePaymentables()
             ->adjustPayments()
             ->adjustPaidToDateAndBalance()
             ->adjustLedger()
             ->triggeredActions();

        $this->invoice->delete();

        event(new \App\Events\Invoice\InvoiceWasDeleted($this->invoice, $this->invoice->company, \App\Utils\Ninja::eventVars(auth()->guard('api')->user() ? auth()->guard('api')->user()->id : null)));

        return $this->invoice;
    }

    private function adjustLedger()
    {
        $this->invoice->ledger()->updatePaymentBalance($this->balance_adjustment * -1, 'Invoice Deleted - reducing ledger balance'); //reduces the payment balance by payment totals

        return $this;
    }

    private function adjustPaidToDateAndBalance()
    {

        $this->invoice
             ->client
             ->service()
             ->updateBalanceAndPaidToDate($this->balance_adjustment * -1, $this->adjustment_amount * -1)
             ->save();

        return $this;
    }

    /* Adjust the payment amounts */
    private function adjustPayments()
    {
        //if total payments = adjustment amount - that means we need to delete the payments as well.
        if ($this->adjustment_amount == $this->total_payments) {
            $this->invoice->payments()->update(['payments.deleted_at' => now(), 'payments.is_deleted' => true]);
        }


        //adjust payments down by the amount applied to the invoice payment.
        $this->invoice->payments->each(function ($payment) {
            $payment_adjustment = $payment->paymentables
                                            ->where('paymentable_type', '=', 'invoices')
                                            ->where('paymentable_id', $this->invoice->id)
                                            ->sum('amount');

            $payment_adjustment -= $payment->paymentables
                                            ->where('paymentable_type', '=', 'invoices')
                                            ->where('paymentable_id', $this->invoice->id)
                                            ->sum('refunded');

            //14-07-2023 - Do not include credits in the payment adjustment.
            $payment_adjustment -= $payment->paymentables
                                            ->where('paymentable_type', '=', 'App\Models\Credit')
                                            ->sum('amount');

            $payment->amount -= $payment_adjustment;
            $payment->applied -= $payment_adjustment;
            $payment->save();
        });


        return $this;
    }

    /**
     * Set the values of two variables
     *
     * $this->adjustment_amount - sum of the invoice paymentables
     * $this->total_payments - sum of the invoice payments
     */
    private function setAdjustmentAmount()
    {
        foreach ($this->invoice->payments as $payment) {
            $this->adjustment_amount += $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum('amount');

            $this->adjustment_amount -= $payment->paymentables
                                                ->where('paymentable_type', '=', 'invoices')
                                                ->where('paymentable_id', $this->invoice->id)
                                                ->sum('refunded');
        }

        $this->total_payments = $this->invoice->payments->sum('amount') - $this->invoice->payments->sum('refunded');

        $this->balance_adjustment = $this->invoice->balance;

        return $this;
    }

    /*
     *
     * This sets the invoice number to _deleted
     * and also removes the links to existing entities
     *
     */
    private function cleanup()
    {
        $check = false;

        $x = 0;

        do {
            $number = $this->calcNumber($x);
            $check = $this->checkNumberAvailable(Invoice::class, $this->invoice, $number);
            $x++;
        } while (! $check);

        $this->invoice->number = $number;

        //wipe references to invoices from related entities.
        $this->invoice->tasks()->update(['invoice_id' => null]);
        $this->invoice->expenses()->update(['invoice_id' => null]);

        return $this;
    }

    private function calcNumber($x)
    {
        if ($x == 0) {
            $number = $this->invoice->number.'_'.ctrans('texts.deleted');
        } else {
            $number = $this->invoice->number.'_'.ctrans('texts.deleted').'_'.$x;
        }

        return $number;
    }

    /* Touches all paymentables as deleted */
    private function deletePaymentables()
    {
        $this->invoice->payments->each(function ($payment) {
            $payment->paymentables()
                    ->where('paymentable_type', '=', 'invoices')
                    ->where('paymentable_id', $this->invoice->id)
                    ->update(['deleted_at' => now()]);
        });

        return $this;
    }

    private function triggeredActions(): self
    {
        if ($this->invoice->quote) {
            $this->invoice->quote->invoice_id = null;
            $this->invoice->quote->status_id = Quote::STATUS_SENT;
            $this->invoice->pushQuietly();
        }

        return $this;
    }
}

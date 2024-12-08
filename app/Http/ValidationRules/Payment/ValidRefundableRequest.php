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

namespace App\Http\ValidationRules\Payment;

use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidRefundableRequest.
 */
class ValidRefundableRequest implements Rule
{
    use MakesHash;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    private $error_msg;

    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        if (! array_key_exists('id', $this->input)) {
            $this->error_msg = ctrans('texts.payment_id_required');

            return false;
        }

        $payment = Payment::query()->where('id', $this->input['id'])->withTrashed()->first();

        if (! $payment) {
            $this->error_msg = ctrans('texts.unable_to_retrieve_payment');

            return false;
        }

        $request_invoices = request()->has('invoices') ? $this->input['invoices'] : [];

        if ($payment->invoices()->exists()) {
            $this->checkInvoice($payment->invoices, $request_invoices);
        }

        foreach ($request_invoices as $request_invoice) {
            $this->checkInvoiceIsPaymentable($request_invoice, $payment);
        }

        if (strlen($this->error_msg) > 0) {
            return false;
        }

        return true;
    }

    private function checkInvoiceIsPaymentable($invoice, $payment)
    {
        /**@var \App\Models\Invoice $invoice **/
        $invoice = Invoice::query()->where('id', $invoice['invoice_id'])->where('company_id', $payment->company_id)->withTrashed()->first();

        if (! $invoice) {
            $this->error_msg = 'Invoice not found for refund';

            return false;
        }

        if ($payment->invoices()->exists()) {
            $paymentable_invoice = $payment->invoices->where('id', $invoice->id)->first();

            if (! $paymentable_invoice) {
                $this->error_msg = ctrans('texts.invoice_not_related_to_payment', ['invoice' => $invoice->hashed_id]);

                return false;
            }
        } else {
            $this->error_msg = ctrans('texts.invoice_not_related_to_payment', ['invoice' => $invoice->hashed_id]);

            return false;
        }
    }

    private function checkInvoice($paymentables, $request_invoices)
    {
        $record_found = false;

        foreach ($paymentables as $paymentable) {
            foreach ($request_invoices as $request_invoice) {

                if ($request_invoice['invoice_id'] == $paymentable->pivot->paymentable_id) {
                    $record_found = true;

                    $refundable_amount = ($paymentable->pivot->amount - $paymentable->pivot->refunded);

                    if ($request_invoice['amount'] > $refundable_amount) {
                        $invoice = $paymentable;

                        $this->error_msg = ctrans('texts.max_refundable_invoice', ['invoice' => $invoice->hashed_id, 'amount' => $refundable_amount]);

                        return false;
                    }
                }
            }
        }

        if (! $record_found) {
            $this->error_msg = ctrans('texts.refund_without_invoices');

            return false;
        }
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->error_msg;
    }
}

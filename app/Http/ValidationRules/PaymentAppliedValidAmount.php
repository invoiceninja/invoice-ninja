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

namespace App\Http\ValidationRules;

use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class PaymentAppliedValidAmount.
 */
class PaymentAppliedValidAmount implements Rule
{
    use MakesHash;

    private $message;

    public function __construct(private array $input)
    {
        $this->input = $input;
    }
    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->message = ctrans('texts.insufficient_applied_amount_remaining');

        return $this->calculateAmounts();
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->message;
    }

    private function calculateAmounts(): bool
    {
        $payment = Payment::withTrashed()->whereId($this->decodePrimaryKey(request()->segment(4)))->company()->first();
        $inv_collection = Invoice::withTrashed()->whereIn('id', array_column($this->input['invoices'], 'invoice_id'))->get();

        if (! $payment) {
            return false;
        }

        $payment_amounts = 0;
        $invoice_amounts = 0;

        // $payment_amounts = $payment->amount - $payment->refunded - $payment->applied;

        //20-03-2024 - applied amounts are never tainted by refunded amount.
        $payment_amounts = $payment->amount - $payment->applied;

        if (request()->has('credits')
            && is_array(request()->input('credits'))
            && count(request()->input('credits')) == 0
            && request()->has('invoices')
            && is_array(request()->input('invoices'))
            && count(request()->input('invoices')) == 0) {
            return true;
        }

        if (request()->input('credits') && is_array(request()->input('credits'))) {
            foreach (request()->input('credits') as $credit) {
                $payment_amounts += $credit['amount'];
            }
        }

        if (isset($this->input['invoices']) && is_array($this->input['invoices'])) {
            foreach ($this->input['invoices'] as $invoice) {
                $invoice_amounts += $invoice['amount'];

                $inv = $inv_collection->firstWhere('id', $invoice['invoice_id']);

                if ($inv->status_id == Invoice::STATUS_DRAFT && $inv->amount >= $invoice['amount']) {

                } elseif ($inv->balance < $invoice['amount']) {
                    $this->message = 'Amount cannot be greater than invoice balance';

                    return false;
                }
            }

            if (count($this->input['invoices']) >= 1 && $payment->status_id == Payment::STATUS_PENDING) {
                $this->message = 'Cannot apply a payment until the status is completed.';
                return false;
            }

        }

        if (round($payment_amounts, 3) >= round($invoice_amounts, 3)) {
            return true;
        }

        return false;
    }
}

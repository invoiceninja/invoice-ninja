<?php
/**
 * Invoice Ninja (https://Paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use App\Models\Company;
use App\Models\Payment;
use App\Factory\PaymentFactory;
use App\Models\Credit;

/**
 *
 * Class PaymentTransformer.
 */
class PaymentTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb()
    {
    }

    public function transform(mixed $qb_data)
    {

        return [
            'date' => data_get($qb_data, 'TxnDate', now()->format('Y-m-d')),
            'amount' => floatval(data_get($qb_data, 'TotalAmt', 0)),
            'applied' => data_get($qb_data, 'TotalAmt', 0) - data_get($qb_data, 'UnappliedAmt', 0),
            'number' => data_get($qb_data, 'DocNumber', null),
            'private_notes' => data_get($qb_data, 'PrivateNote', null),
            'currency_id' => (string) $this->resolveCurrency(data_get($qb_data, 'CurrencyRef.value')),
            'client_id' => $this->getClientId(data_get($qb_data, 'CustomerRef.value', null)),
        ];
    }

    public function buildPayment($qb_data): ?Payment
    {
        $ninja_payment_data = $this->transform($qb_data);

        if ($ninja_payment_data['client_id']) {
            $payment = PaymentFactory::create($this->company->id, $this->company->owner()->id, $ninja_payment_data['client_id']);
            $payment->amount = $ninja_payment_data['amount'];
            $payment->applied = $ninja_payment_data['applied'];
            $payment->status_id = 4;
            $payment->fill($ninja_payment_data);
            $payment->save();

            $payment->client->service()->updatePaidToDate($payment->amount);

            if ($payment->amount == 0) {
                //this is a credit memo, create a stub credit for this.
                $payment = $this->createCredit($payment, $qb_data);
                $payment->type_id = \App\Models\PaymentType::CREDIT;
                $payment->save();
            }


            return $payment;

        }
        return null;
    }

    private function createCredit($payment, $qb_data)
    {
        $credit_line = null;

        foreach ($qb_data->Line as $item) {

            if (data_get($item, 'LinkedTxn.TxnType', null) == 'CreditMemo') {
                $credit_line = $item;
                break;
            }

        }

        if (!$credit_line) {
            return $payment;
        }

        $credit = \App\Factory\CreditFactory::create($this->company->id, $this->company->owner()->id);
        $credit->client_id = $payment->client_id;

        $line = new \App\DataMapper\InvoiceItem();
        $line->quantity = 1;
        $line->cost = $credit_line->Amount;
        $line->product_key = 'CREDITMEMO';
        $line->notes = $payment->private_notes;

        $credit->date = $qb_data->TxnDate;
        $credit->status_id = 4;
        $credit->amount = $credit_line->Amount;
        $credit->paid_to_date = $credit_line->Amount;
        $credit->balance = 0;
        $credit->line_items = [$line];
        $credit->save();

        $paymentable = new \App\Models\Paymentable();
        $paymentable->payment_id = $payment->id;
        $paymentable->paymentable_id = $credit->id;
        $paymentable->paymentable_type = \App\Models\Credit::class;
        $paymentable->amount = $credit->amount;
        $paymentable->created_at = $payment->date;
        $paymentable->save();

        return $payment;
    }

    public function getLine($data, $field = null)
    {
        $invoices = [];
        $invoice = $this->getString($data, 'Line.LinkedTxn.TxnType');
        if (is_null($invoice) || $invoice !== 'Invoice') {
            return $invoices;
        }
        if (is_null(($invoice_id = $this->getInvoiceId($this->getString($data, 'Line.LinkedTxn.TxnId.value'))))) {
            return $invoices;
        }

        return [[
            'amount' => (float) $this->getString($data, 'Line.Amount'),
            'invoice_id' => $invoice_id
        ]];
    }

}

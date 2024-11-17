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

namespace App\Services\ClientPortal;

use App\Exceptions\PaymentFailed;
use App\Jobs\Invoice\CheckGatewayFee;
use App\Jobs\Invoice\InjectSignature;
use App\Jobs\Util\SystemLogger;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * LivewireInstantPayment
 *
 * New entry point for livewire component
 * payments.
 */
class LivewireInstantPayment
{
    use MakesHash;
    use MakesDates;

    /**
     * (bool) success
     * (string) error - "displayed back to the user, either in error div, or in with() on redirect"
     * (string) redirect - ie client.invoices.index
     * (array) payload - the data needed to complete the payment
     * (string) component - the payment component to be displayed
     *
     * @var array $responder
     */
    private array $responder = [
        'success' => true,
        'error' => '',
        'redirect' => '',
        'payload' => [],
        'component' => '',
    ];
    /**
     * is_credit_payment
     *
     * Indicates whether this is a credit payment
     * @var bool
     */
    private $is_credit_payment = false;
    /**
     * __construct
     *
     * contact() guard
     * company_gateway_id
     * payable_invoices[] ['invoice_id' => '', 'amount' => 0]
     * ?signature
     * ?signature_ip
     * payment_method_id
     * ?pre_payment
     * ?frequency_id
     * ?remaining_cycles
     * ?is_recurring
     * ?hash
     *
     * @param  array $data
     * @return void
     */
    public function __construct(public array $data)
    {
    }

    public function run()
    {

        $company_gateway = CompanyGateway::query()->find($this->data['company_gateway_id']);

        if ($this->data['company_gateway_id'] == CompanyGateway::GATEWAY_CREDIT) {
            $this->is_credit_payment = true;
        }

        $payable_invoices = collect($this->data['payable_invoices']);
        $tokens = [];

        $invoices = Invoice::query()
            ->whereIn('id', $this->transformKeys($payable_invoices->pluck('invoice_id')->toArray()))
            ->withTrashed()
            ->get();

        $client = $invoices->first()->client;

        /* pop non payable invoice from the $payable_invoices array */
        $payable_invoices = $payable_invoices->filter(function ($payable_invoice) use ($invoices) { // @phpstan-ignore-line
            return $invoices->where('hashed_id', $payable_invoice['invoice_id'])->first();
        });

        $payable_invoice_collection = collect();

        foreach ($payable_invoices as $payable_invoice) {
            $payable_invoice['amount'] = Number::parseFloat($payable_invoice['amount']);

            $invoice = $invoices->first(function ($inv) use ($payable_invoice) {
                return $payable_invoice['invoice_id'] == $inv->hashed_id;
            });

            $payable_amount = Number::roundValue(Number::parseFloat($payable_invoice['amount']), $client->currency()->precision);
            $invoice_balance = Number::roundValue($invoice->balance, $client->currency()->precision);

            $payable_invoice['due_date'] = $this->formatDate($invoice->due_date, $invoice->client->date_format());
            $payable_invoice['invoice_number'] = $invoice->number;

            if (isset($invoice->po_number)) {
                $additional_info = $invoice->po_number;
            } elseif (isset($invoice->public_notes)) {
                $additional_info = $invoice->public_notes;
            } else {
                $additional_info = $invoice->date;
            }

            $payable_invoice['additional_info'] = $additional_info;

            $payable_invoice_collection->push($payable_invoice);
        }

        if (isset($this->data['signature']) && $this->data['signature']) {

            $contact_id = auth()->guard('contact')->user() ? auth()->guard('contact')->user()->id : null;

            $invoices->each(function ($invoice) use ($contact_id) {
                InjectSignature::dispatch($invoice, $contact_id, $this->data['signature'], $this->data['signature_ip']);
            });
        }

        $payable_invoices = $payable_invoice_collection;

        $payment_method_id = $this->data['payment_method_id'];
        $invoice_totals = $payable_invoices->sum('amount');
        $first_invoice = $invoices->first();
        $credit_totals = in_array($first_invoice->client->getSetting('use_credits_payment'), ['always', 'option']) ? $first_invoice->client->service()->getCreditBalance() : 0;
        $starting_invoice_amount = $first_invoice->balance;

        if ($company_gateway) {
            $first_invoice->service()->addGatewayFee($company_gateway, $payment_method_id, $invoice_totals)->save();
        }

        /**
        * Gateway fee is calculated
        * by adding it as a line item, and then subtract
        * the starting and finishing amounts of the invoice.
        */
        $fee_totals = $first_invoice->balance - $starting_invoice_amount;

        if ($company_gateway) {
            $tokens = $client->gateway_tokens()
                ->whereCompanyGatewayId($company_gateway->id)
                ->whereGatewayTypeId($payment_method_id)
                ->get();
        }

        if (! $this->is_credit_payment) {
            $credit_totals = 0;
        }

        /** $hash_data = mixed[] */
        $hash_data = [
            'invoices' => $payable_invoices->toArray(),
            'credits' => $credit_totals,
            'amount_with_fee' => max(0, (($invoice_totals + $fee_totals) - $credit_totals)),
            'pre_payment' => $this->data['pre_payment'],
            'frequency_id' => $this->data['frequency_id'],
            'remaining_cycles' => $this->data['remaining_cycles'],
            'is_recurring' => $this->data['is_recurring'],
        ];

        if (isset($this->data['hash'])) {
            $hash_data['billing_context'] = Cache::get($this->data['hash']);
        } elseif ($old_hash = PaymentHash::query()->where('fee_invoice_id', $first_invoice->id)->whereNull('payment_id')->orderBy('id', 'desc')->first()) {
            if (isset($old_hash->data->billing_context)) {
                $hash_data['billing_context'] = $old_hash->data->billing_context;
            }
        }

        $payment_hash = new PaymentHash();
        $payment_hash->hash = Str::random(32);
        $payment_hash->data = $hash_data;
        $payment_hash->fee_total = $fee_totals;
        $payment_hash->fee_invoice_id = $first_invoice->id;

        $payment_hash->save();

        if ($this->is_credit_payment) {
            $amount_with_fee = max(0, (($invoice_totals + $fee_totals) - $credit_totals));
        } else {
            $credit_totals = 0;
            $amount_with_fee = max(0, $invoice_totals + $fee_totals);
        }

        $totals = [
            'credit_totals' => $credit_totals,
            'invoice_totals' => $invoice_totals,
            'fee_total' => $fee_totals,
            'amount_with_fee' => $amount_with_fee,
        ];

        $data = [
            'ph' => $payment_hash,
            'payment_hash' => $payment_hash->hash,
            'total' => $totals,
            'invoices' => $payable_invoices,
            'tokens' => $tokens,
            'payment_method_id' => $payment_method_id,
            'amount_with_fee' => $invoice_totals + $fee_totals,
            'client' => $client,
            'pre_payment' => $this->data['pre_payment'],
            'is_recurring' => $this->data['is_recurring'],
            'company_gateway' => $company_gateway,
        ];

        if ($this->is_credit_payment) {
            $this->mergeResponder(['success' => true, 'view' => 'gateways.credit.pay_livewire', 'component' => 'CreditPaymentComponent', 'payload' => $data]);
            return $this->getResponder();
        }

        $this->mergeResponder(['success' => true, 'payload' => $data]);

        return $this->getResponder();

    }

    private function getResponder(): array
    {
        return $this->responder;
    }

    private function mergeResponder(array $data): self
    {
        $this->responder = array_merge($this->responder, $data);

        return $this;
    }
}

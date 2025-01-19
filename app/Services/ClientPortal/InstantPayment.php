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

class InstantPayment
{
    use MakesHash;
    use MakesDates;

    /** $request mixed */
    public Request $request;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    public function run()
    {
        /** @var \App\Models\ClientContact $cc */
        $cc = auth()->guard('contact')->user();
        $cc->first_name = $this->request->contact_first_name;
        $cc->last_name = $this->request->contact_last_name;
        $cc->email = $this->request->contact_email;
        $cc->client->postal_code = strlen($cc->client->postal_code ?? '') > 1 ? $cc->client->postal_code : $this->request->client_postal_code;
        $cc->client->city = strlen($cc->client->city ?? '') > 1 ? $cc->client->city : $this->request->client_city;
        $cc->client->shipping_postal_code = strlen($cc->client->shipping_postal_code ?? '') > 1 ? $cc->client->shipping_postal_code : $cc->client->postal_code;
        $cc->client->shipping_city = strlen($cc->client->shipping_city ?? '') > 1 ? $cc->client->shipping_city : $cc->client->city;
        $cc->pushQuietly();

        $is_credit_payment = false;

        $tokens = [];

        if ($this->request->input('company_gateway_id') == CompanyGateway::GATEWAY_CREDIT) {
            $is_credit_payment = true;
        }

        $gateway = CompanyGateway::query()->find($this->request->input('company_gateway_id'));

        /**
         * find invoices
         *
         * ['invoice_id' => xxx, 'amount' => 22.00]
         */
        $payable_invoices = collect($this->request->payable_invoices);

        $invoices = Invoice::query()->whereIn('id', $this->transformKeys($payable_invoices->pluck('invoice_id')->toArray()))->withTrashed()->get();

        $invoices->each(function ($invoice) {
            $invoice->service()
                    ->markSent()
                    ->removeUnpaidGatewayFees()
                    ->save();
        });

        /* pop non payable invoice from the $payable_invoices array */

        $payable_invoices = $payable_invoices->filter(function ($payable_invoice) use ($invoices) {
            return $invoices->where('hashed_id', $payable_invoice['invoice_id'])->first()->isPayable();
        });

        /*return early if no invoices*/

        if ($payable_invoices->count() == 0) {
            return redirect()
                ->route('client.invoices.index')
                ->with(['message' => 'No payable invoices selected.']);
        }

        $invoices = Invoice::query()->whereIn('id', $this->transformKeys($payable_invoices->pluck('invoice_id')->toArray()))->withTrashed()->get();

        $client = $invoices->first()->client;
        $settings = $client->getMergedSettings();

        /* This loop checks for under / over payments and returns the user if a check fails */

        foreach ($payable_invoices as $payable_invoice) {
            /*Match the payable invoice to the Model Invoice*/

            $invoice = $invoices->first(function ($inv) use ($payable_invoice) {
                return $payable_invoice['invoice_id'] == $inv->hashed_id;
            });

            /*
             * Check if company supports over & under payments.
             * Determine the payable amount and the max payable. ie either partial or invoice balance
             */

            $payable_amount = Number::roundValue(Number::parseFloat($payable_invoice['amount']), $client->currency()->precision);
            $invoice_balance = Number::roundValue(($invoice->partial > 0 ? $invoice->partial : $invoice->balance), $client->currency()->precision);


            /*If we don't allow under/over payments force the payable amount - prevents inspect element adjustments in JS*/

            if ($settings->client_portal_allow_under_payment == false && $settings->client_portal_allow_over_payment == false) {
                $payable_invoice['amount'] = Number::roundValue(($invoice->partial > 0 ? $invoice->partial : $invoice->balance), $client->currency()->precision);
            }

            if (! $settings->client_portal_allow_under_payment && $payable_amount < $invoice_balance) {
                return redirect()
                    ->route('client.invoices.index')
                    ->with('message', ctrans('texts.minimum_required_payment', ['amount' => $invoice_balance]));
            }

            if ($settings->client_portal_allow_under_payment) {
                if ($invoice_balance < $settings->client_portal_under_payment_minimum && $payable_amount < $invoice_balance) {
                    return redirect()
                        ->route('client.invoices.index')
                        ->with('message', ctrans('texts.minimum_required_payment', ['amount' => $invoice_balance]));
                }

                if ($invoice_balance < $settings->client_portal_under_payment_minimum) {
                    // Skip the under payment rule.
                }

                if ($invoice_balance >= $settings->client_portal_under_payment_minimum && $payable_amount < $settings->client_portal_under_payment_minimum) {
                    return redirect()
                        ->route('client.invoices.index')
                        ->with('message', ctrans('texts.minimum_required_payment', ['amount' => $settings->client_portal_under_payment_minimum]));
                }
            }

            /* If we don't allow over payments and the amount exceeds the balance */

            if (! $settings->client_portal_allow_over_payment && $payable_amount > $invoice_balance) {
                return redirect()
                    ->route('client.invoices.index')
                    ->with('message', ctrans('texts.over_payments_disabled'));
            }
        }

        /*Iterate through invoices and add gateway fees and other payment metadata*/

        //$payable_invoices = $payable_invoices->map(function ($payable_invoice) use ($invoices, $settings) {
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

        if ($this->request->has('signature') && ! is_null($this->request->signature) && ! empty($this->request->signature)) {

            $contact_id = auth()->guard('contact')->user() ? auth()->guard('contact')->user()->id : null;

            $invoices->each(function ($invoice) use ($contact_id) {
                InjectSignature::dispatch($invoice, $contact_id, $this->request->signature, request()->getClientIp());
            });
        }

        $payable_invoices = $payable_invoice_collection;

        $payment_method_id = $this->request->input('payment_method_id');
        $invoice_totals = $payable_invoices->sum('amount');
        $first_invoice = $invoices->first();
        $credit_totals = in_array($first_invoice->client->getSetting('use_credits_payment'), ['always', 'option']) ? $first_invoice->client->service()->getCreditBalance() : 0;
        $starting_invoice_amount = $first_invoice->balance;

        $payment_hash_string = Str::random(32);

        if ($gateway) {
            $first_invoice->service()->addGatewayFee($gateway, $payment_method_id, $invoice_totals, $payment_hash_string)->save();
        }

        /**
         * Gateway fee is calculated
         * by adding it as a line item, and then subtract
         * the starting and finishing amounts of the invoice.
         */
        $fee_totals = $first_invoice->balance - $starting_invoice_amount;

        if ($gateway) {
            $tokens = $client->gateway_tokens()
                ->whereCompanyGatewayId($gateway->id)
                ->whereGatewayTypeId($payment_method_id)
                ->get();
        }

        if (! $is_credit_payment) {
            $credit_totals = 0;
        }

        /** $hash_data = mixed[] */
        $hash_data = [
            'invoices' => $payable_invoices->toArray(),
            'credits' => $credit_totals,
            'amount_with_fee' => max(0, (($invoice_totals + $fee_totals) - $credit_totals)),
            'pre_payment' => $this->request->pre_payment,
            'frequency_id' => $this->request->frequency_id,
            'remaining_cycles' => $this->request->remaining_cycles,
            'is_recurring' => $this->request->is_recurring,
        ];

        if ($this->request->query('hash')) {
            $hash_data['billing_context'] = Cache::get($this->request->query('hash'));
        } elseif ($this->request->hash) {
            $hash_data['billing_context'] = Cache::get($this->request->hash);
        } elseif ($old_hash = PaymentHash::query()->where('fee_invoice_id', $first_invoice->id)->whereNull('payment_id')->orderBy('id', 'desc')->first()) {
            if (isset($old_hash->data->billing_context)) {
                $hash_data['billing_context'] = $old_hash->data->billing_context;
            }
        }

        $payment_hash = new PaymentHash();
        $payment_hash->hash = $payment_hash_string;
        $payment_hash->data = $hash_data;
        $payment_hash->fee_total = $fee_totals;
        $payment_hash->fee_invoice_id = $first_invoice->id;

        $payment_hash->save();

        if ($is_credit_payment) {
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
            'payment_hash' => $payment_hash->hash,
            'total' => $totals,
            'invoices' => $payable_invoices,
            'tokens' => $tokens,
            'payment_method_id' => $payment_method_id,
            'amount_with_fee' => $invoice_totals + $fee_totals,
            'client' => $client,
            'pre_payment' => $this->request->pre_payment,
            'is_recurring' => $this->request->is_recurring,
        ];

        if ($is_credit_payment) {
            return $this->processCreditPayment($this->request, $data);
        }

        try {
            return $gateway
                ->driver($client)
                ->setPaymentMethod($payment_method_id)
                ->setPaymentHash($payment_hash)
                ->checkRequirements()
                ->processPaymentView($data);
        } catch (\Exception $e) {
            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_FAILURE,
                $client,
                $client->company
            );

            throw new PaymentFailed($e->getMessage());
        }
    }

    public function processCreditPayment(Request $request, array $data)
    {
        return render('gateways.credit.index', $data);
    }
}

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

namespace App\Http\Controllers\ClientPortal;

use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\HtmlEngine;
use Illuminate\View\View;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Models\CompanyGateway;
use App\Factory\PaymentFactory;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\Factory;
use App\PaymentDrivers\Stripe\BankTransfer;
use App\Services\ClientPortal\InstantPayment;
use App\Services\Subscription\SubscriptionService;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

/**
 * Class PaymentController.
 */
class PaymentController extends Controller
{
    use MakesHash;
    use MakesDates;

    /**
     * Show the list of payments.
     *
     * @return Factory|View
     */
    public function index()
    {
        return $this->render('payments.index');
    }

    /**
     * Display the specified resource.
     *
     * @param Request $request
     * @param Payment $payment
     * @return Factory|View
     */
    public function show(Request $request, Payment $payment)
    {
        $payment->load('invoices');
        $bank_details = false;
        $payment_intent = false;
        $data = false;
        $gateway = false;

        if ($payment->gateway_type_id == GatewayType::DIRECT_DEBIT && $payment->type_id == PaymentType::DIRECT_DEBIT) {
            if (method_exists($payment->company_gateway->driver($payment->client), 'getPaymentIntent')) {
                $stripe = $payment->company_gateway->driver($payment->client);
                $payment_intent = $stripe->getPaymentIntent($payment->transaction_reference);

                $bt = new BankTransfer($stripe);

                match ($payment->currency->code) {
                    'MXN' => $data = $bt->formatDataforMx($payment_intent),
                    'EUR' => $data = $bt->formatDataforEur($payment_intent),
                    'JPY' => $data = $bt->formatDataforJp($payment_intent),
                    'GBP' => $data = $bt->formatDataforUk($payment_intent),
                    default => $data = $bt->formatDataforUk($payment_intent),
                };

                $gateway = $stripe;
            }
        }


        return $this->render('payments.show', [
            'payment' => $payment,
            'bank_details' => $payment_intent ? $data : false,
            'currency' => $payment->currency ? strtolower($payment->currency->code) : strtolower($payment->client->currency()->code),
        ]);
    }

    public function catch_process(Request $request)
    {
        return $this->render('payments.index');
    }

    /**
     * Presents the payment screen for a given
     * gateway and payment method.
     * The request will also contain the amount
     * and invoice ids for reference.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function process(Request $request)
    {
        return (new InstantPayment($request))->run();
    }

    public function response(PaymentResponseRequest $request)
    {
        /** @var \App\Models\CompanyGateway $gateway **/
        $gateway = CompanyGateway::findOrFail($request->input('company_gateway_id'));
        $payment_hash = PaymentHash::with('fee_invoice')->where('hash', $request->payment_hash)->firstOrFail();

        $invoice = $payment_hash->fee_invoice;

        $client = $invoice ? $invoice->client : auth()->guard('contact')->user()->client;

        // 09-07-2022 catch duplicate responses for invoices that already paid here.
        if ($invoice && $invoice->status_id == Invoice::STATUS_PAID) {

            $invitation = $invoice->invitations->first();

            $variables = ($invitation && auth()->guard('contact')->user()->client->getSetting('show_accept_invoice_terms')) ? (new HtmlEngine($invitation))->generateLabelsAndValues() : false;

            $data = [
                'invoice' => $invoice,
                'key' => false,
                'invitation' => $invitation,
                'variables' => $variables,
            ];

            if ($request->query('mode') === 'fullscreen') {
                return render('invoices.show-fullscreen', $data);
            }

            return $this->render('invoices.show', $data);
        }

        return $gateway
            ->driver($client)
            ->setPaymentMethod($request->input('payment_method_id'))
            ->setPaymentHash($payment_hash)
            ->checkRequirements()
            ->processPaymentResponse($request);
    }

    /**
     * Pay for invoice/s using credits only.
     *
     * @param Request $request The request object
     * @return \Illuminate\Http\RedirectResponse        The response view
     */
    public function credit_response(Request $request)
    {
        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->first();

        /* Hydrate the $payment */
        if ($payment_hash->payment()->exists()) {
            $payment = $payment_hash->payment;
        } else {
            $payment = PaymentFactory::create($payment_hash->fee_invoice->company_id, $payment_hash->fee_invoice->user_id);
            $payment->client_id = $payment_hash->fee_invoice->client_id;

            $payment->saveQuietly();
            $payment->currency_id = $payment->client->getSetting('currency_id');
            $payment->saveQuietly();

            $payment_hash->payment_id = $payment->id;
            $payment_hash->save();
        }
        $payment->type_id = PaymentType::CREDIT;
        $payment = $payment->service()->applyCredits($payment_hash)->save();

        /** @var \Illuminate\Database\Eloquent\Collection<\App\Models\Invoice> $invoices */
        $invoices = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')));

        $invoices->each(function ($invoice) {
            /** @var \App\Models\Invoice $invoice **/
            $invoice->is_proforma = false;
            $invoice->saveQuietly();
        });

        event('eloquent.created: App\Models\Payment', $payment);

        if ($invoices->sum('balance') > 0) {
            /** @var \App\Models\Invoice $invoice **/
            $invoice = $invoices->first();

            return redirect()->route('client.invoice.show', ['invoice' => $invoice->hashed_id, 'hash' => $request->input('hash')]);
        }

        if (property_exists($payment_hash->data, 'billing_context')) {
            $billing_subscription = \App\Models\Subscription::find($this->decodePrimaryKey($payment_hash->data->billing_context->subscription_id));
            /** @var \App\Models\Subscription $billing_subscription */
            return (new SubscriptionService($billing_subscription))->completePurchase($payment_hash);
        }

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    public function processCreditPayment(Request $request, array $data)
    {
        return render('gateways.credit.index', $data);
    }
}

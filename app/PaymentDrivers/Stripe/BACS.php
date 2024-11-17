<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Stripe\Jobs\UpdateCustomer;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Number;
use Stripe\Checkout\Session;

class BACS implements LivewireMethodInterface
{
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView(array $data)
    {
        $customer = $this->stripe->findOrCreateCustomer();
        $data['session'] = Session::create([
            'payment_method_types' => ['bacs_debit'],
            'mode' => 'setup',
            'customer' => $customer->id,
            'success_url' => str_replace("%7B", "{", str_replace("%7D", "}", $this->buildAuthorizeUrl())),
            'cancel_url' => route('client.payment_methods.index'),
        ]);
        return render('gateways.stripe.bacs.authorize', $data);
    }
    private function buildAuthorizeUrl(): string
    {
        return route('client.payment_methods.confirm', [
            'method' => GatewayType::BACS,
            'session_id' => "{CHECKOUT_SESSION_ID}",
        ]);
    }

    public function authorizeResponse($request)
    {
        $this->stripe->init();
        if ($request->session_id) {
            $session = $this->stripe->stripe->checkout->sessions->retrieve($request->session_id, ['expand' => ['setup_intent']]);

            $customer = $this->stripe->findOrCreateCustomer();
            $this->stripe->attach($session->setup_intent->payment_method, $customer);
            $payment_method =  $this->stripe->getStripePaymentMethod($session->setup_intent->payment_method);
            $this->storePaymentMethod($payment_method, $customer);
        }
        return redirect()->route('client.payment_methods.index');
    }
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.stripe.bacs.pay', $data);
    }
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->stripe->init();
        $invoice_numbers = collect($this->stripe->payment_hash->invoices())->pluck('invoice_number')->implode(',');
        // $description = ctrans('texts.stripe_payment_text', ['invoicenumber' => $invoice_numbers, 'amount' => Number::formatMoney($request->amount, $this->stripe->client), 'client' => $this->stripe->client->present()->name()]);
        $description = $this->stripe->getDescription(false);

        $payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($request->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $description,
            'payment_method_types' => ['bacs_debit'],
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::BACS,
            ],
            'payment_method' => $request->token,
            'confirm' => true,
        ];
        $state = [
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_intent' => $this->stripe->createPaymentIntent($payment_intent_data),
        ];
        $state = array_merge($state, $request->all());

        $state['customer'] = $state['payment_intent']->customer;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $state);
        $this->stripe->payment_hash->save();

        if ($state['payment_intent']->status == 'processing') {
            $this->stripe->logSuccessfulGatewayResponse(['response' => $state['payment_intent'], 'data' => $this->stripe->payment_hash->data], SystemLog::TYPE_STRIPE);

            return $this->processSuccessfulPayment($state['payment_intent']);
        }

        return $this->processUnsuccessfulPayment("An unknown error occured.");
    }

    public function processSuccessfulPayment($payment_intent)
    {
        UpdateCustomer::dispatch($this->stripe->company_gateway->company->company_key, $this->stripe->company_gateway->id, $this->stripe->client->id);

        $data = [
            'payment_method' => $payment_intent['id'],
            'payment_type' => PaymentType::BACS,
            'amount' => $this->stripe->convertFromStripeAmount($payment_intent->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent['id'],
            'gateway_type_id' => GatewayType::BACS,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        (new SystemLogger(
            ['response' => $payment_intent, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        ))->handle();


        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccessfulPayment($server_response)
    {
        $this->stripe->sendFailureMail($server_response);

        $message = [
            'server_response' => $server_response,
            'data' => $this->stripe->payment_hash->data,
        ];

        (new SystemLogger(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        ))->handle();

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    private function storePaymentMethod($method, $customer)
    {
        try {
            $payment_meta = new \stdClass();
            $payment_meta->brand = (string) $method->bacs_debit->sort_code;
            $payment_meta->last4 = (string) $method->bacs_debit->last4;
            $payment_meta->state = 'unauthorized';
            $payment_meta->type = GatewayType::BACS;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => GatewayType::BACS,
            ];
            $clientgateway = ClientGatewayToken::query()
                ->where('token', $method->id)
                ->first();
            if (!$clientgateway) {
                $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);
            }
        } catch (\Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->stripe;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['payment_hash'] = $this->stripe->payment_hash->hash;

        return $data;
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.bacs.pay_livewire';
    }
}

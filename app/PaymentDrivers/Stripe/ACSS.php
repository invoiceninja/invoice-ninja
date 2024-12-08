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

namespace App\PaymentDrivers\Stripe;

use App\Http\Controllers\ClientPortal\InvoiceController;
use App\Http\Requests\ClientPortal\Invoices\ProcessInvoicesInBulkRequest;
use App\Models\Payment;
use App\Models\SystemLog;
use Stripe\PaymentIntent;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Support\Str;
use App\Http\Requests\Request;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use Illuminate\Support\Facades\Cache;
use App\Jobs\Mail\PaymentFailureMailer;
use App\PaymentDrivers\StripePaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class ACSS implements LivewireMethodInterface
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
        $this->stripe->init();
    }

    /**
     * Generate mandate for future ACSS billing
     *
     * @param  mixed $data
     * @return void
     */
    public function authorizeView($data)
    {
        $data['gateway'] = $this->stripe;
        $data['company_gateway'] = $this->stripe->company_gateway;
        $data['customer'] = $this->stripe->findOrCreateCustomer()->id;
        $data['country'] = $this->stripe->client->country->iso_3166_2;
        $data['post_auth_response'] = false;

        $intent = \Stripe\SetupIntent::create([
        'usage' => 'off_session',
        'payment_method_types' => ['acss_debit'],
        'customer' => $data['customer'],
        'payment_method_options' => [
            'acss_debit' => [
            'currency' => strtolower($this->stripe->client->currency()->code),
            'mandate_options' => [
                'payment_schedule' => 'combined',
                'interval_description' => 'On any invoice due date',
                'transaction_type' => 'personal',
            ],
            'verification_method' => 'instant',
            ],
        ],
        ], $this->stripe->stripe_connect_auth);

        $data['pi_client_secret'] = $intent->client_secret;

        return render('gateways.stripe.acss.authorize', array_merge($data));
    }

    /**
     * Authorizes the mandate for future billing
     *
     * @param  Request $request
     * @return void
     */
    public function authorizeResponse(Request $request)
    {
        $setup_intent = json_decode($request->input('gateway_response'));

        if (isset($setup_intent->type)) {

            $error = "There was a problem setting up this payment method for future use";

            if (in_array($setup_intent->type, ["validation_error", "invalid_request_error"])) {
                $error = "Please provide complete payment details.";
            }

            SystemLogger::dispatch(
                ['response' => (array)$setup_intent, 'data' => $request->all()],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_STRIPE,
                $this->stripe->client,
                $this->stripe->client->company,
            );

            throw new PaymentFailed($error, 400);
        }

        $stripe_setup_intent = $this->stripe->getSetupIntentId($setup_intent->id); //needed to harvest the Mandate

        $client_gateway_token = $this->storePaymentMethod($setup_intent->payment_method, $stripe_setup_intent->mandate, $setup_intent->status == 'succeeded' ? 'authorized' : 'unauthorized');

        if ($request->has('post_auth_response') && boolval($request->post_auth_response)) {
            /** @var array $data */
            $data = Cache::pull($request->post_auth_response);

            if (!$data) {
                throw new PaymentFailed("There was a problem storing this payment method", 500);
            }

            $hash = PaymentHash::with('fee_invoice')->where('hash', $data['payment_hash'])->first();

            $data['tokens'] = [$client_gateway_token];
            $data['one_page_checkout'] = (bool) $request->one_page_checkout;

            $this->stripe->setPaymentHash($hash);
            $this->stripe->setClient($hash->fee_invoice->client);
            $this->stripe->setPaymentMethod(GatewayType::ACSS);

            return $this->paymentView($data);
        }

        return redirect()->route('client.payment_methods.show', $client_gateway_token->hashed_id);

    }

    /**
     * Generates a token Payment Intent
     *
     * @param  ClientGatewayToken $token
     * @return PaymentIntent
     */
    private function tokenIntent(ClientGatewayToken $token): PaymentIntent
    {

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $this->stripe->convertToStripeAmount($this->stripe->payment_hash->amount_with_fee(), $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->currency()->code,
            'payment_method_types' => ['acss_debit'],
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $this->stripe->getDescription(false),
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::ACSS,
            ],
            'payment_method' => $token->token,
            'mandate' => $token->meta?->mandate,
            'confirm' => true,
        ], $this->stripe->stripe_connect_auth);

        return $intent;
    }

    public function paymentData(array $data): array
    {
        if (count($data['tokens']) == 0) {
            $hash = Str::random(32);

            Cache::put($hash, $data, 3600);

            $data['post_auth_response'] = $hash;
            $data['needs_mandate_generate'] = true;

            $data['gateway'] = $this->stripe;
            $data['company_gateway'] = $this->stripe->company_gateway;
            $data['customer'] = $this->stripe->findOrCreateCustomer()->id;
            $data['country'] = $this->stripe->client->country->iso_3166_2;

            $intent = \Stripe\SetupIntent::create([
                'usage' => 'off_session',
                'payment_method_types' => ['acss_debit'],
                'customer' => $data['customer'],
                'payment_method_options' => [
                    'acss_debit' => [
                    'currency' => strtolower($this->stripe->client->currency()->code),
                    'mandate_options' => [
                        'payment_schedule' => 'combined',
                        'interval_description' => 'On any invoice due date',
                        'transaction_type' => 'personal',
                    ],
                    'verification_method' => 'instant',
                    ],
                ],
            ], $this->stripe->stripe_connect_auth);

            $data['pi_client_secret'] = $intent->client_secret;

            return $data;
        }

        $this->stripe->init();

        $data['gateway'] = $this->stripe;
        $data['return_url'] = $this->buildReturnUrl();
        $data['stripe_amount'] = $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency());
        $data['client'] = $this->stripe->client;
        $data['customer'] = $this->stripe->findOrCreateCustomer()->id;
        $data['country'] = $this->stripe->client->country->iso_3166_2;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $data['stripe_amount']]);
        $this->stripe->payment_hash->save();

        return $data;
    }

    /**
     * Payment view for ACSS
     *
     * Determines if any payment tokens are available and if not, generates a mandate
     *
     * @param  array $data

     */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        if (isset($data['one_page_checkout']) && $data['one_page_checkout']) {
            $data = [
                'invoices' => collect($data['invoices'])->map(fn ($invoice) => $invoice['invoice_id'])->toArray(),
                'action' => 'payment',
            ];

            $request = new ProcessInvoicesInBulkRequest();
            $request->replace($data);

            session()->flash('message', ctrans('texts.payment_method_added'));

            return app(InvoiceController::class)->bulk($request);
        }

        if (array_key_exists('needs_mandate_generate', $data)) {
            return render('gateways.stripe.acss.authorize', array_merge($data));
        }

        return render('gateways.stripe.acss.pay', $data);
    }

    /**
     * ?redundant
     *
     * @return string
     */
    private function buildReturnUrl(): string
    {
        return route('client.payments.response', [
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_method_id' => GatewayType::ACSS,
        ]);
    }

    /**
     * PaymentResponseRequest
     *
     * @param  PaymentResponseRequest $request
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {

        $gateway_response = json_decode($request->gateway_response);

        $cgt = ClientGatewayToken::find($this->decodePrimaryKey($request->token));

        /** @var \Stripe\PaymentIntent $intent */
        $intent = $this->tokenIntent($cgt);

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $request->all());
        $this->stripe->payment_hash->save();

        if ($intent->status && $intent->status == 'processing') {

            return $this->processSuccessfulPayment($intent->id);
        }

        return $this->processUnsuccessfulPayment();
    }

    /**
     * Performs token billing using a ACSS payment method
     *
     * @param  ClientGatewayToken $cgt
     * @param  PaymentHash $payment_hash
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->stripe->init();
        $this->stripe->setPaymentHash($payment_hash);
        $this->stripe->setClient($cgt->client);
        $stripe_amount = $this->stripe->convertToStripeAmount($payment_hash->amount_with_fee(), $this->stripe->client->currency()->precision, $this->stripe->client->currency());
        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $stripe_amount]);
        $this->stripe->payment_hash->save();

        /** @var \Stripe\PaymentIntent $intent */
        $intent = $this->tokenIntent($cgt);

        if ($intent->status && $intent->status == 'processing') {
            $this->processSuccessfulPayment($intent->id);
        } else {
            $e = new \Exception("There was a problem processing this payment method", 500);
            $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }


    }

    /**
     * Creates a payment for the transaction
     *
     * @param  string $payment_intent
     */
    public function processSuccessfulPayment(string $payment_intent)
    {
        $data = [
            'payment_method' => $payment_intent,
            'payment_type' => PaymentType::ACSS,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent,
            'gateway_type_id' => GatewayType::ACSS,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', $payment->hashed_id);
    }

    public function processUnsuccessfulPayment()
    {
        $server_response = $this->stripe->payment_hash->data;

        PaymentFailureMailer::dispatch(
            $this->stripe->client,
            $server_response,
            $this->stripe->client->company,
            $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency())
        );

        $message = [
            'server_response' => $server_response,
            'data' => $this->stripe->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    /**
     * Stores the payment token
     *
     * @param  string $payment_method
     * @param  string $mandate
     * @param  string $status
     * @return ClientGatewayToken
     */
    private function storePaymentMethod(string $payment_method, string $mandate, string $status = 'authorized'): ?ClientGatewayToken
    {
        try {
            $method = $this->stripe->getStripePaymentMethod($payment_method);

            $payment_meta = new \stdClass();
            $payment_meta->brand = (string) $method->acss_debit->bank_name;
            $payment_meta->last4 = (string) $method->acss_debit->last4;
            $payment_meta->state = $status;
            $payment_meta->type = GatewayType::ACSS;
            $payment_meta->mandate = $mandate;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $payment_method,
                'payment_method_id' => GatewayType::ACSS,
            ];

            return $this->stripe->storeGatewayToken($data, ['gateway_customer_reference' => $method->customer]);
        } catch (\Exception $e) {
            return $this->stripe->processInternallyFailedPayment($this->stripe, $e);
        }
    }

    public function livewirePaymentView(array $data): string
    {
        if (array_key_exists('needs_mandate_generate', $data)) {
            return 'gateways.stripe.acss.authorize_livewire';
        }

        return 'gateways.stripe.acss.pay_livewire';
    }
}

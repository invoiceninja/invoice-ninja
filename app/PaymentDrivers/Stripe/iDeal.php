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

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\StripePaymentDriver;

class iDeal implements LivewireMethodInterface
{
    /** @var StripePaymentDriver */
    public StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function authorizeView($data)
    {
        return render('gateways.stripe.ideal.authorize', $data);
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.stripe.ideal.pay', $data);
    }

    private function buildReturnUrl(): string
    {
        return route('client.payments.response', [
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_method_id' => GatewayType::IDEAL,
        ]);
    }

    public function paymentResponse($request)
    {
        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $request->all());
        $this->stripe->payment_hash->save();

        if (in_array($request->redirect_status, ['succeeded','pending'])) {
            return $this->processSuccessfulPayment($request->payment_intent);
        }

        return $this->processUnsuccessfulPayment();
    }

    public function processSuccessfulPayment(string $payment_intent)
    {
        /* @todo: https://github.com/invoiceninja/invoiceninja/pull/3789/files#r436175798 */

        $this->stripe->init();

        //catch duplicate submissions.
        if ($pay_exists = Payment::query()->where('transaction_reference', $payment_intent)->first()) {
            return redirect()->route('client.payments.show', ['payment' => $pay_exists->hashed_id]);
        }

        $data = [
            'payment_method' => $payment_intent,
            'payment_type' => PaymentType::IDEAL,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent,
            'gateway_type_id' => GatewayType::IDEAL,
        ];

        $payment = $this->stripe->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
    }

    public function processUnsuccessfulPayment()
    {
        $server_response = $this->stripe->payment_hash->data;

        $this->stripe->sendFailureMail($server_response);

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

    public function paymentData(array $data): array
    {
        $this->stripe->init();

        $data['gateway'] = $this->stripe;
        $data['return_url'] = $this->buildReturnUrl();
        $data['stripe_amount'] = $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency());
        $data['client'] = $this->stripe->client;
        $data['customer'] = $this->stripe->findOrCreateCustomer()->id;
        $data['country'] = $this->stripe->client->country->iso_3166_2;

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $data['stripe_amount'],
            'currency' => 'eur',
            'payment_method_types' => ['ideal'],
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $this->stripe->getDescription(false),
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::IDEAL,
            ],
        ], array_merge($this->stripe->stripe_connect_auth, ['idempotency_key' => uniqid("st", true)]));

        $data['pi_client_secret'] = $intent->client_secret;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $data['stripe_amount']]);
        $this->stripe->payment_hash->save();

        return $data;
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.ideal.pay_livewire';
    }
}

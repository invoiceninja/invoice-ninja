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

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\StripePaymentDriver;
use Stripe\Exception\InvalidRequestException;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use Throwable;

class Alipay implements LivewireMethodInterface
{
    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function paymentView(array $data)
    {

        $data = $this->paymentData($data);

        return render('gateways.stripe.alipay.pay', $data);
    }

    private function buildReturnUrl(): string
    {
        return route('client.payments.response', [
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_method_id' => GatewayType::ALIPAY,
        ]);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->stripe->init();

        $this->stripe->setPaymentHash($request->getPaymentHash());
        $this->stripe->client = $this->stripe->payment_hash->fee_invoice->client;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, $request->all());
        $this->stripe->payment_hash->save();

        if ($request->payment_intent) {
            $pi = \Stripe\PaymentIntent::retrieve(
                $request->payment_intent,
                $this->stripe->stripe_connect_auth
            );

            if (in_array($pi->status, ['succeeded', 'pending'])) {
                return $this->processSuccesfulRedirect($pi);
            }

            /** @phpstan-ignore-next-line */
            if ($pi->status == 'requires_source_action' && $pi->next_action->alipay_handle_redirect) {
                /** @phpstan-ignore-next-line */
                return redirect($pi->next_action->alipay_handle_redirect->url);
            }
        }

        return $this->processUnsuccesfulRedirect();
    }

    public function processSuccesfulRedirect($payment_intent)
    {
        $this->stripe->init();

        $data = [
            'payment_method' => $payment_intent->payment_method,
            'payment_type' => PaymentType::ALIPAY,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent->id,
            'gateway_type_id' => GatewayType::ALIPAY,

        ];

        $payment = $this->stripe->createPayment($data, $payment_intent->status == 'pending' ? Payment::STATUS_PENDING : Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    public function processUnsuccesfulRedirect()
    {
        $server_response = $this->stripe->payment_hash->data;

        $this->stripe->sendFailureMail($server_response->redirect_status);

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
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.alipay.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
                'currency' => $this->stripe->client->currency()->code,
                'payment_method_types' => ['alipay'],
                'customer' => $this->stripe->findOrCreateCustomer(),
                'description' => $this->stripe->getDescription(false),
                'metadata' => [
                    'payment_hash' => $this->stripe->payment_hash->hash,
                    'gateway_type_id' => GatewayType::ALIPAY,
                ],
            ], $this->stripe->stripe_connect_auth);
        } catch (\Throwable $e) {

            throw new PaymentFailed($e->getMessage(), $e->getCode());

        }

        $data['gateway'] = $this->stripe;
        $data['return_url'] = $this->buildReturnUrl();
        $data['ci_intent'] = $intent->client_secret;

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency())]);
        $this->stripe->payment_hash->save();

        return $data;
    }
}

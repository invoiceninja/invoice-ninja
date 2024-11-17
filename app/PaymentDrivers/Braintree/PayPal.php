<?php

namespace App\PaymentDrivers\Braintree;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\BraintreePaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;

class PayPal implements LivewireMethodInterface
{
    /**
     * @var BraintreePaymentDriver
     */
    private $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->braintree;

        return render('gateways.braintree.paypal.authorize', $data);
    }

    public function authorizeResponse($data): \Illuminate\Http\RedirectResponse
    {
        return back();
    }

    /**
     * Credit card payment page.
     *
     * @param array $data
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.braintree.paypal.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $state = [
            'server_response' => json_decode($request->gateway_response),
            'payment_hash' => $request->payment_hash,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $this->braintree->payment_hash->data = array_merge((array) $this->braintree->payment_hash->data, $state);
        $this->braintree->payment_hash->save();

        $customer = $this->braintree->findOrCreateCustomer();

        $token = $this->getPaymentToken($request->all(), $customer->id);

        $result = $this->braintree->gateway->transaction()->sale([
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'paymentMethodToken' => $token,
            'deviceData' => $state['client-data'],
            'channel' => 'invoiceninja_BT',
            'options' => [
                'submitForSettlement' => true,
                'paypal' => [
                    'description' => 'Meaningful description.',
                ],
            ],
        ]);

        if ($result->success) {
            $this->braintree->logSuccessfulGatewayResponse(
                ['response' => $request->server_response, 'data' => $this->braintree->payment_hash->data],
                SystemLog::TYPE_BRAINTREE
            );

            if ($request->store_card && is_null($request->token)) {
                $payment_method = $this->braintree->gateway->paymentMethod()->find($token);

                $this->storePaymentMethod($payment_method, $customer->id);
            }

            return $this->processSuccessfulPayment($result);
        }

        return $this->processUnsuccessfulPayment($result);
    }

    private function getPaymentToken(array $data, string $customerId)
    {
        if (array_key_exists('token', $data) && ! is_null($data['token'])) {
            return $data['token'];
        }

        $gateway_response = json_decode($data['gateway_response']);

        $payment_method = $this->braintree->gateway->paymentMethod()->create([
            'customerId' => $customerId,
            'paymentMethodNonce' => $gateway_response->nonce,
        ]);

        if ($payment_method->success) {
            return $payment_method->paymentMethod->token;
        } else {
            throw new PaymentFailed(property_exists($payment_method, 'message') ? $payment_method->message : 'Undefined error storing payment token.', 0);
        }
    }

    /**
     * Process & complete the successful PayPal transaction.
     *
     * @param $response
     * @return \Illuminate\Http\RedirectResponse
     */
    private function processSuccessfulPayment($response): \Illuminate\Http\RedirectResponse
    {
        $state = $this->braintree->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::PAYPAL,
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction->id,
            'gateway_type_id' => GatewayType::PAYPAL,
        ];

        $payment = $this->braintree->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->braintree->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($response)
    {
        $this->braintree->sendFailureMail($response->message);

        $message = [
            'server_response' => $response,
            'data' => $this->braintree->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company
        );

        throw new PaymentFailed($response->message, 0);
    }

    private function storePaymentMethod($method, string $customer_reference)
    {
        try {
            $payment_meta = new \stdClass();
            $payment_meta->email = (string) $method->email;
            $payment_meta->type = GatewayType::PAYPAL;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->token,
                'payment_method_id' => $this->braintree->payment_hash->data->payment_method_id,
            ];

            $this->braintree->storeGatewayToken($data, ['gateway_customer_reference' => $customer_reference]);
        } catch (\Exception $e) {
            return $this->braintree->processInternallyFailedPayment($this->braintree, $e);
        }
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string 
    {
        return 'gateways.braintree.paypal.pay_livewire';
    }
    
    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array 
    {
        $data['gateway'] = $this->braintree;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();

        return $data;
    }
}

<?php

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class CreditCard implements LivewireMethodInterface
{
    /**
     * @var MolliePaymentDriver
     */
    protected $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    /**
     * Show the page for credit card payments.
     *
     * @param array $data
     * @return Factory|View
     */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.mollie.credit_card.pay', $data);
    }

    /**
     * Create a payment object.
     *
     * @param PaymentResponseRequest $request
     * @return mixed
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $amount = $this->mollie->convertToMollieAmount((float) $this->mollie->payment_hash->data->amount_with_fee);

        $description = sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($this->mollie->payment_hash->invoices())->pluck('invoice_number')->toArray()));

        $this->mollie->payment_hash
            ->withData('gateway_type_id', GatewayType::CREDIT_CARD)
            ->withData('client_id', $this->mollie->client->id);

        if (! empty($request->token)) {
            try {
                $cgt = ClientGatewayToken::where('token', $request->token)->firstOrFail();

                $payment = $this->mollie->gateway->payments->create([
                    'amount' => [
                        'currency' => $this->mollie->client->currency()->code,
                        'value' => $amount,
                    ],
                    'mandateId' => $request->token,
                    'customerId' => $cgt->gateway_customer_reference,
                    'sequenceType' => 'recurring',
                    'description' => $description,
                    'webhookUrl'  => $this->mollie->company_gateway->webhookUrl(),
                    // 'idempotencyKey' => uniqid("st", true),
                    'metadata' => [
                        'client_id' => $this->mollie->client->hashed_id,
                        'hash' => $this->mollie->payment_hash->hash,
                        'gateway_type_id' => GatewayType::CREDIT_CARD,
                        'payment_type_id' => PaymentType::CREDIT_CARD_OTHER,
                    ],
                ]);

                if ($payment->status === 'paid') {
                    $this->mollie->logSuccessfulGatewayResponse(
                        ['response' => $payment, 'data' => $this->mollie->payment_hash->data],
                        SystemLog::TYPE_MOLLIE
                    );

                    return $this->processSuccessfulPayment($payment);
                }

                if ($payment->status === 'open') {
                    $this->mollie->payment_hash->withData('payment_id', $payment->id);

                    if (!$payment->getCheckoutUrl()) {
                        return render('gateways.mollie.mollie_placeholder');
                    } else {
                        return redirect()->away($payment->getCheckoutUrl());
                    }
                }
            } catch (\Exception $e) {
                return $this->processUnsuccessfulPayment($e);
            }
        }

        try {
            $data = [
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $amount,
                ],
                'description' => $description,
                // 'idempotencyKey' => uniqid("st", true),
                'redirectUrl' => route('mollie.3ds_redirect', [
                    'company_key' => $this->mollie->client->company->company_key,
                    'company_gateway_id' => $this->mollie->company_gateway->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                ]),
                'webhookUrl'  => $this->mollie->company_gateway->webhookUrl(),
                'metadata' => [
                    'client_id' => $this->mollie->client->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                    'gateway_type_id' => GatewayType::CREDIT_CARD,
                    'payment_type_id' => PaymentType::CREDIT_CARD_OTHER,
                ],
                'cardToken' => $request->gateway_response,
            ];

            if ($request->shouldStoreToken()) {
                $customer = $this->mollie->gateway->customers->create([
                    'name' => $this->mollie->client->name,
                    'email' => $this->mollie->client->present()->email(),
                    'metadata' => [
                        'id' => $this->mollie->client->hashed_id,
                    ],
                ]);

                $data['customerId'] = $customer->id;
                $data['sequenceType'] = 'first';

                $this->mollie->payment_hash
                    ->withData('mollieCustomerId', $customer->id)
                    ->withData('shouldStoreToken', true);
            }

            $payment = $this->mollie->gateway->payments->create($data);

            if ($payment->status === 'paid') {
                $this->mollie->logSuccessfulGatewayResponse(
                    ['response' => $payment, 'data' => $this->mollie->payment_hash->data],
                    SystemLog::TYPE_MOLLIE
                );

                return $this->processSuccessfulPayment($payment);
            }

            if ($payment->status === 'open') {
                $this->mollie->payment_hash->withData('payment_id', $payment->id);

                nlog("Mollie");
                nlog($payment);

                if (!$payment->getCheckoutUrl()) {
                    return render('gateways.mollie.mollie_placeholder');
                } else {
                    return redirect()->away($payment->getCheckoutUrl());
                }
            }
        } catch (\Exception $e) {
            $this->processUnsuccessfulPayment($e);

            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    public function processSuccessfulPayment(\Mollie\Api\Resources\Payment $payment)
    {
        $payment_hash = $this->mollie->payment_hash;

        if (property_exists($payment_hash->data, 'shouldStoreToken') && $payment_hash->data->shouldStoreToken) {
            try {
                $mandates = \iterator_to_array($this->mollie->gateway->mandates->listForId($payment_hash->data->mollieCustomerId));
            } catch (\Mollie\Api\Exceptions\ApiException $e) {
                return $this->processUnsuccessfulPayment($e);
            }

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) $mandates[0]->details->cardExpiryDate;
            $payment_meta->exp_year = (string) '';
            $payment_meta->brand = (string) $mandates[0]->details->cardLabel;
            $payment_meta->last4 = (string) $mandates[0]->details->cardNumber;
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $this->mollie->storeGatewayToken([
                'token' => $mandates[0]->id,
                'payment_method_id' => GatewayType::CREDIT_CARD,
                'payment_meta' =>  $payment_meta,
            ], ['gateway_customer_reference' => $payment_hash->data->mollieCustomerId]);
        }

        $data = [
            'gateway_type_id' => GatewayType::CREDIT_CARD,
            'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total,
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'transaction_reference' => $payment->id,
        ];

        $payment_record = $this->mollie->createPayment($data, $payment->status === 'paid' ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->mollie->encodePrimaryKey($payment_record->id)]);
    }

    public function processUnsuccessfulPayment(\Exception $e)
    {
        $this->mollie->sendFailureMail($e->getMessage());

        SystemLogger::dispatch(
            $e->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        throw new PaymentFailed($e->getMessage(), $e->getCode());
    }

    /**
     * Show authorization page.
     *
     * @param array $data
     * @return Factory|View
     */
    public function authorizeView(array $data)
    {
        return render('gateways.mollie.credit_card.authorize', $data);
    }

    /**
     * Handle authorization response.
     *
     * @param mixed $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorizeResponse($request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.mollie.credit_card.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->mollie;

        return $data;
    }
}

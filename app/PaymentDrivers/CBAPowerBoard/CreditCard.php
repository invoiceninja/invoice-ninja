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

namespace App\PaymentDrivers\CBAPowerBoard;

use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use Illuminate\Http\Client\RequestException;
use App\PaymentDrivers\CBAPowerBoardPaymentDriver;
use App\PaymentDrivers\CBAPowerBoard\Models\Charge;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\CBAPowerBoard\Models\PaymentSource;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\PaymentDrivers\CBAPowerBoard\Models\Gateway;

class CreditCard implements LivewireMethodInterface
{
    private ?Gateway $cba_gateway;

    public function __construct(public CBAPowerBoardPaymentDriver $powerboard)
    {
        $this->cba_gateway = $this->powerboard->settings()->getPaymentGatewayConfiguration(GatewayType::CREDIT_CARD);
    }

    public function authorizeView(array $data)
    {
        $data['payment_method_id'] = GatewayType::CREDIT_CARD;

        return render('gateways.powerboard.credit_card.authorize', $this->paymentData($data));
    }

    public function authorizeResponse($request)
    {

        if ($request->browser_details) {
            $payment_source = $this->storePaymentSource($request);

            $browser_details = json_decode($request->browser_details, true);

            $payload = [
                "capture" => false,
                "amount" => 1,
                "currency" => $this->powerboard->client->currency()->code,
                "description" => "Card authorization",
                "customer" => [
                    "payment_source" => [
                        "vault_token" => $payment_source->vault_token,
                        "gateway_id" => $this->powerboard->settings()->getGatewayId(GatewayType::CREDIT_CARD),
                    ],
                ],
                "_3ds" => [
                    "browser_details" => $browser_details,
                ],
            ];

            nlog($payload);

            $r = $this->powerboard->gatewayRequest('/v1/charges/3ds', (\App\Enum\HttpVerb::POST)->value, $payload, []);

            if ($r->failed()) {

                $error_payload = $this->getErrorFromResponse($r);
                return response()->json(['message' =>  $error_payload[0]], 400);

                // return $this->processUnsuccessfulPayment($r);
            }

            $charge = $r->json();
            nlog($charge['resource']['data']);

            return response()->json($charge['resource']['data'], 200);

        } elseif ($request->charge) {

            $charge_request = json_decode($request->charge, true);
            nlog("we have the charge request");
            nlog($charge_request);

            $payload = [
                '_3ds' => [
                    'id' => array_key_exists('charge_3ds_id', $charge_request) ? $charge_request['charge_3ds_id'] : $charge_request['_3ds']['id'],
                ],
                "capture" => false,
                "authorization" => true,
                "amount" => 1,
                "currency" => $this->powerboard->client->currency()->code,
                "store_cvv" => true,
            ];

            nlog($payload);

            $r = $this->powerboard->gatewayRequest("/v1/charges", (\App\Enum\HttpVerb::POST)->value, $payload, []);

            if ($r->failed()) {

                $error_payload = $this->getErrorFromResponse($r);
                throw new PaymentFailed($error_payload[0], $error_payload[1]);

            }

            $charge = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(Charge::class, $r->object()->resource->data) ?? $r->throw();

            nlog($charge);

            if ($charge->status == 'complete') {

                $this->powerboard->logSuccessfulGatewayResponse(['response' => $charge, 'data' => $this->powerboard->payment_hash->data], SystemLog::TYPE_POWERBOARD);

                $vt = $charge->customer->payment_source->vault_token;

                $data = [
                    "payment_source" => [
                        "vault_token" => $vt,
                    ],
                ];

                $customer = $this->powerboard->customer()->findOrCreateCustomer($data);
                $cgt = $this->powerboard->customer()->storePaymentMethod($charge->customer->payment_source, $charge->customer);

                return redirect()->route('client.payment_methods.show', ['payment_method' => $cgt->hashed_id]);
            }


        } elseif ($request->charge_no3d) {
            nlog($request->all());

            $payment_source = $this->storePaymentSource($request);

            nlog($payment_source);

            $data = [
                "payment_source" => [
                    "vault_token" => $payment_source->vault_token,
                ],
            ];

            $customer = $this->powerboard->customer()->findOrCreateCustomer($data);

            $cgt = $this->powerboard->customer()->storePaymentMethod($payment_source, $customer);
            $cgt->gateway_customer_reference = $this->powerboard->settings()->getGatewayId(GatewayType::CREDIT_CARD);
            $cgt->save();

            return redirect()->route('client.payment_methods.show', ['payment_method' => $cgt->hashed_id]);

        }

        return redirect()->route('client.payment_methods.index');

    }

    private function getCustomer(): array
    {
        $data = [
            'first_name' => $this->powerboard->client->present()->first_name(),
            'last_name' => $this->powerboard->client->present()->first_name(),
            'email' => $this->powerboard->client->present()->email(),
            // 'phone' => $this->powerboard->client->present()->phone(),
            // 'type' => 'card',
            'address_line1' => $this->powerboard->client->address1 ?? '',
            'address_line2' => $this->powerboard->client->address2 ?? '',
            'address_state' => $this->powerboard->client->state ?? '',
            'address_country' => $this->powerboard->client->country->iso_3166_3 ?? '',
            'address_city' => $this->powerboard->client->city ?? '',
            'address_postcode' => $this->powerboard->client->postal_code ?? '',
        ];

        return \App\Helpers\Sanitizer::removeBlanks($data);

    }

    private function storePaymentSource($request)
    {

        $this->powerboard->init();

        $payment_source = $request->gateway_response;

        $payload = array_merge($this->getCustomer(), [
            'token' => $payment_source,
            "vault_type" => "permanent",
            'store_ccv' => true,
        ]);

        nlog($payload);

        $r = $this->powerboard->gatewayRequest('/v1/vault/payment_sources', (\App\Enum\HttpVerb::POST)->value, $payload, []);

        if ($r->failed()) {
            return $this->powerboard->processInternallyFailedPayment($this->powerboard, $r->throw());
        }

        nlog($r->object());

        $source = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(PaymentSource::class, $r->object()->resource->data);

        return $source;

    }


    public function paymentData(array $data): array
    {
        $this->powerboard->init();

        $available_cards = [
            "amex",
            "ausbc",
            "discover",
            "japcb",
            "laser",
            "mastercard",
            "solo",
            "visa",
            "visa_white",
        ];

        $supported_cards = $this->powerboard->company_gateway->getConfig();

        $supported_cards_array = [];

        foreach ($available_cards as $card) {
            if ($supported_cards->{$card}) {
                $supported_cards_array[] = $card;
            }
        }

        $merge = [
            'public_key' => $this->powerboard->company_gateway->getConfigField('publicKey'),
            'widget_endpoint' => $this->powerboard->widget_endpoint,
            'gateway' => $this->powerboard,
            'environment' => $this->powerboard->environment,
            'gateway_id' => $this->cba_gateway->_id ?? false,
            'supported_cards' => $supported_cards_array,
        ];

        return array_merge($data, $merge);
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.powerboard.credit_card.pay', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.powerboard.credit_card.pay_livewire';
    }

    public function tokenBilling($cgt, $client_present = false)
    {

        $payload = [
            "amount" => $this->powerboard->payment_hash->data->amount_with_fee,
            "currency" => $this->powerboard->client->currency()->code,
            "customer" => [
                "payment_source" => [
                    "vault_token" => $cgt->token,
                    "gateway_id" => $cgt->gateway_customer_reference
                ]
            ]
        ];

        $r = $this->powerboard->gatewayRequest('/v1/charges', (\App\Enum\HttpVerb::POST)->value, $payload, []);

        nlog($r->body());

        if ($r->failed()) {
            $error_payload = $this->getErrorFromResponse($r);
            throw new PaymentFailed($error_payload[0], $error_payload[1]);
        }

        $charge = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(Charge::class, $r->object()->resource->data) ?? $r->throw();

        nlog($charge);

        $this->powerboard->logSuccessfulGatewayResponse(['response' => $charge, 'data' => $this->powerboard->payment_hash->data], SystemLog::TYPE_POWERBOARD);

        return $this->processSuccessfulPayment($charge);
    }

    private function get3dsToken(PaymentSource $source, $request)
    {

        $payment_hash = PaymentHash::query()->where('hash', $request->payment_hash)->first();

        $browser_details = json_decode($request->browser_details, true);

        $payload = [
            "amount" => $payment_hash->data->amount_with_fee,
            "currency" => $this->powerboard->client->currency()->code,
            "description" => $this->powerboard->getDescription(),
            "customer" => [
                "payment_source" => [
                    "vault_token" => $source->vault_token,
                    "gateway_id" => $this->powerboard->settings()->getGatewayId(GatewayType::CREDIT_CARD),
                ],
            ],
            "_3ds" => [
                "browser_details" => $browser_details,
            ],
        ];

        nlog($payload);

        $r = $this->powerboard->gatewayRequest('/v1/charges/3ds', (\App\Enum\HttpVerb::POST)->value, $payload, []);

        if ($r->failed()) {
            $error_payload = $this->getErrorFromResponse($r);
            return response()->json(['message' =>  $error_payload[0]], 400);
        }

        $charge = $r->json();

        return response()->json($charge['resource']['data'], 200);

    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        nlog($request->all());

        $this->powerboard->payment_hash->data = array_merge((array) $this->powerboard->payment_hash->data, ['response' => $request->all()]);
        $this->powerboard->payment_hash->save();

        $payload = [];

        /** Token Payment */
        if ($request->input('token', false)) {
            $cgt = $this->powerboard
                        ->client
                        ->gateway_tokens()
                        ->where('company_gateway_id', $this->powerboard->company_gateway->id)
                        ->where('token', $request->token)
                        ->first();

            return $this->tokenBilling($cgt, true);

        } elseif ($request->browser_details) {
            $payment_source = $this->storePaymentSource($request);

            nlog($payment_source);

            return $this->get3dsToken($payment_source, $request);

        } elseif ($request->charge) {

            $charge_request = json_decode($request->charge, true);

            nlog($charge_request);

            $payload = [
                '_3ds' => [
                    'id' => array_key_exists('charge_3ds_id', $charge_request) ? $charge_request['charge_3ds_id'] : $charge_request['_3ds']['id'],
                ],
                "amount" => $this->powerboard->payment_hash->data->amount_with_fee, //@phpstan-ignore-line
                "currency" => $this->powerboard->client->currency()->code,
                "store_cvv" => true,
            ];

            $r = $this->powerboard->gatewayRequest("/v1/charges", (\App\Enum\HttpVerb::POST)->value, $payload, []);

            if ($r->failed()) {
                return $this->processUnsuccessfulPayment($r);
            }

            $charge = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(Charge::class, $r->object()->resource->data) ?? $r->throw();

            nlog($charge);

            if ($charge->status == 'complete') {
                $this->powerboard->logSuccessfulGatewayResponse(['response' => $charge, 'data' => $this->powerboard->payment_hash->data], SystemLog::TYPE_POWERBOARD);

                $vt = $charge->customer->payment_source->vault_token;

                if ($request->store_card) {
                    $data = [
                        "payment_source" => [
                            "vault_token" => $vt,
                        ],
                    ];

                    $customer = $this->powerboard->customer()->findOrCreateCustomer($data);
                    $cgt = $this->powerboard->customer()->storePaymentMethod($charge->customer->payment_source, $charge->customer);
                }

                return $this->processSuccessfulPayment($charge);
            } elseif ($charge->error) {

                $this->powerboard->logUnsuccessfulGatewayResponse($charge, SystemLog::TYPE_POWERBOARD);

                throw new PaymentFailed($charge->error->message, $charge->status);
            }

        }

        session()->flash('message', ctrans('texts.payment_token_not_found'));

        return redirect()->back();
    }

    public function processSuccessfulPayment(Charge $charge)
    {

        $data = [
            'payment_type' => PaymentType::CREDIT_CARD_OTHER,
            'amount' => $this->powerboard->payment_hash->data->amount_with_fee,
            'transaction_reference' => $charge->_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $this->powerboard->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $charge, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_POWERBOARD,
            $this->powerboard->client,
            $this->powerboard->client->company,
        );

        if ($payment->invoices()->whereHas('subscription')->exists()) {
            $subscription = $payment->invoices()->first()->subscription;

            if ($subscription && array_key_exists('return_url', $subscription->webhook_configuration) && strlen($subscription->webhook_configuration['return_url']) >= 1) {
                return redirect($subscription->webhook_configuration['return_url']);
            }
        }

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);
    }

    private function getErrorFromResponse($response)
    {

        try {
            $response->throw();
        } catch (RequestException $exception) {

            $error_object = $exception->response->object();

            $this->powerboard->logUnsuccessfulGatewayResponse($error_object, SystemLog::TYPE_POWERBOARD);

            $error_message = "Unknown error";

            match($error_object->error->code) {
                "UnfulfilledCondition" => $error_message = $error_object->error->details->messages[0] ?? $error_object->error->message ?? "Unknown error",
                "GatewayError" => $error_message = $error_object->error->message,
                "transaction_declined" => $error_message = $error_object->error->details[0]->status_code_description,
                default => $error_message = $error_object->error->message ?? "Unknown error",
            };

            return [$error_message, $exception->getCode()];

        }

    }
    public function processUnsuccessfulPayment($response)
    {
        $error = $this->getErrorFromResponse($response);

        $this->powerboard->sendFailureMail($error[0]);

        // $message = [
        //     'server_response' => $server_response,
        //     'data' => $this->stripe->payment_hash->data,
        // ];

        SystemLogger::dispatch(
            $error[0],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_POWERBOARD,
            $this->powerboard->client,
            $this->powerboard->client->company,
        );

        if (request()->wantsJson()) {
            return response()->json($error[0], 200);
        }

        throw new PaymentFailed('Failed to process the payment.', $error[1]);
    }

}

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

namespace App\PaymentDrivers;

use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\GatewayType;
use Illuminate\Support\Str;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\Models\PaymentHash;
use App\PaymentDrivers\PayPal\PayPalBasePaymentDriver;

class PayPalRestPaymentDriver extends PayPalBasePaymentDriver
{
    use MakesHash;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    public function processPaymentView($data)
    {
        $data = $this->processPaymentViewData($data);

        if($this->gateway_type_id == 29) {
            return render('gateways.paypal.ppcp.card', $data);
        } else {
            return render('gateways.paypal.pay', $data);
        }
    }

    /**
     * processPaymentResponse
     *
     * @param  mixed $request
     */
    public function processPaymentResponse($request)
    {
        nlog("response");
        $this->init();
        $r = false;

        $request['gateway_response'] = str_replace("Error: ", "", $request['gateway_response']);
        $response = json_decode($request['gateway_response'], true);

        if($request->has('token') && strlen($request->input('token')) > 2) {
            return $this->processTokenPayment($request, $response);
        }

        //capture

        $orderID = $response['orderID'] ?? $this->payment_hash->data->orderID;

        if($this->company_gateway->require_shipping_address) {

            $shipping_data =
            [[
                "op" => "replace",
                "path" => "/purchase_units/@reference_id=='default'/shipping/address",
                "value" => [
                    "address_line_1" => strlen($this->client->shipping_address1) > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    "address_line_2" => $this->client->shipping_address2,
                    "admin_area_2" => strlen($this->client->shipping_city) > 1 ? $this->client->shipping_city : $this->client->city,
                    "admin_area_1" => strlen($this->client->shipping_state) > 1 ? $this->client->shipping_state : $this->client->state,
                    "postal_code" => strlen($this->client->shipping_postal_code) > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
                    "country_code" => $this->client->present()->shipping_country_code(),
                ],
            ]];

            $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}", 'patch', $shipping_data);

        }

        try {

            $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}/capture", 'post', ['body' => '']);

            if($r->status() == 422) {
                //handle conditions where the client may need to try again.

                $r = $this->handleDuplicateInvoiceId($orderID);


            }

        } catch(\Exception $e) {

            //Rescue for duplicate invoice_id
            if(stripos($e->getMessage(), 'DUPLICATE_INVOICE_ID') !== false) {


                $r = $this->handleDuplicateInvoiceId($orderID);

            }

        }

        $response = $r;

        nlog("Process response =>");
        nlog($response->json());

        if(isset($response['status']) && $response['status'] == 'COMPLETED' && isset($response['purchase_units'])) {

            return $this->createNinjaPayment($request, $response);

        } else {

            if(isset($response['headers']) ?? false) {
                unset($response['headers']);
            }

            SystemLogger::dispatch(
                ['response' => $response],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            $message = $response['body']['details'][0]['description'] ?? 'Payment failed. Please try again.';

            return response()->json(['message' => $message], 400);

        }

    }

    public function createOrder(array $data): string
    {

        $_invoice = collect($this->payment_hash->data->invoices)->first();

        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        $description = collect($invoice->line_items)->map(function ($item) {
            return $item->notes;
        })->implode("\n");

        $order = [
                "intent" => "CAPTURE",
                "payment_source" => $this->getPaymentSource(),
                "purchase_units" => [
                    [
                    "custom_id" => $this->payment_hash->hash,
                    "description" => ctrans('texts.invoice_number') . '# ' . $invoice->number,
                    "invoice_id" => $invoice->number,
                    "amount" => [
                        "value" => (string) $data['amount_with_fee'],
                        "currency_code" => $this->client->currency()->code,
                        "breakdown" => [
                            "item_total" => [
                                "currency_code" => $this->client->currency()->code,
                                "value" => (string) $data['amount_with_fee']
                            ]
                        ]
                    ],
                    "items" => [
                        [
                            "name" => ctrans('texts.invoice_number') . '# ' . $invoice->number,
                            "description" => mb_substr($description, 0, 127),
                            "quantity" => "1",
                            "unit_amount" => [
                                "currency_code" => $this->client->currency()->code,
                                "value" => (string) $data['amount_with_fee']
                            ],
                        ],
                    ],
                ],
                ]
            ];

        if($shipping = $this->getShippingAddress()) {
            $order['purchase_units'][0]["shipping"] = $shipping;
        }

        if(isset($data['payment_source'])) {
            $order['payment_source'] = $data['payment_source'];
        }

        if(isset($data["payer"])){
            $order['payer'] = $data["payer"];
        }

        $r = $this->gatewayRequest('/v2/checkout/orders', 'post', $order);

        nlog($r->json());
        $response = $r->json();


        if($r->status() == 422) {
            //handle conditions where the client may need to try again.

            $_invoice = collect($this->payment_hash->data->invoices)->first();
            $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));
            $new_invoice_number = $invoice->number."_".Str::random(5);

            $order['purchase_units'][0]['invoice_id'] = $new_invoice_number;

            $r = $this->gatewayRequest('/v2/checkout/orders', 'post', $order);

            nlog($r->json());
            $response = $r->json();

        }

        if(!isset($response['id'])) {
            $this->handleProcessingFailure($response);
        }

        $this->payment_hash->withData("orderID", $response['id']);

        return $response['id'];

    }



    /**
     * processTokenPayment
     *
     * With PayPal and token payments, the order needs to be
     * deleted and then created with the payment source that
     * has been selected by the client.
     *
     * This method handle the deletion of the current paypal order,
     * and the automatic payment of the order with the selected payment source.
     *
     * ** Do not move to BasePPDriver **
     * @param  mixed $request
     * @param  array $response
     */
    public function processTokenPayment($request, array $response)
    {

        /** @var \App\Models\ClientGatewayToken $cgt */
        $cgt = ClientGatewayToken::where('client_id', $this->client->id)
                                 ->where('token', $request['token'])
                                 ->firstOrFail();

        $orderId = $response['orderID'];
        $r = $this->gatewayRequest("/v1/checkout/orders/{$orderId}/", 'delete', ['body' => '']);

        nlog($r->body());

        $data["payer"] = [
                    "name" => [
                        "given_name" => $this->client->present()->first_name(),
                        "surname" => $this->client->present()->last_name()
                    ],
                    "email_address" => $this->client->present()->email(),
                ];
        $data['amount_with_fee'] = $this->payment_hash->data->amount_with_fee;
        $data["payment_source"] = [
            "card" => [
                "vault_id" => $cgt->token,
                "stored_credential" => [
                    "payment_initiator" => "MERCHANT",
                    "payment_type" => "UNSCHEDULED",
                    "usage" => "SUBSEQUENT",
                ],
            ],
        ];

        $orderId = $this->createOrder($data);

        try {

            $r = $this->gatewayRequest("/v2/checkout/orders/{$orderId}", 'get', ['body' => '']);

            if($r->status() == 422) {
                //handle conditions where the client may need to try again.
                nlog("hit 422");
                $r = $this->handleDuplicateInvoiceId($orderId);


            }

        } catch(\Exception $e) {

            //Rescue for duplicate invoice_id
            if(stripos($e->getMessage(), 'DUPLICATE_INVOICE_ID') !== false) {


                nlog("hit 422 in exception");

                $r = $this->handleDuplicateInvoiceId($orderId);

            }

        }

        $response = $r->json();

        if(isset($response['purchase_units'][0]['payments']['captures'][0]['status']) && $response['purchase_units'][0]['payments']['captures'][0]['status'] == 'COMPLETED')
        {
            $data = [
                'payment_type' => $this->getPaymentMethod($request->gateway_type_id),
                'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => $this->gateway_type_id,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);

        }

        return response()->json(['message' => 'Error processing token payment'], 400);

    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $data = [];
        $this->payment_hash = $payment_hash;

        $data['payer'] = [
                    "name" => [
                        "given_name" => $this->client->present()->first_name(),
                        "surname" => $this->client->present()->last_name()
                    ],
                    "email_address" => $this->client->present()->email(),
                ];

        $data['amount_with_fee'] = $this->payment_hash->data->amount_with_fee;
        $data["payment_source"] = [
            "card" => [
                "vault_id" => $cgt->token,
                "stored_credential" => [
                    "payment_initiator" => "MERCHANT",
                    "payment_type" => "UNSCHEDULED",
                    "usage" => "SUBSEQUENT",
                ],
            ],
        ];

        $orderId = $this->createOrder($data);

        $r = false;

        try {

            $r = $this->gatewayRequest("/v2/checkout/orders/{$orderId}", 'get', ['body' => '']);

            if($r->status() == 422) {
                //handle conditions where the client may need to try again.

                $r = $this->handleDuplicateInvoiceId($orderId);

            }

        } catch(\Exception $e) {

            //Rescue for duplicate invoice_id
            if(stripos($e->getMessage(), 'DUPLICATE_INVOICE_ID') !== false) {

                $r = $this->handleDuplicateInvoiceId($orderId);

            }

        }

        $response = $r->json();

        if(isset($response['purchase_units'][0]['payments']['captures'][0]['status']) && $response['purchase_units'][0]['payments']['captures'][0]['status'] == 'COMPLETED')
        {

            $data = [
                'payment_type' => $this->getPaymentMethod((string)$cgt->gateway_type_id),
                'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => $this->gateway_type_id,
            ];

            $payment = $this->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL_PPCP,
                $this->client,
                $this->client->company,
            );

        }

        $this->processInternallyFailedPayment($this, new \Exception('Auto billing failed.', 400));

        SystemLogger::dispatch($response, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_PAYPAL, $this->client, $this->client->company);

    }

    public function processPaymentViewData(array $data): array
    {
        $this->init();

        $data['gateway'] = $this;

        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, ['amount' => $data['total']['amount_with_fee']]);
        $this->payment_hash->save();

        $data['client_id'] = $this->company_gateway->getConfigField('clientId');
        $data['token'] = $this->getClientToken();
        $data['order_id'] = $this->createOrder($data);
        $data['funding_source'] = $this->paypal_payment_method;
        $data['gateway_type_id'] = $this->gateway_type_id;
        $data['currency'] = $this->client->currency()->code;
        $data['guid'] = $this->risk_guid;
        $data['identifier'] = "s:INN_ACDC_CHCK";
        $data['pp_client_reference'] = $this->getClientHash();
        $data['invoice_hash'] = $this->payment_hash->fee_invoice->hashed_id;
        
        return $data;
    }

    public function livewirePaymentView(array $data): string 
    {
        if ($this->gateway_type_id == 29) {
            return 'gateways.paypal.ppcp.card_livewire';
        }

        return 'gateways.paypal.pay_livewire';
    }
}

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

namespace App\PaymentDrivers\PayPal;

use Str;
use Carbon\Carbon;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\BaseDriver;
use Illuminate\Support\Facades\Http;
use App\PaymentDrivers\PayPal\PayPalWebhook;

class PayPalBasePaymentDriver extends BaseDriver
{
    use MakesHash;

    public string $risk_guid;

    public $token_billing = true;

    public $can_authorise_credit_card = false;

    public float $fee = 0;

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYPAL;

    public string $api_endpoint_url = '';

    public string $paypal_payment_method = '';

    public ?int $gateway_type_id = null;

    public mixed $access_token = null;

    public ?Carbon $token_expiry = null;

    public array $funding_options = [
        3 => 'paypal',
        1 => 'card',
        25 => 'venmo',
        29 => 'paypal_advanced_cards',
        // 9 => 'sepa',
        // 12 => 'bancontact',
        // 17 => 'eps',
        // 15 => 'giropay',
        // 13 => 'ideal',
        // 26 => 'mercadopago',
        // 27 => 'mybank',
        28 => 'paylater',
        // 16 => 'p24',
        // 7 => 'sofort'
    ];

    public function gatewayTypes()
    {

        $funding_options =

        collect($this->company_gateway->fees_and_limits)
            ->filter(function ($fee) {
                return $fee->is_enabled;
            })->map(function ($fee, $key) {
                return (int)$key;
            })->toArray();

        /** Parse funding options and remove card option if advanced cards is enabled. */
        if (in_array(1, $funding_options) && in_array(29, $funding_options)) {

            if (($key = array_search(1, $funding_options)) !== false) {
                unset($funding_options[$key]);
            }

        }

        return $funding_options;

    }

    public function getPaymentMethod($gateway_type_id): int
    {
        $method = PaymentType::PAYPAL;

        match($gateway_type_id) {
            "1" => $method = PaymentType::CREDIT_CARD_OTHER,
            "3" => $method = PaymentType::PAYPAL,
            "25" => $method = PaymentType::VENMO,
            "28" => $method = PaymentType::PAY_LATER,
            "29" => $method = PaymentType::CREDIT_CARD_OTHER,
            default => $method = PaymentType::PAYPAL,
        };

        return $method;
    }

    public function init()
    {
        $this->risk_guid = Str::random(32);

        $this->api_endpoint_url = $this->company_gateway->getConfigField('testMode') ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';

        if (\App\Utils\Ninja::isHosted() && $this->company_gateway->gateway_key != '80af24a6a691230bbec33e930ab40665') {
            $secret = config('ninja.paypal.secret');
            $client_id = config('ninja.paypal.client_id');
        } else {

            $secret = $this->company_gateway->getConfigField('secret');
            $client_id = $this->company_gateway->getConfigField('clientId');
        }

        if ($this->access_token && $this->token_expiry && $this->token_expiry->isFuture()) {
            return $this;
        }

        $response = Http::withBasicAuth($client_id, $secret)
                                    ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                                    ->withQueryParameters(['grant_type' => 'client_credentials'])
                                    ->post("{$this->api_endpoint_url}/v1/oauth2/token");

        if ($response->successful()) {
            $this->access_token = $response->json()['access_token'];
            $this->token_expiry = now()->addSeconds($response->json()['expires_in'] - 60);
        } else {
            throw new PaymentFailed('Unable to gain access token from Paypal. Check your configuration', 401);
        }

        return $this;

    }


    /**
     * getFundingOptions
     *
     * Hosted fields requires this.
     *
     * @return string
     */
    public function getFundingOptions(): string
    {

        $enums = [
            1 => 'card',
            3 => 'paypal',
            25 => 'venmo',
            28 => 'paylater',
            // 9 => 'sepa',
            // 12 => 'bancontact',
            // 17 => 'eps',
            // 15 => 'giropay',
            // 13 => 'ideal',
            // 26 => 'mercadopago',
            // 27 => 'mybank',
            // 28 => 'paylater',
            // 16 => 'p24',
            // 7 => 'sofort'
        ];

        $funding_options = '';

        foreach ($this->company_gateway->fees_and_limits as $key => $value) {

            if ($value->is_enabled) {

                $funding_options .= $enums[$key].',';

            }

        }

        return rtrim($funding_options, ',');

    }

    //@todo turn this back on when PayPal.....
    public function getClientHash()
    {
        return '';

        /** @var ?\App\Models\ClientGatewayToken $cgt */
        $cgt = ClientGatewayToken::where('company_gateway_id', $this->company_gateway->id)
                                 ->where('client_id', $this->client->id)
                                 ->first();
        if (!$cgt) {
            return '';
        }

        $client_reference = $cgt->gateway_customer_reference;

        $secret = $this->company_gateway->getConfigField('secret');
        $client_id = $this->company_gateway->getConfigField('clientId');

        $response = Http::withBasicAuth($client_id, $secret)
                                   ->withHeaders(['Content-Type' => 'application/x-www-form-urlencoded'])
                                   ->withQueryParameters(['grant_type' => 'client_credentials','response_type' => 'id_token', 'target_customer_id' => $client_reference])
                                   ->post("{$this->api_endpoint_url}/v1/oauth2/token");

        if ($response->successful()) {

            $data = $response->json();

            return $data['id_token'] ?? '';

        }

        return '';
    }

    public function handleDuplicateInvoiceId(string $orderID)
    {

        $_invoice = collect($this->payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));
        $new_invoice_number = $invoice->number."_".Str::random(5);

        $update_data =
                [[
                    "op" => "replace",
                    "path" => "/purchase_units/@reference_id=='default'/invoice_id",
                    "value" => $new_invoice_number,
                ]];

        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}", 'patch', $update_data);

        $r = $this->gatewayRequest("/v2/checkout/orders/{$orderID}/capture", 'post', ['body' => '']);

        return $r;
    }

    public function getShippingAddress(): ?array
    {
        return $this->company_gateway->require_shipping_address ?
        [
            "address" =>
                [
                    "address_line_1" => strlen($this->client->shipping_address1 ?? '') > 1 ? $this->client->shipping_address1 : $this->client->address1,
                    "address_line_2" => $this->client->shipping_address2,
                    "admin_area_2" => strlen($this->client->shipping_city ?? '') > 1 ? $this->client->shipping_city : $this->client->city,
                    "admin_area_1" => strlen($this->client->shipping_state ?? '') > 1 ? $this->client->shipping_state : $this->client->state,
                    "postal_code" => strlen($this->client->shipping_postal_code ?? '') > 1 ? $this->client->shipping_postal_code : $this->client->postal_code,
                    "country_code" => $this->client->present()->shipping_country_code(),
                ],
        ]

        : [
            "name" => [
                "full_name" => $this->client->present()->name()
            ]
        ];

    }

    public function getBillingAddress(): array
    {
        return
            [
                "address_line_1" => $this->client->address1,
                "address_line_2" => $this->client->address2,
                "admin_area_2" => $this->client->city,
                "admin_area_1" => $this->client->state,
                "postal_code" => $this->client->postal_code,
                "country_code" => $this->client->country->iso_3166_2,
            ];
    }

    public function getPaymentSource(): array
    {
        //@todo - roll back here for advanced payments vs hosted card fields.
        if ($this->gateway_type_id == GatewayType::PAYPAL_ADVANCED_CARDS) {

            return [
                "card" => [
                    "attributes" => [
                        "verification" => [
                            "method" => "SCA_WHEN_REQUIRED", //SCA_ALWAYS
                            // "method" => "SCA_ALWAYS", //SCA_ALWAYS
                        ],
                        "vault" => [
                            "store_in_vault" => "ON_SUCCESS", //must listen to this webhook - VAULT.PAYMENT-TOKEN.CREATED webhook.
                        ],
                    ],
                    "experience_context" => [
                        "shipping_preference" => "SET_PROVIDED_ADDRESS"
                    ],
                    "stored_credential" => [
                        // "payment_initiator" => "MERCHANT", //"CUSTOMER" who initiated the transaction?
                        "payment_initiator" => "CUSTOMER", //"" who initiated the transaction?
                        "payment_type" => "UNSCHEDULED", //UNSCHEDULED
                        "usage" => "DERIVED",
                    ],
                ],
            ];

        }

        $order = [
            "paypal" => [
                "name" => [
                    "given_name" => $this->client->present()->first_name(),
                    "surname" => $this->client->present()->last_name(),
                ],
                "email_address" => $this->client->present()->email(),
                "experience_context" => [
                    "user_action" => "PAY_NOW"
                ],
            ],
        ];

        /** If we have a complete address, add it to the order, otherwise leave it blank! */
        if (
            strlen($this->client->shipping_address1 ?? '') > 2 &&
            strlen($this->client->shipping_city ?? '') > 2 &&
            strlen($this->client->shipping_state ?? '') >= 2 &&
            strlen($this->client->shipping_postal_code ?? '') > 2 &&
            strlen($this->client->shipping_country->iso_3166_2 ?? '') >= 2
        ) {
            $order['paypal']['address'] = [
                    "address_line_1" => $this->client->shipping_address1,
                    "address_line_2" => $this->client->shipping_address2,
                    "admin_area_2" => $this->client->shipping_city,
                    "admin_area_1" => $this->client->shipping_state,
                    "postal_code" => $this->client->shipping_postal_code,
                    "country_code" => $this->client->present()->shipping_country_code(),
            ];
        } elseif (
            strlen($this->client->address1 ?? '') > 2 &&
            strlen($this->client->city ?? '') > 2 &&
            strlen($this->client->state ?? '') >= 2 &&
            strlen($this->client->postal_code ?? '') > 2 &&
            strlen($this->client->country->iso_3166_2 ?? '') >= 2
        ) {
            $order['paypal']['address'] = [
                    "address_line_1" => $this->client->address1,
                    "address_line_2" => $this->client->address2,
                    "admin_area_2" => $this->client->city,
                    "admin_area_1" => $this->client->state,
                    "postal_code" => $this->client->postal_code,
                    "country_code" => $this->client->country->iso_3166_2,
            ];
        }

        return $order;

    }

    /**
     * Payment method setter
     *
     * @param  mixed $payment_method_id
     * @return self
     */
    public function setPaymentMethod($payment_method_id): self
    {
        if (!$payment_method_id) {
            return $this;
        }

        $this->gateway_type_id = $payment_method_id;

        $this->paypal_payment_method = $this->funding_options[$payment_method_id];

        return $this;
    }

    public function authorizeView($payment_method)
    {
        // PayPal doesn't support direct authorization.

        return $this;
    }

    public function authorizeResponse($request)
    {
        // PayPal doesn't support direct authorization.

        return $this;
    }

    /**
     * Generates the gateway request
     *
     * @param  string $uri
     * @param  string $verb
     * @param  array $data
     * @param  ?array $headers
     * @return \Illuminate\Http\Client\Response
     */
    public function gatewayRequest(string $uri, string $verb, array $data, ?array $headers = [])
    {
        $this->init();

        $r = Http::withToken($this->access_token)
                ->withHeaders($this->getHeaders($headers))
                ->{$verb}("{$this->api_endpoint_url}{$uri}", $data);

        if ($r->status() <= 422) {
            // if($r->successful()) {
            return $r;
        }

        nlog($r->body());
        nlog($r->json());
        nlog($r);

        SystemLogger::dispatch(
            ['response' => $r->body()],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYPAL,
            $this->client,
            $this->client->company ?? $this->company_gateway->company,
        );


        return response()->json(['message' => "Gateway failure - {$r->body()}"], 401);

        // throw new PaymentFailed("Gateway failure - {$r->body()}", 401);

    }

    public function handleProcessingFailure(array $response)
    {

        SystemLogger::dispatch(
            ['response' => $response],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYPAL,
            $this->client,
            $this->client->company ?? $this->company_gateway->company,
        );

        switch ($response['name']) {
            case 'NOT_AUTHORIZED':
                throw new PaymentFailed("There was a permissions issue processing this payment, please contact the merchant. ", 401);


            default:
                throw new PaymentFailed("Unknown error occurred processing payment. Please contact merchant.", 500);

        }
    }

    public function handleRetry($response, $request)
    {
        return response()->json($response->json());
    }

    /**
     * Generates the request headers
     *
     * @param  array $headers
     * @return array
     */
    public function getHeaders(array $headers = []): array
    {

        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
            'Accept-Language' => 'en_US',
            'PayPal-Partner-Attribution-Id' => 'invoiceninja_SP_PPCP',
            'PayPal-Request-Id' => Str::uuid()->toString(),
            'PAYPAL-CLIENT-METADATA-ID' => $this->risk_guid,
        ], $headers);
    }

    /**
     * Generates a client token for the payment form.
     *
     * @return string
     */
    public function getClientToken(): string
    {

        $r = $this->gatewayRequest('/v1/identity/generate-token', 'post', ['body' => '']);

        if ($r->successful()) {
            return $r->json()['client_token'];
        }

        throw new PaymentFailed('Unable to gain client token from Paypal. Check your configuration', 401);

    }

    public function auth(): bool
    {

        try {
            $this->init()->getClientToken();
            return true;
        } catch (\Exception $e) {

        }

        return false;
    }

    public function importCustomers()
    {
        return true;
    }

    public function processWebhookRequest(Request $request)
    {

        $this->init();

        PayPalWebhook::dispatch($request->all(), $request->headers->all(), $this->access_token);

    }

    public function createNinjaPayment($request, $response)
    {

        if (isset($response['purchase_units'][0]['payments']['captures'][0]['status']) && in_array($response['purchase_units'][0]['payments']['captures'][0]['status'], ['COMPLETED', 'PENDING'])) {

            $payment_status = $response['purchase_units'][0]['payments']['captures'][0]['status'] == 'COMPLETED' ? \App\Models\Payment::STATUS_COMPLETED : \App\Models\Payment::STATUS_PENDING;

            $data = [
                'payment_type' => $this->getPaymentMethod($request->gateway_type_id),
                'amount' => $response['purchase_units'][0]['payments']['captures'][0]['amount']['value'],
                'transaction_reference' => $response['purchase_units'][0]['payments']['captures'][0]['id'],
                'gateway_type_id' => GatewayType::PAYPAL,
            ];

            $payment = $this->createPayment($data, $payment_status);

            if ($request->has('store_card') && $request->input('store_card') === true) {
                $payment_source = $response->json()['payment_source'] ?? false;

                if (isset($payment_source['card']) && ($payment_source['card']['attributes']['vault']['status'] ?? false) && $payment_source['card']['attributes']['vault']['status'] == 'VAULTED') {

                    $last4 = $payment_source['card']['last_digits'];
                    $expiry = $payment_source['card']['expiry']; //'2025-01'
                    $expiry_meta = explode('-', $expiry);
                    $brand = $payment_source['card']['brand'];

                    $payment_meta = new \stdClass();
                    $payment_meta->exp_month = $expiry_meta[1] ?? '';
                    $payment_meta->exp_year = $expiry_meta[0] ?? $expiry;
                    $payment_meta->brand = $brand;
                    $payment_meta->last4 = $last4;
                    $payment_meta->type = GatewayType::CREDIT_CARD;

                    $token = $payment_source['card']['attributes']['vault']['id']; // 09f28652d01257021
                    $gateway_customer_reference = $payment_source['card']['attributes']['vault']['customer']['id']; //rbTHnLsZqE;

                    $data['token'] = $token;
                    $data['payment_method_id'] = GatewayType::PAYPAL_ADVANCED_CARDS;
                    $data['payment_meta'] = $payment_meta;

                    $additional['gateway_customer_reference'] = $gateway_customer_reference;

                    $this->storeGatewayToken($data, $additional);

                }
            }

            SystemLogger::dispatch(
                ['response' => $response->json(), 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_PAYPAL,
                $this->client,
                $this->client->company,
            );

            return response()->json(['redirect' => route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)], false)]);
        }

        SystemLogger::dispatch($response, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_PAYPAL, $this->client, $this->client->company);

        $error = isset($response['purchase_units'][0]['payments']['captures'][0]['status_details'][0]) ? $response['purchase_units'][0]['payments']['captures'][0]['status_details'][0] : $response['purchase_units'][0]['payments']['captures'][0]['status'];

        return response()->json(['message' => $error], 400);

    }

}

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
use App\Models\Payment;
use App\Utils\CurlUtils;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Factory\ClientFactory;
use App\Exceptions\SystemError;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Models\ClientGatewayToken;
use App\Repositories\ClientRepository;
use App\PaymentDrivers\PayTrace\CreditCard;
use App\Repositories\ClientContactRepository;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\Models\ClientContact;
use App\PaymentDrivers\Factory\PaytraceCustomerFactory;

class PaytracePaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public $gateway;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_PAYTRACE; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public function init()
    {
        return $this; /* This is where you boot the gateway with your auth credentials*/
    }

    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    /* Sets the payment method initialized */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $data = [
            'amount' => $amount,
            'transaction_id' => $payment->transaction_reference,
            'integrator_id' => $this->company_gateway->getConfigField('integratorId'),
        ];

        $response = $this->gatewayRequest('/v1/transactions/refund/for_transaction', $data);

        if ($response && $response->success) {
            SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_PAYTRACE, $this->client, $this->client->company);

            return [
                'transaction_reference' => $response->transaction_id,
                'transaction_response' => json_encode($response),
                'success' => true,
                'description' => $response->status_message,
                'code' => $response->response_code,
            ];
        }

        SystemLogger::dispatch(['server_response' => $response, 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_PAYTRACE, $this->client, $this->client->company);

        return [
            'transaction_reference' => null,
            'transaction_response' => json_encode($response),
            'success' => false,
            'description' => $response->status_message,
            'code' => 422,
        ];
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $_invoice = collect($payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        if ($invoice) {
            $invoice_id =  ctrans('texts.invoice_number').'# '.$invoice->number;
        }

        $invoice_id = ctrans('texts.invoice_number').'# '.substr($payment_hash->hash, 0, 6);

        $data = [
            'customer_id' => $cgt->token,
            'integrator_id' =>  $this->company_gateway->getConfigField('integratorId'),
            'amount' => $amount,
            'invoice_id' => $invoice_id,
        ];

        $response = $this->gatewayRequest('/v1/transactions/sale/by_customer', $data);

        if ($response && $response->success) {
            $data = [
                'gateway_type_id' => $cgt->gateway_type_id,
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'transaction_reference' => $response->transaction_id,
                'amount' => $amount,
            ];

            $payment = $this->createPayment($data);
            $payment->meta = $cgt->meta;
            $payment->save();

            $payment_hash->payment_id = $payment->id;
            $payment_hash->save();

            return $payment;
        }

        $error = $response->status_message;

        if (property_exists($response, 'approval_message') && $response->approval_message) {
            $error .= " - {$response->approval_message}";
        }

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => 500,
        ];

        $this->processUnsuccessfulTransaction($data, false);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request, Payment $payment = null)
    {
    }

    /*Helpers*/
    private function generateAuthHeaders()
    {
        $api_endpoint = $this->company_gateway->getConfigField('testMode') ? 'https://api.sandbox.paytrace.com' : 'https://api.paytrace.com';

        $url = "{$api_endpoint}/oauth/token";

        $data = [
            'grant_type' => 'password',
            'username' => $this->company_gateway->getConfigField('username'),
            'password' => $this->company_gateway->getConfigField('password'),
        ];

        $response = CurlUtils::post($url, $data, $headers = false);

        $auth_data = json_decode($response);

        if (!isset($auth_data) || ! property_exists($auth_data, 'access_token')) {
            throw new SystemError('Error authenticating with PayTrace');
        }

        $headers = [];
        $headers[] = 'Content-type: application/json';
        $headers[] = 'Authorization: Bearer '.$auth_data->access_token;

        return $headers;
    }

    public function getAuthToken()
    {

        $api_endpoint = $this->company_gateway->getConfigField('testMode') ? 'https://api.sandbox.paytrace.com' : 'https://api.paytrace.com';

        $headers = $this->generateAuthHeaders();

        $response = CurlUtils::post("{$api_endpoint}/v1/payment_fields/token/create", [], $headers);

        $response = json_decode($response);

        if ($response) {
            return $response->clientKey;
        }

        return false;
    }

    public function gatewayRequest($uri, $data, $headers = false)
    {

        $api_endpoint = $this->company_gateway->getConfigField('testMode') ? 'https://api.sandbox.paytrace.com' : 'https://api.paytrace.com';

        $base_url = "{$api_endpoint}{$uri}";

        $headers = $this->generateAuthHeaders();

        $response = CurlUtils::post($base_url, json_encode($data), $headers);

        $response = json_decode($response);

        if ($response) {
            return $response;
        }

        return false;
    }

    public function getClientRequiredFields(): array
    {

        $fields = [];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];


        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value1) {
            $fields[] = ['name' => 'client_custom_value1', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client1'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value2) {
            $fields[] = ['name' => 'client_custom_value2', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client2'), 'type' => 'text', 'validation' => 'required'];
        }


        if ($this->company_gateway->require_custom_value3) {
            $fields[] = ['name' => 'client_custom_value3', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client3'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value4) {
            $fields[] = ['name' => 'client_custom_value4', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client4'), 'type' => 'text', 'validation' => 'required'];
        }




        return $fields;
    }

    public function auth(): bool
    {
        try {
            $this->init()->generateAuthHeaders() && strlen($this->company_gateway->getConfigField('integratorId')) > 2;
            return true;
        } catch (\Exception $e) {

        }

        return false;

    }

    public function importCustomers()
    {

        $data = [
            'integrator_id' =>  $this->company_gateway->getConfigField('integratorId'),
        ];

        $response = $this->gatewayRequest('/v1/customer/export', $data);

        nlog($response);

        if ($response && $response->success) {

            $client_repo = new ClientRepository(new ClientContactRepository());
            $factory = new PaytraceCustomerFactory();

            foreach ($response->customers as $customer) {
                $data = $factory->convertToNinja($customer, $this->company_gateway->company);

                $client = false;

                if (str_contains($data['contacts'][0]['email'], "@")) {
                    $client = ClientContact::query()
                                    ->where('company_id', $this->company_gateway->company_id)
                                    ->where('email', $data['contacts'][0]['email'])
                                    ->first()->client ?? false;
                }

                if (!$client) {
                    $client = $client_repo->save($data, ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id));
                }

                $this->client = $client;

                if (ClientGatewayToken::query()->where('client_id', $client->id)->where('token', $data['card']['token'])->exists()) {
                    continue;
                }

                $cgt = [];
                $cgt['token'] = $data['card']['token'];
                $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

                $payment_meta = new \stdClass();
                $payment_meta->exp_month = $data['card']['expiry_month'];
                $payment_meta->exp_year = $data['card']['expiry_year'];
                $payment_meta->brand = 'CC';
                $payment_meta->last4 = $data['card']['last4'];
                $payment_meta->type = GatewayType::CREDIT_CARD;

                $cgt['payment_meta'] = $payment_meta;

                $token = $this->storeGatewayToken($cgt, []);

            }
        }

    }
}

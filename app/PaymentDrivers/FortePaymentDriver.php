<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://opensource.org/licenses/AAL
 */

namespace App\PaymentDrivers;

use App\Exceptions\PaymentFailed;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\ClientContact;
use App\Factory\ClientFactory;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Forte\ACH;
use Illuminate\Support\Facades\Http;
use App\Repositories\ClientRepository;
use App\PaymentDrivers\Forte\CreditCard;
use App\Repositories\ClientContactRepository;
use App\PaymentDrivers\Factory\ForteCustomerFactory;

class FortePaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
    ];

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_FORTE; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->company_gateway->getConfigField('testMode') == false) {
            $forte_base_uri = "https://api.forte.net/v3/";
        }
        $forte_api_access_id = $this->company_gateway->getConfigField('apiAccessId');
        $forte_secure_key = $this->company_gateway->getConfigField('secureKey');
        $forte_auth_organization_id = $this->company_gateway->getConfigField('authOrganizationId');
        $forte_organization_id = $this->company_gateway->getConfigField('organizationId');
        $forte_location_id = $this->company_gateway->getConfigField('locationId');

        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $forte_base_uri.'organizations/'.$forte_organization_id.'/locations/'.$forte_location_id.'/transactions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{
                     "action":"reverse", 
                     "authorization_amount":'.$amount.',
                     "original_transaction_id":"'.$payment->transaction_reference.'",
                     "authorization_code": "9ZQ754"
              }',
                CURLOPT_HTTPHEADER => [
                  'Content-Type: application/json',
                  'X-Forte-Auth-Organization-Id: '.$forte_organization_id,
                  'Authorization: Basic '.base64_encode($forte_api_access_id.':'.$forte_secure_key)
                ],
              ]);

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            $response = json_decode($response);
        } catch (\Throwable $th) {
            $message = [
                'action' => 'error',
                'data' => $th,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_FORTE,
                $this->client,
                $this->client->company,
            );
        }

        $message = [
            'action' => 'refund',
            'server_message' => $response->response->response_desc,
            'server_response' => $response,
            'data' => $payment->paymentables,
        ];

        if ($httpcode > 299) {
            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_FORTE,
                $this->client,
                $this->client->company,
            );

            return [
                'transaction_reference' => $payment->transaction_reference,
                'transaction_response' => $response,
                'success' => false,
                'description' => $payment->paymentables,
                'code' => 422,
            ];
        }

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_FORTE,
            $this->client,
            $this->client->company,
        );

        return [
            'transaction_reference' => $payment->transaction_reference,
            'transaction_response' => $response,
            'success' => $response->response->response_code == 'A01' ? true : false,
            'description' => $payment->paymentables,
            'code' => $httpcode,
        ];
    }

    ////////////////////////////////////////////
    // DB
    ///////////////////////////////////////////
    public function auth(): bool
    {

        $forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->company_gateway->getConfigField('testMode') == false) {
            $forte_base_uri = "https://api.forte.net/v3/";
        }
        $forte_api_access_id = $this->company_gateway->getConfigField('apiAccessId');
        $forte_secure_key = $this->company_gateway->getConfigField('secureKey');
        $forte_auth_organization_id = $this->company_gateway->getConfigField('authOrganizationId');
        $forte_organization_id = $this->company_gateway->getConfigField('organizationId');
        $forte_location_id = $this->company_gateway->getConfigField('locationId');

        $response = Http::withBasicAuth($forte_api_access_id, $forte_secure_key)
                    ->withHeaders(['X-Forte-Auth-Organization-Id' => $forte_organization_id])
                    ->get("{$forte_base_uri}/organizations/{$forte_organization_id}/locations/{$forte_location_id}/customers/");

        return $response->successful();

    }

    public function baseUri(): string
    {

        $forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->company_gateway->getConfigField('testMode') == false) {
            $forte_base_uri = "https://api.forte.net/v3/";
        }

        return $forte_base_uri;
    }

    public function getOrganisationId(): string
    {
        return $this->company_gateway->getConfigField('organizationId');
    }

    public function getLocationId(): string
    {
        return $this->company_gateway->getConfigField('locationId');
    }

    public function stubRequest()
    {

        $forte_api_access_id = $this->company_gateway->getConfigField('apiAccessId');
        $forte_secure_key = $this->company_gateway->getConfigField('secureKey');
        $forte_auth_organization_id = $this->company_gateway->getConfigField('authOrganizationId');

        return Http::withBasicAuth($forte_api_access_id, $forte_secure_key)
                    ->withHeaders(['X-Forte-Auth-Organization-Id' => $this->getOrganisationId()]);
    }

    private function getClient(?string $email)
    {
        if (!$email) {
            return false;
        }

        return ClientContact::query()
                     ->where('company_id', $this->company_gateway->company_id)
                     ->where('email', $email)
                     ->first();
    }

    public function tokenBilling(\App\Models\ClientGatewayToken $cgt, \App\Models\PaymentHash $payment_hash)
    {

        $amount_with_fee = $payment_hash->data->amount_with_fee;
        $fee_total = $payment_hash->fee_total;

        $data =
        [
            "action" => "sale",
            "authorization_amount" => $amount_with_fee,
            "paymethod_token" => $cgt->token,
            "billing_address" => [
            "first_name" => $this->client->present()->first_name(),
            "last_name" => $this->client->present()->last_name()
            ],
        ];

        if ($fee_total > 0) {
            $data["service_fee_amount"] = $fee_total;
        }

        $response = $this->stubRequest()
        ->post("{$this->baseUri()}/organizations/{$this->getOrganisationId()}/locations/{$this->getLocationId()}/transactions", $data);

        $forte_response = $response->object();

        if ($response->successful()) {

            $data = [
                'payment_method' => $cgt->gateway_type_id,
                'payment_type' => $cgt->gateway_type_id == 2 ? \App\Models\PaymentType::ACH : \App\Models\PaymentType::CREDIT_CARD_OTHER,
                'amount' => $payment_hash->data->amount_with_fee,
                'transaction_reference' => $forte_response->transaction_id,
                'gateway_type_id' => $cgt->gateway_type_id,
            ];

            $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);

            $message = [
                'server_message' => $forte_response->response->response_desc,
                'server_response' => $response->json(),
                'data' => $data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_FORTE,
                $this->client,
                $this->client->company,
            );

            return $payment;
        }

        $message = [
            'server_message' => $forte_response->response->response_desc,
            'server_response' => $response->json(),
            'data' => $data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_FORTE,
            $this->client,
            $this->client->company,
        );

        throw new PaymentFailed($forte_response->response->response_desc, 500);

    }

    public function getLocation()
    {

        $response = $this->stubRequest()
                    ->withQueryParameters(['page_size' => 10000])
                    ->get("{$this->baseUri()}/organizations/{$this->getOrganisationId()}/locations/{$this->getLocationId()}");

        if ($response->successful()) {
            return $response->json();
        }

        // return $response->body();

        return false;
    }

    public function updateFees()
    {
        $response = $this->getLocation();

        if ($response && $response['services']) {
            $body = $response['services'];

            $fees_and_limits = $this->company_gateway->fees_and_limits;

            if ((isset($body['card']['service_fee_percentage']) && $body['card']['service_fee_percentage'] > 0) || (isset($body['card']['service_fee_additional_amount']) && $body['card']['service_fee_additional_amount'] > 0)) {

                $fees_and_limits->{1}->fee_amount = $body['card']['service_fee_additional_amount'];
                $fees_and_limits->{1}->fee_percent = $body['card']['service_fee_percentage'];
            }

            if ((isset($body['debit']['service_fee_percentage']) && $body['debit']['service_fee_percentage'] > 0) || (isset($body['card']['service_fee_additional_amount']) && $body['card']['service_fee_additional_amount'] > 0)) {

                $fees_and_limits->{2}->fee_amount = $body['debit']['service_fee_additional_amount'];
                $fees_and_limits->{2}->fee_percent = $body['debit']['service_fee_percentage'];
            }

            $this->company_gateway->fees_and_limits = $fees_and_limits;
            $this->company_gateway->save();

        }

        return false;

    }

    public function findOrCreateCustomer()
    {

        $client_gateway_token = \App\Models\ClientGatewayToken::query()
                                                    ->where('client_id', $this->client->id)
                                                    ->where('company_gateway_id', $this->company_gateway->id)
                                                    ->whereNotLike('token', 'ott_%')
                                                    ->first();

        if ($client_gateway_token) {
            return $client_gateway_token->gateway_customer_reference;
        } else {

            $factory = new ForteCustomerFactory();
            $data = $factory->convertToForte($this->client);

            $response = $this->stubRequest()
                ->post("{$this->baseUri()}/organizations/{$this->getOrganisationId()}/locations/{$this->getLocationId()}/customers/", $data);


            //create customer
            if ($response->successful()) {
                $customer = $response->object();
                nlog($customer);
                return $customer->customer_token;
            }

            nlog($response->body());

            throw new PaymentFailed("Unable to create customer in Forte", 400);

            //@todo add syslog here
        }

    }

    public function importCustomers()
    {

        $response = $this->stubRequest()
                    ->withQueryParameters(['page_size' => 10000])
                    ->get("{$this->baseUri()}/organizations/{$this->getOrganisationId()}/locations/{$this->getLocationId()}/customers");

        if ($response->successful()) {

            foreach ($response->json()['results'] as $customer) {

                $client_repo = new ClientRepository(new ClientContactRepository());
                $factory = new ForteCustomerFactory();

                $data = $factory->convertToNinja($customer, $this->company_gateway->company);

                if (strlen($data['email']) == 0 || $this->getClient($data['email'])) {
                    continue;
                }

                $client_repo->save($data, ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id));

                //persist any payment methods here!
            }
        }

    }

}

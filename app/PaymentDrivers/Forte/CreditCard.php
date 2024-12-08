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

namespace App\PaymentDrivers\Forte;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\FortePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Validator;

class CreditCard implements LivewireMethodInterface
{
    use MakesHash;

    public $forte;

    private $forte_base_uri = "";
    private $forte_api_access_id = "";
    private $forte_secure_key = "";
    private $forte_auth_organization_id = "";
    private $forte_organization_id = "";
    private $forte_location_id = "";

    public function __construct(FortePaymentDriver $forte)
    {
        $this->forte = $forte;

        $this->forte_base_uri = "https://sandbox.forte.net/api/v3/";
        if ($this->forte->company_gateway->getConfigField('testMode') == false) {
            $this->forte_base_uri = "https://api.forte.net/v3/";
        }
        $this->forte_api_access_id = $this->forte->company_gateway->getConfigField('apiAccessId');
        $this->forte_secure_key = $this->forte->company_gateway->getConfigField('secureKey');
        $this->forte_auth_organization_id = $this->forte->company_gateway->getConfigField('authOrganizationId');
        $this->forte_organization_id = $this->forte->company_gateway->getConfigField('organizationId');
        $this->forte_location_id = $this->forte->company_gateway->getConfigField('locationId');
    }

    public function authorizeView(array $data)
    {
        $data['gateway'] = $this->forte;
        return render('gateways.forte.credit_card.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $cst = $this->forte->findOrCreateCustomer();

        $data = [
            "label" => $request->card_holders_name." " .$request->card_type,
            "notes" => $request->card_holders_name." " .$request->card_type,
            "card" => [
                "one_time_token" => $request->one_time_token,
                "name_on_card" => $request->card_holders_name
                ],
        ];

        $response = $this->forte->stubRequest()
            ->post("{$this->forte->baseUri()}/organizations/{$this->forte->getOrganisationId()}/locations/{$this->forte->getLocationId()}/customers/{$cst}/paymethods", $data);

        if ($response->successful()) {

            $token = $response->object();

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) $request->expire_month;
            $payment_meta->exp_year = (string) $request->expire_year;
            $payment_meta->brand = (string) $request->card_brand;
            $payment_meta->last4 = (string) $request->last_4;
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $token->paymethod_token,
                'payment_method_id' => $request->payment_method_id,
            ];

            $this->forte->storeGatewayToken($data, ['gateway_customer_reference' => $cst]);

            return redirect()->route('client.payment_methods.index')->withSuccess('Payment Method added.');

        }

        $error = $response->object();
        $message = [
            'server_message' => $error->response->response_desc,
            'server_response' => $response->json(),
            'data' => $data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_FORTE,
            $this->forte->client,
            $this->forte->client->company,
        );

        throw new \App\Exceptions\PaymentFailed("Unable to store payment method: {$error->response->response_desc}", 400);

    }

    private function createPaymentToken($request)
    {
        $cst = $this->forte->findOrCreateCustomer();

        $data = [
            "label" => $this->forte->client->present()->name(),
            "notes" => $this->forte->client->present()->name(),
            "card" => [
                "one_time_token" => $request->payment_token,
                "name_on_card" => $this->forte->client->present()->first_name(). " ". $this->forte->client->present()->last_name()
                ],
        ];

        $response = $this->forte->stubRequest()
            ->post("{$this->forte->baseUri()}/organizations/{$this->forte->getOrganisationId()}/locations/{$this->forte->getLocationId()}/customers/{$cst}/paymethods", $data);

        if ($response->successful()) {

            $token = $response->object();

            $payment_meta = new \stdClass();
            $payment_meta->exp_month = (string) $request->expire_month;
            $payment_meta->exp_year = (string) $request->expire_year;
            $payment_meta->brand = (string) $request->card_brand;
            $payment_meta->last4 = (string) $request->last_4;
            $payment_meta->type = GatewayType::CREDIT_CARD;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $token->paymethod_token,
                'payment_method_id' => $request->payment_method_id,
            ];

            $this->forte->storeGatewayToken($data, ['gateway_customer_reference' => $cst]);
        }

    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.forte.credit_card.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {

        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();

        if (strlen($request->token ?? '') > 3) {


            $cgt = \App\Models\ClientGatewayToken::find($this->decodePrimaryKey($request->token));

            $payment = $this->forte->tokenBilling($cgt, $payment_hash);

            return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

        }

        $amount_with_fee = $payment_hash->data->total->amount_with_fee;
        $invoice_totals = $payment_hash->data->total->invoice_totals;
        $fee_total = null;

        $fees_and_limits = $this->forte->company_gateway->getFeesAndLimits(GatewayType::CREDIT_CARD);

        if (property_exists($fees_and_limits, 'fee_percent') && $fees_and_limits->fee_percent > 0) {
            $fee_total = 0;

            for ($i = ($invoice_totals * 100) ; $i < ($amount_with_fee * 100); $i++) {
                $calculated_fee = (3 * $i) / 100;
                $calculated_amount_with_fee = round(($i + $calculated_fee) / 100, 2);
                if ($calculated_amount_with_fee == $amount_with_fee) {
                    $fee_total = round($calculated_fee / 100, 2);
                    $amount_with_fee = $calculated_amount_with_fee;
                    break;
                }
            }
        }

        try {
            $curl = curl_init();

            curl_setopt_array($curl, [
                CURLOPT_URL => $this->forte_base_uri.'organizations/'.$this->forte_organization_id.'/locations/'.$this->forte_location_id.'/transactions',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS => '{
                     "action":"sale", 
                     "authorization_amount":'.$amount_with_fee.',
                     "service_fee_amount":'.$fee_total.',
                     "billing_address":{
                        "first_name":"'.$this->forte->client->present()->first_name().'",
                        "last_name":"'.$this->forte->client->present()->last_name().'"
                     },
                     "card":{
                        "one_time_token":"'.$request->payment_token.'"
                     }
              }',
                CURLOPT_HTTPHEADER => [
                  'Content-Type: application/json',
                  'X-Forte-Auth-Organization-Id: '.$this->forte_organization_id,
                  'Authorization: Basic '.base64_encode($this->forte_api_access_id.':'.$this->forte_secure_key)
                ],
              ]);

            $response = curl_exec($curl);
            $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            curl_close($curl);

            $response = json_decode($response);

        } catch (\Throwable $th) {
            throw $th;
        }

        $message = [
            'server_message' => $response->response->response_desc,
            'server_response' => $response,
            'data' => $payment_hash->data,
        ];

        if ($httpcode > 299) {
            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_FORTE,
                $this->forte->client,
                $this->forte->client->company,
            );
            $error = Validator::make([], []);
            $error->getMessageBag()->add('gateway_error', $response->response->response_desc);

            return redirect()->route('client.invoice.show', ['invoice' => $payment_hash->fee_invoice->hashed_id])->withErrors($error);

            // return redirect('client/invoices')->withErrors($error);
        }

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_FORTE,
            $this->forte->client,
            $this->forte->client->company,
        );

        $data = [
            'payment_method' => $request->payment_method_id,
            'payment_type' => PaymentType::parseCardType(strtolower($request->card_brand)) ?: PaymentType::CREDIT_CARD_OTHER,
            'amount' => $payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction_id,
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];
        $payment = $this->forte->createPayment($data, Payment::STATUS_COMPLETED);

        if ($request->store_card) {
            $this->createPaymentToken($request);
        }

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.forte.credit_card.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $this->forte->payment_hash->data = array_merge((array) $this->forte->payment_hash->data, $data);
        $this->forte->payment_hash->save();

        $data['gateway'] = $this->forte;

        return $data;
    }
}

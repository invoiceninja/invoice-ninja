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

use App\Http\Requests\Request;
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

class ACH implements LivewireMethodInterface
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

        return render('gateways.forte.ach.authorize', $data);
    }

    public function authorizeResponse(Request $request)
    {
        $payment_meta = new \stdClass();
        $payment_meta->brand = (string)ctrans('texts.ach');
        $payment_meta->last4 = (string) $request->last_4;
        $payment_meta->exp_year = '-';
        $payment_meta->type = GatewayType::BANK_TRANSFER;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $request->one_time_token,
            'payment_method_id' => $request->gateway_type_id,
        ];

        $this->forte->storeGatewayToken($data);

        return redirect()->route('client.payment_methods.index')->withSuccess('Payment Method added.');
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        return render('gateways.forte.ach.pay', $data);
    }

    public function paymentResponse($request)
    {
        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();

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
                "authorization_amount": '.$payment_hash->data->total->amount_with_fee.',
                "echeck":{
                    "sec_code":"PPD",
                },
                "billing_address":{
                    "first_name": "'.$this->forte->client->name.'",
                    "last_name": "'.$this->forte->client->name.'"
                },
                "echeck":{
                   "one_time_token":"'.$request->payment_token.'"
                }
            }',
            CURLOPT_HTTPHEADER => [
                'X-Forte-Auth-Organization-Id: '.$this->forte_organization_id,
                'Content-Type: application/json',
                'Authorization: Basic '.base64_encode($this->forte_api_access_id.':'.$this->forte_secure_key),
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

            // return response()->redirect('client/invoices')->withErrors($error);
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
            'payment_type' => PaymentType::ACH,
            'amount' => $payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction_id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->forte->createPayment($data, Payment::STATUS_COMPLETED);
        // return redirect('client/invoices')->withSuccess('Invoice paid.');

        return redirect()->route('client.payments.show', ['payment' => $payment->hashed_id]);

    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.forte.ach.pay_livewire';
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

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

use App\Models\Client;
use App\Models\Gateway;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use Illuminate\Support\Facades\Http;
use App\PaymentDrivers\Blockonomics\Blockonomics;
use App\Http\Requests\Payments\PaymentWebhookRequest;

class BlockonomicsPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false; //does this gateway support refunds?

    public $token_billing = false; //does this gateway support token billing?

    public $can_authorise_credit_card = false; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CRYPTO => Blockonomics::class, //maps GatewayType => Implementation class
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_BLOCKONOMICS; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public $BASE_URL = 'https://www.blockonomics.co';
    public $NEW_ADDRESS_URL = 'https://www.blockonomics.co/api/new_address';
    public $PRICE_URL = 'https://www.blockonomics.co/api/price';

    public function init()
    {
        return $this; /* This is where you boot the gateway with your auth credentials*/
    }

    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CRYPTO;

        return $types;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);
        return $this;
    }

    public function processPaymentView(array $data)
    {
        $this->init();

        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {

        $this->init();

        return $this->payment_method->paymentResponse($request);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {

        $company = $request->getCompany();

        $url_callback_secret = $request->secret;
        $db_callback_secret = $this->company_gateway->getConfigField('callbackSecret');

        if ($url_callback_secret != $db_callback_secret) {
            throw new PaymentFailed('Secret does not match');
        }

        $txid = $request->txid;
        $value = $request->value;
        $status = $request->status;
        $addr = $request->addr;

        $payment = Payment::query()
                            ->where('company_id', $company->id)
                            ->where('transaction_reference', $txid)
                            ->firstOrFail();

        if (!$payment) {
            return response()->json([], 200);
            // TODO: Implement logic to create new payment in case user sends payment to the address after closing the payment page
        }

        $statusId = Payment::STATUS_PENDING;

        switch ($status) {
            case 0:
                $statusId = Payment::STATUS_PENDING;
                break;
            case 1:
                $statusId = Payment::STATUS_PENDING;
                break;
            case 2:
                $statusId = Payment::STATUS_COMPLETED;
                break;
        }

        if ($payment->status_id == $statusId) {
            return response()->json([], 200);
        } else {
            $payment->status_id = $statusId;
            $payment->save();

            return response()->json([], 200);
        }
    }


    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->setPaymentMethod(GatewayType::CRYPTO);
        return $this->payment_method->refund($payment, $amount); //this is your custom implementation from here
    }

    public function auth(): bool
    {
        try {
        
            $api_key = $this->company_gateway->getConfigField('apiKey');
            $url = $this->NEW_ADDRESS_URL . '?reset=1';
            $response = Http::withToken($api_key)
                ->post($url, []);
            if($response->successful()) {
                return true;
            }
            return false;
        } catch (\Exception $e) {
            return false;
        }

    }
}

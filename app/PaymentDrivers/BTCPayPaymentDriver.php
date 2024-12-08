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
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Utils\Traits\MakesHash;
use BTCPayServer\Client\Webhook;
use App\Exceptions\PaymentFailed;
use App\PaymentDrivers\BTCPay\BTCPay;
use App\Jobs\Mail\PaymentFailedMailer;
use App\Http\Requests\Payments\PaymentWebhookRequest;

class BTCPayPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = true; //does this gateway support refunds?

    public $token_billing = false; //does this gateway support token billing?

    public $can_authorise_credit_card = false; //does this gateway support authorizations?

    public $gateway; //initialized gateway

    public $payment_method; //initialized payment method

    public static $methods = [
        GatewayType::CRYPTO => BTCPay::class, //maps GatewayType => Implementation class
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_BTC_PAY; //define a constant for your gateway ie TYPE_YOUR_CUSTOM_GATEWAY - set the const in the SystemLog model

    public $btcpay_url  = "";
    public $api_key  = "";
    public $store_id = "";
    public $webhook_secret = "";
    public $btcpay;


    public function init()
    {
        $this->btcpay_url = $this->company_gateway->getConfigField('btcpayUrl');
        $this->api_key = $this->company_gateway->getConfigField('apiKey');
        $this->store_id = $this->company_gateway->getConfigField('storeId');
        $this->webhook_secret = $this->company_gateway->getConfigField('webhookSecret');
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
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function processWebhookRequest()
    {


        $webhook_payload = file_get_contents('php://input');

        /** @var \stdClass $btcpayRep */
        $btcpayRep = json_decode($webhook_payload);
        if ($btcpayRep == null) {
            throw new PaymentFailed('Empty data');
        }

        if (empty($btcpayRep->invoiceId)) {
            throw new PaymentFailed(
                'Invalid BTCPayServer payment notification- did not receive invoice ID.'
            );
        }

        if (!isset($btcpayRep->metadata->InvoiceNinjaPaymentHash)) {

            throw new PaymentFailed(
                'Invalid BTCPayServer payment notification- did not receive Payment Hashed ID.'
            );

        }

        if (
            str_starts_with($btcpayRep->invoiceId, "__test__")
            || $btcpayRep->type == "InvoiceProcessing"
            || $btcpayRep->type == "InvoiceCreated"
        ) {
            return;
        }

        $sig = '';
        $headers = getallheaders();
        foreach ($headers as $key => $value) {
            if (strtolower($key) === 'btcpay-sig') {
                $sig = $value;
            }
        }


        $webhookClient = new Webhook($this->btcpay_url, $this->api_key);

        if (!$webhookClient->isIncomingWebhookRequestValid($webhook_payload, $sig, $this->webhook_secret)) {
            throw new \RuntimeException(
                'Invalid BTCPayServer payment notification message received - signature did not match.'
            );
        }


        sleep(1);

        $this->init();

        $this->setPaymentMethod(GatewayType::CRYPTO);
        $this->payment_hash = PaymentHash::where('hash', $btcpayRep->metadata->InvoiceNinjaPaymentHash)->firstOrFail();

        $StatusId = Payment::STATUS_PENDING;

        $payment = $this->payment_hash->payment ?? false;
        $_invoice = $this->payment_hash->fee_invoice;
        $this->client = $_invoice->client;


        switch ($btcpayRep->type) {
            case "InvoiceExpired":

                $payment = Payment::query()->withTrashed()->where('client_id', $_invoice->client_id)->where('id', $this->payment_hash->payment_id)->first();

                if ($payment && $payment->status_id == Payment::STATUS_PENDING) {
                    $payment->service()->deletePayment();
                    $this->failedPaymentNotification($payment);

                    $StatusId = Payment::STATUS_CANCELLED;

                    $payment->status_id = $StatusId;
                    $payment->save();

                }

                break;
            case "InvoiceInvalid":

                $payment = Payment::query()->withTrashed()->where('client_id', $_invoice->client_id)->where('id', $this->payment_hash->payment_id)->first();

                if ($payment && $payment->status_id == Payment::STATUS_PENDING) {
                    $payment->service()->deletePayment();
                    $this->failedPaymentNotification($payment);
                    $StatusId = Payment::STATUS_FAILED;

                    $payment->status_id = $StatusId;
                    $payment->save();

                }

                break;
            case "InvoiceSettled":

                $payment = Payment::query()->withTrashed()->where('client_id', $_invoice->client_id)->where('id', $this->payment_hash->payment_id)->first();
                $StatusId = Payment::STATUS_COMPLETED;

                if (!$payment) {


                    $dataPayment = [
                        'payment_method' => $this->payment_method,
                        'payment_type' => PaymentType::CRYPTO,
                        'amount' => $_invoice->amount,
                        'gateway_type_id' => GatewayType::CRYPTO,
                        'transaction_reference' => $btcpayRep->invoiceId
                    ];

                    $payment = $this->createPayment($dataPayment, $StatusId);

                } else {
                    $payment->save();
                }

                break;
        }

    }

    private function failedPaymentNotification(Payment $payment): void
    {

        $error = ctrans('texts.client_payment_failure_body', [
            'invoice' => implode(',', $payment->invoices->pluck('number')->toArray()),
            'amount' => array_sum(array_column($this->payment_hash->invoices(), 'amount')) + $this->payment_hash->fee_total, ]);

        PaymentFailedMailer::dispatch(
            $this->payment_hash,
            $payment->client->company,
            $payment->client,
            $error
        );

    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->setPaymentMethod(GatewayType::CRYPTO);
        return $this->payment_method->refund($payment, $amount); //this is your custom implementation from here
    }
}

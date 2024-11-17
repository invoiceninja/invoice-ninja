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

use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\Utils\HtmlEngine;
use App\Utils\Traits\MakesHash;

/**
 * Class CustomPaymentDriver.
 */
class CustomPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $token_billing = false;

    public $can_authorise_credit_card = false;

    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [
            GatewayType::CUSTOM,
        ];

        return $types;
    }

    public function init()
    {
        return $this;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $this->payment_method = $payment_method_id;

        return $this;
    }

    public function paymentData(array $data): array
    {

        $variables = [];

        if (count($this->payment_hash->invoices()) > 0) {
            $invoice_id = $this->decodePrimaryKey($this->payment_hash->invoices()[0]->invoice_id);
            $invoice = Invoice::withTrashed()->find($invoice_id);

            $variables = (new HtmlEngine($invoice->invitations->first()))->generateLabelsAndValues();
        }

        $variables['values']['$invoices'] = collect($this->payment_hash->invoices())->pluck('invoice_number')->implode(',');
        $variables['labels']['$invoices_label'] = ctrans('texts.invoice_number_short');

        $data['title'] = $this->company_gateway->getConfigField('name');
        $data['instructions'] = strtr($this->company_gateway->getConfigField('text'), $variables['values']);

        $this->payment_hash->data = array_merge((array) $this->payment_hash->data, $data);
        $this->payment_hash->save();

        $data['gateway'] = $this;
        $data['payment_hash'] = $this->payment_hash->hash;

        return $data;

    }

    /**
     * View for displaying custom content of the driver.
     *
     * @param array $data
     * @return mixed
     */
    public function processPaymentView($data)
    {
        $data = $this->paymentData($data);

        return render('gateways.custom.payment', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.custom.pay_livewire';
    }

    public function processPaymentViewData(array $data): array
    {
        return $this->paymentData($data); 
    }

    /**
     * Processing method for payment. Should never be reached with this driver.
     *
     * @return mixed
     */
    public function processPaymentResponse($request)
    {
        if ($request->has('gateway_response')) {
            $this->client = auth()->guard('contact')->user()->client;

            $state = [
                'server_response' => json_decode($request->gateway_response),
                'payment_hash' => $request->payment_hash,
            ];

            $payment_hash = PaymentHash::where('hash', $request->payment_hash)->first();

            if ($payment_hash) {
                $this->payment_hash = $payment_hash;

                $payment_hash->data = array_merge((array) $payment_hash->data, $state);
                $payment_hash->save();
            }

            $gateway_response = json_decode($request->gateway_response);

            if ($gateway_response->status == 'COMPLETED') {
                $this->logSuccessfulGatewayResponse(['response' => json_decode($request->gateway_response), 'data' => $payment_hash->data], SystemLog::TYPE_CUSTOM);

                $data = [
                    'payment_method' => '',
                    'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                    'amount' => $payment_hash->amount_with_fee(),
                    'transaction_reference' => $gateway_response?->purchase_units[0]?->payments?->captures[0]?->id,
                    'gateway_type_id' => GatewayType::PAYPAL,
                ];

                $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);

                SystemLogger::dispatch(
                    ['response' => $payment_hash->data->server_response, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_STRIPE,
                    $this->client,
                    $this->client->company,
                );

                return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
            }
        }


        return redirect()->route('client.invoices');
    }

    /**
     * Detach payment method from custom payment driver.
     *
     * @param ClientGatewayToken $token
     * @return void
     */
    public function detach(ClientGatewayToken $token)
    {
        // Driver doesn't support this feature.
    }

    public function getClientRequiredFields(): array
    {
        return [];
    }
}

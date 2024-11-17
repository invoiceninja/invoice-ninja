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

namespace App\PaymentDrivers\PayTrace;

use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\PaytracePaymentDriver;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CreditCard implements LivewireMethodInterface
{
    use MakesHash;

    public $paytrace;

    public function __construct(PaytracePaymentDriver $paytrace)
    {
        $this->paytrace = $paytrace;
    }

    public function authorizeView($data)
    {
        $data = $this->paymentData($data);
        
        return render('gateways.paytrace.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $data = $request->all();

        $response = $this->createCustomer($data);

        return redirect()->route('client.payment_methods.index');
    }

    private function createCustomer($data)
    {
        $post_data = [
            'customer_id' => Str::random(32),
            'hpf_token' => $data['HPF_Token'],
            'enc_key' => $data['enc_key'],
            'integrator_id' =>  $this->paytrace->company_gateway->getConfigField('integratorId'),
            'billing_address' => $this->buildBillingAddress(),
            'email' => $this->paytrace->client->present()->email(),
            'phone' => $this->paytrace->client->present()->phone(),

        ];

        $response = $this->paytrace->gatewayRequest('/v1/customer/pt_protect_create', $post_data);

        if (! $response->success) {
            $error = 'Error creating customer in gateway';
            $error_code = isset($response->response_code) ? $response->response_code : 'PT_ERR';

            if (isset($response->errors)) {
                foreach ($response->errors as $err) {
                    $error = end($err);
                }
            }

            $data = [
                'response' => $response,
                'error' => $error,
                'error_code' => $error_code,
            ];

            return $this->paytrace->processUnsuccessfulTransaction($data);
        }

        nlog("paytrace response createCustomer");
        nlog($response);

        $cgt = [];
        $cgt['token'] = $response->customer_id;
        $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

        $profile = $this->getCustomerProfile($response->customer_id);

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = $profile->credit_card->expiration_month;
        $payment_meta->exp_year = $profile->credit_card->expiration_year;
        $payment_meta->brand = 'CC';
        $payment_meta->last4 = $profile->credit_card->masked_number;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $cgt['payment_meta'] = $payment_meta;

        $token = $this->paytrace->storeGatewayToken($cgt, []);

        return $response;
    }

    private function getCustomerProfile($customer_id)
    {
        $profile = $this->paytrace->gatewayRequest('/v1/customer/export', [
            'integrator_id' =>  $this->paytrace->company_gateway->getConfigField('integratorId'),
            'customer_id' => $customer_id,
        ]);

        return $profile->customers[0];
    }

    private function buildBillingAddress()
    {
        $data = [
            'name' => $this->paytrace->client->present()->name(),
            'street_address' => $this->paytrace->client->address1,
            'city' => $this->paytrace->client->city,
            'state' => $this->paytrace->client->state,
            'zip' => $this->paytrace->client->postal_code,
            'country' => $this->paytrace->client->country->iso_3166_2
        ];

        return $data;
    }

    public function paymentView($data)
    {
        $data['client_key'] = $this->paytrace->getAuthToken();
        $data['gateway'] = $this->paytrace;

        return render('gateways.paytrace.pay', $data);
    }

    public function paymentResponse(Request $request)
    {
        $response_array = $request->all();

        if ($request->token) {
            $token = ClientGatewayToken::find($this->decodePrimaryKey($request->token));

            return $this->processTokenPayment($token->token, $request);
        }

        if ($request->has('store_card') && $request->input('store_card') === true) {
            $response = $this->createCustomer($request->all());

            return $this->processTokenPayment($response->customer_id, $request);
        }

        //process a regular charge here:
        $data = [
            'hpf_token' => $response_array['HPF_Token'],
            'enc_key' => $response_array['enc_key'],
            'integrator_id' =>  $this->paytrace->company_gateway->getConfigField('integratorId'),
            'billing_address' => $this->buildBillingAddress(),
            'amount' => $request->input('amount_with_fee'),
            'invoice_id' => $this->harvestInvoiceId(),
        ];

        $response = $this->paytrace->gatewayRequest('/v1/transactions/sale/pt_protect', $data);

        if ($response->success) {
            return $this->processSuccessfulPayment($response);
        }

        return $this->processUnsuccessfulPayment($response);
    }

    public function processTokenPayment($token, $request)
    {
        $data = [
            'customer_id' => $token,
            'integrator_id' =>  $this->paytrace->company_gateway->getConfigField('integratorId'),
            'amount' => $request->input('amount_with_fee'),
            'invoice_id' => $this->harvestInvoiceId(),
        ];

        $response = $this->paytrace->gatewayRequest('/v1/transactions/sale/by_customer', $data);

        if ($response->success ?? false) {
            $this->paytrace->logSuccessfulGatewayResponse(['response' => $response, 'data' => $this->paytrace->payment_hash->data], SystemLog::TYPE_PAYTRACE);

            return $this->processSuccessfulPayment($response);
        }

        return $this->processUnsuccessfulPayment($response);
    }

    private function harvestInvoiceId()
    {
        $_invoice = collect($this->paytrace->payment_hash->data->invoices)->first();
        $invoice = Invoice::withTrashed()->find($this->decodePrimaryKey($_invoice->invoice_id));

        if ($invoice) {
            return ctrans('texts.invoice_number').'# '.$invoice->number;
        }

        return ctrans('texts.invoice_number').'####';
    }

    private function processSuccessfulPayment($response)
    {
        $amount = array_sum(array_column($this->paytrace->payment_hash->invoices(), 'amount')) + $this->paytrace->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $response->transaction_id;

        $payment = $this->paytrace->createPayment($payment_record, Payment::STATUS_COMPLETED);

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($response)
    {
        $error = $response->status_message;

        if (property_exists($response, 'approval_message') && $response->approval_message) {
            $error .= " - {$response->approval_message}";
        }

        $error_code = property_exists($response, 'approval_message') ? $response->approval_message : 'Undefined code';

        $data = [
            'response' => $response,
            'error' => $error,
            'error_code' => $error_code,
        ];

        return $this->paytrace->processUnsuccessfulTransaction($data);
    } 

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.paytrace.pay_livewire';
    }
    
    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['client_key'] = $this->paytrace->getAuthToken();
        $data['gateway'] = $this->paytrace;

        return $data;
    }
}

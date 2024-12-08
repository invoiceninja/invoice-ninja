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

namespace App\PaymentDrivers\Eway;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\EwayPaymentDriver;
use App\Utils\Traits\MakesHash;

class CreditCard implements LivewireMethodInterface
{
    use MakesHash;

    public $eway_driver;

    public function __construct(EwayPaymentDriver $eway_driver)
    {
        $this->eway_driver = $eway_driver;
    }

    public function authorizeView($data)
    {
        $data['gateway'] = $this->eway_driver;
        $data['api_key'] = $this->eway_driver->company_gateway->getConfigField('apiKey');
        $data['public_api_key'] = $this->eway_driver->company_gateway->getConfigField('publicApiKey');

        return render('gateways.eway.authorize', $data);
    }

    public function authorizeResponse($request)
    {
        $token = $this->createEwayToken($request->input('securefieldcode'));

        return redirect()->route('client.payment_methods.index');
    }

    private function createEwayToken($securefieldcode)
    {
        $transaction = [
            'Reference' => $this->eway_driver->client->number,
            'Title' => '',
            'FirstName' => $this->eway_driver->client->contacts()->first()->present()->first_name(),
            'LastName' => $this->eway_driver->client->contacts()->first()->present()->last_name(),
            'CompanyName' => $this->eway_driver->client->name,
            'Street1' => $this->eway_driver->client->address1,
            'Street2' => $this->eway_driver->client->address2,
            'City' => $this->eway_driver->client->city,
            'State' => $this->eway_driver->client->state,
            'PostalCode' => $this->eway_driver->client->postal_code,
            'Country' => $this->eway_driver->client->country->iso_3166_2,
            'Phone' => $this->eway_driver->client->phone ?? '',
            'Email' => $this->eway_driver->client->contacts()->first()->email ?? '',
            'Url' => $this->eway_driver->client->website  ?? '',
            'Method' => \Eway\Rapid\Enum\PaymentMethod::CREATE_TOKEN_CUSTOMER,
            'SecuredCardData' => $securefieldcode,
        ];

        $response = $this->eway_driver->init()->eway->createCustomer(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

        if ($response->getErrors()) {

            $response_status['message'] = \Eway\Rapid::getMessage($response->getErrors()[0]);

            $this->eway_driver->sendFailureMail($response_status['message']);

            $this->logResponse($response);

            throw new PaymentFailed($response_status['message'] ?? 'Unknown response from gateway, please contact you merchant.', 400); //@phpstan-ignore-line
        }

        //success
        $cgt = [];
        $cgt['token'] = strval($response->Customer->TokenCustomerID);
        $cgt['payment_method_id'] = GatewayType::CREDIT_CARD;

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = $response->Customer->CardDetails->ExpiryMonth;
        $payment_meta->exp_year = $response->Customer->CardDetails->ExpiryYear;
        $payment_meta->brand = 'CC';
        $payment_meta->last4 = substr($response->Customer->CardDetails->Number, -4);
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $cgt['payment_meta'] = $payment_meta;

        $token = $this->eway_driver->storeGatewayToken($cgt, []);

        $this->logResponse($response);

        return $token;
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->eway_driver;
        $data['public_api_key'] = $this->eway_driver->company_gateway->getConfigField('publicApiKey');

        return $data;
    }

    public function paymentView($data)
    {
        $data = $this->paymentData($data);

        return render('gateways.eway.pay', $data);
    }

    public function paymentResponse($request)
    {
        $state = [
            'server_response' => $request->all(),
        ];

        $this->eway_driver->payment_hash->data = array_merge((array) $this->eway_driver->payment_hash->data, $state);
        $this->eway_driver->payment_hash->save();

        if (boolval($request->input('store_card'))) {
            $token = $this->createEwayToken($request->input('securefieldcode'));
            $payment = $this->tokenBilling($token->token, $this->eway_driver->payment_hash);

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
        }

        if ($request->token) {
            $payment = $this->tokenBilling($request->token, $this->eway_driver->payment_hash);

            return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
        }

        $invoice_numbers = '';

        if ($this->eway_driver->payment_hash->data) {
            $invoice_numbers = collect($this->eway_driver->payment_hash->data->invoices)->pluck('invoice_number')->implode(','); //@phpstan-ignore-line
        }

        $amount = array_sum(array_column($this->eway_driver->payment_hash->invoices(), 'amount')) + $this->eway_driver->payment_hash->fee_total;

        // $description = "Invoices: {$invoice_numbers} for {$amount} for client {$this->eway_driver->client->present()->name()}";

        $transaction = [
            'Payment' => [
                'TotalAmount' => $this->convertAmountForEway(),
                'CurrencyCode' => $this->eway_driver->client->currency()->code,
                'InvoiceNumber' => $invoice_numbers,
                'InvoiceDescription' => substr($invoice_numbers, 0, 63),
            ],
            'TransactionType' => \Eway\Rapid\Enum\TransactionType::PURCHASE,
            'SecuredCardData' => $request->input('securefieldcode'),
        ];

        $response = $this->eway_driver->init()->eway->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

        $this->logResponse($response);

        if ($response->TransactionStatus) {
            $payment = $this->storePayment($response);
        } else {
            $message = 'Error processing payment.';

            if (isset($response->ResponseMessage)) {
                $message .= " Gateway Error Code = {$response->ResponseMessage}";
            }

            if ($response->getErrors()) {
                foreach ($response->getErrors() as $error) {
                    $message = \Eway\Rapid::getMessage($error);
                }
            }

            $this->eway_driver->sendFailureMail($message);

            throw new PaymentFailed($message, 400);
        }

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    private function storePayment($response)
    {
        $amount = array_sum(array_column($this->eway_driver->payment_hash->invoices(), 'amount')) + $this->eway_driver->payment_hash->fee_total;

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $response->TransactionID;

        $payment = $this->eway_driver->createPayment($payment_record);

        return $payment;
    }

    private function convertAmountForEway($amount = false)
    {
        if (! $amount) {
            $amount = array_sum(array_column($this->eway_driver->payment_hash->invoices(), 'amount')) + $this->eway_driver->payment_hash->fee_total;
        }

        if (in_array($this->eway_driver->client->currency()->code, ['VND', 'JPY', 'KRW', 'GNF', 'IDR', 'PYG', 'RWF', 'UGX', 'VUV', 'XAF', 'XPF'])) {
            return $amount;
        }

        return $amount * 100;
    }

    private function logResponse($response, $success = true)
    {
        $logger_message = [
            'server_response' => $response,
        ];

        SystemLogger::dispatch(
            $logger_message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            $success ? SystemLog::EVENT_GATEWAY_SUCCESS : SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_EWAY,
            $this->eway_driver->client,
            $this->eway_driver->client->company,
        );
    }

    public function tokenBilling($token, $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $invoice_numbers = '';

        if ($this->eway_driver->payment_hash->data) {
            $invoice_numbers = collect($this->eway_driver->payment_hash->data->invoices)->pluck('invoice_number')->implode(',');
        }

        $description = "Invoices: {$invoice_numbers} for {$amount} for client {$this->eway_driver->client->present()->name()}";

        $transaction = [
            'Customer' => [
                'TokenCustomerID' => $token,
            ],
            'Payment' => [
                'TotalAmount' => $this->convertAmountForEway($amount),
                'CurrencyCode' => $this->eway_driver->client->currency()->code,
                'InvoiceNumber' => $invoice_numbers,
                'InvoiceDescription' => substr($invoice_numbers, 0, 63),
            ],
            'TransactionType' => \Eway\Rapid\Enum\TransactionType::RECURRING,
        ];

        $response = $this->eway_driver->init()->eway->createTransaction(\Eway\Rapid\Enum\ApiMethod::DIRECT, $transaction);

        if ($response->TransactionStatus ?? false) {
            $this->logResponse($response, true);
            $payment = $this->storePayment($response);
        } else {
            $message = 'Error processing payment.';

            if (isset($response->ResponseMessage)) {
                $message .= " Gateway Error Code = {$response->ResponseMessage}";
            }

            if ($response->getErrors()) {
                foreach ($response->getErrors() as $error) {
                    $message = \Eway\Rapid::getMessage($error);
                }
            }

            $this->logResponse($response, false);

            $this->eway_driver->sendFailureMail($message);

            throw new PaymentFailed($message, 400);
        }

        return $payment;
    }
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.eway.pay_livewire';
    }
}

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

namespace App\PaymentDrivers\Authorize;

use App\Exceptions\PaymentFailed;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\AuthorizePaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Utils\Traits\MakesHash;
use net\authorize\api\contract\v1\DeleteCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\DeleteCustomerProfileRequest;
use net\authorize\api\controller\DeleteCustomerPaymentProfileController;
use net\authorize\api\controller\DeleteCustomerProfileController;

/**
 * Class AuthorizeCreditCard.
 */
class AuthorizeCreditCard implements LivewireMethodInterface
{
    use MakesHash;

    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function paymentData(array $data): array
    {
        $tokens = ClientGatewayToken::where('client_id', $this->authorize->client->id)
                                    ->where('company_gateway_id', $this->authorize->company_gateway->id)
                                    ->where('gateway_type_id', GatewayType::CREDIT_CARD)
                                    ->orderBy('is_default', 'desc')
                                    ->get();

        $data['tokens'] = $tokens;
        $data['gateway'] = $this->authorize;
        $data['public_client_id'] = $this->authorize->init()->getPublicClientKey();
        $data['api_login_id'] = $this->authorize->company_gateway->getConfigField('apiLoginId');

        return $data;
    }

    public function processPaymentView($data)
    {
        $data = $this->paymentData($data);

        return render('gateways.authorize.credit_card.pay', $data);
    }

    public function processPaymentResponse($request)
    {
        if ($request->token) {
            return $this->processTokenPayment($request);
        }

        $data = $request->all();

        $authorise_create_customer = new AuthorizeCreateCustomer($this->authorize, $this->authorize->client);

        $gateway_customer_reference = $authorise_create_customer->create($data);

        if ($request->has('store_card') && $request->input('store_card') === true) {

            $authorise_payment_method = new AuthorizePaymentMethod($this->authorize);

            $payment_profile = $authorise_payment_method->addPaymentMethodToClient($gateway_customer_reference, $data);
            $payment_profile_id = $payment_profile->getPaymentProfile()->getCustomerPaymentProfileId();

            $data = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($gateway_customer_reference, $payment_profile_id, $data['amount_with_fee']);

            $authorise_payment_method->payment_method = GatewayType::CREDIT_CARD;
            $client_gateway_token = $authorise_payment_method->createClientGatewayToken($payment_profile, $gateway_customer_reference);

        } else {

            $authorise_transaction = new AuthorizeTransaction($this->authorize);
            $data = $authorise_transaction->chargeCustomer($gateway_customer_reference, $data);

            $transaction_id = $data['transaction_id'];
            nlog($transaction_id);

        }

        return $this->handleResponse($data, $request);
    }

    private function removePaymentProfile($customer_profile_id, $customer_payment_profile_id)
    {
        $request = new DeleteCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setCustomerProfileId($customer_profile_id);
        $request->setCustomerPaymentProfileId($customer_payment_profile_id);
        $controller = new DeleteCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            nlog('SUCCESS: Delete Customer Payment Profile  SUCCESS');
        } else {
            nlog("unable to delete profile {$customer_profile_id} with payment id {$customer_payment_profile_id}");
        }

        // Delete a customer profile
        // $request = new DeleteCustomerProfileRequest();
        // $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        // $request->setCustomerProfileId( $customer_profile_id );

        // $controller = new DeleteCustomerProfileController($request);
        // $response = $controller->executeWithApiResponse($this->authorize->mode());
        // if (($response != null) && ($response->getMessages()->getResultCode() == "Ok") )
        // {
        //     nlog("SUCCESS: Delete Customer Payment Profile  SUCCESS");
        // }
        // else
        //   nlog("unable to delete profile {$customer_profile_id}");
    }

    private function processTokenPayment($request)
    {
        $client_gateway_token = ClientGatewayToken::query()
            ->where('id', $this->decodePrimaryKey($request->token))
            ->where('company_id', auth()->guard('contact')->user()->client->company->id)
            ->first();

        if (! $client_gateway_token) {
            throw new PaymentFailed(ctrans('texts.payment_token_not_found'), 401);
        }

        $data = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($client_gateway_token->gateway_customer_reference, $client_gateway_token->token, $request->input('amount_with_fee'));

        return $this->handleResponse($data, $request);
    }

    public function tokenBilling($cgt, $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $data = (new ChargePaymentProfile($this->authorize))->chargeCustomerProfile($cgt->gateway_customer_reference, $cgt->token, $amount);

        $response = $data['response'];

        // if ($response != null && $response->getMessages()->getResultCode() == 'Ok') {
        if ($response != null && $response->getMessages() != null) {
            $this->storePayment($payment_hash, $data);

            $vars = [
                'invoices' => $payment_hash->invoices(),
                'amount' => $amount,
            ];

            $logger_message = [
                'server_response' => $response->getTransId(),
                'data' => $this->formatGatewayResponse($data, $vars),
            ];

            SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);

            return true;
        } else {
            $vars = [
                'invoices' => $payment_hash->invoices(),
                'amount' => $amount,
            ];

            $logger_message = [
                'server_response' => $response->getTransId(),
                'data' => $this->formatGatewayResponse($data, $vars),
            ];

            $code = 'Error';
            $description = 'There was an error processing the payment';

            if ($response->getErrors() != null) {
                $code = $response->getErrors()[0]->getErrorCode();
                $description = $response->getErrors()[0]->getErrorText();
            }

            $this->authorize->sendFailureMail($description);

            SystemLogger::dispatch($logger_message, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_AUTHORIZE, $this->authorize->client, $this->authorize->client->company);

            return false;
        }
    }

    private function handleResponse($data, $request)
    {
        $response = $data['response'];

        // if ($response != null && $response->getMessages()->getResultCode() == 'Ok') {
        if ($response != null && $response->getMessages() != null) {
            return $this->processSuccessfulResponse($data, $request);
        }

        return $this->processFailedResponse($data, $request);
    }

    private function storePayment($payment_hash, $data)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;

        $response = $data['response'];

        $payment_record = [];
        $payment_record['amount'] = $amount;
        $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
        $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
        $payment_record['transaction_reference'] = $response->getTransId();

        $payment = $this->authorize->createPayment($payment_record);

        return $payment;
    }

    private function processSuccessfulResponse($data, $request)
    {
        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();
        $payment = $this->storePayment($payment_hash, $data);

        $vars = [
            'invoices' => $payment_hash->invoices(),
            'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total,
        ];

        $logger_message = [
            'server_response' => $data['response']->getTransId(),
            'data' => $this->formatGatewayResponse($data, $vars),
        ];

        SystemLogger::dispatch(
            $logger_message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_AUTHORIZE,
            $this->authorize->client,
            $this->authorize->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->encodePrimaryKey($payment->id)]);
    }

    private function processFailedResponse($data, $request)
    {
        $response = $data['response'];
        $amount = array_key_exists('amount_with_fee', $data) ? $data['amount_with_fee'] : 0;

        $code = 1;
        $description = 'There was an error processing the payment';

        if ($response && $response->getErrors() != null) {
            $code = (int) $response->getErrors()[0]->getErrorCode();
            $description = $response->getErrors()[0]->getErrorText();
        }

        $this->authorize->sendFailureMail($description);

        $payment_hash = PaymentHash::where('hash', $request->input('payment_hash'))->firstOrFail();

        $vars = [
            'invoices' => $payment_hash->invoices(),
            'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total,
        ];

        $logger_message = [
            'server_response' => $response->getErrors(),
            'data' => $this->formatGatewayResponse($data, $vars),
        ];

        SystemLogger::dispatch(
            $logger_message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_ERROR,
            SystemLog::TYPE_AUTHORIZE,
            $this->authorize->client,
            $this->authorize->client->company,
        );

        throw new PaymentFailed($description, $code);
    }

    private function formatGatewayResponse($data, $vars)
    {
        $response = $data['response'];

        $code = '';
        $description = '';

        if ($response->getMessages() !== null) {
            $code = $response->getMessages()[0]->getCode();
            $description = $response->getMessages()[0]->getDescription();
        }

        if ($response->getErrors() != null) {
            $code = $response->getErrors()[0]->getErrorCode();
            $description = $response->getErrors()[0]->getErrorText();
        }

        return [
            'transaction_reference' => $response->getTransId(),
            'amount' => $vars['amount'],
            'auth_code' => $response->getAuthCode(),
            'code' => $code,
            'description' => $description,
            'invoices' => $vars['invoices'],
        ];
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.authorize.credit_card.pay_livewire';
    }
}

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

namespace App\PaymentDrivers\CheckoutCom;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\CheckoutComPaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\Utils\Traits\MakesHash;
use Checkout\CheckoutApiException;
use Checkout\CheckoutArgumentException;
use Checkout\CheckoutAuthorizationException;
use Checkout\Payments\Previous\PaymentRequest as PreviousPaymentRequest;
use Checkout\Payments\Previous\Source\RequestTokenSource;
use Checkout\Payments\Request\PaymentRequest;
use Checkout\Payments\Request\Source\RequestTokenSource as SourceRequestTokenSource;
use Illuminate\Contracts\View\Factory;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CreditCard implements MethodInterface, LivewireMethodInterface
{
    use Utilities;
    use MakesHash;

    /**
     * @var CheckoutComPaymentDriver
     */
    public $checkout;

    public function __construct(CheckoutComPaymentDriver $checkout)
    {
        $this->checkout = $checkout;

        $this->checkout->init();
    }

    /**
     * An authorization view for credit card.
     *
     * @param mixed $data
     * @return Factory|View
     */
    public function authorizeView($data)
    {
        $data['gateway'] = $this->checkout;

        return render('gateways.checkout.credit_card.authorize', $data);
    }

    public function bootRequest($token)
    {
        if ($this->checkout->is_four_api) {
            $token_source = new RequestTokenSource();
            $token_source->token = $token;
            $request = new PreviousPaymentRequest();
            $request->source = $token_source;
        } else {
            $token_source = new SourceRequestTokenSource();
            $token_source->token = $token;
            $request = new PaymentRequest();
            $request->source = $token_source;
        }

        return $request;
    }

    /**
     * Handle authorization for credit card.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function authorizeResponse(Request $request)
    {
        $gateway_response = \json_decode($request->gateway_response);

        $customerRequest = $this->checkout->getCustomer();

        $request = $this->bootRequest($gateway_response->token);
        $request->capture = false;
        $request->reference = '$1 payment for authorization.';
        $request->amount = 100;
        $request->currency = $this->checkout->client->getCurrencyCode();
        $request->customer = $customerRequest;

        try {
            $response = $this->checkout->gateway->getPaymentsClient()->requestPayment($request);

            if ($response['approved'] && $response['status'] === 'Authorized') {
                $payment_meta = new \stdClass();
                $payment_meta->exp_month = (string) $response['source']['expiry_month'];
                $payment_meta->exp_year = (string) $response['source']['expiry_year'];
                $payment_meta->brand = (string) $response['source']['scheme'];
                $payment_meta->last4 = (string) $response['source']['last4'];
                $payment_meta->type = (int) GatewayType::CREDIT_CARD;

                $data = [
                    'payment_meta' => $payment_meta,
                    'token' => $response['source']['id'],
                    'payment_method_id' => GatewayType::CREDIT_CARD,
                ];

                $payment_method = $this->checkout->storeGatewayToken($data, ['gateway_customer_reference' => $customerRequest['id']]);

                return redirect()->route('client.payment_methods.show', $payment_method->hashed_id);
            }
        } catch (CheckoutApiException $e) {

            $error_details = $e->error_details;

            if (isset($e->error_details['error_codes']) ?? false) {
                $error_details = end($e->error_details['error_codes']);
            } else {
                $error_details = $e->getMessage();
            }

            throw new PaymentFailed($error_details, $e->getCode());
        } catch (CheckoutArgumentException $e) {
            // Bad arguments
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization

            throw new PaymentFailed("There is a problem with your Checkout Gateway API keys", 401);
        }
    }

    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->checkout;
        $data['company_gateway'] = $this->checkout->company_gateway;
        $data['client'] = $this->checkout->client;
        $data['currency'] = $this->checkout->client->getCurrencyCode();
        $data['value'] = $this->checkout->convertToCheckoutAmount($data['total']['amount_with_fee'], $this->checkout->client->getCurrencyCode());
        $data['raw_value'] = $data['total']['amount_with_fee'];
        $data['customer_email'] = $this->checkout->client->present()->email();

        return $data;
    }

    public function paymentView($data, $livewire = false)
    {
        $data = $this->paymentData($data);

        if ($livewire) {
            return render('gateways.checkout.credit_card.pay_livewire', $data);
        }

        return render('gateways.checkout.credit_card.pay', $data);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.checkout.credit_card.pay_livewire';
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $state = [
            'server_response' => json_decode($request->gateway_response),
            'value' => $request->value,
            'raw_value' => $request->raw_value,
            'currency' => $request->currency,
            'payment_hash' => $request->payment_hash,
            'client_id' => $this->checkout->client->id,
        ];

        $state = array_merge($state, $request->all());
        $state['store_card'] = boolval($state['store_card']);

        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, $state);
        $this->checkout->payment_hash->save();

        if ($request->has('token') && ! is_null($request->token) && ! empty($request->token)) {
            return $this->attemptPaymentUsingToken($request);
        }

        return $this->attemptPaymentUsingCreditCard($request);
    }

    private function attemptPaymentUsingToken(PaymentResponseRequest $request)
    {
        $cgt = ClientGatewayToken::query()
            ->where('id', $this->decodePrimaryKey($request->input('token')))
            ->where('company_id', auth()->guard('contact')->user()->client->company_id)
            ->first();

        if (! $cgt) {
            throw new PaymentFailed(ctrans('texts.payment_token_not_found'), 401);
        }

        $paymentRequest = $this->checkout->bootTokenRequest($cgt->token);

        return $this->completePayment($paymentRequest, $request);
    }

    private function attemptPaymentUsingCreditCard(PaymentResponseRequest $request)
    {
        $checkout_response = $this->checkout->payment_hash->data->server_response;

        $paymentRequest = $this->bootRequest($checkout_response->token);

        return $this->completePayment($paymentRequest, $request);
    }

    private function completePayment($paymentRequest, PaymentResponseRequest $request)
    {
        $paymentRequest->amount = $this->checkout->payment_hash->data->value;
        $paymentRequest->reference = substr($this->checkout->getDescription(), 0, 49);
        $paymentRequest->customer = $this->checkout->getCustomer();
        $paymentRequest->metadata = ['udf1' => 'Invoice Ninja', 'udf2' => $this->checkout->payment_hash->hash];
        $paymentRequest->currency = $this->checkout->client->getCurrencyCode();

        $this->checkout->payment_hash->data = array_merge((array) $this->checkout->payment_hash->data, ['checkout_payment_ref' => $paymentRequest]);
        $this->checkout->payment_hash->save();

        if ($this->checkout->client->currency()->code == 'EUR' || $this->checkout->company_gateway->getConfigField('threeds')) {
            $paymentRequest->{'3ds'} = ['enabled' => true];

            $paymentRequest->{'success_url'} = route('checkout.3ds_redirect', [
                'company_key' => $this->checkout->client->company->company_key,
                'company_gateway_id' => $this->checkout->company_gateway->hashed_id,
                'hash' => $this->checkout->payment_hash->hash,
            ]);

            $paymentRequest->{'failure_url'} = route('checkout.3ds_redirect', [
                'company_key' => $this->checkout->client->company->company_key,
                'company_gateway_id' => $this->checkout->company_gateway->hashed_id,
                'hash' => $this->checkout->payment_hash->hash,
            ]);
        }

        try {
            $response = $this->checkout->gateway->getPaymentsClient()->requestPayment($paymentRequest);

            if ($this->checkout->company_gateway->update_details && isset($response['customer'])) {
                $this->checkout->updateCustomer($response['customer']['id'] ?? '');
            }

            if ($response['status'] == 'Authorized') {

                return $this->processSuccessfulPayment($response);
            }

            if ($response['status'] == 'Pending') {

                $data = [
                    'gateway_type_id' => GatewayType::CREDIT_CARD,
                ];

                $this->checkout->confirmGatewayFee($data);

                return $this->processPendingPayment($response);
            }

            if ($response['status'] == 'Declined') {
                $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

                //18-10-2022
                SystemLogger::dispatch(
                    $response,
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_ERROR,
                    SystemLog::TYPE_CHECKOUT,
                    $this->checkout->client,
                    $this->checkout->client->company,
                );

                return $this->processUnsuccessfulPayment($response);
            }
        } catch (CheckoutApiException $e) {
            // API error
            $error_details = $e->error_details;

            if (is_array($error_details)) {
                $error_details = end($e->error_details['error_codes']);

                SystemLogger::dispatch(
                    $error_details,
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_ERROR,
                    SystemLog::TYPE_CHECKOUT,
                    $this->checkout->client,
                    $this->checkout->client->company,
                );

            }

            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

            $human_exception = $error_details ? new \Exception($error_details, 400) : $e;

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_CHECKOUT,
                $this->checkout->client,
                $this->checkout->client->company,
            );

            return $this->checkout->processInternallyFailedPayment($this->checkout, $human_exception);
        } catch (CheckoutArgumentException $e) {
            // Bad arguments

            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_CHECKOUT,
                $this->checkout->client,
                $this->checkout->client->company,
            );

            return new PaymentFailed($e->getMessage(), $e->getCode());

        } catch (CheckoutAuthorizationException $e) {
            // Bad Invalid authorization

            $this->checkout->unWindGatewayFees($this->checkout->payment_hash);

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_ERROR,
                SystemLog::TYPE_CHECKOUT,
                $this->checkout->client,
                $this->checkout->client->company,
            );

            return new PaymentFailed("There was a problem communicating with the API credentials for Checkout", $e->getCode());

        }
    }
}

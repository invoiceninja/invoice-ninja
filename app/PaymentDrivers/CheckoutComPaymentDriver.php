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

use Exception;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use Checkout\CheckoutSdk;
use Checkout\Environment;
use Checkout\Common\Phone;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Support\Carbon;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use Checkout\CheckoutApiException;
use App\Utils\Traits\SystemLogTrait;
use Checkout\Payments\RefundRequest;
use Illuminate\Support\Facades\Auth;
use Checkout\CheckoutArgumentException;
use Checkout\Customers\CustomerRequest;
use Checkout\CheckoutAuthorizationException;
use App\PaymentDrivers\CheckoutCom\Utilities;
use Checkout\Payments\Request\PaymentRequest;
use App\PaymentDrivers\CheckoutCom\CreditCard;
use App\PaymentDrivers\CheckoutCom\CheckoutWebhook;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use Checkout\Payments\Request\Source\RequestIdSource;
use App\Http\Requests\Gateways\Checkout3ds\Checkout3dsRequest;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use Checkout\Payments\Previous\PaymentRequest as PreviousPaymentRequest;
use Checkout\Payments\Previous\Source\RequestIdSource as SourceRequestIdSource;

class CheckoutComPaymentDriver extends BaseDriver
{
    use SystemLogTrait;
    use Utilities;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    public $invitation;

    /* Gateway capabilities */
    public $refundable = true;

    /* Token billing */
    public $token_billing = true;

    /* Authorise payment methods */
    public $can_authorise_credit_card = true;

    public $is_four_api = false;

    /**
     * @var CheckoutSdk;
     */
    public $gateway;

    public $payment_method; //the gateway type id

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_CHECKOUT;

    /**
     * Returns the default gateway type.
     */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    /**
     * Since with Checkout.com we handle only credit cards, this method should be empty.
     * @param int|null $payment_method
     * @return CheckoutComPaymentDriver
     */
    public function setPaymentMethod($payment_method = null): self
    {
        // At the moment Checkout.com payment
        // driver only supports payments using credit card.

        $class = self::$methods[GatewayType::CREDIT_CARD];

        $this->payment_method = new $class($this);

        return $this;
    }

    /**
     * Initialize the checkout payment driver
     * @return $this
     */
    public function init()
    {

        if (str_contains($this->company_gateway->getConfigField('secretApiKey'), '-')) {

            $this->is_four_api = true; //was four api, now known as previous.

            /** @phpstan-ignore-next-line **/
            $builder = CheckoutSdk::builder()
                    ->previous()
                    ->staticKeys()
                    ->environment($this->company_gateway->getConfigField('testMode') ? Environment::sandbox() : Environment::production()) /** phpstan-ignore-line **/
                    ->publicKey($this->company_gateway->getConfigField('publicApiKey'))
                    ->secretKey($this->company_gateway->getConfigField('secretApiKey'));

            $this->gateway = $builder->build();

        } else {

            /** @phpstan-ignore-next-line **/
            $builder = CheckoutSdk::builder()
                    ->staticKeys()
                    ->environment($this->company_gateway->getConfigField('testMode') ? Environment::sandbox() : Environment::production()) /** phpstan-ignore-line **/
                    ->publicKey($this->company_gateway->getConfigField('publicApiKey'))
                    ->secretKey($this->company_gateway->getConfigField('secretApiKey'));

            $this->gateway = $builder->build();

        }
        return $this;
    }

    /**
     * Process different view depending on payment type
     *
     * @param int $gateway_type_id The gateway type
     * @return string The view string
     */
    public function viewForType($gateway_type_id)
    {
        return 'gateways.checkout.credit_card.pay';
    }

    /**
     * Authorize View
     *
     * @param  array $data
     * @return \Illuminate\View\View
     */
    public function authorizeView($data)
    {
        return $this->payment_method->authorizeView($data);
    }

    /**
     * Authorize Response
     *
     * @param  array $data
     * @return \Illuminate\View\View
     */
    public function authorizeResponse($data)
    {
        return $this->payment_method->authorizeResponse($data);
    }

    /**
     * Payment View
     *
     * @param array $data Payment data array
     * @return \Illuminate\View\View
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    /**
     * Process the payment response
     *
     * @param \Illuminate\Http\Request $request The payment request
     * @return \Illuminate\View\View
     */
    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    /**
     * Store PaymentMethod
     *
     * @param  array $data
     * @return ?ClientGatewayToken $token
     */
    public function storePaymentMethod(array $data)
    {
        return $this->storeGatewayToken($data);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        if ($this->company_gateway->update_details) {
            $this->updateCustomer();
        }

        $request = new RefundRequest();
        $request->reference = "{$payment->transaction_reference} ".now();
        $request->amount = $this->convertToCheckoutAmount($amount, $this->client->getCurrencyCode());

        try {

            $response = $this->gateway->getPaymentsClient()->refundPayment($payment->transaction_reference, $request);


            SystemLogger::dispatch(
                array_merge(['message' => "Gateway Refund"], $response),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHECKOUT,
                $payment->client,
                $payment->company,
            );

            return [
                'transaction_reference' => $response['action_id'],
                'transaction_response' => json_encode($response),
                'success' => true,
                'description' => $response['reference'],
                'code' => 202,
            ];

        } catch (CheckoutApiException $e) {
            // API error
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        } catch (CheckoutArgumentException $e) {
            // Bad arguments

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHECKOUT,
                $payment->client,
                $payment->company,
            );

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];

        } catch (CheckoutAuthorizationException $e) {

            SystemLogger::dispatch(
                $e->getMessage(),
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHECKOUT,
                $payment->client,
                $payment->company,
            );

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($e->getMessage()),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => $e->getCode(),
            ];
        }
    }

    public function getCustomer()
    {
        try {
            $response = $this->gateway->getCustomersClient()->get($this->client->present()->email());

            return $response;
        } catch (\Exception $e) {

            $request = new CustomerRequest();

            $phone = new Phone();
            $phone->number = substr(str_pad($this->client->present()->phone(), 6, "0", STR_PAD_RIGHT), 0, 24);
            $request->email = $this->client->present()->email();
            $request->name = $this->client->present()->name();
            $request->phone = $phone;

            try {
                $response = $this->gateway->getCustomersClient()->create($request);
            } catch (CheckoutApiException $e) {
                // API error
                $error_details = $e->error_details;

                if (isset($error_details['error_codes']) ?? false) {
                    $error_details = end($e->error_details['error_codes']);
                } else {
                    $error_details = $e->getMessage();
                }

                throw new PaymentFailed($error_details, 400);
            } catch (CheckoutArgumentException $e) {

                throw new PaymentFailed($e->getMessage(), $e->getCode());
            } catch (CheckoutAuthorizationException $e) {
                // Bad Invalid authorization

                throw new PaymentFailed("Checkout Gateway credentials are invalid", 400);
            }

            return $response;
        }
    }

    public function updateCustomer($customer_id = null)
    {

        if (!$customer_id) {
            return;
        }

        try {

            $request = new CustomerRequest();

            $phone = new Phone();
            $phone->number = substr(str_pad($this->client->present()->phone(), 6, "0", STR_PAD_RIGHT), 0, 24);
            $request->email = $this->client->present()->email();
            $request->name = $this->client->present()->name();
            $request->phone = $phone;

            $response = $this->gateway->getCustomersClient()->update($customer_id, $request);


        } catch (CheckoutApiException $e) {
            nlog($e->getMessage());
        } catch (CheckoutAuthorizationException $e) {
            nlog($e->getMessage());
        }

    }

    /**
     * Boots a request for a token payment
     *
     * @param  string $token
     * @return PreviousPaymentRequest | PaymentRequest
     */
    public function bootTokenRequest($token)
    {
        if ($this->is_four_api) {
            $token_source = new SourceRequestIdSource();
            $token_source->id = $token;
            $request = new PreviousPaymentRequest();
            $request->source = $token_source;
        } else {
            $token_source = new RequestIdSource();
            $token_source->id = $token;
            $request = new PaymentRequest();
            $request->source = $token_source;
        }

        return $request;
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();
        $this->client = $invoice->client;
        $this->payment_hash = $payment_hash;

        $this->init();

        $paymentRequest = $this->bootTokenRequest($cgt->token);
        $paymentRequest->amount = $this->convertToCheckoutAmount($amount, $this->client->getCurrencyCode());
        $paymentRequest->reference = '#'.$invoice->number.' - '.now();
        $paymentRequest->customer = $this->getCustomer();
        $paymentRequest->metadata = ['udf1' => 'Invoice Ninja', 'udf2' => $payment_hash->hash];
        $paymentRequest->currency = $this->client->getCurrencyCode();

        $request = new PaymentResponseRequest();
        $request->setMethod('POST');
        $request->request->add(['payment_hash' => $payment_hash->hash]);

        try {
            $response = $this->gateway->getPaymentsClient()->requestPayment($paymentRequest);

            if ($response['status'] == 'Authorized') {

                $data = [
                    'payment_method' => $response['source']['id'],
                    'payment_type' => PaymentType::parseCardType(strtolower($response['source']['scheme'])),
                    'amount' => $amount,
                    'transaction_reference' => $response['id'],
                    'gateway_type_id' => GatewayType::CREDIT_CARD,
                ];

                $this->confirmGatewayFee($data);

                $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);

                SystemLogger::dispatch(
                    ['response' => $response, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_CHECKOUT,
                    $this->client,
                    $this->client->company,
                );

                return $payment;
            }

            if ($response['status'] == 'Declined') {
                $this->unWindGatewayFees($payment_hash);

                $this->sendFailureMail($response['status'].' '.$response['response_summary']);

                $message = [
                    'server_response' => $response,
                    'data' => $payment_hash->data,
                ];

                SystemLogger::dispatch(
                    $message,
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_CHECKOUT,
                    $this->client,
                    $this->client->company
                );

                return false;
            }
        } catch (CheckoutApiException $e) {

            $this->unWindGatewayFees($payment_hash);

            $error_details = $e->error_details;

            if (isset($error_details['error_codes']) ?? false) {
                $error_details = end($e->error_details['error_codes']);
            } else {
                $error_details = $e->getMessage();
            }

            $data = [
                'status' => $e->error_details,
                'error_type' => '',
                'error_code' => $e->getCode(),
                'param' => '',
                'message' => $e->getMessage(),
            ];

            $this->sendFailureMail($e->getMessage());

            SystemLogger::dispatch(
                $data,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_CHECKOUT,
                $this->client,
                $this->client->company
            );
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {

        header('Content-Type: text/plain');
        $webhook_payload = file_get_contents('php://input');

        if ($request->header('cko-signature') == hash_hmac('sha256', $webhook_payload, $this->company_gateway->company->company_key)) {
            CheckoutWebhook::dispatch($request->all(), $request->company_key, $this->company_gateway->id)->delay(10);
        } else {
            nlog("Hash Mismatch = {$request->header('cko-signature')} ".hash_hmac('sha256', $webhook_payload, $this->company_gateway->company->company_key));
            nlog($request->all());
        }

        return response()->json(['success' => true]);
    }

    public function process3dsConfirmation(Checkout3dsRequest $request)
    {
        $this->init();
        $this->setPaymentHash($request->getPaymentHash());

        //11-08-2022 check the user is authenticated
        if (!Auth::guard('contact')->check()) {
            $client = $request->getClient();
            $this->client = $client;
            auth()->guard('contact')->loginUsingId($client->contacts()->first()->id, true);
        }

        try {
            $payment = $this->gateway->getPaymentsClient()->getPaymentDetails(
                $request->query('cko-session-id')
            );

            nlog("checkout3ds");
            nlog($payment);

            if (isset($payment['approved']) && $payment['approved']) {
                return $this->processSuccessfulPayment($payment);
            } else {
                return $this->processUnsuccessfulPayment($payment);
            }
        } catch (CheckoutApiException | Exception $e) {
            nlog("checkout");
            nlog($e->getMessage());
            return $this->processInternallyFailedPayment($this, $e);
        }
    }

    public function detach(ClientGatewayToken $clientGatewayToken)
    {
        // Gateway doesn't support this feature.
    }

    public function auth(): bool
    {
        try {
            $this->init()->gateway->getCustomersClient('x');
            return true;
        } catch (\Exception $e) {

        }
        return false;
    }

    private function getToken(string $token, $gateway_customer_reference)
    {
        return  ClientGatewayToken::query()
                                  ->where('company_id', $this->company_gateway->company_id)
                                  ->where('gateway_customer_reference', $gateway_customer_reference)
                                  ->where('token', $token)
                                  ->first();
    }

    /**
     * ImportCustomers
     *
     * Only their methods because checkout.com
     * does not have a list route for customers
     *
     * @return void
     */
    public function importCustomers()
    {
        $this->init();

        $this->company_gateway
             ->company
             ->clients()
             ->cursor()
             ->each(function ($client) {

                 if (!str_contains($client->present()->email(), "@")) {
                     return;
                 }

                 try {
                     $customer = $this->gateway->getCustomersClient()->get($client->present()->email());
                 } catch (\Exception $e) {
                     nlog("Checkout: Customer not found");
                     return;
                 }

                 $this->client = $client;

                 nlog($customer['instruments']);

                 foreach ($customer['instruments'] as $card) {
                     if (
                         $card['type'] != 'card' ||
                         Carbon::createFromDate($card['expiry_year'], $card['expiry_month'], '1')->lt(now()) || //@phpstan-ignore-line
                         $this->getToken($card['id'], $customer['id'])
                     ) {
                         continue;
                     }

                     $payment_meta = new \stdClass();
                     $payment_meta->exp_month = (string) $card['expiry_month'];
                     $payment_meta->exp_year = (string) $card['expiry_year'];
                     $payment_meta->brand = (string) $card['scheme'];
                     $payment_meta->last4 = (string) $card['last4'];
                     $payment_meta->type = (int) GatewayType::CREDIT_CARD;

                     $data = [
                         'payment_meta' => $payment_meta,
                         'token' => $card['id'],
                         'payment_method_id' => GatewayType::CREDIT_CARD,
                     ];

                     $this->storeGatewayToken($data, ['gateway_customer_reference' => $customer['id']]);

                 }

             });
    }

    public function livewirePaymentView(array $data): string
    {
        return $this->payment_method->livewirePaymentView($data);
    }
}

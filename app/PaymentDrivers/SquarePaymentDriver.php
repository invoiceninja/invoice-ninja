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

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\ClientContact;
use App\Factory\ClientFactory;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use Square\Utils\WebhooksHelper;
use App\Models\ClientGatewayToken;
use App\Repositories\ClientRepository;
use Square\Models\WebhookSubscription;
use App\PaymentDrivers\Square\CreditCard;
use App\PaymentDrivers\Square\SquareWebhook;
use App\Repositories\ClientContactRepository;
use Square\Models\CreateWebhookSubscriptionRequest;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use App\PaymentDrivers\Factory\SquareCustomerFactory;
use Square\Models\Builders\RefundPaymentRequestBuilder;

class SquarePaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false; //does this gateway support refunds?

    public $token_billing = true; //does this gateway support token billing?

    public $can_authorise_credit_card = true; //does this gateway support authorizations?

    public $square;

    public $payment_method;

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class, //maps GatewayType => Implementation class
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_SQUARE;

    public function init()
    {
        $this->square = new \Square\SquareClient([
            'accessToken' => $this->company_gateway->getConfigField('accessToken'),
            'environment' => $this->company_gateway->getConfigField('testMode') ? \Square\Environment::SANDBOX : \Square\Environment::PRODUCTION,
        ]);

        return $this; /* This is where you boot the gateway with your auth credentials*/
    }

    /* Returns an array of gateway types for the payment gateway */
    public function gatewayTypes(): array
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;

        return $types;
    }

    /* Sets the payment method initialized */
    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data); //this is your custom implementation from here
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);  //this is your custom implementation from here
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);  //this is your custom implementation from here
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request); //this is your custom implementation from here
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();
        $this->client = $payment->client;

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount($this->convertAmount($amount));
        $amount_money->setCurrency($this->client->currency()->code);

        $body = RefundPaymentRequestBuilder::init(\Illuminate\Support\Str::random(32), $amount_money)
                ->paymentId($payment->transaction_reference)
                ->reason('Refund Request')
                ->build();

        $apiResponse = $this->square->getRefundsApi()->refundPayment($body);

        if ($apiResponse->isSuccess()) {

            $refundPaymentResponse = $apiResponse->getResult();

            nlog($refundPaymentResponse);

            /**
            * - `PENDING` - Awaiting approval.
            * - `COMPLETED` - Successfully completed.
            * - `REJECTED` - The refund was rejected.
            * - `FAILED` - An error occurred.
            */

            $status = $refundPaymentResponse->getRefund()->getStatus();

            if (in_array($status, ['COMPLETED', 'PENDING'])) {

                $transaction_reference = $refundPaymentResponse->getRefund()->getId();

                $data = [
                    'transaction_reference' => $transaction_reference,
                    'transaction_response' => json_encode($refundPaymentResponse->getRefund()->jsonSerialize()),
                    'success' => true,
                    'description' => $refundPaymentResponse->getRefund()->getReason(),
                    'code' => $refundPaymentResponse->getRefund()->getReason(),
                ];

                SystemLogger::dispatch(
                    [
                        'server_response' => $data,
                        'data' => request()->all()
                    ],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_SQUARE,
                    $this->client,
                    $this->client->company
                );

                return $data;
            } elseif (in_array($status, ['REJECTED', 'FAILED'])) {

                $transaction_reference = $refundPaymentResponse->getRefund()->getId();

                $data = [
                    'transaction_reference' => $transaction_reference,
                    'transaction_response' => json_encode($refundPaymentResponse->getRefund()->jsonSerialize()),
                    'success' => false,
                    'description' => $refundPaymentResponse->getRefund()->getReason(),
                    'code' => $refundPaymentResponse->getRefund()->getReason(),
                ];

                SystemLogger::dispatch(
                    [
                        'server_response' => $data,
                        'data' => request()->all()
                    ],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_FAILURE,
                    SystemLog::TYPE_SQUARE,
                    $this->client,
                    $this->client->company
                );

                return $data;
            }

        } else {

            /** @var \Square\Models\Error $error */
            $error = end($apiResponse->getErrors()); //@phpstan-ignore-line

            $data = [
                    'transaction_reference' => $payment->transaction_reference,
                    'transaction_response' => $error->jsonSerialize(),
                    'success' => false,
                    'description' => $error->getDetail(),
                    'code' => $error->getCode(),
                ];

            SystemLogger::dispatch(
                [
                    'server_response' => $data,
                    'data' => request()->all()
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_SQUARE,
                $this->client,
                $this->client->company
            );

            return $data;
        }

    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $this->init();

        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $amount = $this->convertAmount($amount);

        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($payment_hash->invoices(), 'invoice_id')))->withTrashed()->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Payment with no invoice for amount {$amount} for client {$this->client->present()->name()}";
        }

        $amount_money = new \Square\Models\Money();
        $amount_money->setAmount($amount);
        $amount_money->setCurrency($this->client->currency()->code);

        $body = new \Square\Models\CreatePaymentRequest($cgt->token, \Illuminate\Support\Str::random(32));
        $body->setCustomerId($cgt->gateway_customer_reference);
        $body->setAmountMoney($amount_money);
        $body->setReferenceId($payment_hash->hash);
        $body->setNote(substr($description, 0, 500));

        $response = $this->square->getPaymentsApi()->createPayment($body);
        $body = json_decode($response->getBody());

        if ($response->isSuccess()) {
            $amount = array_sum(array_column($this->payment_hash->invoices(), 'amount')) + $this->payment_hash->fee_total;

            $payment_record = [];
            $payment_record['amount'] = $amount;
            $payment_record['payment_type'] = PaymentType::CREDIT_CARD_OTHER;
            $payment_record['gateway_type_id'] = GatewayType::CREDIT_CARD;
            $payment_record['transaction_reference'] = $body->payment->id;

            $payment = $this->createPayment($payment_record, Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $response, 'data' => $payment_record],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHECKOUT,
                $this->client,
                $this->client->company,
            );

            return $payment;
        }

        $this->unWindGatewayFees($payment_hash);

        $this->sendFailureMail($body->errors[0]->detail);

        $message = [
            'server_response' => $response,
            'data' => $payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_SQUARE,
            $this->client,
            $this->client->company,
        );

        return false;
    }

    public function checkWebhooks(): mixed
    {
        $this->init();

        $api_response = $this->square->getWebhookSubscriptionsApi()->listWebhookSubscriptions();

        if ($api_response->isSuccess()) {

            //array of WebhookSubscription objects
            foreach ($api_response->getResult()->getSubscriptions() ?? [] as $subscription) {
                if ($subscription->getName() == 'Invoice_Ninja_Webhook_Subscription') {
                    return $subscription->getId();
                }
            }

        } else {
            $errors = $api_response->getErrors();
            nlog($errors);
            return false;
        }

        return false;
    }

    // {
    //   "subscription": {
    //     "id": "wbhk_b35f6b3145074cf9ad513610786c19d5",
    //     "name": "Example Webhook Subscription",
    //     "enabled": true,
    //     "event_types": [
    //         "payment.created",
    //         "order.updated",
    //         "invoice.created"
    //     ],
    //     "notification_url": "https://example-webhook-url.com",
    //     "api_version": "2021-12-15",
    //     "signature_key": "1k9bIJKCeTmSQwyagtNRLg",
    //     "created_at": "2022-08-17 23:29:48 +0000 UTC",
    //     "updated_at": "2022-08-17 23:29:48 +0000 UTC"
    //   }
    // }
    public function createWebhooks(): void
    {

        if ($this->checkWebhooks()) {
            return;
        }

        $this->init();

        $event_types = ['payment.created', 'payment.updated'];
        $subscription = new WebhookSubscription();
        $subscription->setName('Invoice_Ninja_Webhook_Subscription');
        $subscription->setEventTypes($event_types);

        // $subscription->setNotificationUrl('https://invoicing.co');

        $subscription->setNotificationUrl($this->company_gateway->webhookUrl());
        // $subscription->setApiVersion('2021-12-15');

        $body = new CreateWebhookSubscriptionRequest($subscription);
        $body->setIdempotencyKey(\Illuminate\Support\Str::uuid());

        $api_response = $this->square->getWebhookSubscriptionsApi()->createWebhookSubscription($body);

        if ($api_response->isSuccess()) {
            $subscription = $api_response->getResult()->getSubscription();
            $signatureKey = $subscription->getSignatureKey();

            $this->company_gateway->setConfigField('signatureKey', $signatureKey);

        } else {
            $errors = $api_response->getErrors();
            nlog($errors);
        }

    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        nlog("square webhook");

        $signature_key = $this->company_gateway->getConfigField('signatureKey');
        $notification_url = $this->company_gateway->webhookUrl();

        $body = '';
        $handle = fopen('php://input', 'r');
        while (!feof($handle)) {
            $body .= fread($handle, 1024);
        }

        if (WebhooksHelper::isValidWebhookEventSignature($body, $request->header('x-square-hmacsha256-signature'), $signature_key, $notification_url)) {
            SquareWebhook::dispatch($request->all(), $request->company_key, $this->company_gateway->id)->delay(5);
        } else {
            nlog("Square Hash Mismatch");
            nlog($request->all());
        }

        return response()->json(['success' => true]);

    }

    public function testWebhook()
    {
        $this->init();

        $body = new \Square\Models\TestWebhookSubscriptionRequest();
        $body->setEventType('payment.created');

        //getsubscriptionid here
        $subscription_id = $this->checkWebhooks();

        if (!$subscription_id) {
            nlog('No Subscription Found');
            return;
        }

        $api_response = $this->square->getWebhookSubscriptionsApi()->testWebhookSubscription($subscription_id, $body);

        if ($api_response->isSuccess()) {
            $result = $api_response->getResult();
            nlog($result);
        } else {
            $errors = $api_response->getErrors();
            nlog($errors);
        }

    }

    public function convertAmount($amount)
    {
        $precision = $this->client->currency()->precision;

        if ($precision == 0) {
            return $amount;
        }

        if ($precision == 1) {
            return $amount * 10;
        }

        if ($precision == 2) {
            return $amount * 100;
        }

        return $amount;
    }

    public function auth(): bool
    {

        $api_response = $this->init()
                    ->square
                    ->getCustomersApi()
                    ->listCustomers();


        return (bool) count($api_response->getErrors()) == 0;

    }

    public function importCustomers()
    {

        $limit = 100;

        $api_response = $this->init()
                    ->square
                    ->getCustomersApi()
                    ->listCustomers(
                        null,
                        $limit,
                        'DEFAULT',
                        'DESC'
                    );

        if ($api_response->isSuccess()) {

            while ($api_response->getResult()->getCustomers()) {

                $customers = $api_response->getResult()->getCustomers();

                $client_repo = new ClientRepository(new ClientContactRepository());

                foreach ($customers as $customer) {

                    $data = (new SquareCustomerFactory())->convertToNinja($customer, $this->company_gateway->company);
                    $client = ClientContact::where('company_id', $this->company_gateway->company_id)->where('email', $customer->getEmailAddress())->first()->client ?? false;

                    if (!$client) {
                        $client = $client_repo->save($data, ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id));
                    }

                    $this->client = $client;

                    foreach ($data['cards'] as $card) {

                        if (ClientGatewayToken::where('company_id', $this->company_gateway->company_id)->where('token', $card['token'])->exists()) {
                            continue;
                        }

                        $this->storeGatewayToken($card);

                    }
                }

                $c = $api_response->getCursor();
                if ($c) {

                    $api_response = $this->init()
                        ->square
                        ->getCustomersApi()
                        ->listCustomers(
                            $c,
                            $limit,
                            'DEFAULT',
                            'DESC'
                        );
                } else {
                    break;
                }


            }

        }
    }

    private function findClient($email = null)
    {

        $email_address_string = $email ?? $this->client->present()->email();

        $email_address = new \Square\Models\CustomerTextFilter();
        $email_address->setExact($email_address_string);

        $filter = new \Square\Models\CustomerFilter();
        $filter->setEmailAddress($email_address);

        $query = new \Square\Models\CustomerQuery();
        $query->setFilter($filter);

        $body = new \Square\Models\SearchCustomersRequest();
        $body->setQuery($query);

        $api_response = $this->init()
                            ->square
                            ->getCustomersApi()
                            ->searchCustomers($body);

        $customers = false;

        if ($api_response->isSuccess()) {
            $customers = $api_response->getBody();
            $customers = json_decode($customers);

            if (count([$api_response->getBody(), 1]) == 0) {
                $customers = false;
            }
        } else {
            $errors = $api_response->getErrors();
        }

        if ($customers && property_exists($customers, 'customers')) {
            return $customers->customers[0]->id;
        }

        return false;

    }

    public function findOrCreateClient()
    {
        if ($customer_id = $this->findClient()) {
            return $customer_id;
        }

        return $this->createClient();
    }

    private function createClient()
    {
        $country = $this->client->country ? $this->client->country->iso_3166_2 : $this->client->company->country()->iso_3166_2;

        /* Step two - create the customer */
        $billing_address = new \Square\Models\Address();
        $billing_address->setAddressLine1($this->client->address1);
        $billing_address->setAddressLine2($this->client->address2);
        $billing_address->setLocality($this->client->city);
        $billing_address->setAdministrativeDistrictLevel1($this->client->state);
        $billing_address->setPostalCode($this->client->postal_code);
        $billing_address->setCountry($country);

        $body = new \Square\Models\CreateCustomerRequest();
        $body->setGivenName($this->client->present()->name());
        $body->setFamilyName('');
        $body->setEmailAddress($this->client->present()->email());
        $body->setAddress($billing_address);
        $body->setReferenceId($this->client->number);
        $body->setNote('Created by Invoice Ninja.');

        $api_response = $this->init()
                             ->square
                             ->getCustomersApi()
                             ->createCustomer($body);

        if ($api_response->isSuccess()) {
            $result = $api_response->getResult();

            return $result->getCustomer()->getId();
        } else {
            $errors = $api_response->getErrors();
            nlog($errors);

            $error = end($errors);

            $data = [
                'response' => $error->getDetail(),
                'error' => $error->getDetail(),
                'error_code' => $error->getCode(),
            ];

            return $this->processUnsuccessfulTransaction($data);

        }
    }

























}

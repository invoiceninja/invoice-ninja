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

use App\Models\Client;
use App\Models\Country;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Factory\ClientFactory;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Models\ClientGatewayToken;
use App\Factory\ClientContactFactory;
use App\Jobs\Mail\PaymentFailedMailer;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;
use App\Http\Requests\Payments\PaymentWebhookRequest;

class GoCardlessPaymentDriver extends BaseDriver
{
    use MakesHash;
    use GeneratesCounter;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public \GoCardlessPro\Client $gateway;

    public $payment_method;

    private bool $completed = true;

    public static $methods = [
        GatewayType::BANK_TRANSFER => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::DIRECT_DEBIT => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::SEPA => \App\PaymentDrivers\GoCardless\DirectDebit::class,
        GatewayType::INSTANT_BANK_PAY => \App\PaymentDrivers\GoCardless\InstantBankPay::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_GOCARDLESS;

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [];

        if (
            $this->client
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['USA'])
        ) {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        if (
            $this->client
            && isset($this->client->country)
            && in_array($this->client->currency()->code, ['EUR', 'GBP','DKK','SEK','AUD','NZD','CAD'])
        ) {
            $types[] = GatewayType::DIRECT_DEBIT;
        }

        if ($this->client && in_array($this->client->currency()->code, ['EUR', 'GBP'])) {
            $types[] = GatewayType::SEPA;
        }

        if ($this->client && $this->client->currency()->code === 'GBP') {
            $types[] = GatewayType::INSTANT_BANK_PAY;
        }

        return $types;
    }

    public function init(): self
    {
        try {
            $this->gateway = new \GoCardlessPro\Client([
                'access_token' => $this->company_gateway->getConfigField('accessToken'),
                'environment'  => $this->company_gateway->getConfigField('testMode') ? \GoCardlessPro\Environment::SANDBOX : \GoCardlessPro\Environment::LIVE,
            ]);
        } catch(\GoCardlessPro\Core\Exception\AuthenticationException $e) {

            throw new \Exception('GoCardless: Invalid Access Token', 403);

        }

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        // ..
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        $amount = array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total;
        $converted_amount = $this->convertToGoCardlessAmount($amount, $this->client->currency()->precision);

        $this->init();

        if ($payment_hash->fee_invoice) {
            $description = "Invoice {$payment_hash->fee_invoice->number} for {$amount} for client {$this->client->present()->name()}";
        } else {
            $description = "Amount {$amount} from client {$this->client->present()->name()}";
        }

        try {
            $payment = $this->gateway->payments()->create([
                'params' => [
                    'amount' => $converted_amount,
                    'currency' => $this->client->getCurrencyCode(),
                    'description' => $description,
                    'metadata' => [
                        'payment_hash' => $this->payment_hash->hash,
                    ],
                    'links' => [
                        'mandate' => $cgt->token,
                    ],
                ],
            ]);

            if (in_array($payment->status, ['submitted', 'pending_submission'])) {
                
                $data = [
                    'payment_method' => $cgt->hashed_id,
                    'payment_type' => PaymentType::ACH,
                    'amount' => $amount,
                    'transaction_reference' => $payment->id,
                    'gateway_type_id' => GatewayType::BANK_TRANSFER,
                ];

                $this->confirmGatewayFee($data);

                $_payment = $this->createPayment($data, Payment::STATUS_PENDING);

                SystemLogger::dispatch(
                    ['response' => $payment, 'data' => $data],
                    SystemLog::CATEGORY_GATEWAY_RESPONSE,
                    SystemLog::EVENT_GATEWAY_SUCCESS,
                    SystemLog::TYPE_GOCARDLESS,
                    $this->client,
                    $this->client->company
                );

                return $_payment;
            }

            $this->sendFailureMail($payment->status);

            $message = [
                'server_response' => $payment,
                'data' => $payment_hash->data,
            ];

            SystemLogger::dispatch(
                $message,
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );

            return false;
        } catch (\Exception $exception) {
            $this->unWindGatewayFees($this->payment_hash);

            $data = [
                'status' => '',
                'error_type' => '',
                'error_code' => $exception->getCode(),
                'param' => '',
                'message' => $exception->getMessage(),
            ];

            SystemLogger::dispatch($data, SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_GOCARDLESS, $this->client, $this->client->company);
        }
    }

    public function convertToGoCardlessAmount($amount, $precision)
    {
        return \round(($amount * pow(10, $precision)), 0);
    }

    public function detach(ClientGatewayToken $token)
    {
        $this->init();

        try {
            $this->gateway->mandates()->cancel($token->token);
        } catch (\Exception $e) {
            nlog($e->getMessage());

            SystemLogger::dispatch(
                [
                    'server_response' => $e->getMessage(),
                    'data' => request()->all(),
                ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_GOCARDLESS,
                $this->client,
                $this->client->company
            );
        }
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        // Allow app to catch up with webhook request.
        $this->init();

        nlog('GoCardless Event');
        nlog($request->all());
        if (! $request->has('events')) {
            nlog('No GoCardless events to process in response?');

            return response()->json([], 200);
        }

        sleep(1);

        foreach ($request->events as $event) {
            if (
            ($event['resource_type'] == 'payments' && $event['action'] == 'confirmed') || 
            $event['action'] === 'paid_out') 
            {
                nlog('Searching for transaction reference');

                $payment = Payment::query()
                    ->where('transaction_reference', $event['links']['payment'])
                    ->where('company_id', $request->getCompany()->id)
                    ->first();

                if ($payment) {
                    $payment->status_id = Payment::STATUS_COMPLETED;
                    $payment->save();
                    nlog('GoCardless completed');
                } else {
                    nlog('I was unable to find the payment for this reference');
                }
                //finalize payments on invoices here.
            }

            if ($event['action'] === 'failed' && array_key_exists('payment', $event['links'])) {
                $payment = Payment::query()
                    ->where('transaction_reference', $event['links']['payment'])
                    ->where('company_id', $request->getCompany()->id)
                    ->first();

                if ($payment) {
                    if ($payment->status_id == Payment::STATUS_PENDING) {
                        $payment->service()->deletePayment();
                    }

                    $payment->status_id = Payment::STATUS_FAILED;
                    $payment->save();

                    $payment_hash = PaymentHash::where('payment_id', $payment->id)->first();
                    $error = '';

                    if (isset($event['details']['description'])) {
                        $error = $event['details']['description'];
                    }

                    PaymentFailedMailer::dispatch(
                        $payment_hash,
                        $payment->client->company,
                        $payment->client,
                        $error
                    );
                }
            }

            //billing_request fulfilled
            //

            //i need to build more context here, i need the client , the payment hash resolved and update the class properties.
            //after i resolve the payment hash, ensure the invoice has not been marked as paid and the payment does not already exist.
            //if it does exist, ensure it is completed and not pending.

            if ($event['action'] == 'fulfilled' && array_key_exists('billing_request', $event['links'])) {
                $hash = PaymentHash::whereJsonContains('data->billing_request', $event['links']['billing_request'])->first();

                if (!$hash) {
                    nlog("GoCardless: couldn't find a hash, need to abort => Billing Request => " . $event['links']['billing_request']);
                    return response()->json([], 200);
                }

                $this->setPaymentHash($hash);

                $billing_request = $this->gateway->billingRequests()->get(
                    $event['links']['billing_request']
                );

                $payment = $this->gateway->payments()->get(
                    $billing_request->payment_request->links->payment
                );

                if ($billing_request->status === 'fulfilled') {
                    $invoices = Invoice::query()->whereIn('id', $this->transformKeys(array_column($hash->invoices(), 'invoice_id')))->withTrashed()->get();

                    $this->client = $invoices->first()->client;

                    $invoices->each(function ($invoice) {
                        //if payments exist already, they just need to be confirmed.
                        if ($invoice->payments()->exists()) {
                            $invoice->payments()->where('status_id', 1)->cursor()->each(function ($payment) {
                                $payment->status_id = 4;
                                $payment->save();
                            });
                        }
                    });

                    // remove all paid invoices
                    $invoices->filter(function ($invoice) {
                        return $invoice->isPayable();
                    });

                    //return early if nothing to do
                    if ($invoices->count() == 0) {
                        nlog("GoCardless: Could not harvest any invoices - probably all paid!!");
                        return response()->json([], 200);
                    }

                    $this->processSuccessfulPayment($payment);
                }
            }
        }

        return response()->json([], 200);
    }


    public function processSuccessfulPayment(\GoCardlessPro\Resources\Payment $payment, array $data = [])
    {
        $data = [
            'payment_method' => $payment->links->mandate,
            'payment_type' => PaymentType::INSTANT_BANK_PAY,
            'amount' => $this->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id,
            'gateway_type_id' => GatewayType::INSTANT_BANK_PAY,
        ];

        $payment = $this->createPayment($data, Payment::STATUS_COMPLETED);
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->save();

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_GOCARDLESS,
            $this->client,
            $this->client->company,
        );
    }

    public function ensureMandateIsReady($token)
    {
        try {
            $this->init();
            $mandate = $this->gateway->mandates()->get($token);

            if(!in_array($mandate->status, ['pending_submission', 'submitted', 'active','pending_customer_approval'])) {

                // if ($mandate->status !== 'active') {
                throw new \Exception(ctrans('texts.gocardless_mandate_not_ready'));
            }
        } catch (\Exception $exception) {
            throw new \App\Exceptions\PaymentFailed($exception->getMessage());
        }
    }

    public function importCustomers()
    {
        $this->init();

        $customers = $this->gateway->customers()->list();

        foreach ($customers->records as $customer) {
            $existing_customer_token = $this->company_gateway
                            ->client_gateway_tokens()
                            ->where('gateway_customer_reference', $customer->id)
                            ->first();

            if ($existing_customer_token) {
                nlog("Skipping - Customer exists: {$customer->email} just updating payment methods");
                $this->updatePaymentMethods($customer, $existing_customer_token->client);
            } elseif ($customer->email && $this->company_gateway->company->client_contacts()->where('email', $customer->email)->exists()) {
                nlog("Customer exists: {$customer->email} just updating payment methods");

                $this->company_gateway->company->client_contacts()->where('email', $customer->email)->each(function ($contact) use ($customer) {
                    $this->updatePaymentMethods($customer, $contact->client);
                });
            } else {
                nlog("Creating new customer: {$customer->email}");
                $client = $this->createNinjaClient($customer);
                $this->updatePaymentMethods($customer, $client);
            }
        }
    }

    private function updatePaymentMethods($customer, Client $client): void
    {
        $this->client = $client;

        $mandates = $this->gateway->mandates()->list();

        foreach ($mandates->records as $mandate) {
            if ($customer->id != $mandate->links->customer || $mandate->status != 'active' || ClientGatewayToken::where('token', $mandate->id)->where('gateway_customer_reference', $customer->id)->exists()) {
                continue;
            }

            $payment_meta = new \stdClass();

            if ($mandate->scheme == 'bacs') {
                $payment_meta->brand = ctrans('texts.payment_type_direct_debit');
                $payment_meta->type = GatewayType::DIRECT_DEBIT;
            } elseif ($mandate->scheme == 'sepa_core') {
                $payment_meta->brand = ctrans('texts.sepa');
                $payment_meta->type = GatewayType::SEPA;
            } else {
                continue;
            }

            $payment_meta->state = 'authorized';

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $mandate->id,
                'payment_method_id' => GatewayType::DIRECT_DEBIT,
            ];

            $payment_method = $this->storeGatewayToken($data, ['gateway_customer_reference' => $mandate->links->customer]);
        }
    }

    /*
            [id] => CU0021PHBG5J6G
            [created_at] => 2022-12-02T11:24:15.739Z
            [email] => test@test.com
            [given_name] => Test
            [family_name] => Red
            [company_name] =>
            [address_line1] =>
            [address_line2] =>
            [address_line3] =>
            [city] =>
            [region] =>
            [postal_code] =>
            [country_code] =>
            [language] => en
            [swedish_identity_number] =>
            [danish_identity_number] =>
            [phone_number] =>
    */
    private function createNinjaClient($customer): Client
    {
        $client = ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id);

        $client->address1 = $customer->address_line1 ?: '';
        $client->address2 = $customer->address_line2 ?: '';
        $client->city = $customer->city ?: '';
        $client->state = $customer->region ?: '';
        $client->postal_code = $customer->postal_code ?: '';
        $client->phone = $customer->phone_number ? $customer->phone_number : '';
        $client->name = $customer->company_name;

        if ($customer->country_code) {
            $country = Country::where('iso_3166_2', $customer->country_code)->first();

            if ($country) {
                $client->country_id = $country->id;
            } else {
                $client->country_id = $this->company_gateway->company->settings->country_id;
            }
        }

        $settings = $client->settings;
        $settings->currency_id = (string) $this->company_gateway->company->settings->currency_id;
        $client->settings = $settings;
        $client->save();

        $contact = ClientContactFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id);
        $contact->first_name = $customer->given_name ?: '';
        $contact->last_name = $customer->family_name ?: '';
        $contact->email = $customer->email ?: '';
        $contact->client_id = $client->id;
        $contact->saveQuietly();

        if (! isset($client->number) || empty($client->number)) {
            $x = 1;

            do {
                try {
                    $client->number = $this->getNextClientNumber($client);
                    $client->saveQuietly();

                    $this->completed = false;
                } catch (QueryException $e) {
                    $x++;

                    if ($x > 10) {
                        $this->completed = false;
                    }
                }
            } while ($this->completed);
        } else {
            $client->saveQuietly();
        }

        return $client;
    }

    public function verificationView()
    {
        return render('gateways.gocardless.verification');
    }

    public function auth(): bool
    {
        try {
            $customers = $this->init()->gateway->customers()->list();
            return true;
        } catch(\Exception $e) {

        }

        return false;
    }
}

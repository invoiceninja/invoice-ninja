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

namespace App\PaymentDrivers\PayPal;

use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use Illuminate\Bus\Queueable;
use App\Models\CompanyGateway;
use App\Jobs\Util\SystemLogger;
use Illuminate\Support\Facades\Http;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Notifications\Ninja\PayPalUnlinkedTransaction;

class PayPalWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1; //number of retries

    public $deleteWhenMissingModels = true;

    private $gateway_key = '80af24a6a691230bbec33e930ab40666';

    private string $test_endpoint = 'https://api-m.sandbox.paypal.com';

    private string $endpoint = 'https://api-m.paypal.com';

    public function __construct(protected array $webhook_request, protected array $headers, protected string $access_token)
    {
    }

    public function handle()
    {
        //testing
        // $this->endpoint = $this->test_endpoint;

        //this can cause problems verifying the webhook, so unset it if it exists
        if (isset($this->webhook_request['q'])) {
            unset($this->webhook_request['q']);
        }

        if ($this->verifyWebhook()) {
            nlog('verified');

            match($this->webhook_request['event_type']) {//@phpstan-ignore-line
                'CHECKOUT.ORDER.COMPLETED' => $this->checkoutOrderCompleted(),
            };

            return;
        }

        nlog(" NOT VERIFIED ");
    }
    /*
    'id' => 'WH-COC11055RA711503B-4YM959094A144403T',
    'create_time' => '2018-04-16T21:21:49.000Z',
    'event_type' => 'CHECKOUT.ORDER.COMPLETED',
    'resource_type' => 'checkout-order',
    'resource_version' => '2.0',
    'summary' => 'Checkout Order Completed',
    'resource' =>
    array (
      'id' => '5O190127TN364715T',
      'status' => 'COMPLETED',
      'intent' => 'CAPTURE',
      'gross_amount' =>
      array (
        'currency_code' => 'USD',
        'value' => '100.00',
      ),
      'payer' =>
      array (
        'name' =>
        array (
          'given_name' => 'John',
          'surname' => 'Doe',
        ),
        'email_address' => 'buyer@example.com',
        'payer_id' => 'QYR5Z8XDVJNXQ',
      ),
      'purchase_units' =>
      array (
        0 =>
        array (
          'reference_id' => 'd9f80740-38f0-11e8-b467-0ed5f89f718b',
          'amount' =>
          array (
            'currency_code' => 'USD',
            'value' => '100.00',
          ),
          'payee' =>
          array (
            'email_address' => 'seller@example.com',
          ),
          'shipping' =>
          array (
            'method' => 'United States Postal Service',
            'address' =>
            array (
              'address_line_1' => '2211 N First Street',
              'address_line_2' => 'Building 17',
              'admin_area_2' => 'San Jose',
              'admin_area_1' => 'CA',
              'postal_code' => '95131',
              'country_code' => 'US',
            ),
          ),
          'payments' =>
          array (
            'captures' =>
            array (
              0 =>
              array (
                'id' => '3C679366HH908993F',
                'status' => 'COMPLETED',
                'amount' =>
                array (
                  'currency_code' => 'USD',
                  'value' => '100.00',
                ),
                'seller_protection' =>
                array (
                  'status' => 'ELIGIBLE',
                  'dispute_categories' =>
                  array (
                    0 => 'ITEM_NOT_RECEIVED',
                    1 => 'UNAUTHORIZED_TRANSACTION',
                  ),
                ),
                'final_capture' => true,
                'seller_receivable_breakdown' =>
                array (
                  'gross_amount' =>
                  array (
                    'currency_code' => 'USD',
                    'value' => '100.00',
                  ),
                  'paypal_fee' =>
                  array (
                    'currency_code' => 'USD',
                    'value' => '3.00',
                  ),
                  'net_amount' =>
                  array (
                    'currency_code' => 'USD',
                    'value' => '97.00',
                  ),
                ),
                'create_time' => '2018-04-01T21:20:49Z',
                'update_time' => '2018-04-01T21:20:49Z',
                'links' =>
                array (
                  0 =>
                  array (
                    'href' => 'https://api.paypal.com/v2/payments/captures/3C679366HH908993F',
                    'rel' => 'self',
                    'method' => 'GET',
                  ),
                  1 =>
                  array (
                    'href' => 'https://api.paypal.com/v2/payments/captures/3C679366HH908993F/refund',
                    'rel' => 'refund',
                    'method' => 'POST',
                  ),
                ),
              ),
            ),
          ),
        ),
      ),
      'create_time' => '2018-04-01T21:18:49Z',
      'update_time' => '2018-04-01T21:20:49Z',
      'links' =>
      */
    private function checkoutOrderCompleted()
    {
        $order = $this->webhook_request['resource'];
        $transaction_reference = $order['purchase_units'][0]['payments']['captures'][0]['id'];
        $amount = $order['purchase_units'][0]['payments']['captures'][0]['amount']['value'];
        $payment_hash = MultiDB::findAndSetByPaymentHash($order['purchase_units'][0]['custom_id']);
        $merchant_id = $order['purchase_units'][0]['payee']['merchant_id'];
        if (!$payment_hash) {

            $ninja_company = Company::on('db-ninja-01')->find(config('ninja.ninja_default_company_id'));
            $ninja_company->notification(new PayPalUnlinkedTransaction($order['id'], $transaction_reference))->ninja();
            return;
        }

        nlog("payment completed check");
        if ($payment_hash->payment && $payment_hash->payment->status_id == Payment::STATUS_COMPLETED) { // Payment made, all good!
            return;
        }

        nlog("invoice paid check");
        if ($payment_hash->fee_invoice && $payment_hash->fee_invoice->status_id == Invoice::STATUS_PAID) { // Payment made, all good!

            nlog("payment status check");
            if ($payment_hash->payment && $payment_hash->payment->status_id != Payment::STATUS_COMPLETED) { // Make sure the payment is marked as completed
                $payment_hash->payment->status_id = Payment::STATUS_COMPLETED;
                $payment_hash->push();
            }
            return;
        }

        nlog("create payment check");
        if ($payment_hash->fee_invoice && in_array($payment_hash->fee_invoice->status_id, [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])) {

            $payment = Payment::where('transaction_reference', $transaction_reference)->first();

            if (!$payment) {
                nlog("make payment here!");
                $payment = $this->createPayment($payment_hash, [
                    'amount' => $amount,
                    'transaction_reference' => $transaction_reference,
                    'merchant_id' => $merchant_id,
                ]);
            }

        }

    }

    private function getPaymentType($source): int
    {
        $method = 'paypal';

        match($source) {
            "card" => $method = PaymentType::CREDIT_CARD_OTHER,
            "paypal" => $method = PaymentType::PAYPAL,
            "venmo" => $method = PaymentType::VENMO,
            "paylater" => $method = PaymentType::PAY_LATER,
            default => $method = PaymentType::PAYPAL,
        };

        return $method;
    }

    private function createPayment(PaymentHash $payment_hash, array $data)
    {

        $client = $payment_hash->fee_invoice->client;

        $company_gateway = $this->harvestGateway($client->company, $data['merchant_id']);
        $driver = $company_gateway->driver($client)->init();
        $driver->setPaymentHash($payment_hash);

        $order = $driver->getOrder($this->webhook_request['resource']['id']);
        $source = 'paypal';

        if (isset($order['payment_source'])) {
            $source = array_key_first($order['payment_source']);
        }

        $data = [
            'payment_type' => $this->getPaymentType($source),
            'amount' => $data['amount'],
            'transaction_reference' => $data['transaction_reference'],
            'gateway_type_id' => GatewayType::PAYPAL,
        ];

        $payment = $driver->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->webhook_request, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_PAYPAL,
            $client,
            $client->company,
        );

    }

    private function harvestGateway(Company $company, string $merchant_id): ?CompanyGateway
    {
        $gateway = CompanyGateway::query()
            ->where('company_id', $company->id)
            ->where('gateway_key', $this->gateway_key)
            ->first(function ($cg) use ($merchant_id) { //@phpstan-ignore-line
                $config = $cg->getConfig();

                if ($config->merchantId == $merchant_id) {
                    return $cg;
                }

            });

        return $gateway ?? null;
    }

    //--------------------------------------------------------------------------------------//
    private function verifyWebhook(): bool
    {
        nlog($this->headers);
        $request = [
            'auth_algo' => $this->headers['paypal-auth-algo'][0],
            'cert_url' => $this->headers['paypal-cert-url'][0],
            'transmission_id' => $this->headers['paypal-transmission-id'][0],
            'transmission_sig' => $this->headers['paypal-transmission-sig'][0],
            'transmission_time' => $this->headers['paypal-transmission-time'][0],
            'webhook_id' => config('ninja.paypal.webhook_id'),
            'webhook_event' =>  $this->webhook_request
        ];

        nlog($request);

        $headers = [
            'Content-type' => 'application/json',
        ];

        $r = Http::withToken($this->access_token)
        ->withHeaders($headers)
        ->post("{$this->endpoint}/v1/notifications/verify-webhook-signature", $request);

        nlog($r);
        nlog($r->json());

        if ($r->successful() && $r->json()['verification_status'] == 'SUCCESS') {
            return true;
        }

        return false;

    }
}
/*
{
"auth_algo": "SHA256withRSA",
"cert_url": "cert_url",
"transmission_id": "69cd13f0-d67a-11e5-baa3-778b53f4ae55",
"transmission_sig": "lmI95Jx3Y9nhR5SJWlHVIWpg4AgFk7n9bCHSRxbrd8A9zrhdu2rMyFrmz+Zjh3s3boXB07VXCXUZy/UFzUlnGJn0wDugt7FlSvdKeIJenLRemUxYCPVoEZzg9VFNqOa48gMkvF+XTpxBeUx/kWy6B5cp7GkT2+pOowfRK7OaynuxUoKW3JcMWw272VKjLTtTAShncla7tGF+55rxyt2KNZIIqxNMJ48RDZheGU5w1npu9dZHnPgTXB9iomeVRoD8O/jhRpnKsGrDschyNdkeh81BJJMH4Ctc6lnCCquoP/GzCzz33MMsNdid7vL/NIWaCsekQpW26FpWPi/tfj8nLA==",
"transmission_time": "2016-02-18T20:01:35Z",
"webhook_id": "1JE4291016473214C",
"webhook_event": {
"id": "8PT597110X687430LKGECATA",
"create_time": "2013-06-25T21:41:28Z",
"resource_type": "authorization",
"event_type": "PAYMENT.AUTHORIZATION.CREATED",
"summary": "A payment authorization was created",
"resource": {
"id": "2DC87612EK520411B",
"create_time": "2013-06-25T21:39:15Z",
"update_time": "2013-06-25T21:39:17Z",
"state": "authorized",
"amount": {
"total": "7.47",
"currency": "USD",
"details": {
"subtotal": "7.47"
}
},
"parent_payment": "PAY-36246664YD343335CKHFA4AY",
"valid_until": "2013-07-24T21:39:15Z",
"links": [
{
"href": "https://api-m.paypal.com/v1/payments/authorization/2DC87612EK520411B",
"rel": "self",
"method": "GET"
},
{
"href": "https://api-m.paypal.com/v1/payments/authorization/2DC87612EK520411B/capture",
"rel": "capture",
"method": "POST"
},
{
"href": "https://api-m.paypal.com/v1/payments/authorization/2DC87612EK520411B/void",
"rel": "void",
"method": "POST"
},
{
"href": "https://api-m.paypal.com/v1/payments/payment/PAY-36246664YD343335CKHFA4AY",
"rel": "parent_payment",
"method": "GET"
}
]
}
}
}
*/


/** token created
 * {
         "id":"WH-1KN88282901968003-82E75604WM969463F",
         "event_version":"1.0",
         "create_time":"2022-08-15T14:13:48.978Z",
         "resource_type":"payment_token",
         "resource_version":"3.0",
         "event_type":"VAULT.PAYMENT-TOKEN.CREATED",
         "summary":"A payment token has been created.",
         "resource":{
            "time_created":"2022-08-15T07:13:48.964PDT",
            "links":[
               {
                  "href":"https://api-m.sandbox.paypal.com/v3/vault/payment-tokens/9n6724m",
                  "rel":"self",
                  "method":"GET",
                  "encType":"application/json"
               },
               {
                  "href":"https://api-m.sandbox.paypal.com/v3/vault/payment-tokens/9n6724m",
                  "rel":"delete",
                  "method":"DELETE",
                  "encType":"application/json"
               }
            ],
            "id":"9n6724m",
            "payment_source":{
               "card":{
                  "last_digits":"1111",
                  "brand":"VISA",
                  "expiry":"2027-02",
                  "billing_address":{
                     "address_line_1":"2211 N First Street",
                     "address_line_2":"17.3.160",
                     "admin_area_2":"San Jose",
                     "admin_area_1":"CA",
                     "postal_code":"95131",
                     "country_code":"US"
                  }
               }
            },
            "customer":{
               "id":"695922590"
            }
         },
         "links":[
            {
               "href":"https://api-m.sandbox.paypal.com/v1/notifications/webhooks-events/WH-1KN88282901968003-82E75604WM969463F",
               "rel":"self",
               "method":"GET"
            },
            {
               "href":"https://api-m.sandbox.paypal.com/v1/notifications/webhooks-events/WH-1KN88282901968003-82E75604WM969463F/resend",
               "rel":"resend",
               "method":"POST"
            }
         ]
      }
 */

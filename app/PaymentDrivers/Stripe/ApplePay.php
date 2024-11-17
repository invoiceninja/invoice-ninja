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

namespace App\PaymentDrivers\Stripe;

use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Models\GatewayType;
use App\Models\SystemLog;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;

class ApplePay
{
    /** @var StripePaymentDriver */
    public $stripe_driver;

    public function __construct(StripePaymentDriver $stripe_driver)
    {
        $this->stripe_driver = $stripe_driver;
    }

    public function paymentView(array $data)
    {
        $this->registerDomain();

        $data['gateway'] = $this->stripe_driver;
        $data['payment_hash'] = $this->stripe_driver->payment_hash->hash;
        $data['payment_method_id'] = GatewayType::APPLE_PAY;
        $data['country'] = $this->stripe_driver->client->country;
        $data['currency'] = $this->stripe_driver->client->currency()->code;
        $data['stripe_amount'] = $this->stripe_driver->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe_driver->client->currency()->precision, $this->stripe_driver->client->currency());
        $data['invoices'] = $this->stripe_driver->payment_hash->invoices();

        $data['intent'] = \Stripe\PaymentIntent::create([
            'amount' => $data['stripe_amount'],
            'currency' => $this->stripe_driver->client->getCurrencyCode(),
            'metadata' => [
                'payment_hash' => $this->stripe_driver->payment_hash->hash,
                'gateway_type_id' => GatewayType::APPLE_PAY,
            ],
        ], $this->stripe_driver->stripe_connect_auth);

        $this->stripe_driver->payment_hash->data = array_merge((array) $this->stripe_driver->payment_hash->data, ['stripe_amount' => $data['stripe_amount']]);
        $this->stripe_driver->payment_hash->save();

        return render('gateways.stripe.applepay.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->stripe_driver->init();

        $state = [
            'server_response' => json_decode($request->gateway_response),
            'payment_hash' => $request->payment_hash,
        ];

        $state['payment_intent'] = \Stripe\PaymentIntent::retrieve($state['server_response']->id, $this->stripe_driver->stripe_connect_auth);

        $state['customer'] = $state['payment_intent']->customer;

        $this->stripe_driver->payment_hash->data = array_merge((array) $this->stripe_driver->payment_hash->data, $state);
        $this->stripe_driver->payment_hash->save();

        $server_response = $this->stripe_driver->payment_hash->data->server_response;

        $response_handler = new CreditCard($this->stripe_driver);

        if ($server_response->status == 'succeeded') {
            $this->stripe_driver->logSuccessfulGatewayResponse(['response' => json_decode($request->gateway_response), 'data' => $this->stripe_driver->payment_hash->data], SystemLog::TYPE_STRIPE);

            return $response_handler->processSuccessfulPayment();
        }

        return $response_handler->processUnsuccessfulPayment($server_response);
    }

    private function registerDomain()
    {
        if (Ninja::isHosted()) {
            $domain = isset($this->stripe_driver->company_gateway->company->portal_domain) ? $this->stripe_driver->company_gateway->company->portal_domain : $this->stripe_driver->company_gateway->company->domain();

            \Stripe\ApplePayDomain::create([
                'domain_name' => $domain,
            ], $this->stripe_driver->stripe_connect_auth);
        } else {
            \Stripe\ApplePayDomain::create([
                'domain_name' => config('ninja.app_url'),
            ]);
        }
    }
}

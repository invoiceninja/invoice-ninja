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

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\ApplePayDomain;
use Stripe\Exception\ApiErrorException;
use Stripe\PaymentIntent;

class BrowserPay implements MethodInterface, LivewireMethodInterface
{
    protected StripePaymentDriver $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;

        $this->stripe->init();

        $this->ensureApplePayDomainIsValidated();
    }

    /**
     * Authorization page for browser pay.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorizeView(array $data): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Handle the authorization for browser pay.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }


    public function paymentData(array $data): array
    {
        $payment_intent_data = [
            'amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->getCurrencyCode(),
            'customer' => $this->stripe->findOrCreateCustomer(),
            'description' => $this->stripe->getDescription(false),
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::APPLE_PAY,
            ],
        ];

        $data['gateway'] = $this->stripe;
        $data['pi_client_secret'] = $this->stripe->createPaymentIntent($payment_intent_data)->client_secret;

        $data['payment_request_data'] = [
            'country' => $this->stripe->client->country->iso_3166_2,
            'currency' => strtolower(
                $this->stripe->client->getCurrencyCode()
            ),
            'total' => [
                'label' => $payment_intent_data['description'],
                'amount' => $payment_intent_data['amount'],
            ],
            'requestPayerName' => true,
            'requestPayerEmail' => true,
        ];

        return $data;
    }

    public function paymentView(array $data): View
    {
        $data = $this->paymentData($data);

        return render('gateways.stripe.browser_pay.pay', $data);
    }

    /**
     * Handle payment response for browser pay.
     *
     * @param PaymentResponseRequest $request
     * @return \Illuminate\Http\RedirectResponse|App\PaymentDrivers\Stripe\never
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $gateway_response = json_decode($request->gateway_response);

        $this->stripe->payment_hash
            ->withData('gateway_response', $gateway_response)
            ->withData('payment_intent', PaymentIntent::retrieve($gateway_response->id, $this->stripe->stripe_connect_auth));

        if ($gateway_response->status === 'succeeded') {
            return $this->processSuccessfulPayment();
        }

        return $this->processUnsuccessfulPayment();
    }

    /**
     * Handle successful payment for browser pay.
     *
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function processSuccessfulPayment()
    {
        $gateway_response = $this->stripe->payment_hash->data->gateway_response;
        $payment_intent = $this->stripe->payment_hash->data->payment_intent;

        $this->stripe->logSuccessfulGatewayResponse(['response' => $gateway_response, 'data' => $this->stripe->payment_hash->data], SystemLog::TYPE_STRIPE);

        $payment_method = $this->stripe->getStripePaymentMethod($gateway_response->payment_method);

        $data = [
            'payment_method' => $gateway_response->payment_method,
            'payment_type' => PaymentType::parseCardType(strtolower($payment_method->card->brand)),
            'amount' => $this->stripe->convertFromStripeAmount($gateway_response->amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => isset($payment_intent->latest_charge) ? $payment_intent->latest_charge : $payment_intent->charges->data[0]->id,
            'gateway_type_id' => GatewayType::APPLE_PAY,
        ];

        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['amount' => $data['amount']]);
        $this->stripe->payment_hash->save();

        $payment = $this->stripe->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $gateway_response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
    }

    /**
     * Handle unsuccessful payment for browser pay.
     *
     * @return never
     */
    protected function processUnsuccessfulPayment()
    {
        $server_response = $this->stripe->payment_hash->data->gateway_response;

        $this->stripe->sendFailureMail($server_response->cancellation_reason);

        $message = [
            'server_response' => $server_response,
            'data' => $this->stripe->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        throw new PaymentFailed('Failed to process the payment.', 500);
    }

    /**
     * Ensure Apple Pay domain is verified.
     *
     * @return void
     * @throws ApiErrorException
     */
    protected function ensureApplePayDomainIsValidated()
    {
        $config = $this->stripe->company_gateway->getConfig();

        if (property_exists($config, 'apple_pay_domain_id')) {
            return;
        }

        $domain = $this->getAppleDomain();

        if (! $domain) {
            throw new PaymentFailed('Unable to register Domain with Apple Pay', 500);
        }

        $response = ApplePayDomain::create([
            'domain_name' => $domain,
        ], $this->stripe->stripe_connect_auth);

        $config->apple_pay_domain_id = $response->id;

        $this->stripe->company_gateway->setConfig($config);

        $this->stripe->company_gateway->save();
    }

    private function getAppleDomain()
    {
        $domain = '';

        if (Ninja::isHosted()) {
            if ($this->stripe->company_gateway->company->portal_mode == 'domain') {
                $domain = $this->stripe->company_gateway->company->portal_domain;
            } else {
                $domain = $this->stripe->company_gateway->company->subdomain.'.'.config('ninja.app_domain');
            }
        } else {
            $domain = config('ninja.app_url');
        }

        return str_replace(['https://', '/public'], '', $domain);
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.browser_pay.pay_livewire';
    }
}

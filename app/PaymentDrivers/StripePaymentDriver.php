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
use Stripe\Stripe;
use Stripe\Account;
use Stripe\Customer;
use App\Models\Client;
use App\Models\Payment;
use Stripe\SetupIntent;
use Stripe\StripeClient;
use App\Models\SystemLog;
use Stripe\PaymentIntent;
use Stripe\PaymentMethod;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use App\Http\Requests\Request;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\Stripe\ACH;
use App\PaymentDrivers\Stripe\EPS;
use App\PaymentDrivers\Stripe\FPX;
use App\PaymentDrivers\Stripe\ACSS;
use App\PaymentDrivers\Stripe\BACS;
use App\PaymentDrivers\Stripe\BECS;
use App\PaymentDrivers\Stripe\SEPA;
use App\PaymentDrivers\Stripe\iDeal;
use App\PaymentDrivers\Stripe\Alipay;
use App\PaymentDrivers\Stripe\Charge;
use App\PaymentDrivers\Stripe\Klarna;
use App\PaymentDrivers\Stripe\SOFORT;
use Illuminate\Http\RedirectResponse;
use App\PaymentDrivers\Stripe\GIROPAY;
use Stripe\Exception\ApiErrorException;
use App\Exceptions\StripeConnectFailure;
use App\PaymentDrivers\Stripe\Utilities;
use App\PaymentDrivers\Stripe\Bancontact;
use App\PaymentDrivers\Stripe\BrowserPay;
use App\PaymentDrivers\Stripe\CreditCard;
use App\PaymentDrivers\Stripe\PRZELEWY24;
use App\PaymentDrivers\Stripe\BankTransfer;
use App\PaymentDrivers\Stripe\Connect\Verify;
use App\PaymentDrivers\Stripe\ImportCustomers;
use App\PaymentDrivers\Stripe\Jobs\ChargeRefunded;
use App\Http\Requests\Payments\PaymentWebhookRequest;
use Laracasts\Presenter\Exceptions\PresenterException;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentWebhook;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentFailureWebhook;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentProcessingWebhook;
use App\PaymentDrivers\Stripe\Jobs\PaymentIntentPartiallyFundedWebhook;

class StripePaymentDriver extends BaseDriver
{
    use MakesHash;
    use Utilities;

    public $refundable = true;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    /** @var StripeClient */
    public $stripe;

    protected $customer_reference = 'customerReferenceParam';

    public $payment_method;

    public $stripe_connect = false;

    public $stripe_connect_auth = [];

    public static $methods = [
        GatewayType::CREDIT_CARD => CreditCard::class,
        GatewayType::BANK_TRANSFER => ACH::class,
        GatewayType::ALIPAY => Alipay::class,
        GatewayType::SOFORT => SOFORT::class,
        GatewayType::APPLE_PAY => BrowserPay::class,
        GatewayType::SEPA => SEPA::class,
        GatewayType::PRZELEWY24 => PRZELEWY24::class,
        GatewayType::GIROPAY => GIROPAY::class,
        GatewayType::IDEAL => iDeal::class,
        GatewayType::EPS => EPS::class,
        GatewayType::BANCONTACT => Bancontact::class,
        GatewayType::BECS => BECS::class,
        GatewayType::ACSS => ACSS::class,
        GatewayType::FPX => FPX::class,
        GatewayType::KLARNA => Klarna::class,
        GatewayType::BACS => BACS::class,
        GatewayType::DIRECT_DEBIT => BankTransfer::class,
    ];

    public const SYSTEM_LOG_TYPE = SystemLog::TYPE_STRIPE;

    /**
     * Indicates if returning responses should be headless or classic redirect.
     * 
     * @var bool
     */
    public bool $headless = false;

    /**
     * Initializes the Stripe API.
     * @return self
     */
    public function init()
    {
        if ($this->stripe_connect) {
            Stripe::setApiKey(config('ninja.ninja_stripe_key'));

            if (strlen($this->company_gateway->getConfigField('account_id')) > 1) {
                $this->stripe_connect_auth = ['stripe_account' => $this->company_gateway->getConfigField('account_id')];
            } else {
                throw new StripeConnectFailure('Stripe Connect has not been configured');
            }
        } else {
            $this->stripe = new StripeClient(
                $this->company_gateway->getConfigField('apiKey')
            );

            Stripe::setApiKey($this->company_gateway->getConfigField('apiKey'));
            // Stripe::setApiVersion('2022-11-15');
            Stripe::setAPiVersion('2023-08-16');
        }

        return $this;
    }

    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];

        $this->payment_method = new $class($this);

        return $this;
    }


    /**
     * Returns the gateway types.
     */
    public function gatewayTypes(): array
    {
        $types = [
            GatewayType::CREDIT_CARD,
            GatewayType::APPLE_PAY,
        ];

        if ($this->client
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['AUT', 'BEL', 'DEU', 'ITA', 'NLD', 'ESP'])) {
            $types[] = GatewayType::SOFORT;
        }

        if ($this->client
            && isset($this->client->country)
            && (in_array($this->client->country->iso_3166_3, ['USA']) || ($this->client->gateway_tokens()->where('gateway_type_id', GatewayType::BANK_TRANSFER)->exists()))
        ) {
            $types[] = GatewayType::BANK_TRANSFER;
        }

        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['CNY', 'AUD', 'CAD', 'EUR', 'GBP', 'HKD', 'JPY', 'SGD', 'MYR', 'NZD', 'USD'])) {
            // && isset($this->client->country)
            // && in_array($this->client->country->iso_3166_3, ['AUS', 'DNK', 'DEU', 'ITA', 'LUX', 'NOR', 'SVN', 'GBR', 'AUT', 'EST', 'GRC', 'JPN', 'MYS', 'PRT', 'ESP', 'USA', 'BEL', 'FIN', 'HKG', 'LVA', 'NLD', 'SGP', 'SWE', 'CAN', 'FRA', 'IRL', 'LTU', 'NZL', 'SVK', 'CHE'])) {
            $types[] = GatewayType::ALIPAY;
        }

        if ($this->client
            && $this->client->currency()
            && ($this->client->currency()->code == 'EUR')
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['AUT', 'BEL', 'CHE', 'CYP', 'CZE', 'BGR', 'DNK', 'DEU', 'ESP', 'FIN', 'FRA', 'HUN', 'IRL', 'ITA', 'LVA', 'LUX', 'LTA', 'MLT', 'NLD', 'NOR', 'POL', 'ROU', 'SVK', 'SVN', 'SWE', 'GBR', 'EST', 'GRC', 'PRT'])) { // TODO: More has to be added https://stripe.com/docs/payments/sepa-debit
            $types[] = GatewayType::SEPA;
        }

        if ($this->client
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['POL'])) {
            $types[] = GatewayType::PRZELEWY24;
        }

        if ($this->client
            && $this->client->currency()
            && ($this->client->currency()->code == 'EUR')
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['DEU'])) {
            $types[] = GatewayType::GIROPAY;
        }

        if ($this->client
            && $this->client->currency()
            && ($this->client->currency()->code == 'EUR')
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['NLD'])) {
            $types[] = GatewayType::IDEAL;
        }

        if ($this->client
            && $this->client->currency()
            && ($this->client->currency()->code == 'EUR')
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['AUT'])) {
            $types[] = GatewayType::EPS;
        }

        if ($this->client
            && $this->client->currency()
            && ($this->client->currency()->code == 'MYR')
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['MYS'])) {
            $types[] = GatewayType::FPX;
        }

        if ($this->client
            && $this->client->currency()
            && ($this->client->currency()->code == 'EUR')
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['BEL'])) {
            $types[] = GatewayType::BANCONTACT;
        }

        if ($this->client
            && $this->client->currency()
            && ($this->client->currency()->code == 'AUD')
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['AUS'])) {
            $types[] = GatewayType::BECS;
        }

        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['CAD', 'USD'])
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['CAN', 'USA'])) {
            $types[] = GatewayType::ACSS;
        }
        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['GBP'])
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['GBR'])) {
            $types[] = GatewayType::BACS;
        }
        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['EUR', 'DKK', 'GBP', 'NOK', 'SEK', 'AUD', 'NZD', 'CAD', 'PLN', 'CHF'])
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['AUT','BEL','DNK','FIN','FRA','DEU','IRL','ITA','NLD','NOR','ESP','SWE','GBR'])) {
            $types[] = GatewayType::KLARNA;
        }
        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['EUR', 'DKK', 'GBP', 'NOK', 'SEK', 'AUD', 'NZD', 'CAD', 'PLN', 'CHF', 'USD'])
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_3, ['AUT','BEL','DNK','FIN','FRA','DEU','IRL','ITA','NLD','NOR','ESP','SWE','GBR','USA'])) {
            $types[] = GatewayType::KLARNA;
        }

        if (
            $this->client
            && isset($this->client->country)
            && (
                (in_array($this->client->country->iso_3166_2, ['FR', 'IE', 'NL', 'DE', 'ES']) && $this->client->currency()->code == 'EUR') ||
                ($this->client->country->iso_3166_2 == 'JP' && $this->client->currency()->code == 'JPY') ||
                ($this->client->country->iso_3166_2 == 'MX' && $this->client->currency()->code == 'MXN') ||
                ($this->client->country->iso_3166_2 == 'GB' && $this->client->currency()->code == 'GBP') ||
                ($this->client->country->iso_3166_2 == 'US' && $this->client->currency()->code == 'USD')
            )
        ) {
            $types[] = GatewayType::DIRECT_DEBIT;
        }

        return $types;
    }

    public function viewForType($gateway_type_id)
    {
        switch ($gateway_type_id) {
            case GatewayType::CREDIT_CARD:
                return 'gateways.stripe.credit_card';
            case GatewayType::SOFORT:
                return 'gateways.stripe.sofort';
            case GatewayType::BANK_TRANSFER:
                return 'gateways.stripe.ach';
            case GatewayType::SEPA:
                return 'gateways.stripe.sepa';
            case GatewayType::PRZELEWY24:
                return 'gateways.stripe.przelewy24';
            case GatewayType::CRYPTO:
            case GatewayType::ALIPAY:
            case GatewayType::APPLE_PAY:
                return 'gateways.stripe.other';
            case GatewayType::GIROPAY:
                return 'gateways.stripe.giropay';
            case GatewayType::KLARNA:
                return 'gateways.stripe.klarna';
            case GatewayType::IDEAL:
                return 'gateways.stripe.ideal';
            case GatewayType::EPS:
                return 'gateways.stripe.eps';
            case GatewayType::BANCONTACT:
                return 'gateways.stripe.bancontact';
            case GatewayType::BECS:
                return 'gateways.stripe.becs';
            case GatewayType::BACS:
                return 'gateways.stripe.bacs';
            case GatewayType::ACSS:
                return 'gateways.stripe.acss';
            case GatewayType::FPX:
                return 'gateways.stripe.fpx';
            default:
                break;
        }
    }

    public function getClientRequiredFields(): array
    {
        $fields = [];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        // if ($this->company_gateway->require_contact_name) {
        $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        // }

        // if ($this->company_gateway->require_contact_email) {
        $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];
        // }

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_billing_address) {
            $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
            //            $fields[] = ['name' => 'client_address_line_2', 'label' => ctrans('texts.address2'), 'type' => 'text', 'validation' => 'nullable'];
            $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_postal_code) {
            $fields[] = ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
            //            $fields[] = ['name' => 'client_shipping_address_line_2', 'label' => ctrans('texts.shipping_address2'), 'type' => 'text', 'validation' => 'sometimes'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value1) {
            $fields[] = ['name' => 'client_custom_value1', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client1'), 'type' => 'text', 'validation' => 'required'];
        }


        if ($this->company_gateway->require_custom_value2) {
            $fields[] = ['name' => 'client_custom_value2', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client2'), 'type' => 'text', 'validation' => 'required'];
        }


        if ($this->company_gateway->require_custom_value3) {
            $fields[] = ['name' => 'client_custom_value3', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client3'), 'type' => 'text', 'validation' => 'required'];
        }


        if ($this->company_gateway->require_custom_value4) {
            $fields[] = ['name' => 'client_custom_value4', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client4'), 'type' => 'text', 'validation' => 'required'];
        }

        return $fields;
    }

    /**
     * Proxy method to pass the data into payment method authorizeView().
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    /**
     * Processes the gateway response for credit card authorization.
     *
     * @param \Illuminate\Http\Request $request
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    /**
     * Process the payment with gateway.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse|mixed
     */
    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentViewData(array $data): array
    {
        $data = $this->payment_method->paymentData($data);

        $data['stripe_account_id'] = $this->company_gateway->getConfigField('account_id');

        if (array_key_exists('intent', $data)) {
            $data['client_secret'] = $data['intent']->client_secret;
        }

        unset($data['intent']);

        $token_billing_string = 'true';

        if ($this->company_gateway->token_billing == 'off' || $this->company_gateway->token_billing == 'optin') {
            $token_billing_string = 'false';
        }

        if (isset($data['pre_payment']) && $data['pre_payment'] == '1' && isset($data['is_recurring']) && $data['is_recurring'] == '1') {
            $token_billing_string = 'true';
        }

        $data['token_billing_string'] = $token_billing_string;

        return $data;
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    /**
     * Creates a new String Payment Intent.
     *
     * @param array $data The data array to be passed to Stripe
     * @return PaymentIntent       The Stripe payment intent object
     * @throws ApiErrorException
     */
    public function createPaymentIntent($data): ?PaymentIntent
    {
        $this->init();

        $meta = $this->stripe_connect_auth;

        return PaymentIntent::create($data, array_merge($meta, ['idempotency_key' => uniqid("st", true)]));
    }

    public function getPaymentIntent($payment_intent_id): ?PaymentIntent
    {
        $this->init();

        return PaymentIntent::retrieve(
            $payment_intent_id,
            $this->stripe_connect_auth
        );
    }

    /**
     * Returns a setup intent that allows the user
     * to enter card details without initiating a transaction.
     *
     * @return SetupIntent
     * @throws ApiErrorException
     */
    public function getSetupIntent(): SetupIntent
    {
        $this->init();

        $params = ['usage' => 'off_session'];
        $meta = $this->stripe_connect_auth;

        return SetupIntent::create($params, array_merge($meta, ['idempotency_key' => uniqid("st", true)]));
    }

    public function getSetupIntentId(string $id): SetupIntent
    {
        $this->init();

        return SetupIntent::retrieve(
            $id,
            $this->stripe_connect_auth
        );
    }

    /**
     * Returns the Stripe publishable key.
     * @return null|string The stripe publishable key
     */
    public function getPublishableKey(): ?string
    {
        return $this->company_gateway->getPublishableKey();
    }

    public function getCustomer($customer_id): ?Customer
    {
        $customer = Customer::retrieve($customer_id, $this->stripe_connect_auth);

        return $customer ?? null; // @phpstan-ignore-line
    }

    /**
     * Finds or creates a Stripe Customer object.
     *
     * @return null|Customer A Stripe customer object
     * @throws PresenterException
     * @throws ApiErrorException
     */
    public function findOrCreateCustomer(): ?Customer
    {
        $customer = null;

        $this->init();

        $client_gateway_token = ClientGatewayToken::query()
                                                  ->whereClientId($this->client->id)
                                                  ->whereCompanyGatewayId($this->company_gateway->id)
                                                  ->first();

        //Search by customer reference
        if ($client_gateway_token && $client_gateway_token->gateway_customer_reference) {
            $customer = Customer::retrieve($client_gateway_token->gateway_customer_reference, $this->stripe_connect_auth);

            if ($customer) {
                return $customer;
            }
        }

        //Search by email
        $searchResults = \Stripe\Customer::all([
            'email' => $this->client->present()->email(),
            'limit' => 2,
            'starting_after' => null,
        ], $this->stripe_connect_auth);

        if (count($searchResults) == 1) {
            $customer = $searchResults->data[0];
            // $this->updateStripeCustomer($customer);
            return $customer;
        }

        //Else create a new record
        $data['name'] = $this->client->present()->name();
        $data['phone'] = substr($this->client->present()->phone(), 0, 20);

        if (filter_var($this->client->present()->email(), FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $this->client->present()->email();
        }

        $data['address']['line1'] = $this->client->address1;
        $data['address']['line2'] = $this->client->address2;
        $data['address']['city'] = $this->client->city;
        $data['address']['postal_code'] = $this->client->postal_code;
        $data['address']['state'] = $this->client->state;
        $data['address']['country'] = $this->client->country ? $this->client->country->iso_3166_2 : '';

        $customer = Customer::create($data, array_merge($this->stripe_connect_auth, ['idempotency_key' => uniqid("st", true)]));

        if (! $customer) {
            throw new Exception('Unable to create gateway customer');
        }

        return $customer;
    }

    public function updateStripeCustomer($customer)
    {
        //Else create a new record
        $data['name'] = $this->client->present()->name();
        $data['phone'] = substr($this->client->present()->phone(), 0, 20);

        if (filter_var($this->client->present()->email(), FILTER_VALIDATE_EMAIL)) {
            $data['email'] = $this->client->present()->email();
        }

        $data['address']['line1'] = $this->client->address1;
        $data['address']['line2'] = $this->client->address2;
        $data['address']['city'] = $this->client->city;
        $data['address']['postal_code'] = $this->client->postal_code;
        $data['address']['state'] = $this->client->state;
        $data['address']['country'] = $this->client->country ? $this->client->country->iso_3166_2 : '';

        try {
            \Stripe\Customer::update($customer->id, $data, $this->stripe_connect_auth);
        } catch (Exception $e) {
            nlog('unable to update clients in Stripe');
        }
    }

    public function updateCustomer()
    {
        if ($this->client) {
            $customer = $this->findOrCreateCustomer();
            //Else create a new record
            $data['name'] = $this->client->present()->name();
            $data['phone'] = substr($this->client->present()->phone(), 0, 20);

            $data['address']['line1'] = $this->client->address1;
            $data['address']['line2'] = $this->client->address2;
            $data['address']['city'] = $this->client->city;
            $data['address']['postal_code'] = $this->client->postal_code;
            $data['address']['state'] = $this->client->state;
            $data['address']['country'] = $this->client->country ? $this->client->country->iso_3166_2 : '';

            $data['shipping']['name'] = $this->client->present()->name();
            $data['shipping']['address']['line1'] = $this->client->shipping_address1;
            $data['shipping']['address']['line2'] = $this->client->shipping_address2;
            $data['shipping']['address']['city'] = $this->client->shipping_city;
            $data['shipping']['address']['postal_code'] = $this->client->shipping_postal_code;
            $data['shipping']['address']['state'] = $this->client->shipping_state;
            $data['shipping']['address']['country'] = $this->client->shipping_country ? $this->client->shipping_country->iso_3166_2 : '';

            \Stripe\Customer::update($customer->id, $data, $this->stripe_connect_auth);
        }
    }

    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
        $this->init();

        $meta = $this->stripe_connect_auth;

        /** Response from Stripe SDK/API. */
        $response = null;

        try {
            $response = \Stripe\Refund::create([
                'charge' => $payment->transaction_reference,
                'amount' => $this->convertToStripeAmount($amount, $this->client->currency()->precision, $this->client->currency()),
            ], $meta);

            if (in_array($response->status, [$response::STATUS_SUCCEEDED, 'pending'])) {
                SystemLogger::dispatch(['server_response' => $response, 'data' => request()->all()], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_SUCCESS, SystemLog::TYPE_STRIPE, $this->client, $this->client->company);

                return [
                    'transaction_reference' => $response->charge,
                    'transaction_response' => json_encode($response),
                    'success' => $response->status == $response::STATUS_SUCCEEDED ? true : false,
                    'description' => $response->metadata,
                    'code' => $response,
                ];
            }

            SystemLogger::dispatch(['server_response' => $response, 'data' => request()->all()], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->client, $this->client->company);

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($response),
                'success' => false,
                'description' => $response->failure_reason,
                'code' => 422,
            ];
        } catch (Exception $e) {
            SystemLogger::dispatch(['server_response' => $response, 'data' => request()->all()], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_STRIPE, $this->client, $this->client->company);

            nlog($e->getMessage());

            return [
                'transaction_reference' => null,
                'transaction_response' => json_encode($response),
                'success' => false,
                'description' => $e->getMessage(),
                'code' => 422,
            ];
        }
    }

    public function verificationView(ClientGatewayToken $payment_method)
    {
        return $this->payment_method->verificationView($payment_method);
    }

    public function processVerification(Request $request, ClientGatewayToken $payment_method)
    {
        return $this->payment_method->processVerification($request, $payment_method);
    }

    public function processWebhookRequest(PaymentWebhookRequest $request)
    {
        nlog($request->all());

        if ($request->type === 'customer.source.updated') {
            $ach = new ACH($this);
            $ach->updateBankAccount($request->all());
        }

        if ($request->type === 'payment_intent.processing') {
            PaymentIntentProcessingWebhook::dispatch($request->data, $request->company_key, $this->company_gateway->id)->delay(now()->addSeconds(5));
            return response()->json([], 200);
        }

        //payment_intent.succeeded - this will confirm or cancel the payment
        if ($request->type === 'payment_intent.succeeded') {
            PaymentIntentWebhook::dispatch($request->data, $request->company_key, $this->company_gateway->id)->delay(now()->addSeconds(5));

            return response()->json([], 200);
        }

        if ($request->type === 'payment_intent.partially_funded') {
            PaymentIntentPartiallyFundedWebhook::dispatch($request->data, $request->company_key, $this->company_gateway->id)->delay(now()->addSeconds(5));

            return response()->json([], 200);
        }

        if (in_array($request->type, ['payment_intent.payment_failed', 'charge.failed'])) {
            PaymentIntentFailureWebhook::dispatch($request->data, $request->company_key, $this->company_gateway->id)->delay(now()->addSeconds(2));

            return response()->json([], 200);
        }

        if ($request->type === 'charge.refunded' && $request->data['object']['status'] == 'succeeded') {
            ChargeRefunded::dispatch($request->data, $request->company_key)->delay(now()->addSeconds(5));

            return response()->json([], 200);
        }

        if ($request->type === 'charge.succeeded') {
            foreach ($request->data as $transaction) {

                $payment = Payment::query()
                    ->where('company_id', $this->company_gateway->company_id)
                    ->where(function ($query) use ($transaction) {

                        if (isset($transaction['payment_intent'])) {
                            $query->where('transaction_reference', $transaction['payment_intent']);
                        }

                        if (isset($transaction['payment_intent']) && isset($transaction['id'])) {
                            $query->orWhere('transaction_reference', $transaction['id']);
                        }

                        if (!isset($transaction['payment_intent']) && isset($transaction['id'])) {
                            $query->where('transaction_reference', $transaction['id']);
                        }

                    })
                    ->first();


                if ($payment) {

                    if (isset($transaction['payment_method_details']['au_becs_debit'])) {
                        $payment->transaction_reference = $transaction['id'];
                    }

                    $payment->status_id = Payment::STATUS_COMPLETED;
                    $payment->save();
                }
            }
        } elseif ($request->type === 'source.chargeable') {
            $this->init();

            foreach ($request->data as $transaction) {
                if (! $request->data['object']['amount'] || empty($request->data['object']['amount'])) {
                    continue;
                }

                $charge = \Stripe\Charge::create([
                    'amount' => $request->data['object']['amount'],
                    'currency' => $request->data['object']['currency'],
                    'source' => $request->data['object']['id'],
                ], $this->stripe_connect_auth);

                if ($charge->captured) {


                    $payment = Payment::query()
                        ->where('company_id', $this->company_gateway->company_id)
                        ->where(function ($query) use ($transaction) {

                            if (isset($transaction['payment_intent'])) {
                                $query->where('transaction_reference', $transaction['payment_intent']);
                            }

                            if (isset($transaction['payment_intent']) && isset($transaction['id'])) {
                                $query->orWhere('transaction_reference', $transaction['id']);
                            }

                            if (!isset($transaction['payment_intent']) && isset($transaction['id'])) {
                                $query->where('transaction_reference', $transaction['id']);
                            }

                        })
                        ->first();



                    if ($payment) {
                        $payment->status_id = Payment::STATUS_COMPLETED;
                        $payment->save();
                    }
                }
            }
        } elseif ($request->type === "payment_method.automatically_updated") {
            // Will notify customer on updated information
            return response()->json([], 200);
        } elseif ($request->type === "mandate.updated") {
            if ($request->data['object']['status'] == "active") {
                // Check if payment method existsn
                $payment_method = (string) $request->data['object']['payment_method'];

                $clientgateway = ClientGatewayToken::query()
                    ->where('token', $payment_method)
                    ->first();

                if ($clientgateway) {
                    $meta = $clientgateway->meta;
                    $meta->state = 'authorized';
                    $clientgateway->meta = $meta;
                    $clientgateway->save();
                }

                return response()->json([], 200);
            } elseif ($request->data['object']['status'] == "inactive" && $request->data['object']['payment_method']) {
                // Delete payment method
                $clientgateway = ClientGatewayToken::query()
                    ->where('token', $request->data['object']['payment_method'])
                    ->first();

                if ($clientgateway) {
                    $clientgateway->delete();
                }

                return response()->json([], 200);
            } elseif ($request->data['object']['status'] == "pending") {
                return response()->json([], 200);
            }
        }

        return response()->json([], 200);
    }

    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
        return (new Charge($this))->tokenBilling($cgt, $payment_hash);
    }

    /**
     * Attach Stripe payment method to Stripe client.
     *
     * @param string $payment_method
     * @param mixed $customer
     *
     * @return void
     */
    public function attach(string $payment_method, $customer): void
    {
        $this->init();

        try {
            $stripe_payment_method = $this->getStripePaymentMethod($payment_method);
            $stripe_payment_method->attach(['customer' => $customer->id], $this->stripe_connect_auth);
        } catch (ApiErrorException | Exception $e) {
            nlog($e->getMessage());

            SystemLogger::dispatch(
                [
                'server_response' => $e->getMessage(),
                'data' => request()->all(),
            ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_STRIPE,
                $this->client,
                $this->client->company
            );
        }
    }

    /**
     * Detach payment method from the Stripe.
     * https://stripe.com/docs/api/payment_methods/detach
     *
     * @param ClientGatewayToken $token
     * @return void
     */
    public function detach(ClientGatewayToken $token)
    {
        $this->init();

        try {
            $pm = $this->getStripePaymentMethod($token->token);
            $pm->detach([], $this->stripe_connect_auth);
        } catch (ApiErrorException | Exception $e) {
            nlog($e->getMessage());

            SystemLogger::dispatch(
                [
                'server_response' => $e->getMessage(),
                'data' => request()->all(),
            ],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_FAILURE,
                SystemLog::TYPE_STRIPE,
                $this->client,
                $this->client->company
            );
        }
    }

    public function getCompanyGatewayId(): int
    {
        return $this->company_gateway->id;
    }

    /**
     * Retrieve payment method from Stripe.
     *
     * @param string $source
     *
     * @return PaymentMethod|void
     */
    public function getStripePaymentMethod(string $source)
    {
        try {
            return PaymentMethod::retrieve($source, $this->stripe_connect_auth);
        } catch (ApiErrorException | Exception $e) {
            throw new PaymentFailed($e->getMessage(), $e->getCode());
        }
    }

    public function getAllConnectedAccounts()
    {
        $this->init();

        return Account::all();
    }

    public function setClientFromCustomer($customer): self
    {
        $this->client = ClientGatewayToken::query()->where('gateway_customer_reference', $customer)->first()->client;

        return $this;
    }

    /**
     * Imports stripe customers and their payment methods
     * Matches users in the system based on the $match_on_record
     * ie. email
     *
     * Phone
     * Email
     */
    public function importCustomers()
    {
        return (new ImportCustomers($this))->run();
        //match clients based on the gateway_customer_reference column
    }

    public function importMatchedClients()
    {
        return (new ImportCustomers($this))->match();
    }

    public function importCustomer($customer_id)
    {
        return (new ImportCustomers($this))->importCustomer($customer_id);
    }

    public function verifyConnect()
    {
        return (new Verify($this))->run();
    }

    public function setApplePayDomain($domain)
    {
        $this->init();

        \Stripe\ApplePayDomain::create([
            'domain_name' => $domain,
        ], $this->stripe_connect_auth);
    }

    public function disconnect()
    {
        if (! $this->stripe_connect) {
            return true;
        }

        if (! strlen($this->company_gateway->getConfigField('account_id')) > 1) {
            throw new StripeConnectFailure('Stripe Connect has not been configured');
        }

        Stripe::setApiKey(config('ninja.ninja_stripe_key'));

        try {
            \Stripe\OAuth::deauthorize([
                'client_id' => config('ninja.ninja_stripe_client_id'),
                'stripe_user_id' => $this->company_gateway->getConfigField('account_id'),
            ]);

            $config = $this->company_gateway->getConfig();
            $config->account_id = '';
            $this->company_gateway->setConfig($config);
            $this->company_gateway->save();
        } catch (\Exception $e) {
            throw new StripeConnectFailure('Unable to disconnect Stripe Connect');
        }

        return response()->json(['message' => 'success'], 200);
    }

    public function decodeUnicodeString($string)
    {
        return html_entity_decode($string, ENT_QUOTES, 'UTF-8');
        // return iconv("UTF-8", "ISO-8859-1//TRANSLIT", $this->decode_encoded_utf8($string));
    }

    public function decode_encoded_utf8($string)
    {
        return preg_replace_callback('#\\\\u([0-9a-f]{4})#ism', function ($matches) {
            return mb_convert_encoding(pack('H*', $matches[1]), 'UTF-8', 'UCS-2BE');
        }, $string);
    }

    public function auth(): bool
    {
        $this->init();

        try {
            $this->verifyConnect();
            return true;
        } catch (\Exception $e) {

        }

        return false;

    }

}

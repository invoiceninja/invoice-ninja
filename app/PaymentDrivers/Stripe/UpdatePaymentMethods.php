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

use App\Factory\ClientGatewayTokenFactory;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Traits\MakesHash;
use Stripe\Customer;
use Stripe\PaymentMethod;

class UpdatePaymentMethods
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function updateMethods(Customer $customer, Client $client)
    {
        $this->stripe->client = $client;

        $card_methods = PaymentMethod::all(
            [
            'customer' => $customer->id,
            'type' => 'card',
        ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($card_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::CREDIT_CARD);
        }

        $link_methods = PaymentMethod::all(
            [
                'customer' => $customer->id,
                'type' => 'link',
            ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($link_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::CREDIT_CARD);
        }

        $alipay_methods = PaymentMethod::all(
            [
            'customer' => $customer->id,
            'type' => 'alipay',
        ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($alipay_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::ALIPAY);
        }

        $sofort_methods = PaymentMethod::all(
            [
            'customer' => $customer->id,
            'type' => 'sofort',
        ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($sofort_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::SOFORT);
        }

        $sepa_methods = PaymentMethod::all(
            [
                    'customer' => $customer->id,
                    'type' => 'sepa_debit',
                ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($sepa_methods as $method) {
            $this->addOrUpdateCard($method, $customer->id, $client, GatewayType::SEPA);
        }

        $this->importBankAccounts($customer, $client);

        $this->importPMBankAccounts($customer, $client);
    }

    /* ACH may also be nested inside Payment Methods.*/
    public function importPMBankAccounts($customer, $client)
    {
        $bank_methods = \Stripe\PaymentMethod::all(
            [
            'customer' => $customer->id,
            'type' => 'us_bank_account',
        ],
            $this->stripe->stripe_connect_auth
        );

        foreach ($bank_methods->data as $method) {
            $token = ClientGatewayToken::query()->where([
                'gateway_customer_reference' => $customer->id,
                'token' => $method->id,
                'client_id' => $client->id,
                'company_id' => $client->company_id,
            ])->first();

            /* Already exists return */
            if ($token) {
                $meta = $token->meta;
                $meta->state = 'authorized';
                $token->meta = $meta;
                $token->save();

                continue;
            }

            $bank_account = $method['us_bank_account'];

            $payment_meta = new \stdClass();
            $payment_meta->brand = (string) \sprintf('%s (%s)', $bank_account->bank_name, ctrans('texts.ach'));
            $payment_meta->last4 = (string) $bank_account->last4;
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->state = 'authorized';

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $additional_data = ['gateway_customer_reference' => $customer->id];

            if ($customer->default_source === $method->id) {
                $additional_data = ['gateway_customer_reference' => $customer->id, 'is_default' => 1];
            }

            $this->stripe->storeGatewayToken($data, $additional_data);
        }
    }

    public function importBankAccounts($customer, $client)
    {
        $sources = $customer->sources ?? false;

        if (!$customer || is_null($sources) || !$sources || !property_exists($sources, 'data')) {
            return;
        }

        foreach ($sources->data as $method) {
            $token_exists = ClientGatewayToken::query()->where([
                'gateway_customer_reference' => $customer->id,
                'token' => $method->id,
                'client_id' => $client->id,
                'company_id' => $client->company_id,
            ])->exists();

            /* Already exists return */
            if ($token_exists) {
                continue;
            }

            $payment_meta = new \stdClass();
            $payment_meta->brand = (string) \sprintf('%s (%s)', $method->bank_name, ctrans('texts.ach'));
            $payment_meta->last4 = (string) $method->last4;
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->state = $method->status;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $method->id,
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $additional_data = ['gateway_customer_reference' => $customer->id];

            if ($customer->default_source === $method->id) {
                $additional_data = ['gateway_customer_reference' => $customer->id, 'is_default' => 1];
            }

            $this->stripe->storeGatewayToken($data, $additional_data);
        }
    }

    private function addOrUpdateCard(PaymentMethod $method, $customer_reference, Client $client, $type_id)
    {
        $token_exists = ClientGatewayToken::query()->where([
            'gateway_customer_reference' => $customer_reference,
            'token' => $method->id,
            'client_id' => $client->id,
            'company_id' => $client->company_id,
        ])->exists();

        /* Already exists return */
        if ($token_exists) {
            return;
        }

        /* Ignore Expired cards */
        if ($method->card && $method->card->exp_year <= date('Y') && $method->card->exp_month < date('m')) {
            return;
        }

        $cgt = ClientGatewayTokenFactory::create($client->company_id);
        $cgt->client_id = $client->id;
        $cgt->token = $method->id;
        $cgt->gateway_customer_reference = $customer_reference;
        $cgt->company_gateway_id = $this->stripe->company_gateway->id;
        $cgt->gateway_type_id = $type_id;
        $cgt->meta = $this->buildPaymentMethodMeta($method, $type_id);
        $cgt->save();
    }

    private function buildPaymentMethodMeta(PaymentMethod $method, $type_id)
    {

        switch ($type_id) {
            case GatewayType::CREDIT_CARD:

                if ($method->type == 'link') {
                    return new \stdClass();
                }

                /**
                 * @class \Stripe\PaymentMethod $method
                 * @property \Stripe\StripeObject $card
                 * @class \Stripe\StripeObject $card
                 * @property string $exp_year
                 * @property string $exp_month
                 * @property string $brand
                 * @property string $last4
                */

                $payment_meta = new \stdClass();
                $payment_meta->exp_month = (string) $method->card->exp_month;
                $payment_meta->exp_year = (string) $method->card->exp_year;
                $payment_meta->brand = (string) $method->card->brand;
                $payment_meta->last4 = (string) $method->card->last4;
                $payment_meta->type = GatewayType::CREDIT_CARD;

                return $payment_meta;
            case GatewayType::ALIPAY:
            case GatewayType::SOFORT:

                return new \stdClass();

            case GatewayType::SEPA:

                $payment_meta = new \stdClass();
                $payment_meta->brand = (string) \sprintf('%s (%s)', $method->sepa_debit->bank_code, ctrans('texts.sepa'));
                $payment_meta->last4 = (string) $method->sepa_debit->last4;
                $payment_meta->state = 'authorized';
                $payment_meta->type = GatewayType::SEPA;

                return $payment_meta;
            default:

                break;
        }
    }
}

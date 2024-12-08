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
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Stripe\PaymentIntent;

class BankTransfer implements LivewireMethodInterface
{
    use MakesHash;

    /** @var StripePaymentDriver */
    public $stripe;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);
        $data = $this->paymentData($data);

        return render('gateways.stripe.bank_transfer.pay', $data);
    }

    /**
     * Resolve the bank type based on the currency
     *
     * @return array
     */
    private function resolveBankType()
    {
        return match ($this->stripe->client->currency()->code) { //@phpstan-ignore-line
            'GBP' =>  ['type' => 'gb_bank_transfer'],
            'EUR' => ['type' => 'eu_bank_transfer', 'eu_bank_transfer' => ['country' => $this->stripe->client->country->iso_3166_2]],
            'JPY' => ['type' => 'jp_bank_transfer'],
            'MXN' => ['type' => 'mx_bank_transfer'],
            'USD' => ['type' => 'us_bank_transfer'],
        };
    }

    /**
     * Return URL
     *
     * @return string
     */
    private function buildReturnUrl(): string
    {
        return route('client.payments.response.get', [
            'company_gateway_id' => $this->stripe->company_gateway->id,
            'payment_hash' => $this->stripe->payment_hash->hash,
            'payment_method_id' => GatewayType::DIRECT_DEBIT,
        ]);
    }


    /**
     * paymentResponse
     *
     * @param  PaymentResponseRequest $request
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->stripe->init();

        $this->stripe->setPaymentHash($request->getPaymentHash());
        $this->stripe->client = $this->stripe->payment_hash->fee_invoice->client;

        if ($request->payment_intent) {
            $pi = \Stripe\PaymentIntent::retrieve(
                $request->payment_intent,
                $this->stripe->stripe_connect_auth
            );

            if (in_array($pi->status, ['succeeded', 'processing'])) {
                $payment = $this->processSuccesfulRedirect($pi);
                redirect()->route('client.payments.show', ['payment' => $this->stripe->encodePrimaryKey($payment->id)]);
            }

            /*  Create a pending payment */
            if ($pi->status == 'requires_action' && $pi->next_action->type == 'display_bank_transfer_instructions') { //@phpstan-ignore-line
                match ($pi->next_action->display_bank_transfer_instructions->currency) { //@phpstan-ignore-line
                    'mxn' => $data['bank_details'] = $this->formatDataforMx($pi),
                    'gbp' => $data['bank_details'] = $this->formatDataforUk($pi),
                    'eur' => $data['bank_details'] = $this->formatDataforEur($pi),
                    'jpy' => $data['bank_details'] = $this->formatDataforJp($pi),
                    'usd' => $data['bank_details'] = $this->formatDataforUs($pi),
                };

                $payment = $this->processSuccesfulRedirect($pi);

                return render('gateways.stripe.bank_transfer.bank_details_container', $data);
            }

            return $this->processUnsuccesfulRedirect();
        }
    }

    /**
     * formatDataForUk
     *
     * @param  PaymentIntent $pi
     * @return array
     */
    public function formatDataForUk(PaymentIntent $pi): array
    {
        return  [
            'amount' => Number::formatMoney($this->stripe->convertFromStripeAmount($pi->next_action->display_bank_transfer_instructions->amount_remaining, $this->stripe->client->currency()->precision, $this->stripe->client->currency()), $this->stripe->client),
            'account_holder_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->sort_code->account_holder_name,
            'account_number' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->sort_code->account_number,
            'sort_code' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->sort_code->sort_code,
            'reference' => $pi->next_action->display_bank_transfer_instructions->reference,
            'description' => $pi->description,
            'gateway'   => $this->stripe->company_gateway,
            'currency' => $pi->next_action->display_bank_transfer_instructions->currency,

        ];
    }

    /**
     * formatDataforMx
     *
     * @param  PaymentIntent $pi
     * @return array
     */
    public function formatDataforMx(PaymentIntent $pi): array
    {
        return  [
            'amount' => Number::formatMoney($this->stripe->convertFromStripeAmount($pi->next_action->display_bank_transfer_instructions->amount_remaining, $this->stripe->client->currency()->precision, $this->stripe->client->currency()), $this->stripe->client),
            'account_holder_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->spei->bank_name,
            'account_number' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->spei->bank_code,
            'sort_code' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->spei->clabe,
            'reference' => $pi->next_action->display_bank_transfer_instructions->reference,
            'description' => $pi->description,
            'gateway'   => $this->stripe->company_gateway,
            'currency' => $pi->next_action->display_bank_transfer_instructions->currency,

        ];
    }


    /**
     * formatDataforEur
     *
     * @param  mixed $pi
     * @return array
     */
    public function formatDataforEur(PaymentIntent $pi): array
    {
        return  [
                    'amount' => Number::formatMoney($this->stripe->convertFromStripeAmount($pi->next_action->display_bank_transfer_instructions->amount_remaining, $this->stripe->client->currency()->precision, $this->stripe->client->currency()), $this->stripe->client),
                    'account_holder_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->iban->account_holder_name,
                    'account_number' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->iban->iban,
                    'sort_code' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->iban->bic,
                    'reference' => $pi->next_action->display_bank_transfer_instructions->reference,
                    'description' => $pi->description,
                    'gateway'   => $this->stripe->company_gateway,
                    'currency' => $pi->next_action->display_bank_transfer_instructions->currency,

                ];
    }

    /**
     *
     * @param PaymentIntent $pi
     * @return array
     */
    public function formatDataforJp(PaymentIntent $pi): array
    {
        return  [
                    'amount' => Number::formatMoney($this->stripe->convertFromStripeAmount($pi->next_action->display_bank_transfer_instructions->amount_remaining, $this->stripe->client->currency()->precision, $this->stripe->client->currency()), $this->stripe->client),
                    'account_holder_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->zengin->account_holder_name,
                    'account_number' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->zengin->account_number,
                    'account_type' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->zengin->account_type,
                    'bank_code' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->zengin->bank_code,
                    'bank_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->zengin->bank_name,
                    'branch_code' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->zengin->branch_code,
                    'branch_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->zengin->branch_name,
                    'reference' => $pi->next_action->display_bank_transfer_instructions->reference,
                    'description' => $pi->description,
                    'gateway'   => $this->stripe->company_gateway,
                    'currency' => $pi->next_action->display_bank_transfer_instructions->currency,

                ];
    }


    /**
     *
     * @param PaymentIntent $pi
     * @return array
     */
    public function formatDataforUs(PaymentIntent $pi): array
    {
        return  [
                    'amount' => Number::formatMoney($this->stripe->convertFromStripeAmount($pi->next_action->display_bank_transfer_instructions->amount_remaining, $this->stripe->client->currency()->precision, $this->stripe->client->currency()), $this->stripe->client),
                    'account_holder_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->aba->bank_name,
                    'account_number' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->aba->account_number,
                    'bank_name' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->aba->bank_name,
                    'sort_code' => $pi->next_action->display_bank_transfer_instructions->financial_addresses[0]->aba->routing_number,
                    'reference' => $pi->next_action->display_bank_transfer_instructions->reference,
                    'description' => $pi->description,
                    'gateway'   => $this->stripe->company_gateway,
                    'currency' => $pi->next_action->display_bank_transfer_instructions->currency,

                ];
    }


    /**
     * processSuccesfulRedirect
     *
     * @param  PaymentIntent $payment_intent
     * @return Payment
     */
    public function processSuccesfulRedirect(PaymentIntent $payment_intent): Payment
    {
        $this->stripe->init();

        $data = [
            'payment_method' => $payment_intent->payment_method,
            'payment_type' => PaymentType::STRIPE_BANK_TRANSFER,
            'amount' => $this->stripe->convertFromStripeAmount($this->stripe->payment_hash->data->stripe_amount, $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'transaction_reference' => $payment_intent->id,
            'gateway_type_id' => GatewayType::DIRECT_DEBIT,

        ];

        $payment = $this->stripe->createPayment($data, $payment_intent->status == 'succeeded' ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $this->stripe->payment_hash->data, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $this->stripe->client,
            $this->stripe->client->company,
        );

        return  $payment;
    }

    /**
     * processUnsuccesfulRedirect
     *
     * @return void
     */
    public function processUnsuccesfulRedirect()
    {
        $server_response = $this->stripe->payment_hash->data;

        $this->stripe->sendFailureMail($server_response->redirect_status);

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

    public function paymentData(array $data): array
    {
        $this->stripe->init();

        $intent = \Stripe\PaymentIntent::create([
            'amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency()),
            'currency' => $this->stripe->client->currency()->code,
            'customer' => $this->stripe->findOrCreateCustomer()->id,
            'description' => $this->stripe->getDescription(false),
            'payment_method_types' => ['customer_balance'],
            'payment_method_data' => [
                'type' => 'customer_balance',
            ],
            'payment_method_options' => [
                'customer_balance' => [
                'funding_type' => 'bank_transfer',
                'bank_transfer' => $this->resolveBankType()
                ],
            ],
            'metadata' => [
                'payment_hash' => $this->stripe->payment_hash->hash,
                'gateway_type_id' => GatewayType::DIRECT_DEBIT,
            ],
        ], $this->stripe->stripe_connect_auth);


        $this->stripe->payment_hash->data = array_merge((array) $this->stripe->payment_hash->data, ['stripe_amount' => $this->stripe->convertToStripeAmount($data['total']['amount_with_fee'], $this->stripe->client->currency()->precision, $this->stripe->client->currency())]);
        $this->stripe->payment_hash->save();

        $data = [];
        $data['return_url'] = $this->buildReturnUrl();
        $data['gateway'] = $this->stripe;
        $data['client_secret'] = $intent ? $intent->client_secret : false;

        return $data;
    }

    public function livewirePaymentView(array $data): string
    {
        return 'gateways.stripe.bank_transfer.pay_livewire';
    }
}

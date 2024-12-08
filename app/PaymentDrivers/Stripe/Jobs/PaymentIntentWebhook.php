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

namespace App\PaymentDrivers\Stripe\Jobs;

use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Modules\Admin\Jobs\Stripe\CampaignCharge;

class PaymentIntentWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

    public $tries = 1; //number of retries

    public $deleteWhenMissingModels = true;

    public $stripe_request;

    public $company_key;

    private $company_gateway_id;

    public $payment_completed = false;

    public function __construct($stripe_request, $company_key, $company_gateway_id)
    {
        $this->stripe_request = $stripe_request;
        $this->company_key = $company_key;
        $this->company_gateway_id = $company_gateway_id;
    }

    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);
        
        $company = Company::query()->where('company_key', $this->company_key)->first();

        foreach ($this->stripe_request as $transaction) {

            $ninja_promo = data_get($transaction, 'charges.data.0.metadata.product', false);

            if ($ninja_promo && class_exists(\Modules\Admin\Jobs\Stripe\CampaignCharge::class)) {
                \Modules\Admin\Jobs\Stripe\CampaignCharge::dispatch(data_get($transaction, 'charges.data.0'));
                continue;
            }

            $payment = Payment::query()
                ->where('company_id', $company->id)
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

                $this->payment_completed = true;
            }
        }


        if ($this->payment_completed) {
            return;
        }

        $company_gateway = CompanyGateway::query()->find($this->company_gateway_id);

        if (!$company_gateway) {
            return;
        }

        $stripe_driver = $company_gateway->driver()->init();

        $charge_id = false;


        if (isset($this->stripe_request['object']['charges']) && optional($this->stripe_request['object']['charges']['data'][0])['id']) {
            $charge_id = $this->stripe_request['object']['charges']['data'][0]['id'];
        } // API VERSION 2018
        elseif (isset($this->stripe_request['object']['latest_charge'])) {
            $charge_id = $this->stripe_request['object']['latest_charge'];
        } // API VERSION 2022-11-15


        if (!$charge_id) {
            nlog("could not resolve charge");
            return;
        }

        $pi = \Stripe\PaymentIntent::retrieve($this->stripe_request['object']['id'], $stripe_driver->stripe_connect_auth);

        $charge = \Stripe\Charge::retrieve($charge_id, $stripe_driver->stripe_connect_auth);

        if (!$charge) {
            nlog("no charge found");
            nlog($this->stripe_request);
            return;
        }

        /** @var \App\Models\Company $company **/
        $company = Company::where('company_key', $this->company_key)->first();

        /** @var \App\Models\Payment $payment **/
        $payment = Payment::query()
                         ->where('company_id', $company->id)
                         ->where('transaction_reference', $charge['id'])
                         ->first();

        //return early
        if ($payment && $payment->status_id == Payment::STATUS_COMPLETED) {
            nlog(" payment found and status correct - returning ");
            return;
        } elseif ($payment) {
            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->save();
        }

        $hash = isset($charge['metadata']['payment_hash']) ? $charge['metadata']['payment_hash'] : false;

        if (!$hash) {
            return;
        }

        $payment_hash = PaymentHash::where('hash', $hash)->first();

        if (!$payment_hash) {
            return;
        }

        $stripe_driver->client = $payment_hash->fee_invoice->client;

        $meta = [
            'gateway_type_id' => $pi['metadata']['gateway_type_id'],
            'transaction_reference' => $charge['id'],
            'customer' => $charge['customer'],
            'payment_method' => $charge['payment_method'],
            'card_details' => isset($charge['payment_method_details']['card']['brand']) ? $charge['payment_method_details']['card']['brand'] : PaymentType::CREDIT_CARD_OTHER
        ];

        SystemLogger::dispatch(
            ['response' => $this->stripe_request, 'data' => []],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            null,
            $company,
        );

        if (isset($pi['allowed_source_types']) && in_array('card', $pi['allowed_source_types'])) {
            $invoice = Invoice::with('client')->withTrashed()->find($payment_hash->fee_invoice_id);
            $client = $invoice->client;

            if ($invoice->is_deleted) {
                return;
            }

            $this->updateCreditCardPayment($payment_hash, $client, $meta);
        } elseif (isset($pi['payment_method_types']) && in_array('card', $pi['payment_method_types'])) {
            $invoice = Invoice::with('client')->withTrashed()->find($payment_hash->fee_invoice_id);
            $client = $invoice->client;

            if ($invoice->is_deleted) {
                return;
            }

            $this->updateCreditCardPayment($payment_hash, $client, $meta);
        } elseif (isset($pi['payment_method_types']) && in_array('us_bank_account', $pi['payment_method_types'])) {
            $invoice = Invoice::with('client')->withTrashed()->find($payment_hash->fee_invoice_id);
            $client = $invoice->client;

            if ($invoice->is_deleted) {
                return;
            }

            $this->updateAchPayment($payment_hash, $client, $meta);
        } elseif (isset($pi['payment_method_types']) && in_array('bacs_debit', $pi['payment_method_types'])) {
            return;
        }
    }

    private function updateAchPayment($payment_hash, $client, $meta)
    {
        $company_gateway = CompanyGateway::query()->find($this->company_gateway_id);
        $payment_method_type = $meta['gateway_type_id'];
        $driver = $company_gateway->driver($client)->init()->setPaymentMethod($payment_method_type);

        $payment_hash->data = array_merge((array) $payment_hash->data, $this->stripe_request);
        $payment_hash->save();
        $driver->setPaymentHash($payment_hash);

        $data = [
            'payment_method' => $payment_hash->data->object->payment_method,
            'payment_type' => PaymentType::ACH,
            'amount' => $payment_hash->data->amount_with_fee,
            'transaction_reference' => $meta['transaction_reference'],
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $driver->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe_request, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $client,
            $client->company,
        );

        try {
            $customer = $driver->getCustomer($meta['customer']);
            $method = $driver->getStripePaymentMethod($meta['payment_method']);
            $payment_method = $meta['payment_method'];

            $token_exists = ClientGatewayToken::where([
                'gateway_customer_reference' => $customer->id,
                'token' => $payment_method,
                'client_id' => $client->id,
                'company_id' => $client->company_id,
            ])->exists();

            /* Already exists return */
            if ($token_exists) {
                return;
            }

            $payment_meta = new \stdClass();
            $payment_meta->brand = (string) \sprintf('%s (%s)', $method->us_bank_account['bank_name'], ctrans('texts.ach'));
            $payment_meta->last4 = (string) $method->us_bank_account['last4'];
            $payment_meta->type = GatewayType::BANK_TRANSFER;
            $payment_meta->state = 'verified';

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $payment_method,
                'payment_method_id' => GatewayType::BANK_TRANSFER,
            ];

            $additional_data = ['gateway_customer_reference' => $customer->id];

            if ($customer->default_source === $method->id) {
                $additional_data = ['gateway_customer_reference' => $customer->id, 'is_default' => 1];
            }

            $driver->storeGatewayToken($data, $additional_data);
        } catch (\Exception $e) {
            nlog("failed to import payment methods");
            nlog($e->getMessage());
        }
    }

    private function updateCreditCardPayment($payment_hash, $client, $meta)
    {
        $company_gateway = CompanyGateway::query()->find($this->company_gateway_id);
        $payment_method_type = $meta['gateway_type_id'];
        $driver = $company_gateway->driver($client)->init()->setPaymentMethod($payment_method_type);

        $payment_hash->data = array_merge((array) $payment_hash->data, $this->stripe_request);
        $payment_hash->save();
        $driver->setPaymentHash($payment_hash);

        $data = [
            'payment_method' => $payment_hash->data->object->payment_method,
            'payment_type' => PaymentType::parseCardType(strtolower($meta['card_details'])) ?: PaymentType::CREDIT_CARD_OTHER,
            'amount' => $payment_hash->data->amount_with_fee,
            'transaction_reference' => $meta['transaction_reference'],
            'gateway_type_id' => GatewayType::CREDIT_CARD,
        ];

        $payment = $driver->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $this->stripe_request, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_STRIPE,
            $client,
            $client->company,
        );
    }

    public function failed($exception = null)
    {
        if ($exception) {
            nlog($exception->getMessage());
        }

        config(['queue.failed.driver' => null]);
    }
}

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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentIntentPartiallyFundedWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    public function __construct(public array $stripe_request, public string $company_key, public int $company_gateway_id)
    {
    }

    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $company = Company::query()->where('company_key', $this->company_key)->first();

        foreach ($this->stripe_request as $transaction) {
            $payment_intent = false;

            if (array_key_exists('payment_intent', $transaction)) {
                $payment_intent = $transaction['payment_intent'];
            } else {
                $payment_intent = $transaction['id'];
            }

            if (!$payment_intent) {
                nlog("payment intent not found");
                nlog($transaction);
                return;
            }

            $payment = Payment::query()
            ->where('company_id', $company->id)
            ->where('transaction_reference', $payment_intent)
            ->first();

            if (!$payment) {
                nlog("paymentintent found but no payment");
            }

            $company_gateway = CompanyGateway::query()->find($this->company_gateway_id);
            $stripe_driver = $company_gateway->driver()->init();

            $hash = isset($transaction['metadata']['payment_hash']) ? $transaction['metadata']['payment_hash'] : false;

            if (!$hash) {
                nlog("no hash found");
                return;
            }

            $payment_hash = PaymentHash::where('hash', $hash)->first();

            if (!$payment_hash) {
                nlog("no payment hash found");
                return;
            }

            $stripe_driver->client = $payment_hash->fee_invoice->client;

            $pi = \Stripe\PaymentIntent::retrieve($payment_intent, $stripe_driver->stripe_connect_auth);

            $amount = $stripe_driver->convertFromStripeAmount($pi->amount, $stripe_driver->client->currency()->precision, $stripe_driver->client->currency());
            $amount_received =  $stripe_driver->convertFromStripeAmount($pi->amount_received, $stripe_driver->client->currency()->precision, $stripe_driver->client->currency());

            //at this point we just send notification emails to the client and advise of over/under payments.
        }
    }
}

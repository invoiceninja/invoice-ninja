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

use App\Jobs\Mail\PaymentFailedMailer;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PaymentIntentFailureWebhook implements ShouldQueue
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
        nlog("payment intent failed");

        MultiDB::findAndSetDbByCompanyKey($this->company_key);
        nlog($this->stripe_request);

        $company = Company::query()->where('company_key', $this->company_key)->first();

        foreach ($this->stripe_request as $transaction) {

            nlog($transaction);

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
                $client = $payment->client;

                if ($payment->status_id == Payment::STATUS_PENDING) {
                    $payment->service()->deletePayment();
                }

                $payment->status_id = Payment::STATUS_FAILED;
                $payment->save();

                $payment_hash = PaymentHash::query()->where('payment_id', $payment->id)->first();

                if ($payment_hash) {
                    $error = ctrans('texts.client_payment_failure_body', [
                        'invoice' => implode(',', $payment->invoices->pluck('number')->toArray()),
                        'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total, ]);
                } else {
                    $error = 'Payment for '.$payment->client->present()->name()." for {$payment->amount} failed";
                }

                if (array_key_exists('failure_message', $transaction)) {
                    $error .= "\n\n".$transaction['failure_message'];
                }

                PaymentFailedMailer::dispatch(
                    $payment_hash,
                    $client->company,
                    $client,
                    $error
                );
            }
        }
    }
}

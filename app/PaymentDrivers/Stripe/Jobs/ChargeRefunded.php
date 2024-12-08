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

use App\Models\Company;
use App\Models\Payment;
use App\Libraries\MultiDB;
use App\Models\PaymentHash;
use App\Services\Email\Email;
use Illuminate\Bus\Queueable;
use App\Models\CompanyGateway;
use App\Services\Email\EmailObject;
use Illuminate\Support\Facades\App;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;
use App\PaymentDrivers\Stripe\Utilities;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class ChargeRefunded implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

    public $tries = 1; //number of retries

    public $deleteWhenMissingModels = true;

    public $payment_completed = false;

    public function __construct(public array $stripe_request, private string $company_key)
    {
    }

    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->company_key);
        nlog($this->stripe_request);

        $company = Company::query()->where('company_key', $this->company_key)->first();

        $source = $this->stripe_request['object'];
        $charge_id = $source['id'];
        $amount_refunded = $source['amount_refunded'] ?? 0;

        $payment_hash_key = $source['metadata']['payment_hash'] ?? null;

        if (is_null($payment_hash_key)) {
            nlog("charge.refunded not found");
            return;
        }

        $payment_hash = PaymentHash::query()->where('hash', $payment_hash_key)->first();
        $company_gateway = $payment_hash->payment->company_gateway;

        $stripe_driver = $company_gateway->driver()->init();

        $stripe_driver->payment_hash = $payment_hash;

        /** @var \App\Models\Payment $payment **/
        $payment = Payment::query()
                         ->withTrashed()
                         ->where('company_id', $company->id)
                         ->where('transaction_reference', $charge_id)
                         ->first();

        //don't touch if already refunded
        if (!$payment || $payment->status_id == Payment::STATUS_REFUNDED || $payment->is_deleted) {
            return;
        }

        $stripe_driver->client = $payment->client;

        $amount_refunded = $stripe_driver->convertFromStripeAmount($amount_refunded, $payment->client->currency()->precision, $payment->client->currency());

        if ($payment->status_id == Payment::STATUS_PENDING) {
            $payment->service()->deletePayment();
            $payment->status_id = Payment::STATUS_FAILED;
            $payment->save();
            return;
        }

        usleep(rand(200000, 300000));
        $payment = $payment->fresh();

        if ($payment->status_id == Payment::STATUS_PARTIALLY_REFUNDED) {
            //determine the delta in the refunded amount - how much has already been refunded and only apply the delta.

            if (floatval($payment->refunded) >= floatval($amount_refunded)) {
                return;
            }

            $amount_refunded -= $payment->refunded;

        }

        $invoice_collection = $payment->paymentables
                    ->where('paymentable_type', 'invoices')
                    ->map(function ($pivot) {
                        return [
                            'invoice_id' => $pivot->paymentable_id,
                            'amount' => $pivot->amount - $pivot->refunded
                        ];
                    });

        if ($invoice_collection->count() == 1 && $invoice_collection->first()['amount'] >= $amount_refunded) {
            //If there is only one invoice- and we are refunding _less_ than the amount of the invoice, we can just refund the payment

            $invoice_collection = $payment->paymentables
                    ->where('paymentable_type', 'invoices')
                    ->map(function ($pivot) use ($amount_refunded) {
                        return [
                            'invoice_id' => $pivot->paymentable_id,
                            'amount' => $amount_refunded
                        ];
                    });

        } elseif ($invoice_collection->sum('amount') != $amount_refunded) {

            $refund_text = "A partial refund was processed for Payment #{$payment_hash->payment->number}. <br><br> This payment is associated with multiple invoices, so you will need to manually apply the refund to the correct invoice/s.";

            App::setLocale($payment_hash->payment->company->getLocale());

            $mo = new EmailObject();
            $mo->subject = "Refund processed in Stripe for multiple invoices, action required.";
            $mo->body = $refund_text;
            $mo->text_body = $refund_text;
            $mo->company_key = $payment_hash->payment->company->company_key;
            $mo->html_template = 'email.template.generic';
            $mo->to = [new Address($payment_hash->payment->company->owner()->email, $payment_hash->payment->company->owner()->present()->name())];

            Email::dispatch($mo, $payment_hash->payment->company);
            return;

        }

        $invoices = $invoice_collection->toArray();

        $data = [
            'id' => $payment->id,
            'amount' => $amount_refunded,
            'invoices' => $invoices,
            'date' => now()->format('Y-m-d'),
            'gateway_refund' => false,
            'email_receipt' => false,
            'via_webhook' => true,
        ];

        nlog($data);

        $payment->refund($data);

        $payment->private_notes .= 'Refunded via Stripe  ';

        $payment->saveQuietly();

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company_key)];
    }
}

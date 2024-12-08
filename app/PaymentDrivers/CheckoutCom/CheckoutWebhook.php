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

namespace App\PaymentDrivers\CheckoutCom;

use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
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

class CheckoutWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use Utilities;

    public $tries = 1;

    public $deleteWhenMissingModels = true;

    public CompanyGateway $company_gateway;

    public function __construct(public array $webhook_array, public string $company_key, public int $company_gateway_id)
    {
    }

    public function handle()
    {
        // nlog("Checkout Webhook");

        MultiDB::findAndSetDbByCompanyKey($this->company_key);

        $this->company_gateway = CompanyGateway::withTrashed()->find($this->company_gateway_id);

        if (!isset($this->webhook_array['type'])) {
            nlog("Checkout Webhook type not set");
        }

        /** @phpstan-ignore-next-line */
        match($this->webhook_array['type']) {
            'payment_approved' => $this->paymentApproved(),
        };

    }

    /**
     * {
     * "id":"evt_dli6ty4qo5vuxle5wklf5gwbwy","type":"payment_approved","version":"1.0.33","created_on":"2023-07-21T10:03:07.1555904Z",
     * "data":{"id":"pay_oqwbsd22kvpuvd35y5fhbdawxa","action_id":"act_buviezur7zsurnsorcgfn63e44","reference":"0014","amount":584168,"auth_code":"113059","currency":"USD","customer":{"id":"cus_6n4yt4q5kf4unn36o5qpbevxhe","email":"cypress@example.com"},
     * "metadata":{"udf1":"Invoice Ninja","udf2":"ofhgiGjyQXbsbUwygURfYFT2C3E7iY7U"},"payment_type":"Regular","processed_on":"2023-07-21T10:02:57.4678165Z","processing":{"acquirer_transaction_id":"645272142084717830381","retrieval_reference_number":"183042259107"},"response_code":"10000","response_summary":"Approved","risk":{"flagged":false,"score":0},"3ds":{"version":"2.2.0","challenged":true,"challenge_indicator":"no_preference","exemption":"none","eci":"05","cavv":"AAABAVIREQAAAAAAAAAAAAAAAAA=","xid":"74afa3ac-25d3-4d95-b815-cefbdd7c8270","downgraded":false,"enrolled":"Y","authentication_response":"Y","flow_type":"challenged"},"scheme_id":"114455763095262",
     * "source":{"id":"src_ghavmefpetjellmteqwj5jjcli","type":"card","billing_address":{},"expiry_month":10,"expiry_year":2025,"scheme":"VISA","last_4":"4242","fingerprint":"BD864B08D0B098DD83052A038FD2BA967DF2D48E375AAEEF54E37BC36B385E9A","bin":"424242","card_type":"CREDIT","card_category":"CONSUMER","issuer_country":"GB","product_id":"F","product_type":"Visa Classic","avs_check":"G","cvv_check":"Y"},"balances":{"total_authorized":584168,"total_voided":0,"available_to_void":584168,"total_captured":0,"available_to_capture":584168,"total_refunded":0,"available_to_refund":0},"event_links":{"payment":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa","payment_actions":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa/actions","capture":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa/captures","void":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa/voids"}},"_links":{"self":{"href":"https://api.sandbox.checkout.com/workflows/events/evt_dli6ty4qo5vuxle5wklf5gwbwy"},"subject":{"href":"https://api.sandbox.checkout.com/workflows/events/subject/pay_oqwbsd22kvpuvd35y5fhbdawxa"},"payment":{"href":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa"},"payment_actions":{"href":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa/actions"},"capture":{"href":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa/captures"},"void":{"href":"https://api.sandbox.checkout.com/payments/pay_oqwbsd22kvpuvd35y5fhbdawxa/voids"}}}
     */

    private function paymentApproved()
    {
        $payment_object = $this->webhook_array['data'];

        $payment = Payment::query()->withTrashed()->where('transaction_reference', $payment_object['id'])->first();

        if ($payment && $payment->status_id == Payment::STATUS_COMPLETED) {
            return;
        }

        if ($payment) {
            $payment->status_id = Payment::STATUS_COMPLETED;
            $payment->save();
            return;
        }

        if (isset($this->webhook_array['metadata'])) {

            $metadata = $this->webhook_array['metadata'];

            $payment_hash = PaymentHash::query()->where('hash', $metadata['udf2'])->first();

            $driver = $this->company_gateway->driver($payment_hash->fee_invoice->client)->init()->setPaymentMethod();

            $payment_hash->data = array_merge((array) $payment_hash->data, $this->webhook_array); // @phpstan-ignore-line
            $payment_hash->save();
            $driver->setPaymentHash($payment_hash);

            // @phpstan-ignore-line
            $data = [
                'payment_method' => isset($this->webhook_array['source']['id']) ? $this->webhook_array['source']['id'] : '',
                'payment_type' => PaymentType::CREDIT_CARD_OTHER,
                'amount' => $payment_hash->data->raw_value, // @phpstan-ignore-line
                'transaction_reference' => $payment_object['id'],
                'gateway_type_id' => GatewayType::CREDIT_CARD,
            ];

            $payment = $driver->createPayment($data, \App\Models\Payment::STATUS_COMPLETED);

            SystemLogger::dispatch(
                ['response' => $this->webhook_array, 'data' => $data],
                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                SystemLog::EVENT_GATEWAY_SUCCESS,
                SystemLog::TYPE_CHECKOUT,
                $payment_hash->fee_invoice->client,
                $this->company_gateway->company,
            );

        }

    }
}

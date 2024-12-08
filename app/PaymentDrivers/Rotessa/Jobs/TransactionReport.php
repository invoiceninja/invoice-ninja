<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\Rotessa\Jobs;

use App\Utils\Ninja;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use App\Models\PaymentHash;
use Illuminate\Bus\Queueable;
use App\Models\CompanyGateway;
use App\Jobs\Util\SystemLogger;
use Illuminate\Support\Facades\App;
use App\Jobs\Mail\PaymentFailedMailer;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class TransactionReport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1; //number of retries

    public $deleteWhenMissingModels = true;

    public function __construct()
    {
    }

    public function handle()
    {
        set_time_limit(0);

        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            CompanyGateway::query()
                            ->where('gateway_key', '91be24c7b792230bced33e930ac61676')
                            ->cursor()
                            ->each(function ($cg) {

                                $driver = $cg->driver()->init();

                                //Approved Transactions
                                $transactions = $driver->gatewayRequest("get", "transaction_report", ['page' => 1, 'status' => 'Approved', 'start_date' => now()->subMonths(2)->format('Y-m-d')]);

                                if ($transactions->successful()) {
                                    $transactions = $transactions->json();
                                    nlog($transactions);

                                    Payment::query()
                                        ->where('company_id', $cg->company_id)
                                        ->where('status_id', Payment::STATUS_PENDING)
                                        ->whereIn('transaction_reference', array_column($transactions, "transaction_schedule_id"))
                                        ->cursor()
                                        ->each(function ($payment) use ($transactions) {

                                            $payment->status_id = Payment::STATUS_COMPLETED;
                                            $payment->save();

                                            SystemLogger::dispatch(
                                                ['response' => collect($transactions)->where('id', $payment->transaction_reference)->first()->toArray(), 'data' => []],
                                                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                                                SystemLog::EVENT_GATEWAY_SUCCESS,
                                                SystemLog::TYPE_ROTESSA,
                                                $payment->client,
                                                $payment->company,
                                            );

                                        });

                                }


                                //Declined / Charged Back Transactions
                                $declined_transactions = $driver->gatewayRequest("get", "transaction_report", ['page' => 1, 'status' => 'Declined', 'start_date' => now()->subMonths(2)->format('Y-m-d')]);
                                $chargeback_transactions = $driver->gatewayRequest("get", "transaction_report", ['page' => 1, 'status' => 'Chargeback', 'start_date' => now()->subMonths(2)->format('Y-m-d')]);

                                if ($declined_transactions->successful() && $chargeback_transactions->successful()) {

                                    $transactions = array_merge($declined_transactions->json(), $chargeback_transactions->json());

                                    nlog($transactions);

                                    Payment::query()
                                        ->where('company_id', $cg->company_id)
                                        ->where('status_id', Payment::STATUS_PENDING)
                                        ->whereIn('transaction_reference', array_column($transactions, "transaction_schedule_id"))
                                        ->cursor()
                                        ->each(function ($payment) use ($transactions) {


                                            $client = $payment->client;

                                            $payment->service()->deletePayment();

                                            $payment->status_id = Payment::STATUS_FAILED;
                                            $payment->save();

                                            $payment_hash = PaymentHash::query()->where('payment_id', $payment->id)->first();

                                            if ($payment_hash) {

                                                App::forgetInstance('translator');
                                                $t = app('translator');
                                                $t->replace(Ninja::transformTranslations($client->getMergedSettings()));
                                                App::setLocale($client->locale());

                                                $error = ctrans('texts.client_payment_failure_body', [
                                                    'invoice' => implode(',', $payment->invoices->pluck('number')->toArray()),
                                                    'amount' => array_sum(array_column($payment_hash->invoices(), 'amount')) + $payment_hash->fee_total, ]);
                                            } else {
                                                $error = 'Payment for '.$payment->client->present()->name()." for {$payment->amount} failed";
                                            }

                                            PaymentFailedMailer::dispatch(
                                                $payment_hash,
                                                $client->company,
                                                $client,
                                                $error
                                            );

                                            SystemLogger::dispatch(
                                                ['response' => collect($transactions)->where('id', $payment->transaction_reference)->first()->toArray(), 'data' => []],
                                                SystemLog::CATEGORY_GATEWAY_RESPONSE,
                                                SystemLog::EVENT_GATEWAY_FAILURE,
                                                SystemLog::TYPE_ROTESSA,
                                                $payment->client,
                                                $payment->company,
                                            );

                                        });
                                }
                            });

        }
    }

}

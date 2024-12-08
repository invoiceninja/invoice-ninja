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

namespace App\Jobs\Ninja;

use App\Jobs\Bank\ProcessBankTransactionsYodlee;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\BankIntegration;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BankTransactionSync implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */

    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (config('ninja.db.multi_db_enabled')) {

            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->processYodlee();
                $this->processNordigen();
            }

        } else {
            $this->processYodlee();
            $this->processNordigen();
        }

        nlog("syncing transactions - done");
    }

    private function processYodlee()
    {
        if (Ninja::isHosted()) {
            nlog("syncing transactions - yodlee");

            Account::with('bank_integrations')->whereNotNull('bank_integration_account_id')->cursor()->each(function ($account) {

                if ($account->isEnterprisePaidClient()) {
                    $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_YODLEE)->where('auto_sync', true)->where('disabled_upstream', 0)->cursor()->each(function ($bank_integration) use ($account) {
                        (new ProcessBankTransactionsYodlee($account->bank_integration_account_id, $bank_integration))->handle();
                    });
                }

            });
        }
    }
    private function processNordigen()
    {
        if (config("ninja.nordigen.secret_id") && config("ninja.nordigen.secret_key")) {
            nlog("syncing transactions - nordigen");

            Account::with('bank_integrations')->cursor()->each(function ($account) {

                if ((Ninja::isSelfHost() || (Ninja::isHosted() && $account->isEnterprisePaidClient()))) {
                    $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_NORDIGEN)->where('auto_sync', true)->where('disabled_upstream', 0)->cursor()->each(function ($bank_integration) {
                        try {
                            (new ProcessBankTransactionsNordigen($bank_integration))->handle();
                        } catch (\Exception $e) {
                            nlog("Exception:: BankTransactioSync::" . $e->getMessage());
                            sleep(20);
                        }

                        sleep(5);
                    });
                }

            });
        }
    }
}

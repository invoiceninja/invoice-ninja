<?php
/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Jobs\Bank;

use App\Helpers\Bank\Nordigen\Nordigen;
use App\Libraries\MultiDB;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Notifications\Ninja\GenericNinjaAdminNotification;
use App\Services\Bank\BankMatchingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessBankTransactionsNordigen implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private BankIntegration $bank_integration;

    private ?string $from_date;

    public Company $company;
    public Nordigen $nordigen;
    public $nordigen_account;
    
    /**
     * Create a new job instance.
     */
    public function __construct(BankIntegration $bank_integration)
    {
        $this->bank_integration = $bank_integration;
        $this->from_date = $bank_integration->from_date ?: now()->subDays(90);
        $this->company = $this->bank_integration->company;
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        if ($this->bank_integration->integration_type != BankIntegration::INTEGRATION_TYPE_NORDIGEN) {
            throw new \Exception("Invalid BankIntegration Type");
        }

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key'))) {
            throw new \Exception("Missing credentials for bank_integration service nordigen");
        }

        $this->nordigen = new Nordigen();

        set_time_limit(0);

        nlog("Nordigen: Processing transactions for account: {$this->bank_integration->account->key}");

        // UPDATE ACCOUNT
        try {
            $this->updateAccount();
        } catch (\Exception $e) {
            nlog("Nordigen: {$this->bank_integration->nordigen_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->nordigen_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();

            sleep(5);

            throw $e;
        }
        if (!$this->nordigen_account) {
            return;
        }

        // UPDATE TRANSACTIONS
        try {
            $this->processTransactions();
        } catch (\Exception $e) {
            nlog("Nordigen: {$this->bank_integration->nordigen_account_id} - exited abnormally => " . $e->getMessage());

            $content = [
                "Processing transactions for account: {$this->bank_integration->nordigen_account_id} failed",
                "Exception Details => ",
                $e->getMessage(),
            ];

            $this->bank_integration->company->notification(new GenericNinjaAdminNotification($content))->ninja();

            throw $e;
        }

        // Perform Matching
        BankMatchingService::dispatch($this->company->id, $this->company->db);
    }
    
    // const DISCOVERED = 'DISCOVERED';   // Account was discovered but not yet processed
    // const PROCESSING = 'PROCESSING';   // Initial processing of account
    // const READY = 'READY';            // Account ready to be accessed
    // const ERROR = 'ERROR';            // Error occurred during processing
    // const EXPIRED = 'EXPIRED';        // Access to account has expired
    // const SUSPENDED = 'SUSPENDED';     // Account access temporarily suspended
    // const FAILED = 'FAILED';          // Connection failed
    // const DELETED = 'DELETED';        // Account has been deleted
    private function updateAccount()
    {
        $account_status = $this->nordigen->isAccountActive($this->bank_integration->nordigen_account_id);
        
        //Return early if the account status is not in a good state
        if(isset($account_status['status']) && in_array($account_status['status'], ['EXPIRED','DELETED'])) {

            $this->bank_integration->disabled_upstream = true;
            $this->bank_integration->bank_account_status = $account_status['status'];
            $this->bank_integration->save();

            nlog("Nordigen: account inactive: " . $this->bank_integration->nordigen_account_id);

            //Need requisition refresh!
            if($account_status['status'] == 'EXPIRED')
                $this->nordigen->disabledAccountEmail($this->bank_integration);

            return;

        }
        elseif(isset($account_status['status']) && $account_status['status'] != 'READY') {
            //There may be other issues, return and await retry
            nlog($account_status['id']. " Nordigen account status == ". $account_status['status']);
            return;

        }
        
        $account = $this->nordigen->getAccount($this->bank_integration->nordigen_account_id);

        if(isset($account['error'])){
            
            $this->bank_integration->bank_account_status = "Error:: " . $account['error'];
            $this->bank_integration->save();
            return;

        }

        if(!$account){

            $this->bank_integration->bank_account_status = "Error:: Failed to update account.";
            $this->bank_integration->save();
            return;

        }

        $this->nordigen_account = $account;
        $this->bank_integration->disabled_upstream = false;
        $this->bank_integration->bank_account_status = $account['account_status'];
        $this->bank_integration->balance = $account['current_balance'];
        $this->bank_integration->save();

    }

    private function processTransactions()
    {
        //Get transaction count object
        $transactions = $this->nordigen->getTransactions($this->company, $this->bank_integration->nordigen_account_id, $this->from_date);

        //if no transactions, update the from_date and move on
        if (count($transactions) == 0) {

            $this->bank_integration->from_date = now()->subDays(5);
            $this->bank_integration->disabled_upstream = false;
            $this->bank_integration->save();

            return;
        }

        //Harvest the company

        MultiDB::setDb($this->company->db);

        /*Get the user */
        $user_id = $this->company->owner()->id;

        /* Unguard the model to perform batch inserts */
        BankTransaction::unguard();

        $now = now();

        foreach ($transactions as $transaction) {

            if (BankTransaction::where('nordigen_transaction_id', $transaction['nordigen_transaction_id'])->where('company_id', $this->company->id)->where('bank_integration_id', $this->bank_integration->id)->where('is_deleted', 0)->withTrashed()->exists()) {
                continue;
            }

            //this should be much faster to insert than using ::create()
            \DB::table('bank_transactions')->insert(
                array_merge($transaction, [
                    'company_id' => $this->company->id,
                    'user_id' => $user_id,
                    'bank_integration_id' => $this->bank_integration->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])
            );

        }

        $this->bank_integration->from_date = now()->subDays(5);
        $this->bank_integration->save();
    }
}

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

use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Client;
use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CompanySizeCheck implements ShouldQueue
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
        if (! config('ninja.db.multi_db_enabled')) {
            Company::where('is_large', false)->withCount(['invoices', 'clients', 'products', 'quotes'])->cursor()->each(function ($company) {
                if ($company->invoices_count > 500 || $company->products_count > 500 || $company->clients_count > 500) {
                    nlog("Marking company {$company->id} as large");

                    $company->account->companies()->update(['is_large' => true]);
                }
            });

            nlog("updating all client credit balances");

            Client::query()
                  ->where('updated_at', '>', now()->subDay())
                  ->cursor()
                  ->each(function ($client) {

                      $old_credit_balance = $client->credit_balance;
                      $new_credit_balance = $client->service()->getCreditBalance();

                      if (floatval($old_credit_balance) !== floatval($new_credit_balance)) {
                          $client->credit_balance = $client->service()->getCreditBalance();
                          $client->saveQuietly();
                      }

                  });

            /* Ensures lower permissioned users return the correct dataset and refresh responses */
            Account::whereHas('companies', function ($query) {
                $query->where('is_large', 0);
            })
                  ->whereHas('company_users', function ($query) {
                      $query->where('is_admin', 0);
                  })
                  ->cursor()->each(function ($account) {
                      $account->companies()->update(['is_large' => true]);
                  });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                nlog("Company size check db {$db}");

                Company::where('is_large', false)->withCount(['invoices', 'clients', 'products', 'quotes'])->cursor()->each(function ($company) {
                    if ($company->invoices_count > 500 || $company->products_count > 500 || $company->clients_count > 500 || $company->quotes_count > 500) {
                        nlog("Marking company {$company->id} as large");

                        $company->account->companies()->update(['is_large' => true]);
                    }
                });

                nlog("updating all client credit balances");

                Client::query()->where('updated_at', '>', now()->subDay())
                      ->cursor()
                      ->each(function ($client) {


                          $old_credit_balance = $client->credit_balance;
                          $new_credit_balance = $client->service()->getCreditBalance();

                          if (floatval($old_credit_balance) !== floatval($new_credit_balance)) {
                              $client->credit_balance = $client->service()->getCreditBalance();
                              $client->saveQuietly();
                          }


                      });

                Account::where('plan', 'enterprise')
                      ->whereDate('plan_expires', '>', now())
                      ->whereHas('companies', function ($query) {
                          $query->where('is_large', 0);
                      })
                      ->whereHas('company_users', function ($query) {
                          $query->where('is_admin', 0);
                      })
                      ->cursor()->each(function ($account) {
                          $account->companies()->update(['is_large' => true]);
                      });
            }
        }
    }
}

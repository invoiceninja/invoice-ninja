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

use App\Models\Company;
use App\Models\CompanyLedger;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CheckCompanyData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $company;

    public $hash;

    public $company_data = [];

    public $is_valid;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Company $company, string $hash = '')
    {
        $this->company = $company;
        $this->hash = $hash;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        $this->is_valid = true;

        $this->checkInvoiceBalances();
        $this->checkInvoicePayments();
        $this->checkPaidToDates();
        $this->checkClientBalances();
        $this->checkContacts();

        if (Cache::has($this->hash)) {
            $cache_instance = Cache::get($this->hash);
        } else {
            $cache_instance = Cache::add($this->hash, '');
        }

        $this->company_data['company_hash'] = $this->company->company_hash;

        Cache::put($this->hash, $cache_instance, now()->addMinutes(30));

        nlog(Cache::get($this->hash));
        nlog($this->company_data);

        if (! $this->is_valid) {
            $this->company_data['status'] = 'errors';
        } else {
            $this->company_data['status'] = 'success';
        }

        return $this->company_data;
    }

    public function middleware()
    {
        return [new RateLimited('checkdata')];
    }

    private function checkInvoiceBalances()
    {
        $wrong_balances = 0;
        $wrong_paid_to_dates = 0;

        foreach ($this->company->clients->where('is_deleted', 0) as $client) {
            $invoice_balance = $client->invoices->where('is_deleted', false)->where('status_id', '>', 1)->sum('balance');
            //$credit_balance = $client->credits->where('is_deleted', false)->sum('balance');

            // if($client->balance != $invoice_balance)
            //     $invoice_balance -= $credit_balance;//doesn't make sense to remove the credit amount

            $ledger = CompanyLedger::where('client_id', $client->id)->orderBy('id', 'DESC')->first();

            if ($ledger && number_format($invoice_balance, 4) != number_format($client->balance, 4)) {
                $wrong_balances++;

                $this->company_data[] = "# {$client->id} ".$client->present()->name().' - '.$client->number." - Balance Failure - Invoice Balances = {$invoice_balance} Client Balance = {$client->balance} Ledger Balance = {$ledger->balance} ";

                $this->is_valid = false;
            }
        }

        $this->company_data[] = "{$wrong_balances} clients with incorrect balances";
    }

    private function checkInvoicePayments()
    {
        $wrong_balances = 0;
        $wrong_paid_to_dates = 0;

        $this->company->clients->where('is_deleted', 0)->each(function ($client) use ($wrong_balances) {
            $client->invoices->where('is_deleted', false)->whereIn('status_id', '!=', Invoice::STATUS_DRAFT)->each(function ($invoice) use ($wrong_balances, $client) {
                $total_amount = $invoice->payments->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED])->sum('pivot.amount');
                $total_refund = $invoice->payments->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED])->sum('pivot.refunded');
                $total_credit = $invoice->credits->sum('amount');

                $total_paid = $total_amount - $total_refund;
                $calculated_paid_amount = $invoice->amount - $invoice->balance - $total_credit;

                if ((string) $total_paid != (string) ($invoice->amount - $invoice->balance - $total_credit)) {
                    $wrong_balances++;

                    $this->company_data[] = $client->present()->name().' - '.$client->id." - Total Amount = {$total_amount} != Calculated Total = {$calculated_paid_amount} - Total Refund = {$total_refund} Total credit = {$total_credit}";

                    $this->is_valid = false;
                }
            });
        });

        $this->company_data[] = "{$wrong_balances} clients with incorrect invoice balances";
    }

    private function checkPaidToDates()
    {
        $wrong_paid_to_dates = 0;
        $credit_total_applied = 0;

        $this->company->clients->where('is_deleted', 0)->each(function ($client) use ($wrong_paid_to_dates, $credit_total_applied) {
            $total_invoice_payments = 0;

            foreach ($client->invoices->where('is_deleted', false)->where('status_id', '>', 1) as $invoice) {
                $total_amount = $invoice->payments->where('is_deleted', false)->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])->sum('pivot.amount');
                $total_refund = $invoice->payments->where('is_deleted', false)->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])->sum('pivot.refunded');

                $total_invoice_payments += ($total_amount - $total_refund);
            }

            if (round($total_invoice_payments, 2) != round($client->paid_to_date, 2)) {
                $wrong_paid_to_dates++;

                $this->company_data[] = $client->present()->name().'id = # '.$client->id." - Paid to date does not match Client Paid To Date = {$client->paid_to_date} - Invoice Payments = {$total_invoice_payments}";

                $this->is_valid = false;
            }
        });

        $this->company_data[] = "{$wrong_paid_to_dates} clients with incorrect paid to dates";
    }

    private function checkClientBalances()
    {
        $wrong_balances = 0;
        $wrong_paid_to_dates = 0;

        foreach ($this->company->clients->where('is_deleted', 0) as $client) {
            //$invoice_balance = $client->invoices->where('is_deleted', false)->where('status_id', '>', 1)->sum('balance');
            $invoice_balance = Invoice::where('client_id', $client->id)->where('is_deleted', false)->where('status_id', '>', 1)->withTrashed()->sum('balance');
            $credit_balance = Credit::where('client_id', $client->id)->where('is_deleted', false)->withTrashed()->sum('balance');

            //10/02/21
            // Legacy - V4 will add credits to the balance - we may need to reverse engineer this and remove the credits from the client balance otherwise we need this hack here and in the invoice balance check.
            // if($client->balance != $invoice_balance)
            //     $invoice_balance -= $credit_balance;

            $ledger = CompanyLedger::where('client_id', $client->id)->orderBy('id', 'DESC')->first();

            if ($ledger && (string) $invoice_balance != (string) $client->balance) {
                $wrong_paid_to_dates++;

                $this->company_data[] = $client->present()->name().' - '.$client->id." - calculated client balances do not match {$invoice_balance} - ".rtrim($client->balance, '0').'';

                $this->is_valid = false;
            }
        }

        $this->company_data[] = "{$wrong_paid_to_dates} clients with incorrect client balances";
    }

    private function checkContacts()
    {
        // check for contacts with the contact_key value set
        $contacts = DB::table('client_contacts')
                        ->where('company_id', $this->company->id)
                        ->whereNull('contact_key')
                        ->orderBy('id')
                        ->get(['id']);

        $this->company_data[] = $contacts->count().' contacts without a contact_key';

        if ($contacts->count() > 0) {
            $this->is_valid = false;
        }

        // if ($this->option('fix') == 'true') {
        //     foreach ($contacts as $contact) {
        //         DB::table('client_contacts')
        //             ->where('company_id', $this->company->id)
        //             ->where('id', '=', $contact->id)
        //             ->whereNull('contact_key')
        //             ->update([
        //                 'contact_key' => str_random(config('ninja.key_length')),
        //             ]);
        //     }
        // }

        // check for missing contacts
        $clients = DB::table('clients')
                    ->where('clients.company_id', $this->company->id)
                    ->leftJoin('client_contacts', function ($join) {
                        $join->on('client_contacts.client_id', '=', 'clients.id')
                            ->whereNull('client_contacts.deleted_at');
                    })
                    ->groupBy('clients.id', 'clients.user_id', 'clients.company_id')
                    ->havingRaw('count(client_contacts.id) = 0');

        // if ($this->option('client_id')) {
        //     $clients->where('clients.id', '=', $this->option('client_id'));
        // }

        $clients = $clients->get(['clients.id', 'clients.user_id', 'clients.company_id']);

        $this->company_data[] = $clients->count().' clients without any contacts';

        if ($clients->count() > 0) {
            $this->is_valid = false;
        }

        // check for more than one primary contact
        $clients = DB::table('clients')
                    ->where('clients.company_id', $this->company->id)
                    ->leftJoin('client_contacts', function ($join) {
                        $join->on('client_contacts.client_id', '=', 'clients.id')
                            ->where('client_contacts.is_primary', '=', true)
                            ->whereNull('client_contacts.deleted_at');
                    })
                    ->groupBy('clients.id')
                    ->havingRaw('count(client_contacts.id) != 1');

        $clients = $clients->get(['clients.id', DB::raw('count(client_contacts.id)')]);
        $this->company_data[] = $clients->count().' clients without a single primary contact';

        if ($clients->count() > 0) {
            $this->is_valid = false;
        }
    }

    private function checkCompanyData()
    {
        $tables = [
            'activities' => [
                'invoice',
                'client',
                'client_contact',
                'payment',
            ],
            'invoices' => [
                'client',
            ],
            'payments' => [
                'client',
            ],
            'products' => [

            ],
        ];

        foreach ($tables as $table => $entityTypes) {
            foreach ($entityTypes as $entityType) {
                $tableName = $this->pluralizeEntityType($entityType);
                $field = $entityType;
                if ($table == 'companies') {
                    $company_id = 'id';
                } else {
                    $company_id = 'company_id';
                }
                $records = DB::table($table)
                                ->join($tableName, "{$tableName}.id", '=', "{$table}.{$field}_id")
                                ->where("{$table}.{$company_id}", '!=', "{$tableName}.company_id")
                                ->get(["{$table}.id"]);

                if ($records->count()) {
                    $this->is_valid = false;
                    $this->company_data[] = $records->count()." {$table} records with incorrect {$entityType} company id";
                }
            }
        }
    }

    public function pluralizeEntityType($type)
    {
        if ($type === 'company') {
            return 'companies';
        }

        return $type.'s';
    }
}

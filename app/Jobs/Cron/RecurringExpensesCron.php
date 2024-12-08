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

namespace App\Jobs\Cron;

use App\Utils\Ninja;
use App\Libraries\MultiDB;
use Illuminate\Support\Carbon;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\Auth;
use App\Utils\Traits\GeneratesCounter;
use App\Events\Expense\ExpenseWasCreated;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Factory\RecurringExpenseToExpenseFactory;
use App\Libraries\Currency\Conversion\CurrencyApi;

class RecurringExpensesCron
{
    use Dispatchable;
    use GeneratesCounter;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        /* Get all expenses where the send date is less than NOW + 30 minutes() */
        nlog('Sending recurring expenses '.Carbon::now()->format('Y-m-d h:i:s'));

        Auth::logout();

        if (! config('ninja.db.multi_db_enabled')) {
            $recurring_expenses = RecurringExpense::query()->where('next_send_date', '<=', now()->toDateTimeString())
                                                        ->whereNotNull('next_send_date')
                                                        ->whereNull('deleted_at')
                                                        ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                                        ->where('remaining_cycles', '!=', '0')
                                                        ->whereHas('company', function ($query) {
                                                            $query->where('is_disabled', 0);
                                                        })
                                                        ->with('company')
                                                        ->cursor();

            nlog(now()->format('Y-m-d').' Generating Recurring Expenses. Count = '.$recurring_expenses->count());

            $recurring_expenses->each(function ($recurring_expense, $key) {
                nlog('Current date = '.now()->format('Y-m-d').' Recurring date = '.$recurring_expense->next_send_date);

                if (! $recurring_expense->company->is_disabled) {
                    $this->generateExpense($recurring_expense);
                }
            });
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $recurring_expenses = RecurringExpense::query()->where('next_send_date', '<=', now()->toDateTimeString())
                                                            ->whereNotNull('next_send_date')
                                                            ->whereNull('deleted_at')
                                                            ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                                            ->where('remaining_cycles', '!=', '0')
                                                            ->whereHas('company', function ($query) {
                                                                $query->where('is_disabled', 0);
                                                            })
                                                            ->with('company')
                                                            ->cursor();

                nlog(now()->format('Y-m-d').' Generating Recurring Expenses. Count = '.$recurring_expenses->count());

                $recurring_expenses->each(function ($recurring_expense, $key) {
                    nlog('Current date = '.now()->format('Y-m-d').' Recurring date = '.$recurring_expense->next_send_date);

                    if (! $recurring_expense->company->is_disabled) {
                        $this->generateExpense($recurring_expense);
                    }
                });
            }
        }
    }

    private function generateExpense(RecurringExpense $recurring_expense)
    {
        $expense = RecurringExpenseToExpenseFactory::create($recurring_expense);
        $expense->saveQuietly();

        if ($expense->company->mark_expenses_paid) {
            $expense->payment_date = now()->format('Y-m-d');
        }

        if ((int)$expense->company->settings->currency_id != $expense->currency_id) {
            $exchange_rate = new CurrencyApi();

            $expense->exchange_rate = $exchange_rate->exchangeRate($expense->currency_id, (int)$expense->company->settings->currency_id, Carbon::parse($expense->date));
        } else {
            $expense->exchange_rate = 1;
        }

        $expense->number = $this->getNextExpenseNumber($expense);
        $expense->saveQuietly();

        event(new ExpenseWasCreated($expense, $expense->company, Ninja::eventVars(null)));
        event('eloquent.created: App\Models\Expense', $expense);

        $recurring_expense->next_send_date = $recurring_expense->nextSendDate();
        $recurring_expense->next_send_date_client = $recurring_expense->next_send_date;
        $recurring_expense->last_sent_date = now();
        $recurring_expense->remaining_cycles = $recurring_expense->remainingCycles();
        $recurring_expense->save();
    }
}

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

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\ExpenseCategoryFactory;
use App\Factory\ExpenseFactory;
use App\Factory\PaymentFactory;
use App\Helpers\Bank\Yodlee\Yodlee;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Libraries\MultiDB;
use App\Models\BankTransaction;
use App\Models\Company;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class MatchBankTransactions implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use GeneratesCounter;
    use MakesHash;

    private int $company_id;

    private string $db;

    private array $input;

    /** @var \App\Models\Company */
    protected ?Company $company;

    public Invoice $invoice;

    /** @var \App\Models\BankTransaction $bt */
    private ?BankTransaction $bt;

    private $categories;

    private float $available_balance = 0;

    private float $applied_amount = 0;

    private array $attachable_invoices = [];

    public $bts;

    /**
     * Create a new job instance.
     */
    public function __construct(int $company_id, string $db, array $input)
    {
        $this->company_id = $company_id;
        $this->db = $db;
        $this->input = $input['transactions'];
        $this->categories = collect();
        $this->bts = collect();
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        MultiDB::setDb($this->db);

        $this->company = Company::query()->find($this->company_id);

        if ($this->company->account->bank_integration_account_id) {
            $yodlee = new Yodlee($this->company->account->bank_integration_account_id);
        } else {
            $yodlee = false;
        }

        $bank_categories = Cache::get('bank_categories');
        if (!$bank_categories && $yodlee) {
            $_categories = $yodlee->getTransactionCategories();
            $this->categories = collect($_categories->transactionCategory);
            Cache::forever('bank_categories', $this->categories);
        } else {
            $this->categories = collect($bank_categories);
        }

        foreach ($this->input as $input) {
            if (array_key_exists('invoice_ids', $input) && strlen($input['invoice_ids']) >= 1) {
                $this->matchInvoicePayment($input);
            } elseif (array_key_exists('payment_id', $input) && strlen($input['payment_id']) >= 1) {
                $this->linkPayment($input);
            } elseif (array_key_exists('expense_id', $input) && strlen($input['expense_id']) >= 1) {
                $this->linkExpense($input);
            } elseif ((array_key_exists('vendor_id', $input) && strlen($input['vendor_id']) >= 1) || (array_key_exists('ninja_category_id', $input) && strlen($input['ninja_category_id']) >= 1)) {
                $this->matchExpense($input);
            }
        }

        return BankTransaction::query()->whereIn('id', $this->bts);
    }

    private function getInvoices(string $invoice_hashed_ids): array
    {
        $collection = collect();

        /** @array $invoices */
        $invoices = explode(",", $invoice_hashed_ids);

        foreach ($invoices as $invoice) {
            if (is_string($invoice) && strlen($invoice) > 1) {
                $collection->push($this->decodePrimaryKey($invoice));
            }
        }

        return $collection->toArray();
    }

    private function checkPayable($invoices): bool
    {
        foreach ($invoices as $invoice) {
            $invoice->service()->markSent();

            if (!$invoice->isPayable()) {
                return false;
            }
        }

        return true;
    }

    private function linkExpense($input)
    {
        $this->bt = BankTransaction::withTrashed()->find($input['id']);

        if (!$this->bt) {
            return $this;
        }

        $_expenses = explode(",", $input['expense_id']);

        foreach ($_expenses as $_expense) {

            $expense = Expense::withTrashed()
                ->where('id', $this->decodePrimaryKey($_expense))
                ->where('company_id', $this->bt->company_id)
                ->first();

            if ($expense && !$expense->transaction_id) {
                $expense->transaction_id = $this->bt->id;
                $expense->save();

                $this->bt->expense_id = $this->coalesceExpenses($expense->hashed_id);
                $this->bt->status_id = BankTransaction::STATUS_CONVERTED;
                $this->bt->vendor_id = $expense->vendor_id;
                $this->bt->ninja_category_id = $expense->category_id;
                $this->bt->save();

                $this->bts->push($this->bt->id);
            }
        }

        return $this;
    }

    private function coalesceExpenses($expense): string
    {

        if (!$this->bt->expense_id || strlen($this->bt->expense_id ?? '') < 2) {
            return $expense;
        }

        return collect(explode(",", $this->bt->expense_id))->push($expense)->implode(",");

    }

    private function linkPayment($input)
    {
        $this->bt = BankTransaction::query()->withTrashed()->find($input['id']);

        if (!$this->bt || $this->bt->status_id == BankTransaction::STATUS_CONVERTED) {
            return $this;
        }

        $payment = Payment::withTrashed()->find($input['payment_id']);

        if ($payment && !$payment->transaction_id) {
            $payment->transaction_id = $this->bt->id;
            $payment->saveQuietly();

            $this->bt->payment_id = $payment->id;
            $this->bt->status_id = BankTransaction::STATUS_CONVERTED;
            $this->bt->invoice_ids = collect($payment->invoices)->pluck('hashed_id')->implode(',');
            $this->bt->save();

            $this->bts->push($this->bt->id);
        }

        return $this;
    }

    private function matchInvoicePayment($input): self
    {
        $this->bt = BankTransaction::withTrashed()->find($input['id']);

        if (!$this->bt || $this->bt->status_id == BankTransaction::STATUS_CONVERTED) {
            return $this;
        }

        $_invoices = Invoice::query()
            ->withTrashed()
            ->where('company_id', $this->bt->company_id)
            ->whereIn('id', $this->getInvoices($input['invoice_ids']))
            ->get();

        $amount = $this->bt->amount;

        if ($_invoices->count() > 0 && $this->checkPayable($_invoices)) {

            $this->createPayment($_invoices, $amount);

            $this->bts->push($this->bt->id);
        }

        return $this;
    }

    private function matchExpense($input): self
    {
        //if there is a category id, pull it from Yodlee and insert - or just reuse!!
        $this->bt = BankTransaction::query()->withTrashed()->find($input['id']);

        if (!$this->bt || $this->bt->status_id == BankTransaction::STATUS_CONVERTED) {
            return $this;
        }

        $expense = ExpenseFactory::create($this->bt->company_id, $this->bt->user_id);
        $expense->category_id = $this->resolveCategory($input);
        $expense->amount = $this->bt->amount;
        $expense->number = $this->getNextExpenseNumber($expense);
        $expense->currency_id = $this->bt->currency_id;
        $expense->date = Carbon::parse($this->bt->date);
        $expense->payment_date = Carbon::parse($this->bt->date);
        $expense->transaction_reference = $this->bt->description;
        $expense->transaction_id = $this->bt->id;

        if (array_key_exists('vendor_id', $input)) {
            $expense->vendor_id = $input['vendor_id'];
        }

        $expense->invoice_documents = $this->company->invoice_expense_documents;
        $expense->should_be_invoiced = $this->company->mark_expenses_invoiceable;
        $expense->save();

        $this->bt->expense_id = $this->coalesceExpenses($expense->hashed_id);

        if (array_key_exists('vendor_id', $input)) {
            $this->bt->vendor_id = $input['vendor_id'];
        }

        $this->bt->status_id = BankTransaction::STATUS_CONVERTED;
        $this->bt->save();

        $this->bts->push($this->bt->id);

        return $this;
    }

    private function createPayment($invoices, float $amount): void
    {

        $this->attachable_invoices = [];
        $this->available_balance = $amount;

        \DB::connection(config('database.default'))->transaction(function () use ($invoices) {
            $invoices->each(function ($invoice) {
                $this->invoice = Invoice::withTrashed()->where('id', $invoice->id)->lockForUpdate()->first();

                $_amount = false;

                if (floatval($this->invoice->balance) < floatval($this->available_balance) && $this->available_balance > 0) {
                    $_amount = $this->invoice->balance;
                    $this->applied_amount += $this->invoice->balance;
                    $this->available_balance = $this->available_balance - $this->invoice->balance;
                } elseif (floatval($this->invoice->balance) >= floatval($this->available_balance) && $this->available_balance > 0) {
                    $_amount = $this->available_balance;
                    $this->applied_amount += $this->available_balance;
                    $this->available_balance = 0;
                }

                if ($_amount) {
                    $this->attachable_invoices[] = ['id' => $this->invoice->id, 'amount' => $_amount];

                    $this->invoice->next_send_date = null;

                    $this->invoice
                        ->service()
                        ->applyNumber()
                        ->setExchangeRate()
                        ->updateBalance($_amount * -1)
                        ->updatePaidToDate($_amount)
                        ->setCalculatedStatus()
                        ->save();
                }
            });
        }, 2);

        // @phpstan-ignore-next-line
        if (!$this->invoice) {
            return;
        }


        nlog("post");

        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $this->bt->amount;
        $payment->applied = $this->applied_amount;
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = $this->bt->description;
        $payment->transaction_id = $this->bt->id;
        $payment->currency_id = $this->bt->currency_id;
        $payment->is_manual = false;
        $payment->date = $this->bt->date ? Carbon::parse($this->bt->date) : now();


        /* Bank Transfer! */
        $payment_type_id = 1;

        $payment->saveQuietly();

        $payment->service()->applyNumber()->save();

        if ($payment->client->getSetting('send_email_on_mark_paid')) {
            $payment->service()->sendEmail();
        }

        $this->setExchangeRate($payment);

        /* Create a payment relationship to the invoice entity */
        foreach ($this->attachable_invoices as $attachable_invoice) { // @phpstan-ignore-line
            $payment->invoices()->attach($attachable_invoice['id'], [
                'amount' => $attachable_invoice['amount'],
            ]);
        }

        event('eloquent.created: App\Models\Payment', $payment);

        $payment->ledger()
            ->updatePaymentBalance($amount * -1);

        $this->invoice
            ->client
            ->service()
            ->updateBalanceAndPaidToDate($this->applied_amount * -1, $amount)
            ->save();

        $this->invoice = $this->invoice
            ->service()
            ->workFlow()
            ->save();

        /* Update Invoice balance */
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        $hashed_keys = [];

        foreach ($this->attachable_invoices as $attachable_invoice) { //@phpstan-ignore-line
            $hashed_keys[] = $this->encodePrimaryKey($attachable_invoice['id']);
        }

        $this->bt->invoice_ids = implode(",", $hashed_keys);
        $this->bt->status_id = BankTransaction::STATUS_CONVERTED;
        $this->bt->payment_id = $payment->id;
        $this->bt->save();

        nlog($this->bt->toArray());

    }

    private function resolveCategory($input): ?int
    {
        if (array_key_exists('ninja_category_id', $input) && (int) $input['ninja_category_id'] > 1) {
            $this->bt->ninja_category_id = $input['ninja_category_id'];
            $this->bt->save();

            return (int) $input['ninja_category_id'];
        }

        $category = $this->categories->firstWhere('highLevelCategoryId', $this->bt->category_id);

        $ec = ExpenseCategory::query()->where('company_id', $this->bt->company_id)->where('bank_category_id', $this->bt->category_id)->first();

        if ($ec) {
            return $ec->id;
        }

        if ($category) {
            $ec = ExpenseCategoryFactory::create($this->bt->company_id, $this->bt->user_id);
            $ec->bank_category_id = $this->bt->category_id;
            $ec->name = $category->highLevelCategoryName;
            $ec->save();

            return $ec->id;
        }


        return null;
    }

    private function setExchangeRate(Payment $payment)
    {
        if ($payment->exchange_rate != 1) {
            return;
        }

        $client_currency = $payment->client->getSetting('currency_id');
        $company_currency = $payment->client->company->settings->currency_id;

        if ($company_currency != $client_currency) {
            $exchange_rate = new CurrencyApi();

            $payment->exchange_rate = $exchange_rate->exchangeRate($client_currency, $company_currency, Carbon::parse($payment->date));
            $payment->exchange_currency_id = $company_currency;

            $payment->saveQuietly();
        }
    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->account->bank_integration_account_id)];
    }
}

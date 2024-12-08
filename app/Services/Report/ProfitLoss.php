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

namespace App\Services\Report;

use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Currency;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use League\Csv\Writer;

use function Sentry\continueTrace;

class ProfitLoss
{
    private bool $is_income_billed = true;

    private bool $is_expense_billed = true;

    private bool $is_tax_included = true;

    private $start_date;

    private $end_date;

    private float $income = 0;

    private float $income_taxes = 0;

    private float $credit = 0;

    private float $credit_invoice = 0;

    private float $credit_taxes = 0;

    private array $invoice_payment_map = [];

    private array $expenses = [];

    private array $expense_break_down = [];

    private array $income_map;

    private array $foreign_income = [];

    protected CurrencyApi $currency_api;

    /*
         payload variables.

         start_date - Y-m-d
         end_date - Y-m-d
         date_range -
             all
             last7
             last30
             this_month
             last_month
             this_quarter
             last_quarter
             this_year
             custom
         is_income_billed - true = Invoiced || false = Payments
         is_expense_billed - true = Expensed || false = Expenses marked as paid
         include_tax - true tax_included || false - tax_excluded

     */

    protected array $payload;

    protected Company $company;

    public function __construct(Company $company, array $payload)
    {
        $this->currency_api = new CurrencyApi();

        $this->company = $company;

        $this->payload = $payload;

        $this->setBillingReportType();
    }

    public function run()
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        return $this->build()->getCsv();
    }

    public function build()
    {
        MultiDB::setDb($this->company->db);

        if ($this->is_income_billed) { //get invoiced amounts
            $this->filterIncome();
        } else {

            $this->filterInvoicePaymentIncome();
        }

        $this->expenseData()->buildExpenseBreakDown();

        return $this;
    }

    public function getIncome(): float
    {
        return round($this->income, 2);
    }

    public function getIncomeMap(): array
    {
        return $this->income_map;
    }

    public function getIncomeTaxes(): float
    {
        return round($this->income_taxes, 2);
    }

    public function getExpenses(): array
    {
        return $this->expenses;
    }

    public function getExpenseBreakDown(): array
    {
        ksort($this->expense_break_down);

        return $this->expense_break_down;
    }

    private function filterIncome()
    {
        $invoices = $this->invoiceIncome();

        $this->foreign_income = [];

        $this->income = 0;
        $this->income_taxes = 0;
        $this->income_map = $invoices;

        foreach ($invoices as $invoice) {
            $this->income += $invoice->net_converted_amount;
            $this->income_taxes += $invoice->net_converted_taxes;

            $currency = Currency::find(intval(str_replace('"', '', $invoice->currency_id)));
            $currency->name = ctrans('texts.currency_'.Str::slug($currency->name, '_'));

            $this->foreign_income[] = ['currency' => $currency->name, 'amount' => $invoice->amount, 'total_taxes' => $invoice->total_taxes];
        }

        return $this;
    }

    private function filterInvoicePaymentIncome()
    {
        $this->paymentEloquentIncome();

        foreach ($this->invoice_payment_map as $map) {
            $this->income += $map->amount_payment_paid_converted - $map->tax_amount_converted;
            $this->income_taxes += $map->tax_amount_converted;

            $this->credit += $map->amount_credit_paid_converted - $map->tax_amount_credit_converted;
            $this->credit_taxes += $map->tax_amount_credit_converted;
        }

        return $this;
    }

    // private function getForeignIncome(): array
    // {
    //     return $this->foreign_income;
    // }

    // private function filterPaymentIncome()
    // {
    //     $payments = $this->paymentIncome();

    //     return $this;
    // }

    /*
        //returns an array of objects
        => [
         {#2047
           +"amount": "706.480000",
           +"total_taxes": "35.950000",
           +"currency_id": ""1"",
           +"net_converted_amount": "670.5300000000",
           +"net_converted_taxes": "10"
         },
         {#2444
           +"amount": "200.000000",
           +"total_taxes": "0.000000",
           +"currency_id": ""23"",
           +"net_converted_amount": "1.7129479802",
           +"net_converted_taxes": "10"
         },
         {#2654
           +"amount": "140.000000",
           +"total_taxes": "40.000000",
           +"currency_id": ""12"",
           +"net_converted_amount": "69.3275024282",
           +"net_converted_taxes": "10"
         },
       ]
   */
    private function invoiceIncome()
    {
        return \DB::select("
            SELECT
            sum(invoices.amount) as amount,
            sum(invoices.total_taxes) as total_taxes,
            (sum(invoices.total_taxes) / IFNULL(invoices.exchange_rate, 1)) AS net_converted_taxes,
            sum(invoices.amount - invoices.total_taxes) as net_amount,
            IFNULL(CAST(JSON_UNQUOTE(JSON_EXTRACT( clients.settings, '$.currency_id' )) AS SIGNED), :company_currency) AS currency_id,
            (sum(invoices.amount - invoices.total_taxes) / IFNULL(invoices.exchange_rate, 1)) AS net_converted_amount
            FROM clients
            JOIN invoices
            on invoices.client_id = clients.id
            WHERE invoices.status_id IN (2,3,4)
            AND invoices.company_id = :company_id
            AND invoices.amount > 0
            AND clients.is_deleted = 0
            AND invoices.is_deleted = 0
            AND (invoices.date BETWEEN :start_date AND :end_date)
            GROUP BY currency_id
        ", ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $this->start_date, 'end_date' => $this->end_date]);
    }

    /**
     * The income calculation is based on the total payments received during
     * the selected time period.
     *
     * Once we have the payments we iterate through the attached invoices and
     * we also determine the total taxes paid as our
     * Profit and loss statement should be net of all taxes
     *
     * This calculation also considers partial payments and pro rata's any taxes.
     *
     * This calculation also considers exchange rates and we convert (based on the payment exchange rate)
     * to the native company currency.
     */
    private function paymentEloquentIncome()
    {
        $this->invoice_payment_map = [];

        Payment::query()->where('company_id', $this->company->id)
                        ->whereIn('status_id', [1, 4, 5])
                        ->where('is_deleted', 0)
                        ->whereBetween('date', [$this->start_date, $this->end_date])
                        ->whereHas('client', function ($query) {
                            $query->where('is_deleted', 0);
                        })
                        ->with(['company', 'client'])
                        ->cursor()
                        ->each(function ($payment) {

                            $map = new \stdClass();
                            $amount_payment_paid = 0;
                            $amount_credit_paid = 0;
                            $amount_payment_paid_converted = 0;
                            $amount_credit_paid_converted = 0;
                            $tax_amount = 0;
                            $tax_amount_converted = 0;
                            $tax_amount_credit = 0;
                            $tax_amount_credit_converted = $tax_amount_credit_converted = 0;

                            $invoice = false;

                            foreach ($payment->paymentables as $pivot) {
                                if ($pivot->paymentable_type == 'invoices') {
                                    $invoice = Invoice::query()->withTrashed()->find($pivot->paymentable_id);

                                    if (!$invoice) {
                                        continue;
                                    }

                                    $pivot_diff = $pivot->amount - $pivot->refunded;
                                    $amount_payment_paid += $pivot_diff;
                                    $amount_payment_paid_converted += $pivot_diff * ($payment->exchange_rate ?: 1);

                                    if ($invoice->amount > 0) {
                                        $tax_amount += ($pivot_diff / $invoice->amount) * $invoice->total_taxes;
                                        $tax_amount_converted += (($pivot_diff / $invoice->amount) * $invoice->total_taxes) / $invoice->exchange_rate;
                                    }

                                }

                                if (!$invoice) {
                                    continue;
                                }

                                if ($pivot->paymentable_type == 'credits') {
                                    $amount_credit_paid += $pivot->amount - $pivot->refunded;
                                    $amount_credit_paid_converted += $pivot_diff * ($payment->exchange_rate ?: 1);

                                    $tax_amount_credit += ($pivot_diff / $invoice->amount) * $invoice->total_taxes;
                                    $tax_amount_credit_converted += (($pivot_diff / $invoice->amount) * $invoice->total_taxes) / $invoice->exchange_rate;
                                }
                            }

                            $map->amount_payment_paid = $amount_payment_paid;
                            $map->amount_payment_paid_converted = $amount_payment_paid_converted;
                            $map->tax_amount = $tax_amount;
                            $map->tax_amount_converted = $tax_amount_converted;
                            $map->amount_credit_paid = $amount_credit_paid;
                            $map->amount_credit_paid_converted = $amount_credit_paid_converted;
                            $map->tax_amount_credit = $tax_amount_credit;
                            $map->tax_amount_credit_converted = $tax_amount_credit_converted;
                            $map->currency_id = $payment->currency_id;

                            $this->invoice_payment_map[] = $map;
                        });

        return $this;
    }

    /**
     => [
     {#2047
       +"amount": "110.000000",
       +"total_taxes": "10.0000000000000000",
       +"net_converted_amount": "110.0000000000",
       +"net_converted_taxes": "10.00000000000000000000",
       +"currency_id": ""1"",
     },
     {#2444
       +"amount": "50.000000",
       +"total_taxes": "4.5454545454545455",
       +"net_converted_amount": "61.1682150381",
       +"net_converted_taxes": "5.56074682164393914741",
       +"currency_id": ""2"",
     },
   ]
     */
    public function getCsv()
    {
        nlog($this->income);
        nlog($this->income_taxes);
        nlog(array_sum(array_column($this->expense_break_down, 'total')));

        $csv = Writer::createFromString();

        $csv->insertOne([ctrans('texts.profit_and_loss')]);
        $csv->insertOne([ctrans('texts.company_name'), $this->company->present()->name()]);
        $csv->insertOne([ctrans('texts.date_range'), Carbon::parse($this->start_date)->format($this->company->date_format()), Carbon::parse($this->end_date)->format($this->company->date_format())]);

        //gross sales ex tax

        $csv->insertOne(['--------------------']);

        $csv->insertOne([ctrans('texts.total_revenue'). "[".ctrans('texts.tax')." " .ctrans('texts.exclusive'). "]", Number::formatMoney($this->income, $this->company)]);

        //total taxes

        $csv->insertOne([ctrans('texts.total_taxes'), Number::formatMoney($this->income_taxes, $this->company)]);

        //expense

        $csv->insertOne(['--------------------']);
        foreach ($this->expense_break_down as $expense_breakdown) {
            $csv->insertOne([$expense_breakdown['category_name'], Number::formatMoney($expense_breakdown['total'], $this->company)]);
        }
        //total expense taxes

        $csv->insertOne(['--------------------']);
        $csv->insertOne([ctrans('texts.total_expenses'). "[".ctrans('texts.tax')." " .ctrans('texts.exclusive'). "]", Number::formatMoney(array_sum(array_column($this->expense_break_down, 'total')), $this->company)]);

        $csv->insertOne([ctrans('texts.total_taxes'), Number::formatMoney(array_sum(array_column($this->expense_break_down, 'tax')), $this->company)]);

        $csv->insertOne(['--------------------']);
        $csv->insertOne([ctrans('texts.total_profit'), Number::formatMoney($this->income - array_sum(array_column($this->expense_break_down, 'total')), $this->company)]);

        //net profit

        $csv->insertOne(['--------------------']);
        $csv->insertOne(['']);
        $csv->insertOne(['']);


        $csv->insertOne(['--------------------']);
        $csv->insertOne([ctrans('texts.revenue')]);
        $csv->insertOne(['--------------------']);

        $csv->insertOne([ctrans('texts.currency'), ctrans('texts.amount'), ctrans('texts.total_taxes')]);
        foreach ($this->foreign_income as $foreign_income) {
            $csv->insertOne([$foreign_income['currency'], ($foreign_income['amount'] - $foreign_income['total_taxes']), $foreign_income['total_taxes']]);
        }

        $csv->insertOne(['']);
        $csv->insertOne(['']);
        $csv->insertOne(['--------------------']);
        $csv->insertOne([ctrans('texts.expenses')]);
        $csv->insertOne(['--------------------']);
        foreach ($this->expenses as $expense) {
            $csv->insertOne([$expense->currency, ($expense->total - $expense->foreign_tax_amount), $expense->foreign_tax_amount]);
        }

        return  $csv->toString();
    }

    /**
       +"payments": "12260.870000",
       +"payments_converted": "12260.870000000000",
       +"currency_id": 1,
     */
    // private function paymentIncome()
    // {
    //     return \DB::select('
    //          SELECT
    //          SUM(coalesce(payments.amount - payments.refunded,0)) as payments,
    //          SUM(coalesce(payments.amount - payments.refunded,0)) * IFNULL(payments.exchange_rate ,1) as payments_converted,
    //          payments.currency_id as currency_id
    //          FROM clients
    //          INNER JOIN
    //          payments ON
    //          clients.id=payments.client_id
    //          WHERE payments.status_id IN (1,4,5,6)
    //          AND clients.is_deleted = false
    //          AND payments.is_deleted = false
    //          AND payments.company_id = :company_id
    //          AND (payments.date BETWEEN :start_date AND :end_date)
    //          GROUP BY currency_id
    //          ORDER BY currency_id;
    //     ', ['company_id' => $this->company->id, 'start_date' => $this->start_date, 'end_date' => $this->end_date]);
    // }

    private function expenseData()
    {
        $expenses = Expense::query()->where('company_id', $this->company->id)
                           ->where(function ($query) {
                               $query->whereNull('client_id')->orWhereHas('client', function ($q) {
                                   $q->where('is_deleted', 0);
                               });
                           })
                           ->where('is_deleted', 0)
                           ->withTrashed()
                           ->whereBetween('date', [$this->start_date, $this->end_date])
                           ->cursor();

        $this->expenses = [];

        $company_currency_code = $this->company->currency()->code;

        foreach ($expenses as $expense) {
            $map = new \stdClass();

            $expense_tax_total = $this->getTax($expense);
            $map->total = $expense->amount;
            $map->converted_total = $converted_total = $this->getConvertedTotal($expense->amount, $expense->exchange_rate); //converted to company currency
            $map->tax = $tax = $this->getConvertedTotal($expense_tax_total, $expense->exchange_rate); //tax component
            $map->net_converted_total = $expense->uses_inclusive_taxes ? ($converted_total - $tax) : $converted_total; //excludes all taxes
            $map->category_id = $expense->category_id;
            $map->category_name = $expense->category ? $expense->category->name : 'No Category Defined';
            $map->currency_id = $expense->currency_id ?: $expense->company->settings->currency_id;
            $map->currency = $expense->currency ? $expense->currency->code : $company_currency_code;
            $map->foreign_tax_amount = $expense_tax_total;
            $this->expenses[] = $map;
        }

        return $this;
    }

    private function buildExpenseBreakDown()
    {
        $data = [];

        foreach ($this->expenses as $expense) {
            if (! array_key_exists($expense->category_id, $data)) {
                $data[$expense->category_id] = [];
            }

            if (! array_key_exists('total', $data[$expense->category_id])) {
                $data[$expense->category_id]['total'] = 0;
            }

            if (! array_key_exists('tax', $data[$expense->category_id])) {
                $data[$expense->category_id]['tax'] = 0;
            }

            $data[$expense->category_id]['total'] += $expense->net_converted_total;
            $data[$expense->category_id]['category_name'] = $expense->category_name;
            $data[$expense->category_id]['tax'] += $expense->tax;
        }

        $this->expense_break_down = $data;

        return $this;
    }

    private function getTax($expense)
    {
        $amount = $expense->amount;
        //is amount tax

        if ($expense->calculate_tax_by_amount) {
            return $expense->tax_amount1 + $expense->tax_amount2 + $expense->tax_amount3;
        }

        if ($expense->uses_inclusive_taxes) {
            $inclusive = 0;

            $inclusive += ($amount - ($amount / (1 + ($expense->tax_rate1 / 100))));
            $inclusive += ($amount - ($amount / (1 + ($expense->tax_rate2 / 100))));
            $inclusive += ($amount - ($amount / (1 + ($expense->tax_rate3 / 100))));

            return round($inclusive, 2);
        }

        $exclusive = 0;

        $exclusive += $amount * ($expense->tax_rate1 / 100);
        $exclusive += $amount * ($expense->tax_rate2 / 100);
        $exclusive += $amount * ($expense->tax_rate3 / 100);

        return $exclusive;
    }

    private function getConvertedTotal($amount, $exchange_rate = 1)
    {
        return round(($amount * $exchange_rate), 2);
    }

    // private function expenseCalcWithTax()
    // {
    //     return \DB::select('
    //         SELECT sum(expenses.amount) as amount,
    //         IFNULL(expenses.currency_id, :company_currency) as currency_id
    //         FROM expenses
    //         WHERE expenses.is_deleted = 0
    //         AND expenses.company_id = :company_id
    //         AND (expenses.date BETWEEN :start_date AND :end_date)
    //         GROUP BY currency_id
    //     ', ['company_currency' => $this->company->settings->currency_id, 'company_id' => $this->company->id, 'start_date' => $this->start_date, 'end_date' => $this->end_date]);
    // }

    private function setBillingReportType()
    {
        if (array_key_exists('is_income_billed', $this->payload)) {
            $this->is_income_billed = boolval($this->payload['is_income_billed']);
        }

        if (array_key_exists('is_expense_billed', $this->payload)) {
            $this->is_expense_billed = boolval($this->payload['is_expense_billed']);
        }

        if (array_key_exists('include_tax', $this->payload)) {
            $this->is_tax_included = boolval($this->payload['include_tax']);
        }

        $this->addDateRange();

        return $this;
    }

    private function addDateRange()
    {
        $date_range = 'this_year';

        if (array_key_exists('date_range', $this->payload)) {
            $date_range = $this->payload['date_range'];
        }

        try {
            $custom_start_date = Carbon::parse($this->payload['start_date']);
            $custom_end_date = Carbon::parse($this->payload['end_date']);
        } catch (\Exception $e) {
            $custom_start_date = now()->startOfYear();
            $custom_end_date = now();
        }

        switch ($date_range) {
            case 'all':
                $this->start_date = now()->subYears(50);
                $this->end_date = now();
                break;

            case 'last7':
                $this->start_date = now()->subDays(7);
                $this->end_date = now();
                break;

            case 'last30':
                $this->start_date = now()->subDays(30);
                $this->end_date = now();
                break;

            case 'this_month':
                $this->start_date = now()->startOfMonth();
                $this->end_date = now();
                break;

            case 'last_month':
                $this->start_date = now()->startOfMonth()->subMonth();
                $this->end_date = now()->startOfMonth()->subMonth()->endOfMonth();
                break;

            case 'this_quarter':
                $this->start_date = (new \Carbon\Carbon('0 months'))->startOfQuarter();
                $this->end_date = (new \Carbon\Carbon('0 months'))->endOfQuarter();
                break;

            case 'last_quarter':
                $this->start_date = (new \Carbon\Carbon('-3 months'))->startOfQuarter();
                $this->end_date = (new \Carbon\Carbon('-3 months'))->endOfQuarter();
                break;

            case 'this_year':
                $this->start_date = now()->startOfYear();
                $this->end_date = now();
                break;

            case 'custom':
                $this->start_date = $custom_start_date;
                $this->end_date = $custom_end_date;
                break;
            default:
                $this->start_date = now()->startOfYear();
                $this->end_date = now();
        }

        return $this;
    }
}

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

namespace App\Services\Chart;

use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quote;
use App\Models\Task;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Class ChartCalculations.
 */
trait ChartCalculations
{
    public function getActiveInvoices($data): int|float
    {
        $result = 0;

        $q = Invoice::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2,3,4]);

        if (in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount'),
            'avg' => $result = $q->avg('amount'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getOutstandingInvoices($data): int|float
    {
        $result = 0;

        $q = Invoice::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2,3]);

        if (in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('balance'),
            'avg' => $result = $q->avg('balance'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getCompletedPayments($data): int|float
    {
        $result = 0;

        $q = Payment::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->where('status_id', 4);

        if (in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount'),
            'avg' => $result = $q->avg('amount'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getRefundedPayments($data): int|float
    {
        $result = 0;

        $q = Payment::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [5,6]);

        if (in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('refunded'),
            'avg' => $result = $q->avg('refunded'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getActiveQuotes($data): int|float
    {
        $result = 0;

        $q = Quote::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2,3])
                    ->where(function ($qq) {
                        $qq->where('due_date', '>=', now()->toDateString())->orWhereNull('due_date');
                    });

        if (in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('amount'),
            'avg' => $result = $q->avg('amount'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getUnapprovedQuotes($data): int|float
    {
        $result = 0;

        $q = Quote::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0)
                    ->whereIn('status_id', [2])
                    ->where(function ($qq) {
                        $qq->where('due_date', '>=', now()->toDateString())->orWhereNull('due_date');
                    });

        if (in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        match ($data['calculation']) {
            'sum' => $result = $q->sum('refunded'),
            'avg' => $result = $q->avg('refunded'),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

    public function getLoggedTasks($data): int|float
    {

        $q = $this->taskQuery($data);

        return $this->taskCalculations($q, $data);

    }

    public function getPaidTasks($data): int|float
    {
        $q = $this->taskQuery($data);
        $q->whereHas('invoice', function ($query) {
            $query->where('status_id', 4)->where('is_deleted', 0);
        });

        return $this->taskCalculations($q, $data);

    }

    public function getInvoicedTasks($data): int|float
    {

        $q = $this->taskQuery($data);
        $q->whereHas('invoice');

        return $this->taskCalculations($q, $data);

    }

    /**
     * All Expenses
     */
    public function getLoggedExpenses($data): int|float
    {
        $q = $this->expenseQuery($data);

        return $this->expenseCalculations($q, $data);
    }


    /**
     * Expenses that should be invoiced - but are not yet invoiced.
     */
    public function getPendingExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->where('should_be_invoiced', true)->whereNull('invoice_id');
        return $this->expenseCalculations($q, $data);
    }

    /**
     * Invoiced.
     */
    public function getInvoicedExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->whereNotNull('invoice_id');
        return $this->expenseCalculations($q, $data);
    }

    /**
     * Paid.
     */
    public function getPaidExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->whereNotNull('payment_date');
        return $this->expenseCalculations($q, $data);
    }

    /**
     * Paid.
     */
    public function getInvoicedPaidExpenses($data): int|float
    {

        $q = $this->expenseQuery($data);
        $q->whereNotNull('invoice_id')->whereNotNull('payment_date');
        return $this->expenseCalculations($q, $data);
    }

    private function expenseCalculations(Builder $query, array $data): int|float
    {

        $result = 0;
        $calculated = $this->expenseCalculator($query, $data);

        match ($data['calculation']) {
            'sum' => $result = $calculated->sum(),
            'avg' => $result = $calculated->avg(),
            'count' => $result = $query->count(),
            default => $result = 0,
        };

        return $result;


    }

    private function expenseCalculator(Builder $query, array $data)
    {

        return $query->get()
                    ->when($data['currency_id'] == '999', function ($collection) {
                        return $collection->map(function ($e) {
                            /** @var \App\Models\Expense $e */
                            return $e->amount * $e->exchange_rate;
                        });
                    })
                    ->when($data['currency_id'] != '999', function ($collection) {

                        return $collection->map(function ($e) {

                            /** @var \App\Models\Expense $e */
                            return $e->amount;
                        });

                    });

    }

    private function expenseQuery($data): Builder
    {
        $query = Expense::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0);

        if (in_array($data['period'], ['current,previous'])) {
            $query->whereBetween('date', [$data['start_date'], $data['end_date']]);
        }

        return $query;
    }

    ////////////////////////////////////////////////////////////////
    private function taskMoneyCalculator($query, $data)
    {

        return $query->get()
                    ->when($data['currency_id'] == '999', function ($collection) {
                        $collection->map(function ($t) {
                            return $t->taskCompanyValue();
                        });
                    })
                    ->when($data['currency_id'] != '999', function ($collection) {

                        $collection->map(function ($t) {
                            return $t->taskValue();
                        });

                    });

    }

    private function taskQuery($data): Builder
    {
        $q = Task::query()
                    ->withTrashed()
                    ->where('company_id', $this->company->id)
                    ->where('is_deleted', 0);

        if (in_array($data['period'], ['current,previous'])) {
            $q->whereBetween('calculated_start_date', [$data['start_date'], $data['end_date']]);
        }

        return $q;

    }

    private function taskCalculations(Builder $q, array $data): int|float
    {

        $result = 0;
        $calculated = collect();

        if ($data['calculation'] != 'count' && $data['format'] == 'money') {
            if ($data['currency_id'] != '999') {

                $q->whereHas('client', function ($query) use ($data) {
                    $query->where('settings->currency_id', $data['currency_id']);
                });

            }

            $calculated = $this->taskMoneyCalculator($q, $data);

        }

        if ($data['calculation'] != 'count' && $data['format'] == 'time') {
            $calculated = $q->get()->map(function ($t) {
                return $t->calcDuration();
            });
        }

        match ($data['calculation']) {
            'sum' => $result = $calculated->sum(),
            'avg' => $result = $calculated->avg(),
            'count' => $result = $q->count(),
            default => $result = 0,
        };

        return $result;

    }

}

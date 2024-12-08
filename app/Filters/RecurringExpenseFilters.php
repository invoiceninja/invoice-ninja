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

namespace App\Filters;

use Illuminate\Database\Eloquent\Builder;

/**
 * RecurringExpenseFilters.
 */
class RecurringExpenseFilters extends QueryFilters
{
    /**
     * Filter based on search text.
     *
     * @param string $filter
     * @return Builder
     * @deprecated
     */
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return  $this->builder->where(function ($query) use ($filter) {
            $query->where('number', 'like', '%' . $filter . '%')
                ->orWhere('amount', 'like', '%' . $filter . '%')
                ->orWhere('public_notes', 'like', '%' . $filter . '%')
                ->orWhere('custom_value1', 'like', '%' . $filter . '%')
                ->orWhere('custom_value2', 'like', '%' . $filter . '%')
                ->orWhere('custom_value3', 'like', '%' . $filter . '%')
                ->orWhere('custom_value4', 'like', '%' . $filter . '%')
                ->orWhereHas('category', function ($q) use ($filter) {
                    $q->where('name', 'like', '%' . $filter . '%');
                })
                ->orWhereHas('vendor', function ($q) use ($filter) {
                    $q->where('name', 'like', '%' . $filter . '%');
                });
        });

    }

    public function number(string $number = ''): Builder
    {
        if (strlen($number) == 0) {
            return $this->builder;
        }

        return $this->builder->where('number', $number);
    }


    /**
     * Filter based on client status.
     *
     * Statuses we need to handle
     * - all
     * - logged
     * - pending
     * - invoiced
     * - paid
     * - unpaid
     *
     * @return Builder
     */
    public function client_status(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        $status_parameters = explode(',', $value);

        if (in_array('all', $status_parameters)) {
            return $this->builder;
        }

        $this->builder->where(function ($query) use ($status_parameters) {
            if (in_array('logged', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->where('amount', '>', 0)
                          ->whereNull('invoice_id')
                          ->whereNull('payment_date')
                          ->where('should_be_invoiced', false);
                });
            }

            if (in_array('pending', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->where('should_be_invoiced', true)
                          ->whereNull('invoice_id');
                });
            }

            if (in_array('invoiced', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('invoice_id');
                });
            }

            if (in_array('paid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('payment_date');
                });
            }

            if (in_array('unpaid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNull('payment_date');
                });
            }
        });

        // nlog($this->builder->toSql());

        return $this->builder;
    }


    /**
     * Sorts the list based on $sort.
     *
     * @param string $sort formatted as column|asc
     * @return Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (!is_array($sort_col) || count($sort_col) != 2) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        if ($sort_col[0] == 'client_id' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(client_id), client_id '. $sort_col[1])
                    ->orderBy(\App\Models\Client::select('name')
                    ->whereColumn('clients.id', 'recurring_expenses.client_id'), $sort_col[1]);
        }

        if ($sort_col[0] == 'vendor_id' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(vendor_id), vendor_id '. $sort_col[1])
                    ->orderBy(\App\Models\Vendor::select('name')
                    ->whereColumn('vendors.id', 'recurring_expenses.vendor_id'), $sort_col[1]);

        }

        if ($sort_col[0] == 'category_id' && in_array($sort_col[1], ['asc', 'desc'])) {
            return $this->builder
                    ->orderByRaw('ISNULL(category_id), category_id '. $sort_col[1])
                    ->orderBy(\App\Models\ExpenseCategory::select('name')
                    ->whereColumn('expense_categories.id', 'recurring_expenses.category_id'), $sort_col[1]);
        }

        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw('ABS(number) ' . $dir);
        }

        if (is_array($sort_col) && in_array($sort_col[1], ['asc', 'desc']) && in_array($sort_col[0], ['public_notes', 'date', 'id_number', 'custom_value1', 'custom_value2', 'custom_value3', 'custom_value4'])) {
            return $this->builder->orderBy($sort_col[0], $sort_col[1]);
        }

        return $this->builder;
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function entityFilter(): Builder
    {
        return $this->builder->company();
    }
}

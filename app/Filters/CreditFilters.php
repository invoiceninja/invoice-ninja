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

use App\Models\Credit;
use Illuminate\Database\Eloquent\Builder;

class CreditFilters extends QueryFilters
{
    /**
     * Filter based on client status.
     *
     * Statuses we need to handle
     * - all
     * - paid
     * - unpaid
     * - overdue
     * - reversed
     *
     * @param string $value The credit status as seen by the client
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

        $credit_filters = [];

        if (in_array('draft', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_DRAFT;
        }

        if (in_array('sent', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_SENT;
        }

        if (in_array('partial', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_PARTIAL;
        }

        if (in_array('applied', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_APPLIED;
        }

        if (count($credit_filters) >= 1) {
            $this->builder->whereIn('status_id', $credit_filters);
        }

        return $this->builder;
    }

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
            $query->where('credits.number', 'like', '%'.$filter.'%')
                          ->orWhere('credits.number', 'like', '%'.$filter.'%')
                          ->orWhere('credits.date', 'like', '%'.$filter.'%')
                          ->orWhere('credits.amount', 'like', '%'.$filter.'%')
                          ->orWhere('credits.balance', 'like', '%'.$filter.'%')
                          ->orWhere('credits.custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('credits.custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('credits.custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('credits.custom_value4', 'like', '%'.$filter.'%')
                          ->orWhereHas('client', function ($q) use ($filter) {
                              $q->where('name', 'like', '%'.$filter.'%');
                          })
                          ->orWhereHas('client.contacts', function ($q) use ($filter) {
                              $q->where('first_name', 'like', '%'.$filter.'%')
                                ->orWhere('last_name', 'like', '%'.$filter.'%')
                                ->orWhere('email', 'like', '%'.$filter.'%');
                          })
                                                    ->orWhereRaw("
                            JSON_UNQUOTE(JSON_EXTRACT(
                                JSON_ARRAY(
                                    JSON_UNQUOTE(JSON_EXTRACT(line_items, '$[*].notes')), 
                                    JSON_UNQUOTE(JSON_EXTRACT(line_items, '$[*].product_key'))
                                ), '$[*]')
                            ) LIKE ?", ['%'.$filter.'%']);
            //   ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(line_items, '$[*].notes')) LIKE ?", ['%'.$filter.'%']);
        });
    }

    public function applicable(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        return $this->builder->where(function ($query) {
            $query->whereIn('status_id', [Credit::STATUS_SENT, Credit::STATUS_PARTIAL])
                  ->where('balance', '>', 0)
                  ->where(function ($q) {
                      $q->whereNull('due_date')->orWhere('due_date', '>', now());
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

        if ($sort_col[0] == 'client_id') {
            return $this->builder->orderBy(\App\Models\Client::select('name')
                    ->whereColumn('clients.id', 'credits.client_id'), $dir);
        }


        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . $dir);
        }

        return $this->builder->orderBy($sort_col[0], $dir);
    }

    /**
     * Filters the query by the users company ID.
     *
     * We need to ensure we are using the correct company ID
     * as we could be hitting this from either the client or company auth guard
     *
     * @return Builder
     */
    public function entityFilter()
    {
        if (auth()->guard('contact')->user()) {
            return $this->contactViewFilter();
        } else {
            return $this->builder->company();
        }

        //            return $this->builder->whereCompanyId(auth()->user()->company()->id);
    }

    /**
     * We need additional filters when showing credits for the
     * client portal. Need to automatically exclude drafts and cancelled credits.
     *
     * @return Builder
     */
    private function contactViewFilter(): Builder
    {
        return $this->builder
                    ->whereCompanyId(auth()->guard('contact')->user()->company->id)
                    ->whereNotIn('status_id', [Credit::STATUS_DRAFT]);
    }
}

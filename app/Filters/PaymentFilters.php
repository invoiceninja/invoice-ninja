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

use App\Models\Payment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Builder;

/**
 * PaymentFilters.
 */
class PaymentFilters extends QueryFilters
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
            $query->where('amount', 'like', '%'.$filter.'%')
                          ->orWhere('date', 'like', '%'.$filter.'%')
                          ->orWhere('number', 'like', '%'.$filter.'%')
                          ->orWhere('transaction_reference', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value4', 'like', '%'.$filter.'%')
                          ->orWhereHas('client', function ($q) use ($filter) {
                              $q->where('name', 'like', '%'.$filter.'%');
                          })
                            ->orWhereHas('client.contacts', function ($q) use ($filter) {
                                $q->where('first_name', 'like', '%'.$filter.'%')
                                  ->orWhere('last_name', 'like', '%'.$filter.'%')
                                  ->orWhere('email', 'like', '%'.$filter.'%');
                            });
        });
    }


    /**
        * Filter based on client status.
        *
        * Statuses we need to handle
        * - all
        * - pending
        * - cancelled
        * - failed
        * - completed
        * - partially refunded
        * - refunded
        *
        * @param string $value The payment status as seen by the client
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
            $payment_filters = [];

            if (in_array('pending', $status_parameters)) {
                $payment_filters[] = Payment::STATUS_PENDING;
            }

            if (in_array('cancelled', $status_parameters)) {
                $payment_filters[] = Payment::STATUS_CANCELLED;
            }

            if (in_array('failed', $status_parameters)) {
                $payment_filters[] = Payment::STATUS_FAILED;
            }

            if (in_array('completed', $status_parameters)) {
                $payment_filters[] = Payment::STATUS_COMPLETED;
            }

            if (in_array('partially_refunded', $status_parameters)) {
                $payment_filters[] = Payment::STATUS_PARTIALLY_REFUNDED;
            }

            if (in_array('refunded', $status_parameters)) {
                $payment_filters[] = Payment::STATUS_REFUNDED;
            }

            if (count($payment_filters) > 0) {
                $query->whereIn('status_id', $payment_filters);
            }

            if (in_array('partially_unapplied', $status_parameters)) {
                $query->whereColumn('amount', '>', 'applied')->where('refunded', 0);
            }
        });


        return $this->builder;
    }

    /**
     * Returns a list of payments that can be matched to bank transactions
     * @param ?string $value
     * @return Builder
     */
    public function match_transactions($value = 'true'): Builder
    {

        if ($value == 'true') {
            return $this->builder
                        ->where('is_deleted', 0)
                        ->where(function (Builder $query) {
                            $query->whereNull('transaction_id')
                            ->orWhere("transaction_id", "")
                            ->company();
                        });
        }

        return $this->builder;
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
     *  formatted as column|asc
     *
     * @param string $sort
     * @return Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (!is_array($sort_col) || count($sort_col) != 2 || !in_array($sort_col[0], Schema::getColumnListing('payments'))) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        if ($sort_col[0] == 'client_id') {
            return $this->builder->orderBy(\App\Models\Client::select('name')
                    ->whereColumn('clients.id', 'payments.client_id'), $dir);
        }

        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . $dir);
        }

        return $this->builder->orderBy($sort_col[0], $dir);
    }

    public function date_range(string $date_range = ''): Builder
    {
        $parts = explode(",", $date_range);

        if (count($parts) != 3) {
            return $this->builder;
        }

        if (!in_array($parts[0], ['date'])) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($parts[1]);
            $end_date = Carbon::parse($parts[2]);

            return $this->builder->whereBetween($parts[0], [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Builder
     */
    public function entityFilter(): Builder
    {
        if (auth()->guard('contact')->user()) {
            return $this->contactViewFilter();
        } else {
            return $this->builder->company();
        }
    }

    /**
     * We need additional filters when showing invoices for the
     * client portal. Need to automatically exclude drafts and cancelled invoices.
     *
     * @return Builder
     */
    private function contactViewFilter(): Builder
    {
        return $this->builder
                    ->whereCompanyId(auth()->guard('contact')->user()->company->id)
                    ->whereIsDeleted(false);
    }
}

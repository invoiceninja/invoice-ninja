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

use App\Models\BankTransaction;
use Illuminate\Database\Eloquent\Builder;

/**
 * BankTransactionFilters.
 */
class BankTransactionFilters extends QueryFilters
{
    /**
     * Filter by name.
     *
     * @param string $name
     * @return Builder
     */
    public function name(string $name = ''): Builder
    {
        if (strlen($name) == 0) {
            return $this->builder;
        }

        return $this->builder->where('bank_account_name', 'like', '%'.$name.'%');
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
            $query->where('bank_transactions.description', 'like', '%'.$filter.'%');
        });
    }


    /**
         * Filter based on client status.
         *
         * Statuses we need to handle
         * - all
         * - unmatched
         * - matched
         * - converted
         * - deposits
         * - withdrawals
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
            $status_array = [];

            $debit_or_withdrawal_array = [];

            if (in_array('unmatched', $status_parameters)) {
                $status_array[] = BankTransaction::STATUS_UNMATCHED;
            }

            if (in_array('matched', $status_parameters)) {
                $status_array[] = BankTransaction::STATUS_MATCHED;
            }

            if (in_array('converted', $status_parameters)) {
                $status_array[] = BankTransaction::STATUS_CONVERTED;
            }

            if (in_array('deposits', $status_parameters)) {
                $debit_or_withdrawal_array[] = 'CREDIT';
            }

            if (in_array('withdrawals', $status_parameters)) {
                $debit_or_withdrawal_array[] = 'DEBIT';
            }

            if (count($status_array) >= 1) {
                $query->whereIn('status_id', $status_array);
            }

            if (count($debit_or_withdrawal_array) >= 1) {
                $query->whereIn('base_type', $debit_or_withdrawal_array);
            }
        });

        return $this->builder;
    }

    public function active_banks(string $value = ''): Builder
    {

        if (strlen($value) == 0 || $value != 'true') {
            return $this->builder;
        }

        return $this->builder->whereHas('bank_integration', function ($query) {
            $query->where('is_deleted', 0)->whereNull('deleted_at');
        });
    }

    /**
     * Filters the list based on Bank Accounts.
     *
     * @param string $ids Comma Separated List of bank account ids
     * @return Builder
     */
    public function bank_integration_ids(string $ids = ''): Builder
    {
        if (strlen($ids) == 0) {
            return $this->builder;
        }

        $ids = $this->transformKeys(explode(",", $ids));

        $this->builder->where(function ($query) use ($ids) {
            $query->whereIn('bank_integration_id', $ids);
        });

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

        if ($sort_col[0] == 'deposit') {
            return $this->builder->orderByRaw("(CASE WHEN base_type = 'CREDIT' THEN amount END) $dir")->orderBy('amount', $dir);
            // return $this->builder->where('base_type', 'CREDIT')->orderBy('amount', $dir);
        }

        if ($sort_col[0] == 'withdrawal') {
            return $this->builder->orderByRaw("(CASE WHEN base_type = 'DEBIT' THEN amount END) $dir")->orderBy('amount', $dir);
            // return $this->builder->where('base_type', 'DEBIT')->orderBy('amount', $dir);
        }

        if ($sort_col[0] == 'status') {
            $sort_col[0] = 'status_id';
        }

        if (in_array($sort_col[0], ['invoices','expense'])) {
            return $this->builder;
        }

        return $this->builder->orderBy($sort_col[0], $dir);
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function entityFilter()
    {
        return $this->builder->company();
    }
}

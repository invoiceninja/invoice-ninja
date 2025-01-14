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
 * ProductAllocationFilters.
 */
class ProductAllocationFilters extends QueryFilters
{
    protected $with_property = 'product_key';

    /**
     * Filter based on search text.
     *
     * @param string $filter
     * @return Builder
     */
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->where(function ($query) use ($filter) {
            $query->where('invoice_aggregation_key', 'like', '%' . $filter . '%')
                ->orWhere('private_notes', 'like', '%' . $filter . '%')
                ->orWhere('public_notes', 'like', '%' . $filter . '%')
                ->orWhere('custom_value1', 'like', '%' . $filter . '%')
                ->orWhere('custom_value2', 'like', '%' . $filter . '%')
                ->orWhere('custom_value3', 'like', '%' . $filter . '%')
                ->orWhere('custom_value4', 'like', '%' . $filter . '%');
        })->orWhereHas('product', function ($query) use ($filter) {
            $query->where('product_key', 'like', '%' . $filter . '%')
                ->orWhere('notes', 'like', '%' . $filter . '%')
                ->orWhere('custom_value1', 'like', '%' . $filter . '%')
                ->orWhere('custom_value2', 'like', '%' . $filter . '%')
                ->orWhere('custom_value3', 'like', '%' . $filter . '%')
                ->orWhere('custom_value4', 'like', '%' . $filter . '%');
        });
    }

    /**
     * Filter based on invoice aggregation keys
     * @param string $filter
     * @return Builder
     */
    public function invoice_aggregation_keys(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('invoice_aggregation_key', explode(",", $filter));

    }

    /**
     * Filter based on product id
     * @param string $filter
     * @return Builder
     */
    public function products(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('product_id', explode(",", $filter));

    }

    /**
     * Filter based on client id
     * @param string $filter
     * @return Builder
     */
    public function clients(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('client_id', explode(",", $filter));

    }

    /**
     * filter based on product key
     * @param string $filter
     * @return Builder
     */
    public function product_key(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereHas('product', function ($query) use ($filter) {
            $query->where('product_key', $filter);
        });

    }

    /**
     * Filter based on project id
     * @param string $filter
     * @return Builder
     */
    public function projects(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('project_id', explode(",", $filter));

    }

    /**
     * Filter based on invoice id
     * @param string $filter
     * @return Builder
     */
    public function invoices(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('invoice_id', explode(",", $filter));

    }

    /**
     * Filter based on invoice status
     * @param string $filter
     * @return Builder
     */
    public function invoice_status(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereHas('invoice', function ($query) use ($filter) {
            $query->whereIn('status_id', explode(",", $filter));
        });

    }

    /**
     * Filter based on recurring invoice id
     * @param string $filter
     * @return Builder
     */
    public function recurring_invoices(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('recurring_id', explode(",", $filter));

    }

    /**
     * Filter based on subscription id
     * @param string $filter
     * @return Builder
     */
    public function subscriptions(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('subscriptions', explode(",", $filter));

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

        return $this->builder->orderBy($sort_col[0], $sort_col[1]);
    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Builder
     */
    public function entityFilter(): Builder
    {
        return $this->builder->company();
    }
}

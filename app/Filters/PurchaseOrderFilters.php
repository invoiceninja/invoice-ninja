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

use App\Models\PurchaseOrder;
use Illuminate\Database\Eloquent\Builder;

class PurchaseOrderFilters extends QueryFilters
{
    /**
     * Filter based on client status.
     *
     * Statuses we need to handle
     * - all
     * - draft
     * - sent
     * - accepted
     * - cancelled
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
            $po_status = [];

            if (in_array('draft', $status_parameters)) {
                $po_status[] = PurchaseOrder::STATUS_DRAFT;
            }

            if (in_array('sent', $status_parameters)) {
                $query->orWhere(function ($q) {
                    $q->where('status_id', PurchaseOrder::STATUS_SENT)
                    ->whereNull('due_date')
                    ->orWhere('due_date', '>=', now()->toDateString());
                });
            }

            if (in_array('accepted', $status_parameters)) {
                $po_status[] = PurchaseOrder::STATUS_ACCEPTED;
            }

            if (in_array('cancelled', $status_parameters)) {
                $po_status[] = PurchaseOrder::STATUS_CANCELLED;
            }

            if (count($po_status) >= 1) {
                $query->whereIn('status_id', $po_status);
            }
        });

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
            $query->where('number', 'like', '%'.$filter.'%')
                ->orWhere('number', 'like', '%'.$filter.'%')
                ->orWhere('date', 'like', '%'.$filter.'%')
                ->orWhere('amount', 'like', '%'.$filter.'%')
                ->orWhere('balance', 'like', '%'.$filter.'%')
                ->orWhere('custom_value1', 'like', '%'.$filter.'%')
                ->orWhere('custom_value2', 'like', '%'.$filter.'%')
                ->orWhere('custom_value3', 'like', '%'.$filter.'%')
                ->orWhere('custom_value4', 'like', '%'.$filter.'%')
                ->orWhereHas('vendor', function ($q) use ($filter) {
                    $q->where('name', 'like', '%'.$filter.'%');
                })
                ->orWhereRaw("
                JSON_UNQUOTE(JSON_EXTRACT(
                    JSON_ARRAY(
                        JSON_UNQUOTE(JSON_EXTRACT(line_items, '$[*].notes')), 
                        JSON_UNQUOTE(JSON_EXTRACT(line_items, '$[*].product_key'))
                    ), '$[*]')
                ) LIKE ?", ['%'.$filter.'%']);
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

        if ($sort_col[0] == 'vendor_id') {
            return $this->builder->orderBy(\App\Models\Vendor::select('name')
                    ->whereColumn('vendors.id', 'purchase_orders.vendor_id'), $dir);
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
     */
    public function entityFilter(): Builder
    {
        if (auth()->guard('contact')->user()) {
            return $this->contactViewFilter();
        } else {
            return $this->builder->company();
        }

        //            return $this->builder->whereCompanyId(auth()->user()->company()->id);
    }

    /**
     * We need additional filters when showing purchase orders for the
     * client portal. Need to automatically exclude drafts and cancelled purchase orders.
     *
     * @return Builder
     */
    private function contactViewFilter(): Builder
    {
        return $this->builder
            ->whereCompanyId(auth()->guard('contact')->user()->company->id)
            ->whereNotIn('status_id', [PurchaseOrder::STATUS_DRAFT]);
    }
}

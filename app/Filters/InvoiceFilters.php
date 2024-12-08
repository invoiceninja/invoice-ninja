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

use App\Models\Client;
use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use InvalidArgumentException;
use RuntimeException;

/**
 * InvoiceFilters.
 */
class InvoiceFilters extends QueryFilters
{
    use MakesHash;

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
     * @param string $value The invoice status as seen by the client
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
            $invoice_filters = [];

            if (in_array('draft', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_DRAFT;
            }

            if (in_array('paid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_PAID;
            }

            if (in_array('cancelled', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_CANCELLED;
            }

            if (in_array('unpaid', $status_parameters)) {
                $invoice_filters[] = Invoice::STATUS_SENT;
                $invoice_filters[] = Invoice::STATUS_PARTIAL;
            }

            if (count($invoice_filters) > 0) {
                $query->whereIn('status_id', $invoice_filters);
            }

            if (in_array('overdue', $status_parameters)) {
                $query->orWhereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                ->where('due_date', '<', Carbon::now())
                                ->orWhere('partial_due_date', '<', Carbon::now());
            }
        });

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

        return $this->builder->where(function ($query) use ($filter) {
            $query->where('number', 'like', '%'.$filter.'%')
                          ->orWhere('po_number', 'like', '%'.$filter.'%')
                          ->orWhere('date', 'like', '%'.$filter.'%')
                          ->orWhere('amount', 'like', '%'.$filter.'%')
                          ->orWhere('balance', 'like', '%'.$filter.'%')
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

    /**
     * @return Builder
     * @throws RuntimeException
     */
    public function status_id(string $status = ''): Builder
    {

        if (strlen($status) == 0) {
            return $this->builder;
        }

        return $this->builder->whereIn('status_id', explode(",", $status));

    }

    /**
     * @return Builder
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function upcoming(): Builder
    {

        return $this->builder->where(function ($query) {
            $query->whereIn('status_id', [Invoice::STATUS_PARTIAL, Invoice::STATUS_SENT])
            ->where('is_deleted', 0)
            ->where('balance', '>', 0)
            ->where(function ($query) {

                $query->whereNull('due_date')
                    ->orWhere(function ($q) {
                        $q->where('due_date', '>=', now()->startOfDay()->subSecond())->where('partial', 0);
                    })
                    ->orWhere(function ($q) {
                        $q->where('partial_due_date', '>=', now()->startOfDay()->subSecond())->where('partial', '>', 0);
                    });

            })
            ->orderByRaw('ISNULL(due_date), due_date ' . 'desc')
            ->orderByRaw('ISNULL(partial_due_date), partial_due_date ' . 'desc');
        });

    }

    /**
     * @return void
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function overdue(): Builder
    {
        return $this->builder->where(function ($query) {

            $query->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_deleted', 0)
                    ->where('balance', '>', 0)
                    ->where(function ($query) {
                        $query->where('due_date', '<', now())
                            ->orWhere('partial_due_date', '<', now());
                    })
                    ->orderBy('due_date', 'ASC');
        });

    }

    /**
     * @param string $client_id
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function payable(string $client_id = ''): Builder
    {

        if (strlen($client_id) == 0) {
            return $this->builder;
        }

        return $this->builder
                    ->where('client_id', $this->decodePrimaryKey($client_id))
                    ->whereIn('status_id', [Invoice::STATUS_DRAFT, Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                    ->where('is_deleted', 0)
                    ->where('balance', '>', 0);
    }


    /**
     * @param string $date
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function date(string $date = ''): Builder
    {
        if (strlen($date) == 0) {
            return $this->builder;
        }

        if (is_numeric($date)) {
            $date = Carbon::createFromTimestamp((int)$date);
        } else {

            try {
                $date = Carbon::parse($date);
            } catch (\Exception $e) {
                return $this->builder;
            }
        }

        return $this->builder->where('date', '>=', $date);
    }

    /**
     * @param string $date
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function due_date(string $date = ''): Builder
    {
        if (strlen($date) == 0) {
            return $this->builder;
        }

        if (is_numeric($date)) {
            $date = Carbon::createFromTimestamp((int)$date);
        } else {
            $date = Carbon::parse($date);
        }

        return $this->builder->where('due_date', '>=', $date);
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

        if (!is_array($sort_col) || count($sort_col) != 2 || !in_array($sort_col[0], \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable()))) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        if ($sort_col[0] == 'client_id') {

            return $this->builder->orderBy(\App\Models\Client::select('name')
                             ->whereColumn('clients.id', 'invoices.client_id'), $dir);

        }

        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(invoices.number,'[^0-9]+','')+0 " . $dir);
        }

        return $this->builder->orderBy("{$this->builder->getQuery()->from}.".$sort_col[0], $dir);
    }

    /**
     * Filters the query by the users company ID.
     *
     * We need to ensure we are using the correct company ID
     * as we could be hitting this from either the client or company auth guard
     *
     * @return Builder
     */
    public function entityFilter(): Builder
    {
        if (auth()->guard('contact')->user()) {
            return $this->contactViewFilter();
        } else {
            return $this->builder->company()->with(['invitations.company'], ['documents.company']);
        }
    }

    /**
     * @param string $filter
     * @return Builder
     * @throws InvalidArgumentException
     */
    public function private_notes($filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->where('private_notes', 'LIKE', '%'.$filter.'%');
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
                    ->whereNotIn('status_id', [Invoice::STATUS_DRAFT, Invoice::STATUS_CANCELLED]);
    }
}

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

use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\Builder;

/**
 * TaskFilters.
 */
class TaskFilters extends QueryFilters
{
    use MakesHash;

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

        return  $this->builder->where(function ($query) use ($filter) {
            $query->where('description', 'like', '%'.$filter.'%')
                          ->orWhere('time_log', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value1', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value2', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value3', 'like', '%'.$filter.'%')
                          ->orWhere('custom_value4', 'like', '%'.$filter.'%')
                          ->orWhereHas('project', function ($q) use ($filter) {
                              $q->where('name', 'like', '%'.$filter.'%');
                          })
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
     * - invoiced
     * - uninvoiced
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

        if (in_array('invoiced', $status_parameters)) {
            $this->builder->whereNotNull('invoice_id');
        }

        if (in_array('uninvoiced', $status_parameters)) {
            $this->builder->whereNull('invoice_id');
        }

        if (in_array('is_running', $status_parameters)) {
            $this->builder->where('is_running', true);
        }

        return $this->builder;
    }

    public function project_tasks(string $project = ''): Builder
    {
        if (strlen($project) == 0) {
            return $this->builder;
        }

        return $this->builder->where('project_id', $this->decodePrimaryKey($project));
    }

    public function hash(string $hash = ''): Builder
    {
        if (strlen($hash) == 0) {
            return $this->builder;
        }

        return $this->builder->where('hash', $hash);

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
                    ->whereColumn('clients.id', 'tasks.client_id'), $dir);
        }

        if ($sort_col[0] == 'user_id') {
            return $this->builder->orderBy(\App\Models\User::select('first_name')
                    ->whereColumn('users.id', 'tasks.user_id'), $dir);
        }

        if ($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . $dir);
        }

        return $this->builder->orderBy($sort_col[0], $dir);
    }

    public function user_id(string $user = ''): Builder
    {
        if (strlen($user) == 0) {
            return $this->builder;
        }

        return $this->builder->where('user_id', $this->decodePrimaryKey($user));

    }

    public function assigned_user(string $user = ''): Builder
    {
        if (strlen($user) == 0) {
            return $this->builder;
        }

        return $this->builder->where('assigned_user_id', $this->decodePrimaryKey($user));

    }

    public function task_status(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        /** @var array $status_parameters */
        $status_parameters = explode(',', $value);

        if (count($status_parameters) >= 1) {

            $this->builder->where(function ($query) use ($status_parameters) {
                $query->whereIn('status_id', $this->transformKeys($status_parameters))->whereNull('invoice_id');
            });

        }

        return $this->builder;
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

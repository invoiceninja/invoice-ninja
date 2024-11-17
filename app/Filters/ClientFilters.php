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
 * ClientFilters.
 */
class ClientFilters extends QueryFilters
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

        return $this->builder->where('name', 'like', '%'.$name.'%');
    }

    /**
     * Filter by balance.
     *
     * @param string $balance
     * @return Builder
     */
    public function balance(string $balance = ''): Builder
    {
        if (strlen($balance) == 0 || count(explode(":", $balance)) < 2) {
            return $this->builder;
        }

        $parts = $this->split($balance);

        return $this->builder->where('balance', $parts->operator, $parts->value);
    }

    /**
     * Filter between balances.
     *
     * @param string $balance
     * @return Builder
     */
    public function between_balance(string $balance = ''): Builder
    {
        $parts = explode(':', $balance);

        if (!is_array($parts) || count($parts) != 2) {
            return $this->builder;
        }

        return $this->builder->whereBetween('balance', [$parts[0], $parts[1]]);
    }

    public function email(string $email = ''): Builder
    {
        if (strlen($email) == 0) {
            return $this->builder;
        }

        return $this->builder->whereHas('contacts', function ($query) use ($email) {
            $query->where('email', $email);
        });
    }

    public function client_id(string $client_id = ''): Builder
    {
        if (strlen($client_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('id', $this->decodePrimaryKey($client_id));
    }

    public function id_number(string $id_number = ''): Builder
    {
        if (strlen($id_number) == 0) {
            return $this->builder;
        }

        return $this->builder->where('id_number', $id_number);
    }

    public function number(string $number = ''): Builder
    {
        if (strlen($number) == 0) {
            return $this->builder;
        }

        return $this->builder->where('number', $number);
    }

    public function group(string $group_id = ''): Builder
    {
        if (strlen($group_id) == 0) {
            return $this->builder;
        }

        return $this->builder->where('group_settings_id', $this->decodePrimaryKey($group_id));

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

        $searchTerms = array_filter(explode(' ', $filter));

        return $this->builder->where(function ($query) use ($searchTerms) {
            foreach ($searchTerms as $term) {
                $query->where(function ($subQuery) use ($term) {
                    $subQuery->where('name', 'like', '%'.$term.'%')
                        ->orWhere('id_number', 'like', '%'.$term.'%')
                        ->orWhere('number', 'like', '%'.$term.'%')
                        ->orWhereHas('contacts', function ($contactQuery) use ($term) {
                            $contactQuery->where('first_name', 'like', '%'.$term.'%')
                                ->orWhere('last_name', 'like', '%'.$term.'%')
                                ->orWhere('email', 'like', '%'.$term.'%')
                                ->orWhere('phone', 'like', '%'.$term.'%');
                        })
                        ->orWhere('custom_value1', 'like', '%'.$term.'%')
                        ->orWhere('custom_value2', 'like', '%'.$term.'%')
                        ->orWhere('custom_value3', 'like', '%'.$term.'%')
                        ->orWhere('custom_value4', 'like', '%'.$term.'%');
                });
            }
        });


        // return  $this->builder->where(function ($query) use ($filter) {
        //     $query->where('name', 'like', '%'.$filter.'%')
        //                   ->orWhere('id_number', 'like', '%'.$filter.'%')
        //                   ->orWhere('number', 'like', '%'.$filter.'%')
        //                   ->orWhereHas('contacts', function ($query) use ($filter) {
        //                       $query->where('first_name', 'like', '%'.$filter.'%');
        //                       $query->orWhere('last_name', 'like', '%'.$filter.'%');
        //                       $query->orWhere('email', 'like', '%'.$filter.'%');
        //                       $query->orWhere('phone', 'like', '%'.$filter.'%');
        //                   })
        //                   ->orWhere('custom_value1', 'like', '%'.$filter.'%')
        //                   ->orWhere('custom_value2', 'like', '%'.$filter.'%')
        //                   ->orWhere('custom_value3', 'like', '%'.$filter.'%')
        //                   ->orWhere('custom_value4', 'like', '%'.$filter.'%');
        // });
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

        if($sort_col[0] == 'documents') {
            return $this->builder;
        }

        if ($sort_col[0] == 'display_name') {
            $sort_col[0] = 'name';
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        if($sort_col[0] == 'number') {
            return $this->builder->orderByRaw("REGEXP_REPLACE(number,'[^0-9]+','')+0 " . $dir);
        }

        return $this->builder->orderBy($sort_col[0], $dir);
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

    public function filter_details(string $filter = '')
    {
        if ($filter == 'true') {
            return $this->builder->select('id', 'name', 'number', 'id_number');
        }

        return $this->builder;
    }
}

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
 * DesignFilters.
 */
class DesignFilters extends QueryFilters
{
    /**
     * Filter based on search text.
     *
     * @param string $filter
     * @return Builder
     *
     */
    public function filter(string $filter = ''): Builder
    {
        if (strlen($filter) == 0) {
            return $this->builder;
        }

        return $this->builder->where(function ($query) use ($filter) {
            $query->where('name', 'like', '%'.$filter.'%');
        });
    }

    /**
     * Sorts the list based on $sort.
     *
     * @param string $sort formatted as column|asc
     *
     * @return Builder
     */
    public function sort(string $sort = ''): Builder
    {
        $sort_col = explode('|', $sort);

        if (!is_array($sort_col) || count($sort_col) != 2) {
            return $this->builder;
        }

        $dir = ($sort_col[1] == 'asc') ? 'asc' : 'desc';

        return $this->builder->orderBy($sort_col[0], $dir);
    }

    public function entities(string $entities = ''): Builder
    {

        if (stripos($entities, 'statement') !== false) {
            $entities = 'client';
        }

        if (strlen($entities) == 0 || str_contains($entities, ',')) {
            return $this->builder;
        }

        return $this->builder
                    ->where('is_template', true)
                    ->whereRaw('FIND_IN_SET( ? ,entities)', [trim($entities)]);

    }

    /**
     * Filters the query by the users company ID.
     *
     * @return Builder
     */
    public function entityFilter(): Builder
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return  $this->builder->where(function ($query) use ($user) {
            $query->where('company_id', $user->company()->id)->orWhere('company_id', null)->orderBy('id', 'asc');
        });
    }

    public function template(string $template = 'false'): Builder
    {

        if (strlen($template) == 0) {
            return $this->builder;
        }

        $bool_val = $template == 'true' ? true : false;

        return $this->builder->where('is_template', $bool_val);
    }

    /**
     * Filter the designs by `is_custom` column.
     *
     * @return Builder
     */
    public function custom(string $custom): Builder
    {
        if (strlen($custom) === 0) {
            return $this->builder;
        }

        return $this->builder->where('is_custom', filter_var($custom, FILTER_VALIDATE_BOOLEAN));
    }
}

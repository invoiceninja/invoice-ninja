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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Class QueryFilters.
 */
abstract class QueryFilters
{
    use MakesHash;

    /**
     * active status.
     */
    public const STATUS_ACTIVE = 'active';

    /**
     * archived status.
     */
    public const STATUS_ARCHIVED = 'archived';

    /**
     * deleted status.
     */
    public const STATUS_DELETED = 'deleted';

    /**
     * The request object.
     *
     * @var Request
     */
    protected $request;

    /**
     * The builder instance.
     *
     * @var Builder
     */
    protected $builder;

    /**
     * The "with" filter property column.
     *
     * var string
     */
    protected $with_property = 'id';

    /**
     * Create a new QueryFilters instance.
     *
     * @param Request $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Apply the filters to the builder.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $builder
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function apply(Builder $builder)
    {
        $this->builder = $builder;

        $this->entityFilter();

        $this->clientFilter();

        foreach ($this->filters() as $name => $value) {
            if (! method_exists($this, $name)) {
                continue;
            }

            if (is_string($value) && strlen($value)) {
                $this->$name($value);
            } else {
                $this->$name();
            }
        }

        // nlog('[Search] SQL: ' . $this->builder->toSql() . " Bindings: " . implode(', ', $this->builder->getBindings()));

        return $this->builder->withTrashed();
    }

    /**
     * Get all request filters data.
     *
     * @return array
     */
    public function filters()
    {
        return $this->request->all();
    }

    /**
     * Explodes the value by delimiter.
     *
     * @param  string $value
     * @return \stdClass
     */
    public function split($value): \stdClass
    {
        $exploded_array = explode(':', $value);

        $parts = new \stdClass();

        $parts->value = $exploded_array[0];
        $parts->operator = $this->operatorConvertor($exploded_array[1]);

        return $parts;
    }

    /**
     * Filters the list based on the status
     * archived, active, deleted.
     *
     * @param string $filter
     * @return Builder
     */
    public function status(string $filter = ''): Builder
    {

        if (strlen($filter) == 0) {
            return $this->builder;
        }

        $filters = explode(',', $filter);

        return $this->builder->where(function ($query) use ($filters) {
            if (in_array(self::STATUS_ACTIVE, $filters)) {
                $query = $query->orWhereNull('deleted_at');
            }

            if (in_array(self::STATUS_ARCHIVED, $filters)) {
                $query = $query->orWhere(function ($q) {
                    $q->whereNotNull('deleted_at')->where('is_deleted', 0);
                });
            }

            if (in_array(self::STATUS_DELETED, $filters)) {
                $query = $query->orWhere('is_deleted', 1);
            }
        });
    }

    /**
     * String to operator convertor.
     *
     * @param string $operator
     * @return string
     */
    private function operatorConvertor(string $operator): string
    {
        switch ($operator) {
            case 'lt':
                return '<';
            case 'gt':
                return '>';
            case 'lte':
                return '<=';
            case 'gte':
                return '>=';
            case 'eq':
                return '=';
            default:
                return '=';
        }
    }

    /**
     * Filters the query by the contact's client_id.
     *
     * -Can only be used on contact routes
     *
     * @return Builder
     */
    public function clientFilter(): Builder
    {
        if (auth()->guard('contact')->user()) {
            return $this->builder->where('client_id', auth()->guard('contact')->user()->client->id);
        }

        return $this->builder;
    }

    public function created_at($value = '')
    {
        if ($value == '') {
            return $this->builder;
        }

        try {
            if (is_numeric($value)) {
                $created_at = Carbon::createFromTimestamp((int)$value);
            } else {
                $created_at = Carbon::parse($value);
            }

            return $this->builder->where('created_at', '>=', $created_at);
        } catch (\Exception $e) {
            return $this->builder;
        }
    }

    public function updated_at($value = '')
    {
        if ($value == '') {
            return $this->builder;
        }

        try {
            if (is_numeric($value)) {
                $created_at = Carbon::createFromTimestamp((int)$value);
            } else {
                $created_at = Carbon::parse($value);
            }

            return $this->builder->where('updated_at', '>=', $created_at);
        } catch (\Exception $e) {
            return $this->builder;
        }
    }

    /**
     *
     * @param string $value
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function is_deleted($value = 'true')
    {
        if ($value == 'true') {
            return $this->builder->where('is_deleted', $value)->withTrashed();
        }

        return $this->builder->where('is_deleted', $value);
    }

    public function client_id(string $client_id = ''): Builder
    {
        if (strlen($client_id) == 0 || !in_array('client_id', \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable()))) {
            return $this->builder;
        }

        return $this->builder->where('client_id', $this->decodePrimaryKey($client_id));
    }

    public function vendor_id(string $vendor_id = ''): Builder
    {
        if (strlen($vendor_id) == 0 || !in_array('vendor_id', \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable()))) {
            return $this->builder;
        }

        return $this->builder->where('vendor_id', $this->decodePrimaryKey($vendor_id));
    }

    public function filter_deleted_clients($value)
    {
        if ($value == 'true') {
            return $this->builder->whereHas('client', function (Builder $query) {
                $query->where('is_deleted', 0);
            });
        }

        return $this->builder;
    }

    public function with_trashed($value)
    {
        if ($value == 'false') {
            return $this->builder->where('is_deleted', 0);
        }

        return $this->builder;
    }

    /**
     * @return Builder
     */
    public function without_deleted_clients(): Builder
    {
        return $this->builder->where(function ($query) {
            $query->whereHas('client', function ($sub_query) {
                $sub_query->where('is_deleted', 0)->where('deleted_at', null);
            })->orWhere('client_id', null);
        });
    }

    /**
     * @return Builder
     */
    public function without_deleted_vendors(): Builder
    {
        return $this->builder->where(function ($query) {
            $query->whereHas('vendor', function ($sub_query) {
                $sub_query->where('is_deleted', 0)->where('deleted_at', null);
            })->orWhere('vendor_id', null);
        });
    }


    public function with(string $value = ''): Builder
    {
        if (strlen($value) == 0) {
            return $this->builder;
        }

        if ($this->with_property == 'id') {
            $value = $this->decodePrimaryKey($value);
        }

        return $this->builder
            ->orWhere($this->with_property, $value)
            ->orderByRaw("{$this->with_property} = ? DESC", [$value])
            ->company();
    }


    /**
     * Filter by date range
     *
     * @param string $date_range
     * @return Builder
     */
    public function date_range(string $date_range = ''): Builder
    {
        $parts = explode(",", $date_range);

        if (count($parts) != 2 || !in_array('date', \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable()))) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($parts[0]);
            $end_date = Carbon::parse($parts[1]);

            return $this->builder->whereBetween('date', [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

    }

    /**
     * Filter by due date range
     *
     * @param string $date_range
     * @return Builder
     */
    public function due_date_range(string $date_range = ''): Builder
    {

        $parts = explode(",", $date_range);

        if (count($parts) != 2 || !in_array('due_date', \Illuminate\Support\Facades\Schema::getColumnListing($this->builder->getModel()->getTable()))) {
            return $this->builder;
        }

        try {

            $start_date = Carbon::parse($parts[0]);
            $end_date = Carbon::parse($parts[1]);

            return $this->builder->whereBetween('due_date', [$start_date, $end_date]);
        } catch (\Exception $e) {
            return $this->builder;
        }

    }



}

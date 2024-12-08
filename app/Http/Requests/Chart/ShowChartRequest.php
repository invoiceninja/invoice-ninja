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

namespace App\Http\Requests\Chart;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesDates;

class ShowChartRequest extends Request
{
    use MakesDates;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /**@var \App\Models\User auth()->user */
        $user = auth()->user();

        return $user->isAdmin() || $user->hasPermission('view_dashboard');
    }

    public function rules()
    {
        return [
            'date_range' => 'bail|sometimes|string|in:last7_days,last30_days,last365_days,this_month,last_month,this_quarter,last_quarter,this_year,last_year,all_time,custom',
            'start_date' => 'bail|sometimes|date',
            'end_date' => 'bail|sometimes|date',
        ];
    }

    public function prepareForValidation()
    {

        /**@var \App\Models\User auth()->user */
        $user = auth()->user();

        $input = $this->all();

        if (isset($input['date_range'])) {
            $dates = $this->calculateStartAndEndDates($input, $user->company());
            $input['start_date'] = $dates[0];
            $input['end_date'] = $dates[1];
        }

        if (! isset($input['start_date'])) {
            $input['start_date'] = now()->subDays(20)->format('Y-m-d');
        }

        if (! isset($input['end_date'])) {
            // $input['end_date'] = now()->lastOfMonth()->format('Y-m-d');
            $input['end_date'] = now()->format('Y-m-d');
        }

        $this->replace($input);
    }
}

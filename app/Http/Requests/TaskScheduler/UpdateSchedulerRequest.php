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

namespace App\Http\Requests\TaskScheduler;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Scheduler\ValidClientIds;

class UpdateSchedulerRequest extends Request
{
    public array $client_statuses = [
                        'all',
                        'draft',
                        'paid',
                        'unpaid',
                        'overdue',
                        'pending',
                        'invoiced',
                        'logged',
                        'partial',
                        'applied',
                        'active',
                        'paused',
                        'completed',
                        'approved',
                        'expired',
                        'upcoming',
                        'converted',
                        'uninvoiced',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin() && $this->task_scheduler->company_id == $user->company()->id;
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'bail|sometimes|nullable|string',
            'is_paused' => 'bail|sometimes|boolean',
            'frequency_id' => 'bail|sometimes|integer|digits_between:1,12',
            'next_run' => 'bail|required|date:Y-m-d|after_or_equal:today',
            'next_run_client' => 'bail|sometimes|date:Y-m-d',
            'template' => 'bail|required|string',
            'parameters' => 'bail|array',
            'parameters.clients' => ['bail','sometimes', 'array', new ValidClientIds()],
            'parameters.date_range' => 'bail|sometimes|string|in:last7_days,last30_days,last365_days,this_month,last_month,this_quarter,last_quarter,this_year,last_year,all_time,custom,all',
            'parameters.start_date' => ['bail', 'sometimes', 'date:Y-m-d', 'required_if:parameters.date_range,custom'],
            'parameters.end_date' => ['bail', 'sometimes', 'date:Y-m-d', 'required_if:parameters.date_range,custom', 'after_or_equal:parameters.start_date'],
            'parameters.entity' => ['bail', 'sometimes', 'string', 'in:invoice,credit,quote,purchase_order'],
            'parameters.entity_id' => ['bail', 'sometimes', 'string'],
            'parameters.report_name' => ['bail','sometimes', 'string', 'required_if:template,email_report','in:vendor,purchase_order_item,purchase_order,ar_detailed,ar_summary,client_balance,tax_summary,profitloss,client_sales,user_sales,product_sales,activity,activities,client,clients,client_contact,client_contacts,credit,credits,document,documents,expense,expenses,invoice,invoices,invoice_item,invoice_items,quote,quotes,quote_item,quote_items,recurring_invoice,recurring_invoices,payment,payments,product,products,task,tasks'],
            'parameters.date_key' => ['bail','sometimes', 'string'],
            'parameters.status' => ['bail','sometimes', 'nullable', 'string'],
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('next_run', $input) && is_string($input['next_run'])) {
            $input['next_run_client'] = $input['next_run'];
        }

        if ($input['template'] == 'email_record') {
            $input['frequency_id'] = 0;
        }

        if (isset($input['parameters']) && !isset($input['parameters']['clients'])) {
            $input['parameters']['clients'] = [];
        }

        if (isset($input['parameters']['status'])) {


            $task_statuses = [];

            if (isset($input['parameters']['report_name']) && $input['parameters']['report_name'] == 'task') {
                $task_statuses = array_diff(explode(",", $input['parameters']['status']), $this->client_statuses);
            }

            $input['parameters']['status'] = collect(explode(",", $input['parameters']['status']))
                                                    ->filter(function ($status) {
                                                        return in_array($status, $this->client_statuses);
                                                    })->merge($task_statuses)
                                                    ->implode(",") ?? '';
        }

        $this->replace($input);



    }
}

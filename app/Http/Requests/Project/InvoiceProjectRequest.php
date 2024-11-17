<?php
/**
 * Project Ninja (https://paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Project Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Project;

use App\Models\Invoice;
use App\Http\Requests\Request;

class InvoiceProjectRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->project);
    }

    public function rules()
    {
       return [];
       
       //if we need to restrict a project to only one invoice...

        // $user = auth()->user();
        // $company = $user->company();

        // return [
        //     'project_id' => [
        //     'required',
        //     function($attribute, $value, $fail) use($company){
        //         if (Invoice::withTrashed()->where('company_id', $company->id)->where('is_deleted', 0)->where('project_id', $value)->exists()) {
        //             $fail('This project has already been invoiced.');
        //         }
        //     }
        // ]
        // ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

            // $input['project_id'] = $this->project->id;

        $this->replace($input);

    }
}

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

namespace App\Http\Requests\Project;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class BulkProjectRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {

        return [
            'action' => 'required|string',
            // 'ids' => 'required|array',
            'ids' => ['required', 'array', function($attribute, $value, $fail) {
                $projects = \App\Models\Project::withTrashed()->whereIn('id', $this->transformKeys($value))->company()->get();

                if($projects->isEmpty()) {
                    return;
                }

                $clientId = $projects->first()->client_id;
                
                if($this->action == 'invoice' && $projects->contains('client_id', '!=', $clientId)) {
                    $fail('All selected projects must belong to the same client.');
                }

        }],
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool'
        ];

    }
}

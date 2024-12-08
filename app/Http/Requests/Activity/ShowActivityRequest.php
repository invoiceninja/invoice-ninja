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

namespace App\Http\Requests\Activity;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class ShowActivityRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'entity' => 'bail|required|in:invoice,quote,credit,purchase_order,payment,client,vendor,expense,task,project,subscription,recurring_invoice,',
            'entity_id' => 'bail|required|exists:'.$this->entity.'s,id,company_id,'.auth()->user()->company()->id,
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['entity_id'])) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        $this->replace($input);

    }
}

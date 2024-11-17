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

namespace App\Http\Requests\Design;

use App\Http\Requests\Request;

class DefaultDesignRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $user = auth()->user();

        return [
            'entity' => 'bail|required',
            'design_id' => 'bail|required',
            'settings_level' => 'bail|sometimes|in:company,client,group_settings',
            'client_id' => 'bail|required_if:settings_level,client|exists:clients,id,company_id,'.$user->company()->id,
            'group_settings_id' => 'bail|required_if:settings_level,group_settings|exists:group_settings,id,company_id,'.$user->company()->id,
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}

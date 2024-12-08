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

namespace App\Http\Requests\Import;

use App\Http\Requests\Request;

class ImportRequest extends Request
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

        return $user->isAdmin();
    }

    public function rules()
    {
        return [
            'import_type' => 'required',
            'files' => 'required_without:hash|array|min:1|max:6',
            'hash' => 'nullable|string',
            'column_map' => 'required_with:hash|array',
            'skip_header' => 'required_with:hash|boolean',
            'files.*' => 'file|mimes:csv,txt',
            'bank_integration_id' => 'bail|required_with:column_map.bank_transaction|min:2'
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (!isset($input['column_map']['bank_transaction']) && array_key_exists('bank_integration_id', $input)) {
            unset($input['bank_integration_id']);
        }

        $this->replace($input);
    }
}

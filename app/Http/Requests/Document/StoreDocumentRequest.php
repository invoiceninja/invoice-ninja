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

namespace App\Http\Requests\Document;

use App\Http\Requests\Request;
use App\Models\Document;

class StoreDocumentRequest extends Request
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

        return $user->can('create', Document::class) || ($user->hasIntersectPermissions(['edit_all', 'create_all']));
    }

    public function rules()
    {
        return [
            'is_public' => 'sometimes|boolean',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['is_public'])) {
            $input['is_public'] = $this->toBoolean($input['is_public']);
        }

        $this->replace($input);
    }

}

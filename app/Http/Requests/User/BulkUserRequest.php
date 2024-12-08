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

namespace App\Http\Requests\User;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Ninja\CanRestoreUserRule;
use App\Utils\Ninja;
use Illuminate\Auth\Access\AuthorizationException;

class BulkUserRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if ($this->action == 'delete' && in_array(auth()->user()->hashed_id, $this->ids)) {
            return false;
        }

        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        $rules = [];

        if (Ninja::isHosted() && $this->action && $this->action == 'restore') {
            $rules['ids'] = new CanRestoreUserRule();
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException("This Action is unauthorized.");
    }
}

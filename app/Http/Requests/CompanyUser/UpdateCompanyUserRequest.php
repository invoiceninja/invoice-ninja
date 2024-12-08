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

namespace App\Http\Requests\CompanyUser;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class UpdateCompanyUserRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $auth_user */
        $auth_user = auth()->user();

        return $auth_user->isAdmin() || ($auth_user->id == $this->user->id);
    }

    public function rules()
    {
        return [];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['company_user']['user'])) {
            unset($input['company_user']['user']);
        }

        $this->replace($input);
    }
}

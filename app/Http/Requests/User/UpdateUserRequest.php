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
use App\Http\ValidationRules\UniqueUserRule;
use App\Http\ValidationRules\User\HasValidPhoneNumber;
use App\Utils\Ninja;

class UpdateUserRequest extends Request
{
    private bool $phone_has_changed = false;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->id == $this->user->id || auth()->user()->isAdmin();
    }

    public function rules()
    {
        $input = $this->all();

        $rules = [
            'password' => 'nullable|string|min:6',
        ];

        $rules['email'] = ['email', 'sometimes', new UniqueUserRule($this->user, $input['email'])];

        if (Ninja::isHosted() && $this->phone_has_changed && $this->phone && isset($this->phone)) {
            $rules['phone'] = ['sometimes', 'bail', 'string', new HasValidPhoneNumber()];
        }

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['email']) && is_string($input['email']) && strlen($input['email']) > 2) {
            $input['email'] = trim($input['email']);
        } elseif (isset($input['email'])) {
            $input['email'] = false;
        }

        if (array_key_exists('first_name', $input)) {
            $input['first_name'] = strip_tags($input['first_name']);
        }

        if (array_key_exists('last_name', $input)) {
            $input['last_name'] = strip_tags($input['last_name']);
        }

        if (array_key_exists('phone', $input) && isset($input['phone']) && strlen($input['phone']) > 1 && ($this->user->phone != $input['phone'])) {
            $this->phone_has_changed = true;
        }

        if (array_key_exists('oauth_provider_id', $input) && $input['oauth_provider_id'] == '') {
            $input['oauth_user_id'] = '';
        }

        if (array_key_exists('oauth_user_token', $input) && $input['oauth_user_token'] == '***') {
            unset($input['oauth_user_token']);
        }

        if (isset($input['password']) && is_string($input['password'])) {
            $input['password'] = trim($input['password']);
        }

        $this->replace($input);
    }
}

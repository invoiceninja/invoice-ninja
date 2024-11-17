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

namespace App\Http\Requests\Smtp;

use App\Http\Requests\Request;

class CheckSmtpRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'smtp_host' => 'required|string|min:3',
            'smtp_port' => 'required|integer',
            'smtp_username' => 'required|min:3',
            'smtp_password' => 'required|min:3',
        ];
    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();

        $input = $this->input();

        if(isset($input['smtp_username']) && $input['smtp_username'] == '********') {
            
            $input['smtp_username'] = $company->smtp_username;
        }

        if(isset($input['smtp_password']) && $input['smtp_password'] == '********') {   
            $input['smtp_password'] = $company->smtp_password;
        }

        if(isset($input['smtp_host']) && strlen($input['smtp_host']) >= 3) {

        } else {
            $input['smtp_host'] = $company->smtp_host;
        }

        if (!isset($input['smtp_port']) || !is_numeric($input['smtp_port'])) {
            $input['smtp_port'] = $company->smtp_port ?? 0;
        }

        $this->replace($input);
    }
}

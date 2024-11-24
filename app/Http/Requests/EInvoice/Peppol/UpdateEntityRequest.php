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

namespace App\Http\Requests\EInvoice\Peppol;

use App\Models\Country;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateEntityRequest extends FormRequest
{
    public function authorize(): bool
    {
        /**
         * @var \App\Models\User
         */
        $user = auth()->user();

        if (config('ninja.app_env') == 'local') {
            return true;
        }

        return $user->account->isPaid() && $user->isAdmin() &&
            $user->company()->legal_entity_id != null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'acts_as_receiver' => ['required', 'bool'],
            'acts_as_sender' => ['required', 'bool'],
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }

    // public function after(): array 
    // {
    //     return [
    //         function (Validator $validator) {
    //             if ($this->input('acts_as_sender') === false && $this->input('acts_as_receiver') === false) {
    //                 $validator->errors()->add('acts_as_receiver', ctrans('texts.acts_as_must_be_true'));
    //             }
    //         }
    //     ];
    // }
}

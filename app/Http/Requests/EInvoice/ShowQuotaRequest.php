<?php

namespace App\Http\Requests\EInvoice;

use Illuminate\Foundation\Http\FormRequest;

class ShowQuotaRequest extends FormRequest
{
    public function authorize(): bool
    {
        if (config('ninja.app_env') == 'local') {
            return true;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return \App\Utils\Ninja::isSelfHost() && $user->account->isPaid();
    }

    public function rules(): array
    {
        return [
            //
        ];
    }
}

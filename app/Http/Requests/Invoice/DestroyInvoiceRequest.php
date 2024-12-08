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

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;

class DestroyInvoiceRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('edit', $this->invoice);
    }


    public function rules()
    {
        return [];
    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (\Illuminate\Support\Facades\Cache::has($this->ip()."|".$this->invoice->id."|".$user->company()->company_key)) {
            throw new \App\Exceptions\DuplicatePaymentException('Duplicate request.', 429);
        }

        \Illuminate\Support\Facades\Cache::put(($this->ip()."|".$this->invoice->id."|".$user->company()->company_key), true, 1);

    }
}

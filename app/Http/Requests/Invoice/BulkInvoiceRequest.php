<?php
/**
 * Invoice Ninja (https://invoiceninja.com)
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Invoice;

use App\Http\Requests\Request;
use App\Exceptions\DuplicatePaymentException;

class BulkInvoiceRequest extends Request
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        return [
            'action' => 'required|string',
            'ids' => 'required|array',
            'email_type' => 'sometimes|in:reminder1,reminder2,reminder3,reminder_endless,custom1,custom2,custom3,invoice,quote,credit,payment,payment_partial,statement,purchase_order',
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool',
            'subscription_id' => 'sometimes|string',
        ];
    }

    public function prepareForValidation()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (\Illuminate\Support\Facades\Cache::has($this->ip()."|".$this->input('action', 0)."|".$user->company()->company_key)) {
            throw new DuplicatePaymentException('Action still processing, please wait. ', 429);
        }

        $delay = $this->input('action', 'delete') == 'delete' ? (ceil(count($this->input('ids', 2)))) : 1;
        \Illuminate\Support\Facades\Cache::put(($this->ip()."|".$this->input('action', 0)."|".$user->company()->company_key), true, $delay);

    }

}

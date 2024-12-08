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

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Http\Requests\Request;

class RetrySendRequest extends Request
{
    private string $entity_plural = 'invoices';

    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (config('ninja.app_env') == 'local') {
            return true;
        }

        return $user->account->isPaid() && $user->isAdmin() && $user->company()->legal_entity_id != null;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var \App\Models\User $user **/
        $user = auth()->user();

        return [
            'entity' => ['bail','required','in:App\Models\Invoice,App\Models\Quote,App\Models\Credit,App\Models\PurchaseOrder'],
            'entity_id' => ['bail', 'required', Rule::exists($this->entity_plural, 'id')->where('company_id', $user->company()->id)],
        ];
    }

    public function prepareForValidation()
    {
        
        $input = $this->all();

        
        if (array_key_exists('entity_id', $input)) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        if (isset($input['entity']) && in_array($input['entity'], ['invoice','quote','credit','purchase_order'])) {
            $this->entity_plural = Str::plural($input['entity']);
            $input['entity'] = "App\Models\\".ucfirst(Str::camel($input['entity']));
        }

        $this->replace($input);

    }
}

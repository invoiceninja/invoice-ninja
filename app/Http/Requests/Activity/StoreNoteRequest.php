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

namespace App\Http\Requests\Activity;

use Illuminate\Support\Str;
use App\Http\Requests\Request;
use Illuminate\Validation\Rule;

class StoreNoteRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->checkAuthority();
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'entity' => 'required|bail|in:invoices,quotes,credits,recurring_invoices,clients,vendors,credits,payments,projects,tasks,expenses,recurring_expenses,bank_transactions,purchase_orders',
            'entity_id' => ['required','bail', Rule::exists($this->entity, 'id')->where('company_id', $user->company()->id)],
            'notes' => 'required|bail',
        ];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['entity_id']) && $input['entity_id'] != null) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        $this->replace($input);
    }

    public function checkAuthority(): bool
    {

        $this->error_message = ctrans('texts.authorization_failure');

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $entity = $this->getEntity();

        return $user->isAdmin() || $user->can('view', $entity);

    }

    public function getEntity()
    {
        if (!$this->entity) {
            return false;
        }

        $class = "\\App\\Models\\".ucfirst(Str::camel(rtrim($this->entity, 's')));
        return $class::withTrashed()->find(is_string($this->entity_id) ? $this->decodePrimaryKey($this->entity_id) : $this->entity_id);

    }

}

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

namespace App\Http\Requests\Subscription;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class BulkSubscriptionRequest extends Request
{
    use MakesHash;

    private $entity_table = 'invoices';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'action' => 'required|string|in:archive,restore,delete,assign_invoice',
            'ids' => ['required','bail','array',Rule::exists('subscriptions', 'id')->where('company_id', $user->company()->id)],
            'entity' => 'sometimes|bail|string|in:invoice,recurring_invoice',
            'entity_id' => ['sometimes','bail', Rule::exists($this->entity_table, 'id')->where('company_id', $user->company()->id)],
        ];

    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['ids'])) {
            $input['ids'] = $this->transformKeys($input['ids']);
        }

        if (isset($input['entity']) && $input['entity'] == 'recurring_invoice') {
            $this->entity_table = 'recurring_invoices';
        }

        if (isset($input['entity_id']) && $input['entity_id'] != null) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        $this->replace($input);
    }
}

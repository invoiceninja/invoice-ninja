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

namespace App\Http\Requests\Quote;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateQuoteRequest extends Request
{
    use MakesHash;
    use CleanLineItems;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->quote);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $rules = [];

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        $rules['invitations'] = 'sometimes|bail|array';
        $rules['invitations.*.client_contact_id'] = 'bail|required|distinct';

        $rules['number'] = ['bail', 'sometimes', 'nullable', Rule::unique('quotes')->where('company_id', $user->company()->id)->ignore($this->quote->id)];
        $rules['client_id'] = ['bail', 'sometimes', Rule::in([$this->quote->client_id])];
        $rules['line_items'] = 'array';
        $rules['discount'] = 'sometimes|numeric|max:99999999999999';
        $rules['is_amount_discount'] = ['boolean'];
        $rules['exchange_rate'] = 'bail|sometimes|numeric';

        $rules['date'] = 'bail|sometimes|date:Y-m-d';

        $rules['partial_due_date'] = ['bail', 'sometimes', 'nullable', 'exclude_if:partial,0', 'date', 'before:due_date', 'after_or_equal:date'];
        $rules['due_date'] = ['bail', 'sometimes', 'nullable', 'after:partial_due_date', 'after_or_equal:date', Rule::requiredIf(fn () => strlen($this->partial_due_date) > 1), 'date'];
        $rules['amount'] = ['sometimes', 'bail', 'numeric', 'max:99999999999999'];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['id'] = $this->quote->id;

        if (isset($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
            $input['amount'] = $this->entityTotalAmount($input['line_items']);
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        if (array_key_exists('exchange_rate', $input) && is_null($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        if(isset($input['partial']) && $input['partial'] == 0) {
            $input['partial_due_date'] = null;
        }
        
        if (isset($input['footer']) && $this->hasHeader('X-REACT')) {
            $input['footer'] = str_replace("\n", "", $input['footer']);
        }
        if (isset($input['public_notes']) && $this->hasHeader('X-REACT')) {
            $input['public_notes'] = str_replace("\n", "", $input['public_notes']);
        }
        if (isset($input['private_notes']) && $this->hasHeader('X-REACT')) {
            $input['private_notes'] = str_replace("\n", "", $input['private_notes']);
        }
        if (isset($input['terms']) && $this->hasHeader('X-REACT')) {
            $input['terms'] = str_replace("\n", "", $input['terms']);
        }


        $this->replace($input);
    }
}

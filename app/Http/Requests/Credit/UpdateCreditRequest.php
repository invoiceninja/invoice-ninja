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

namespace App\Http\Requests\Credit;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateCreditRequest extends Request
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

        return $user->can('edit', $this->credit);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
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

        $rules['number'] = ['bail', 'sometimes', 'nullable', Rule::unique('credits')->where('company_id', $user->company()->id)->ignore($this->credit->id)];

        $rules['client_id'] = ['bail', 'sometimes',Rule::in([$this->credit->client_id])];

        $rules['invitations'] = 'sometimes|bail|array';
        $rules['invitations.*.client_contact_id'] = 'bail|required|distinct';

        $rules['line_items'] = 'array';

        $rules['date'] = 'bail|sometimes|date:Y-m-d';

        $rules['discount'] = 'sometimes|numeric|max:99999999999999';
        $rules['is_amount_discount'] = ['boolean'];
        $rules['tax_rate1'] = 'bail|sometimes|numeric';
        $rules['tax_rate2'] = 'bail|sometimes|numeric';
        $rules['tax_rate3'] = 'bail|sometimes|numeric';
        $rules['tax_name1'] = 'bail|sometimes|string|nullable';
        $rules['tax_name2'] = 'bail|sometimes|string|nullable';
        $rules['tax_name3'] = 'bail|sometimes|string|nullable';
        $rules['exchange_rate'] = 'bail|sometimes|numeric';
        $rules['amount'] = ['sometimes', 'bail', 'numeric', 'max:99999999999999'];

        $rules['custom_surcharge1'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];
        $rules['custom_surcharge2'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];
        $rules['custom_surcharge3'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];
        $rules['custom_surcharge4'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['partial']) && $input['partial'] == 0) {
            $input['partial_due_date'] = null;
        }

        if (isset($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
            $input['amount'] = $this->entityTotalAmount($input['line_items']);

        }

        if (array_key_exists('exchange_rate', $input) && is_null($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        $input['id'] = $this->credit->id;

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

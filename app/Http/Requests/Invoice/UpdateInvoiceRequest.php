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
use App\Http\ValidationRules\Invoice\LockedInvoiceRule;
use App\Http\ValidationRules\Project\ValidProjectForClient;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateInvoiceRequest extends Request
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

        return $user->can('edit', $this->invoice);
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

        // $rules['id'] = new LockedInvoiceRule($this->invoice);

        $rules['number'] = ['bail', 'sometimes', 'nullable', Rule::unique('invoices')->where('company_id', $user->company()->id)->ignore($this->invoice->id)];

        $rules['is_amount_discount'] = ['boolean'];
        $rules['client_id'] = ['bail', 'sometimes', Rule::in([$this->invoice->client_id])];
        $rules['line_items'] = 'array';

        $rules['invitations'] = 'sometimes|bail|array';
        $rules['invitations.*.client_contact_id'] = 'bail|required|distinct';

        $rules['discount'] = 'sometimes|numeric|max:99999999999999';
        $rules['project_id'] = ['bail', 'sometimes', new ValidProjectForClient($this->all())];
        $rules['tax_rate1'] = 'bail|sometimes|numeric';
        $rules['tax_rate2'] = 'bail|sometimes|numeric';
        $rules['tax_rate3'] = 'bail|sometimes|numeric';
        $rules['tax_name1'] = 'bail|sometimes|string|nullable';
        $rules['tax_name2'] = 'bail|sometimes|string|nullable';
        $rules['tax_name3'] = 'bail|sometimes|string|nullable';
        $rules['status_id'] = 'bail|sometimes|not_in:5'; //do not allow cancelled invoices to be modfified.
        $rules['exchange_rate'] = 'bail|sometimes|numeric';
        $rules['partial'] = 'bail|sometimes|nullable|numeric';
        $rules['amount'] = ['sometimes', 'bail', 'numeric', 'max:99999999999999'];

        $rules['custom_surcharge1'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];
        $rules['custom_surcharge2'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];
        $rules['custom_surcharge3'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];
        $rules['custom_surcharge4'] = ['sometimes', 'nullable', 'bail', 'numeric', 'max:99999999999999'];


        $rules['date'] = 'bail|sometimes|date:Y-m-d';

        $rules['partial_due_date'] = ['bail', 'sometimes', 'nullable', 'exclude_if:partial,0', 'date', 'before:due_date', 'after_or_equal:date'];
        $rules['due_date'] = ['bail', 'sometimes', 'nullable', 'after:partial_due_date', 'after_or_equal:date', Rule::requiredIf(fn () => strlen($this->partial_due_date) > 1), 'date'];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['id'] = $this->invoice->id;

        if (isset($input['partial']) && $input['partial'] == 0) {
            $input['partial_due_date'] = null;
        }

        if (isset($input['line_items']) && is_array($input['line_items'])) {
            $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
            $input['amount'] = $this->entityTotalAmount($input['line_items']);
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        if (array_key_exists('exchange_rate', $input) && is_null($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        //handles edge case where we need for force set the due date of the invoice.
        if ((isset($input['partial_due_date']) && strlen($input['partial_due_date']) > 1) && (!array_key_exists('due_date', $input) || (empty($input['due_date']) && empty($this->invoice->due_date)))) {
            $client = \App\Models\Client::withTrashed()->find($input['client_id']);
            $input['due_date'] = \Illuminate\Support\Carbon::parse($input['date'])->addDays((int)$client->getSetting('payment_terms'))->format('Y-m-d');
        }

        if (isset($input['e_invoice']) && is_array($input['e_invoice'])) {
            //ensure it is normalized first!
            $input['e_invoice'] = $this->invoice->filterNullsRecursive($input['e_invoice']);
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

    public function messages()
    {
        return [
            'id' => ctrans('texts.locked_invoice'),
            'status_id' => ctrans('texts.locked_invoice'),
        ];
    }
}

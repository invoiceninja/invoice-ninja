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

namespace App\Http\Requests\Payment;

use App\Http\Requests\Request;
use App\Http\ValidationRules\PaymentAppliedValidAmount;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdatePaymentRequest extends Request
{
    use ChecksEntityStatus;
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->payment);
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'client_id' => ['sometimes', 'bail', Rule::in([$this->payment->client_id])],
            'number' => ['sometimes', 'bail', Rule::unique('payments')->where('company_id', $user->company()->id)->ignore($this->payment->id)],
            'invoices' => ['sometimes', 'bail', 'nullable', 'array', new PaymentAppliedValidAmount($this->all())],
            'invoices.*.invoice_id' => ['sometimes','distinct',Rule::exists('invoices', 'id')->where('company_id', $user->company()->id)->where('client_id', $this->payment->client_id)],
            'invoices.*.amount' => ['sometimes','numeric','min:0'],
            'credits.*.credit_id' => ['sometimes','bail','distinct',Rule::exists('credits', 'id')->where('company_id', $user->company()->id)->where('client_id', $this->payment->client_id)],
            'credits.*.amount' => ['required', 'bail'],
        ];

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

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        if (isset($input['amount'])) {
            unset($input['amount']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                if (isset($input['invoices'][$key]['invoice_id'])) {
                    $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
                }
            }
        }

        if (isset($input['credits']) && is_array($input['credits']) !== false) {
            foreach ($input['credits'] as $key => $value) {
                if (isset($input['credits'][$key]['credit_id'])) {
                    $input['credits'][$key]['credit_id'] = $this->decodePrimaryKey($value['credit_id']);
                }
            }
        }

        $this->replace($input);
    }

    public function messages()
    {
        return [
            'distinct' => 'Attemping duplicate payment on the same Invoice',
        ];
    }
}

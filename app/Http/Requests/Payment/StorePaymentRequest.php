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

use App\Exceptions\DuplicatePaymentException;
use App\Http\Requests\Request;
use App\Http\ValidationRules\Credit\CreditsSumRule;
use App\Http\ValidationRules\Credit\ValidCreditsRules;
use App\Http\ValidationRules\Payment\ValidInvoicesRules;
use App\Http\ValidationRules\PaymentAmountsBalanceRule;
use App\Http\ValidationRules\ValidPayableInvoicesRule;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends Request
{
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

        return $user->can('create', Payment::class);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [
            'client_id' => ['bail','required',Rule::exists('clients', 'id')->where('company_id', $user->company()->id)->where('is_deleted', 0)],
            'invoices' => ['bail', 'sometimes', 'nullable', 'array', new ValidPayableInvoicesRule()],
            'invoices.*.amount' => ['bail','required'],
            'invoices.*.invoice_id' => ['bail','required','distinct', new ValidInvoicesRules($this->all()),Rule::exists('invoices', 'id')->where('company_id', $user->company()->id)->where('client_id', $this->client_id)],
            'credits.*.credit_id' => ['bail','required','distinct', new ValidCreditsRules($this->all()),Rule::exists('credits', 'id')->where('company_id', $user->company()->id)->where('client_id', $this->client_id)],
            'credits.*.amount' => ['bail','required', new CreditsSumRule($this->all())],
            'amount' => ['bail', 'numeric', new PaymentAmountsBalanceRule(), 'max:99999999999999'],
            'number' => ['bail', 'nullable',  Rule::unique('payments')->where('company_id', $user->company()->id)],
            'idempotency_key' => ['nullable', 'bail', 'string','max:64', Rule::unique('payments')->where('company_id', $user->company()->id)],
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

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $client_id = is_string($this->input('client_id', '')) ? $this->input('client_id') : '';

        if (\Illuminate\Support\Facades\Cache::has($this->ip()."|".$this->input('amount', 0)."|".$client_id."|".$user->company()->company_key)) {
            throw new DuplicatePaymentException('Duplicate request.', 429);
        }

        \Illuminate\Support\Facades\Cache::put(($this->ip()."|".$this->input('amount', 0)."|".$client_id."|".$user->company()->company_key), true, 1);

        $input = $this->all();

        $invoices_total = 0;
        $credits_total = 0;

        if (isset($input['client_id']) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id'], true);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (isset($input['invoices']) && is_array($input['invoices']) !== false) {
            foreach ($input['invoices'] as $key => $value) {
                if (isset($value['invoice_id']) && is_string($value['invoice_id'])) {
                    $input['invoices'][$key]['invoice_id'] = $this->decodePrimaryKey($value['invoice_id']);
                }

                if (array_key_exists('amount', $value) && is_numeric($value['amount'])) {
                    $invoices_total += $value['amount'];
                }
            }
        }

        if (isset($input['invoices']) && is_array($input['invoices']) === false) {
            $input['invoices'] = null;
        }

        if (isset($input['credits']) && is_array($input['credits']) !== false) {
            foreach ($input['credits'] as $key => $value) {
                if (isset($value['credit_id']) && is_string($value['credit_id'])) {
                    $input['credits'][$key]['credit_id'] = $this->decodePrimaryKey($value['credit_id']);

                    if (array_key_exists('amount', $value) && is_numeric($value['amount'])) {
                        $credits_total += $value['amount'];
                    }
                }
            }
        }

        if (isset($input['credits']) && is_array($input['credits']) === false) {
            $input['credits'] = null;
        }

        if (! isset($input['amount']) || $input['amount'] == 0) {
            $input['amount'] = $invoices_total - $credits_total;
        }

        if (! isset($input['date'])) {
            $input['date'] = now()->addSeconds($user->company()->utc_offset())->format('Y-m-d');
        }

        if (! isset($input['idempotency_key'])) {
            $input['idempotency_key'] = substr(time()."{$input['date']}{$input['amount']}{$credits_total}{$this->client_id}{$user->company()->company_key}", 0, 64);
        }

        if (array_key_exists('exchange_rate', $input) && $input['exchange_rate'] === null) {
            unset($input['exchange_rate']);
        }

        $this->replace($input);
    }


}

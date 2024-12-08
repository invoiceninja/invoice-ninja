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

namespace App\Http\Requests\RecurringInvoice;

use App\Http\Requests\Request;
use App\Http\ValidationRules\Project\ValidProjectForClient;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateRecurringInvoiceRequest extends Request
{
    use ChecksEntityStatus;
    use CleanLineItems;
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();

        return $user->can('edit', $this->recurring_invoice);
    }

    public function rules()
    {
        /** @var \App\Models\User auth()->user() */
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

        $rules['number'] = ['bail', 'sometimes', Rule::unique('recurring_invoices')->where('company_id', $user->company()->id)->ignore($this->recurring_invoice->id)];

        $rules['invitations'] = 'sometimes|bail|array';
        $rules['invitations.*.client_contact_id'] = 'bail|required|distinct';

        $rules['client_id'] = ['bail', 'sometimes', Rule::in([$this->recurring_invoice->client_id])];

        $rules['project_id'] = ['bail', 'sometimes', new ValidProjectForClient($this->all())];
        $rules['tax_rate1'] = 'bail|sometimes|numeric';
        $rules['tax_rate2'] = 'bail|sometimes|numeric';
        $rules['tax_rate3'] = 'bail|sometimes|numeric';
        $rules['tax_name1'] = 'bail|sometimes|string|nullable';
        $rules['tax_name2'] = 'bail|sometimes|string|nullable';
        $rules['tax_name3'] = 'bail|sometimes|string|nullable';
        $rules['exchange_rate'] = 'bail|sometimes|numeric';
        $rules['next_send_date'] = 'bail|required|date|after:yesterday';
        $rules['amount'] = ['sometimes', 'bail', 'numeric', 'max:99999999999999'];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (array_key_exists('due_date_days', $input) && is_null($input['due_date_days'])) {
            $input['due_date_days'] = 'terms';
        }

        if (!isset($input['next_send_date']) || $input['next_send_date'] == '') {
            $input['next_send_date'] = now()->format('Y-m-d');
        }

        if (array_key_exists('next_send_date', $input) && is_string($input['next_send_date'])) {
            $input['next_send_date_client'] = $input['next_send_date'];
        }

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }

        if (isset($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('project_id', $input) && is_string($input['project_id'])) {
            $input['project_id'] = $this->decodePrimaryKey($input['project_id']);
        }

        if (isset($input['invitations'])) {
            foreach ($input['invitations'] as $key => $value) {
                if (isset($input['invitations'][$key]['id']) && is_numeric($input['invitations'][$key]['id'])) {
                    unset($input['invitations'][$key]['id']);
                }

                if (array_key_exists('id', $input['invitations'][$key]) && is_string($input['invitations'][$key]['id'])) {
                    $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);
                }

                if (is_string($input['invitations'][$key]['client_contact_id'])) {
                    $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);
                }
            }
        }

        if (isset($input['line_items'])) {
            $input['line_items'] = $this->cleanItems($input['line_items']);
            $input['amount'] = $this->entityTotalAmount($input['line_items']);
        }

        if (array_key_exists('auto_bill', $input) && isset($input['auto_bill'])) {
            $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
        }

        if (array_key_exists('documents', $input)) {
            unset($input['documents']);
        }

        if (array_key_exists('exchange_rate', $input) && (is_null($input['exchange_rate']) || $input['exchange_rate'] == 0) || !isset($input['exchange_rate'])) {
            $input['exchange_rate'] = 1;
        }

        $this->replace($input);
    }

    /**
     * if($auto_bill == '')
     * off / optin / optout will reset the status of this field to off to allow
     * the client to choose whether to auto_bill or not.
     *
     * @param string $auto_bill off/always/optin/optout
     *
     * @return bool
     */
    private function setAutoBillFlag($auto_bill): bool
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }
}

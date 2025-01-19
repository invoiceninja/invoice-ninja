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

namespace App\Http\Requests\RecurringQuote;

use App\Http\Requests\Request;
use App\Models\Client;
use App\Models\RecurringQuote;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;

class StoreRecurringQuoteRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();

        return $user->can('create', RecurringQuote::class);
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
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        $rules['client_id'] = 'required|exists:clients,id,company_id,'.$user->company()->id;

        $rules['invitations.*.client_contact_id'] = 'distinct';

        $rules['frequency_id'] = 'required|integer|digits_between:1,12';

        $rules['number'] = ['bail', 'nullable', \Illuminate\Validation\Rule::unique('recurring_quotes')->where('company_id', $user->company()->id)];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();
        $input = $this->decodePrimaryKeys($input);

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];

        if (isset($input['auto_bill'])) {
            $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
        } else {
            if ($client = Client::find($input['client_id'])) {
                /** @var \App\Models\Client $client */
                $input['auto_bill'] = $client->getSetting('auto_bill');
                $input['auto_bill_enabled'] = $this->setAutoBillFlag($input['auto_bill']);
            }
        }

        $this->replace($input);
    }

    private function setAutoBillFlag($auto_bill)
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }

    public function messages()
    {
        return [];
    }
}

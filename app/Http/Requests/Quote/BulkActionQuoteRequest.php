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
use App\Http\ValidationRules\Quote\ConvertableQuoteRule;

class BulkActionQuoteRequest extends Request
{
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
        $input = $this->all();

        $rules = [
            'action' => 'sometimes|in:template,convert_to_invoice,convert_to_project,email,bulk_download,bulk_print,clone_to_invoice,approve,download,restore,archive,delete,send_email,mark_sent',
            'ids' => 'required|array',
            'template' => 'sometimes|string',
            'template_id' => 'sometimes|string',
            'send_email' => 'sometimes|bool',
            'email_type' => 'sometimes|in:quote,reminder1,custom1,custom2,custom3',
        ];

        if (in_array($input['action'], ['convert','convert_to_invoice'])) {
            $rules['action'] = [new ConvertableQuoteRule()];
        }

        return $rules;
    }
}

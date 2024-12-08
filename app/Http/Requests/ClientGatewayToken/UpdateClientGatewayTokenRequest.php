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

namespace App\Http\Requests\ClientGatewayToken;

use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;

class UpdateClientGatewayTokenRequest extends Request
{
    use MakesHash;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->isAdmin();
    }

    public function rules()
    {
        /* Ensure we have a client name, and that all emails are unique*/
        $rules = [];

        return $rules;
    }

    public function messages()
    {
        return [
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['client_id'])) {
            unset($input['client_id']);
        }

        if (isset($input['company_gateway_id'])) {
            unset($input['company_gateway_id']);
        }

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}

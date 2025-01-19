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

namespace App\Http\Requests\Nordigen;

use App\Http\Requests\Request;
use App\Libraries\MultiDB;
use App\Models\Company;
use Cache;

class ConfirmNordigenBankIntegrationRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'ref' => 'required|string', // nordigen redirects only with the ref-property
            'lang' => 'string',
        ];
    }
    public function getTokenContent()
    {
        $input = $this->all();

        $data = Cache::get($input['ref']);

        return $data;
    }

    public function getCompany()
    {
        MultiDB::findAndSetDbByCompanyKey($this->getTokenContent()['company_key']);

        return Company::where('company_key', $this->getTokenContent()['company_key'])->firstOrFail();
    }
}

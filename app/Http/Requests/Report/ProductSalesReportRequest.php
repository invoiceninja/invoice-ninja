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

namespace App\Http\Requests\Report;

use App\Utils\Ninja;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Auth\Access\AuthorizationException;

class ProductSalesReportRequest extends Request
{
    use MakesHash;

    private string $error_message = '';

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->checkAuthority();
    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'date_range' => 'bail|required|string',
            'end_date' => 'bail|required_if:date_range,custom|nullable|date',
            'start_date' => 'bail|required_if:date_range,custom|nullable|date',
            'report_keys' => 'bail|present|array',
            'send_email' => 'bail|required|bool',
            'client_id' => 'bail|nullable|sometimes|exists:clients,id,company_id,'.$user->company()->id.',is_deleted,0',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! array_key_exists('date_range', $input) || $input['date_range'] == '') {
            $input['date_range'] = 'all';
        }

        if (! array_key_exists('report_keys', $input)) {
            $input['report_keys'] = [];
        }

        if (! array_key_exists('send_email', $input)) {
            $input['send_email'] = true;
        }

        if (array_key_exists('date_range', $input) && $input['date_range'] != 'custom') {
            $input['start_date'] = null;
            $input['end_date'] = null;
        }

        if (array_key_exists('client_id', $input) && strlen($input['client_id']) >= 1) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        $this->replace($input);
    }

    private function checkAuthority()
    {
        $this->error_message = ctrans('texts.authorization_failure');

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (Ninja::isHosted() && $user->account->isFreeHostedClient()) {
            $this->error_message = ctrans('texts.upgrade_to_view_reports');
            return false;
        }

        return $user->isAdmin() || $user->hasPermission('view_reports');

    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException($this->error_message);
    }

}

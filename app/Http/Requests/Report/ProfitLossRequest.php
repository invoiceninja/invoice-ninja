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
use Illuminate\Auth\Access\AuthorizationException;

class ProfitLossRequest extends Request
{
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
        return [
            'start_date' => 'bail|nullable|required_if:date_range,custom|string|date',
            'end_date' => 'bail|nullable|required_if:date_range,custom|string|date',
            'is_income_billed' => 'required|bail|bool',
            'is_expense_billed' => 'bool',
            'include_tax' => 'required|bail|bool',
            'date_range' => 'sometimes|string',
            'send_email' => 'bool',
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (! array_key_exists('date_range', $input) || $input['date_range'] == '') {
            $input['date_range'] = 'all';
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

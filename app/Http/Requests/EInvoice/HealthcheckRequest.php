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

namespace App\Http\Requests\EInvoice;

use App\Utils\Ninja;
use App\Http\Requests\Request;
use Illuminate\Auth\Access\AuthorizationException;

class HealthcheckRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        if (config('ninja.app_env') == 'local') {
            return true;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return Ninja::isSelfHost() && $user->account->isPaid();
    }

    public function rules(): array
    {
        return [];
    }

    protected function failedAuthorization(): void
    {
        throw new AuthorizationException(
            message: ctrans('texts.peppol_not_paid_message'),
        );
    }
}

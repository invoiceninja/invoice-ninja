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

namespace App\Http\ValidationRules\Company;

use App\Utils\Ninja;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class ValidCompanyQuantity.
 */
class ValidCompanyQuantity implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $message = ctrans('texts.company_limit_reached', ['limit' => Ninja::isSelfHost() ? 10 : auth()->user()->company()->account->hosted_company_count]);

        $test = Ninja::isSelfHost() ?
            auth()->user()->company()->account->companies->count() < 10 :
            (auth()->user()->account->isPaid() || auth()->user()->account->isTrial()) && auth()->user()->company()->account->companies->count() < 10 ;

        if (!$test) {
            $fail($message);
        }
    }

}

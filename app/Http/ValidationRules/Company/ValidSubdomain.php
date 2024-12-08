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

use App\Libraries\MultiDB;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Class ValidSubdomain.
 */
class ValidSubdomain implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (empty($value)) {
            return;
        }

        if (!MultiDB::checkDomainAvailable($value)) {
            $fail(ctrans('texts.subdomain_taken'));
        }
    }
}

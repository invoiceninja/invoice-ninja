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

namespace App\Http\ValidationRules\Credit;

use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class CreditsSumRule.
 */
class CreditsSumRule implements Rule
{
    use MakesHash;

    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        return $this->checkCreditTotals();
    }

    private function checkCreditTotals()
    {
        if (array_sum(array_map('floatval', array_column($this->input['credits'], 'amount'))) > array_sum(array_map('floatval', array_column($this->input['invoices'], 'amount')))) {
            return false;
        }

        return true;
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.credits_applied_validation');
    }
}

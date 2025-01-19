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

use App\Models\Credit;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidCreditsRules.
 */
class ValidCreditsRules implements Rule
{
    use MakesHash;

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    private $error_msg;

    private $input;

    public function __construct($input)
    {
        $this->input = $input;
    }

    public function passes($attribute, $value)
    {
        return $this->checkCreditsAreHomogenous();
    }

    private function checkCreditsAreHomogenous()
    {
        if (! array_key_exists('client_id', $this->input)) {
            $this->error_msg = ctrans('texts.client_id_required');

            return false;
        }

        $unique_array = [];

        // $total_credit_amount = array_sum(array_column($this->input['credits'], 'amount'));
        $total_credit_amount = array_sum(array_map('floatval', array_column($this->input['credits'], 'amount')));

        if ($total_credit_amount <= 0) {
            $this->error_msg = "Total of credits must be more than zero.";

            return false;
        }

        $cred_collection = Credit::withTrashed()->whereIn('id', array_column($this->input['credits'], 'credit_id'))->get();

        foreach ($this->input['credits'] as $credit) {
            $unique_array[] = $credit['credit_id'];

            $cred = $cred_collection->firstWhere('id', $credit['credit_id']);

            if (! $cred) {
                $this->error_msg = ctrans('texts.credit_not_found');
                return false;
            }

            if ($cred->client_id != $this->input['client_id']) {
                $this->error_msg = ctrans('texts.invoices_dont_match_client');
                return false;
            }

            if ($cred->status_id == Credit::STATUS_DRAFT) {
                $cred->service()->markSent()->save();
                $cred = $cred->fresh();
            }

            if ($cred->balance < $credit['amount']) {
                $this->error_msg = ctrans('texts.insufficient_credit_balance');
                return false;
            }
        }

        if (! (array_unique($unique_array) == $unique_array)) {
            $this->error_msg = ctrans('texts.duplicate_credits_submitted');

            return false;
        }

        if (count($this->input['credits']) >= 1 && count($this->input['invoices']) == 0) {
            $this->error_msg = ctrans('texts.credit_with_no_invoice');

            return false;
        }

        if (count($this->input['credits']) >= 1) {
        }

        return true;
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->error_msg;
    }
}

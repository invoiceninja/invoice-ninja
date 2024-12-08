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

namespace App\Http\ValidationRules\Payment;

use App\Models\Invoice;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidInvoicesRules.
 */
class ValidInvoicesRules implements Rule
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
        return $this->checkInvoicesAreHomogenous();
    }

    private function checkInvoicesAreHomogenous()
    {
        if (! array_key_exists('client_id', $this->input)) {
            $this->error_msg = ctrans('texts.client_id_required');

            return false;
        }

        $unique_array = [];

        /////
        $inv_collection = Invoice::withTrashed()->whereIn('id', array_column($this->input['invoices'], 'invoice_id'))->get();

        //todo optimize this into a single query
        foreach ($this->input['invoices'] as $invoice) {
            $unique_array[] = $invoice['invoice_id'];

            if (! array_key_exists('amount', $invoice)) {
                $this->error_msg = ctrans('texts.amount').' required';

                return false;
            }

            /////
            $inv = $inv_collection->firstWhere('id', $invoice['invoice_id']);

            if (! $inv) {
                $this->error_msg = ctrans('texts.invoice_not_found');

                return false;
            }

            if ($inv->client_id != $this->input['client_id']) {
                $this->error_msg = ctrans('texts.invoices_dont_match_client');

                return false;
            }

            if ($inv->status_id == Invoice::STATUS_DRAFT && $invoice['amount'] <= $inv->amount) {
                //catch here nothing to do - we need this to prevent the last elseif triggering
            } elseif ($inv->status_id == Invoice::STATUS_DRAFT && floatval($invoice['amount']) > floatval($inv->amount)) {
                $this->error_msg = 'Amount cannot be greater than invoice balance';
                return false;
            } elseif (floatval($invoice['amount']) > floatval($inv->balance)) {
                $this->error_msg = ctrans('texts.amount_greater_than_balance_v5');
                return false;
            } elseif ($inv->is_deleted) {
                $this->error_msg = 'One or more invoices in this request have since been deleted';
                return false;
            }
        }

        if (! (array_unique($unique_array) == $unique_array)) {
            $this->error_msg = ctrans('texts.duplicate_invoices_submitted');

            return false;
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

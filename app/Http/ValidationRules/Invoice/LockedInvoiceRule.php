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

namespace App\Http\ValidationRules\Invoice;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class LockedInvoiceRule.
 * @deprecated
 */
class LockedInvoiceRule implements Rule
{
    public $invoice;

    public function __construct(Invoice $invoice)
    {
        $this->invoice = $invoice;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        return $this->checkIfInvoiceLocked(); //if it exists, return false!
    }

    /**
     * @return string
     */
    public function message()
    {
        return ctrans('texts.locked_invoice');
    }

    /**
     * @return bool
     */
    private function checkIfInvoiceLocked(): bool
    {
        $lock_invoices = $this->invoice->client->getSetting('lock_invoices');

        switch ($lock_invoices) {
            case 'off':
                return true;
            case 'when_sent':
                if ($this->invoice->status_id == Invoice::STATUS_SENT) {
                    return false;
                }

                return true;

            case 'when_paid':
                if ($this->invoice->status_id == Invoice::STATUS_PAID) {
                    return false;
                }

                return true;

                //if now is greater than the end of month the invoice was dated - do not modify
            case 'end_of_month':
                if (\Carbon\Carbon::parse($this->invoice->date)->setTimezone($this->invoice->company->timezone()->name)->endOfMonth()->lte(now())) {
                    return false;
                }

                return true;
            default:
                return true;
        }
    }
}

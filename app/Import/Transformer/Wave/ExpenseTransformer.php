<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Wave;

use App\Import\Transformer\BaseTransformer;

/**
 * Class ExpenseTransformer.
 */
class ExpenseTransformer extends BaseTransformer
{
    /**
     * @param $line_items_data
     *
     * @return bool|array
     */
    public function transform($line_items_data)
    {
        $data = $line_items_data[0];

        $amount = 0;
        $total_tax = 0;
        $tax_rate = 0;

        foreach ($line_items_data as $record) {
            $amount += floatval($record['Amount (One column)']);
            $total_tax += floatval($record['Sales Tax Amount']);
        }

        $tax_rate = $total_tax > 0 ? round(($total_tax / $amount) * 100, 3) : 0;

        if (isset($data['Notes / Memo']) && strlen($data['Notes / Memo']) > 1) {
            $public_notes = $data['Notes / Memo'];
        } elseif (isset($data['Transaction Description']) && strlen($data['Transaction Description']) > 1) {
            $public_notes = $data['Transaction Description'];
        } else {
            $public_notes = '';
        }


        $transformed = [
            'company_id'  => $this->company->id,
            'vendor_id'   => $this->getVendorIdOrCreate($this->getString($data, 'Vendor')),
            'number' 	  => $this->getString($data, 'Bill Number'),
            'public_notes' => $public_notes,
            'date'        => $this->parseDate($data['Transaction Date Added']) ?: now()->format('Y-m-d'), //27-01-2022
            'currency_id' => $this->company->settings->currency_id,
            'category_id' => $this->getOrCreateExpenseCategry($data['Account Name']),
            'amount'	  => $amount,
            'tax_name1'   => isset($data['Sales Tax Name']) ? $data['Sales Tax Name'] : '',
            'tax_rate1'	  => $tax_rate,
        ];

        return $transformed;
    }
}

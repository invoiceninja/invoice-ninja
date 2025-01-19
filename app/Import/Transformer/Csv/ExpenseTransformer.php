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

namespace App\Import\Transformer\Csv;

use App\Import\Transformer\BaseTransformer;

/**
 * Class InvoiceTransformer.
 */
class ExpenseTransformer extends BaseTransformer
{
    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($data)
    {

        $clientId = isset($data['expense.client'])
            ? $this->getClientId($data['expense.client'])
            : null;

        return [
            'company_id' => $this->company->id,
            'amount' => abs($this->getFloat($data, 'expense.amount')),
            'currency_id' => $this->getCurrencyByCode(
                $data,
                'expense.currency_id'
            ),
            'vendor_id' => isset($data['expense.vendor'])
                ? $this->getVendorId($data['expense.vendor'])
                : null,
            'client_id' => isset($data['expense.client'])
                ? $this->getClientId($data['expense.client'])
                : null,
            'date' => strlen($this->getString($data, 'expense.date')) > 1 ? $this->parseDate($data['expense.date']) : now()->format('Y-m-d'),
            'public_notes' => $this->getString($data, 'expense.public_notes'),
            'private_notes' => $this->getString($data, 'expense.private_notes'),
            'category_id' => isset($data['expense.category'])
                ? $this->getExpenseCategoryId($data['expense.category'])
                : null,
            'project_id' => isset($data['expense.project'])
                ? $this->getProjectId($data['expense.project'], $clientId)
                : null,
            'payment_type_id' => isset($data['expense.payment_type'])
                ? $this->getPaymentTypeId($data['expense.payment_type'])
                : null,
            'payment_date' => isset($data['expense.payment_date'])
                ? $this->parseDate($data['expense.payment_date'])
                : null,
            'custom_value1' => $this->getString($data, 'expense.custom_value1'),
            'custom_value2' => $this->getString($data, 'expense.custom_value2'),
            'custom_value3' => $this->getString($data, 'expense.custom_value3'),
            'custom_value4' => $this->getString($data, 'expense.custom_value4'),
            'transaction_reference' => $this->getString(
                $data,
                'expense.transaction_reference'
            ),
            'should_be_invoiced' => $clientId ? true : false,
            'uses_inclusive_taxes' => (bool) $this->getString($data, 'expense.uses_inclusive_taxes'),
            'tax_name1' => $this->getString($data, 'expense.tax_name1'),
            'tax_rate1' => $this->getFloat($data, 'expense.tax_rate1'),
            'tax_name2' => $this->getString($data, 'expense.tax_name2'),
            'tax_rate2' => $this->getFloat($data, 'expense.tax_rate2'),
            'tax_name3' => $this->getString($data, 'expense.tax_name3'),
            'tax_rate3' => $this->getFloat($data, 'expense.tax_rate3'),

        ];
    }
}

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

namespace App\Import\Transformer\Bank;

use App\Import\Transformer\BaseTransformer;

/**
 * Class BankTransformer.
 */
class BankTransformer extends BaseTransformer
{
    /**
     * @param $line_items_data
     *
     * @return bool|array
     */
    public function transform($transaction)
    {
        $now = now();

        $transformed = [
            'bank_integration_id' => $transaction['transaction.bank_integration_id'],
            'transaction_id' => $this->getNumber($transaction, 'transaction.transaction_id'),
            'amount' => $this->calculateAmount($transaction),
            'currency_id' => $this->getCurrencyByCode($transaction, 'transaction.currency'),
            'account_type' => strlen($this->getString($transaction, 'transaction.account_type')) > 1 ? $this->getString($transaction, 'transaction.account_type') : 'bank',
            'category_id' => $this->getNumber($transaction, 'transaction.category_id') > 0 ? $this->getNumber($transaction, 'transaction.category_id') : null,
            'category_type' => $this->getString($transaction, 'transaction.category_type'),
            'date' => array_key_exists('transaction.date', $transaction) ? $this->parseDate($transaction['transaction.date'])
                : now()->format('Y-m-d'),
            'bank_account_id' => array_key_exists('transaction.bank_account_id', $transaction) ? $transaction['transaction.bank_account_id'] : 0,
            'description' => array_key_exists('transaction.description', $transaction) ? $transaction['transaction.description'] : '',
            'base_type' => $this->calculateType($transaction),
            'created_at' => $now,
            'updated_at' => $now,
            'company_id' => $this->company->id,
            'user_id' => $this->company->owner()->id,
            'participant' => $this->getString($transaction, 'transaction.participant'),
            'participant_name' => $this->getString($transaction, 'transaction.participant_name'),
        ];

        return $transformed;
    }

    private function calculateAmount(array $transaction): float
    {

        if (isset($transaction['transaction.amount'])) {
            return abs($this->getFloat($transaction, 'transaction.amount'));
        }

        if (isset($transaction['transaction.payment_type_Credit'])) {
            return abs($this->getFloat($transaction, 'transaction.payment_type_Credit'));
        }

        if (isset($transaction['transaction.payment_type_Debit'])) {
            return abs($this->getFloat($transaction, 'transaction.payment_type_Debit'));
        }

        return 0;
    }

    private function calculateType($transaction)
    {

        if (array_key_exists('transaction.payment_type_Credit', $transaction) && is_numeric($transaction['transaction.payment_type_Credit'])) {
            return 'CREDIT';
        }

        if (array_key_exists('transaction.transaction.payment_type_Debit', $transaction) && is_numeric($transaction['transaction.payment_type_Debit'])) {
            return 'DEBIT';
        }

        if (array_key_exists('transaction.base_type', $transaction) && (($transaction['transaction.base_type'] == 'DEBIT') || strtolower($transaction['transaction.base_type']) == 'withdrawal')) {
            return 'DEBIT';
        }

        if (array_key_exists('transaction.category_id', $transaction)) {
            return 'DEBIT';
        }

        if (array_key_exists('transaction.category_type', $transaction) && $transaction['transaction.category_type'] == 'Income') {
            return 'CREDIT';
        }

        if (array_key_exists('transaction.category_type', $transaction)) {
            return 'DEBIT';
        }

        if (array_key_exists('transaction.amount', $transaction) && is_numeric($transaction['transaction.amount']) && $transaction['transaction.amount'] > 0) {
            return 'CREDIT';
        }

        return 'DEBIT';
    }
}

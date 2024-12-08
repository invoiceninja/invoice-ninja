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

namespace App\Helpers\Bank\Yodlee\Transformer;

use App\Helpers\Bank\BankRevenueInterface;
use App\Utils\Traits\AppSetup;
use Illuminate\Support\Facades\Cache;

/**
"date": "string",
"sourceId": "string",
"symbol": "string",
"cusipNumber": "string",
"highLevelCategoryId": 0,
"detailCategoryId": 0,
"description": {},
"memo": "string",
"settleDate": "string",
"type": "string",
"intermediary": [],
"baseType": "CREDIT",
"categorySource": "SYSTEM",
"principal": {},
"lastUpdated": "string",
"interest": {},
"price": {},
"commission": {},
"id": 0,
"merchantType": "string",
"amount": {
"amount": 0,
"convertedAmount": 0,
"currency": "USD",
"convertedCurrency": "USD"
},
"checkNumber": "string",
"isPhysical": true,
"quantity": 0,
"valoren": "string",
"isManual": true,
"merchant": {
"website": "string",
"address": {},
"contact": {},
"categoryLabel": [],
"coordinates": {},
"name": "string",
"id": "string",
"source": "YODLEE",
"logoURL": "string"
},
"sedol": "string",
"transactionDate": "string",
"categoryType": "TRANSFER",
"accountId": 0,
"createdDate": "string",
"sourceType": "AGGREGATED",
"CONTAINER": "bank",
"postDate": "string",
"parentCategoryId": 0,
"subType": "OVERDRAFT_CHARGE",
"category": "string",
"runningBalance": {},
"categoryId": 0,
"holdingDescription": "string",
"isin": "string",
"status": "POSTED"

(
[CONTAINER] => bank
[id] => 103953585
[amount] => stdClass Object
    (
        [amount] => 480.66
        [currency] => USD
    )

[categoryType] => UNCATEGORIZE
[categoryId] => 1
[category] => Uncategorized
[categorySource] => SYSTEM
[highLevelCategoryId] => 10000017
[createdDate] => 2022-08-04T21:50:17Z
[lastUpdated] => 2022-08-04T21:50:17Z
[description] => stdClass Object
    (
        [original] => CHEROKEE NATION TAX TA TAHLEQUAH OK
    )

[isManual] =>
[sourceType] => AGGREGATED
[date] => 2022-08-03
[transactionDate] => 2022-08-03
[postDate] => 2022-08-03
[status] => POSTED
[accountId] => 12331794
[runningBalance] => stdClass Object
    (
        [amount] => 480.66
        [currency] => USD
    )

[checkNumber] => 998
)
*/

class IncomeTransformer implements BankRevenueInterface
{
    use AppSetup;

    public function transform($transaction)
    {
        $data = [];

        if (!property_exists($transaction, 'transaction')) {
            return $data;
        }

        foreach ($transaction->transaction as $transaction) {
            //do not store duplicate / pending transactions
            if (property_exists($transaction, 'status') && $transaction->status == 'PENDING') {
                continue;
            }

            //some object do no store amounts ignore these
            if (!property_exists($transaction, 'amount')) {
                continue;
            }

            $data[] = $this->transformTransaction($transaction);
        }

        return $data;
    }

    public function transformTransaction($transaction)
    {
        return [
            'transaction_id' => $transaction->id,
            'amount' => $transaction->amount->amount,
            'currency_id' => $this->convertCurrency($transaction->amount->currency),
            'account_type' => $transaction->CONTAINER,
            'category_id' => $transaction->highLevelCategoryId,
            'category_type' => $transaction->categoryType,
            'date' => $transaction->date,
            'bank_account_id' => $transaction->accountId,
            'description' => $transaction?->description?->original ?? '',
            'base_type' => property_exists($transaction, 'baseType') ? $transaction->baseType : $this->calculateBaseType($transaction),
        ];
    }

    private function calculateBaseType($transaction)
    {
        //CREDIT / DEBIT

        if (property_exists($transaction, 'highLevelCategoryId') && $transaction->highLevelCategoryId == 10000012) {
            return 'CREDIT';
        }

        return 'DEBIT';
    }

    private function convertCurrency(string $code)
    {

        $currencies = app('currencies');

        $currency = $currencies->first(function ($item) use ($code) {
            /** @var \App\Models\Currency $item */
            return $item->code == $code;
        });

        /** @var \App\Models\Currency $currency */
        return $currency ? $currency->id : 1; //@phpstan-ignore-line

    }
}

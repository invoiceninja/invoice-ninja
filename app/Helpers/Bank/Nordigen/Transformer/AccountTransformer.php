<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Helpers\Bank\Nordigen\Transformer;

use App\Helpers\Bank\AccountTransformerInterface;

/**
[0] => stdClass Object
(
    [data] => stdClass Object
        (
            [resourceId] => XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
            [iban] => DE0286055592XXXXXXXXXX
            [currency] => EUR
            [ownerName] => Max Mustermann
            [product] => GiroKomfort
            [bic] => WELADE8LXXX
            [usage] => PRIV
        )
    [metadata] => stdClass Object
        (
            [id] => XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX
            [created] => 2022-12-05T18:41:53.986028Z
            [last_accessed] => 2023-10-29T08:35:34.003611Z
            [iban] => DE0286055592XXXXXXXXXX
            [institution_id] => STADT_KREISSPARKASSE_LEIPZIG_WELADE8LXXX
            [status] => READY
            [owner_name] => Max Mustermann
        )
    [balances] => stdClass Object
        (
            [balances]: [
                {
                    [balanceAmount]: {
                        [amount] => 9825.64
                        [currency] => EUR
                    },
                    [balanceType] => closingBooked
                    [referenceDate] => 2023-12-01
                },
                {
                    [balanceAmount[: {
                        [amount] => 10325.64
                        [currency] => EUR
                    },
                    [balanceType] => interimAvailable
                    [creditLimitIncluded]: true,
                    [referenceDate] => 2023-12-01
                }
            ]
        )
    [institution] => stdClass Object
        (
            [id] => STADT_KREISSPARKASSE_LEIPZIG_WELADE8LXXX
            [name] => Stadt- und Kreissparkasse Leipzig
            [bic] => WELADE8LXXX
            [transaction_total_days] => 360
            [countries] => [
                "DE"
            ],
            [logo] => https://storage.googleapis.com/gc-prd-institution_icons-production/DE/PNG/sparkasse.png
            [supported_payments] => {
                [single-payment] => [
                    "SCT",
                    "ISCT"
                ]
            },
            [supported_features] => [
                "card_accounts",
                "payments",
                "pending_transactions"
            ],
            [identification_codes] => []
        )

    )
 */


class AccountTransformer implements AccountTransformerInterface
{

    public function transform($nordigen_account)
    {

        $data = [];

        if (!property_exists($nordigen_account, 'data') || !property_exists($nordigen_account, 'metadata') || !property_exists($nordigen_account, 'balances') || !property_exists($nordigen_account, 'institution'))
            return $data;

        foreach ($nordigen_account->account as $account) {
            $data[] = $this->transformAccount($account);
        }

        return $data;
    }

    public function transformAccount($account)
    {

        $used_balance = $account->balances[0];
        // prefer entry with closingBooked
        foreach ($account->balances as $entry) {
            if ($entry->balanceType === 'closingBooked') { // available: closingBooked, interimAvailable
                $used_balance = $entry;
                break;
            }
        }

        return [
            'id' => $account->data->id,
            'account_type' => $account->CONTAINER,
            'account_name' => $account->data->iban,
            'account_status' => $account->metadata->status,
            'account_number' => '**** ' . substr($account->data->iban, -7),
            'provider_account_id' => $account->data->iban,
            'provider_id' => $account->institution_id,
            'provider_name' => $account->institution->name,
            'nickname' => property_exists($account->data, 'owner_name') ? $account->data->owner_name : '',
            'current_balance' => (int) property_exists($used_balance, 'balanceAmount') ? $used_balance->balanceAmount->amount : 0,
            'account_currency' => property_exists($used_balance, 'balanceAmount') ? $used_balance->balanceAmount->currency : '',
        ];
    }
}


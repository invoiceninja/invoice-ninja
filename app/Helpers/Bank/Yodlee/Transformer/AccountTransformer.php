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

use App\Helpers\Bank\AccountTransformerInterface;

/**
[0] => stdClass Object
(
    [CONTAINER] => bank
    [providerAccountId] => 11308693
    [accountName] => My CD - 8878
    [accountStatus] => ACTIVE
    [accountNumber] => xxxx8878
    [aggregationSource] => USER
    [isAsset] => 1
    [balance] => stdClass Object
        (
            [currency] => USD
            [amount] => 49778.07
        )

    [id] => 12331861
    [includeInNetWorth] => 1
    [providerId] => 18769
    [providerName] => Dag Site Captcha
    [isManual] =>
    [currentBalance] => stdClass Object
        (
            [currency] => USD
            [amount] => 49778.07
        )

    [accountType] => CD
    [displayedName] => LORETTA
    [createdDate] => 2022-07-28T06:55:33Z
    [lastUpdated] => 2022-07-28T06:56:09Z
    [dataset] => Array
        (
            [0] => stdClass Object
                (
                    [name] => BASIC_AGG_DATA
                    [additionalStatus] => AVAILABLE_DATA_RETRIEVED
                    [updateEligibility] => ALLOW_UPDATE
                    [lastUpdated] => 2022-07-28T06:55:50Z
                    [lastUpdateAttempt] => 2022-07-28T06:55:50Z
                )

        )

)
    )
 */


class AccountTransformer implements AccountTransformerInterface
{
    public function transform($yodlee_account)
    {
        $data = [];

        if (!property_exists($yodlee_account, 'account')) {
            return $data;
        }

        foreach ($yodlee_account->account as $account) {
            $data[] = $this->transformAccount($account);
        }

        return $data;
    }

    public function transformAccount($account)
    {
        $current_balance = 0;
        $account_currency = '';

        if (property_exists($account, 'currentBalance')) {
            $current_balance = $account->currentBalance->amount ?? 0;
            $account_currency = $account->currentBalance->currency ?? '';
        } elseif (property_exists($account, 'balance')) {
            $current_balance = $account->balance->amount ?? 0;
            $account_currency = $account->balance->currency ?? '';
        }

        $account_status = $account->accountStatus;

        if (property_exists($account, 'dataset')) {
            $dataset = $account->dataset[0];
            $status = false;
            $update = false;

            match($dataset->additionalStatus ?? '') {
                'LOGIN_IN_PROGRESS' => $status =  'Data retrieval in progress.',
                'USER_INPUT_REQUIRED' => $status =  'Please reconnect your account, authentication required.',
                'LOGIN_SUCCESS' => $status =  'Data retrieval in progress',
                'ACCOUNT_SUMMARY_RETRIEVED' => $status =  'Account summary retrieval in progress.',
                'NEVER_INITIATED' => $status =  'Upstream working on connecting to your account.',
                'LOGIN_FAILED' => $status =  'Authentication failed, please try reauthenticating.',
                'REQUEST_TIME_OUT' => $status =  'Timeout encountered retrieving data.',
                'DATA_RETRIEVAL_FAILED' => $status =  'Login successful, but data retrieval failed.',
                'PARTIAL_DATA_RETRIEVED' => $status =  'Partial data update failed.',
                'PARTIAL_DATA_RETRIEVED_REM_SCHED' => $status =  'Partial data update failed.',
                'SUCCESS' => $status =  'All accounts added or updated successfully.',
                default => $status = false
            };

            if ($status) {
                $account_status = $status;
            }

            match($dataset->updateEligibility ?? '') {
                'ALLOW_UPDATE' => $update = 'Account connection stable.',
                'ALLOW_UPDATE_WITH_CREDENTIALS' => $update = 'Please reconnect your account with updated credentials.',
                'DISALLOW_UPDATE' => $update = 'Update not available due to technical issues.',
                default => $update = false,
            };

            if ($status && $update) {
                $account_status = $status . ' - ' . $update;
            } elseif ($update) {
                $account_status = $update;
            }

        }

        return [
            'id' => $account->id,
            'account_type' => $account->CONTAINER,
            // 'account_name' => $account->accountName,
            'account_name' => property_exists($account, 'accountName') ? $account->accountName : ($account->nickname ?? 'Unknown Account'),
            'account_status' => $account_status,
            'account_number' => property_exists($account, 'accountNumber') ? '**** ' . substr($account?->accountNumber, -7) : '',
            'provider_account_id' => $account->providerAccountId,
            'provider_id' => $account->providerId,
            'provider_name' => $account->providerName,
            'nickname' => property_exists($account, 'nickname') ? $account->nickname : '',
            'current_balance' => $current_balance,
            'account_currency' => $account_currency,
        ];
    }
}

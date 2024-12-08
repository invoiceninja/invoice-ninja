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

namespace App\Repositories;

use App\Helpers\Bank\Yodlee\Yodlee;
use App\Models\BankIntegration;
use App\Utils\Ninja;

/**
 * Class for bank integration repository.
 */
class BankIntegrationRepository extends BaseRepository
{
    public function save($data, BankIntegration $bank_integration)
    {
        //stub to store
        $bank_integration->fill($data);

        $bank_integration->save();

        return $bank_integration->fresh();
    }

    /**
    * Removes the bank integration from Yodlee
    *
    * @param BankIntegration $bank_integration
    *
    * @return BankIntegration $bank_integration
    */
    public function delete($bank_integration): BankIntegration
    {
        if ($bank_integration->is_deleted) {
            return $bank_integration;
        }

        if (Ninja::isHosted()) {

            $account = $bank_integration->account;

            $bank_integration_account_id = $account->bank_integration_account_id;

            $yodlee = new Yodlee($bank_integration_account_id);

            try {
                $yodlee->deleteAccount($bank_integration->bank_account_id);
            } catch (\Exception $e) {

            }

        }

        parent::delete($bank_integration);

        return $bank_integration;
    }

}

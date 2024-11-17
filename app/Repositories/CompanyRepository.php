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

use App\DataMapper\Tax\TaxModel;
use App\Utils\Ninja;
use App\Models\Company;
use App\Repositories\BaseRepository;

/**
 * CompanyRepository.
 */
class CompanyRepository extends BaseRepository
{
    public function __construct()
    {
    }

    /**
     * Saves the client and its contacts.
     *
     * @param array $data The data
     * @param Company $company
     * @return Company|null  Company Object
     */
    public function save(array $data, Company $company): ?Company
    {

        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $data['custom_fields'] = $this->parseCustomFields($data['custom_fields']);
        }

        $company->fill($data);

        // nlog($data);
        /** Only required to handle v4 migration workloads */
        if(Ninja::isHosted() && $company->isDirty('is_disabled') && !$company->is_disabled) {
            Ninja::triggerForwarding($company->company_key, $company->owner()->email);
        }

        if (array_key_exists('settings', $data)) {
            $company->saveSettings($data['settings'], $company);
        }

        if(isset($data['smtp_username'])) {
            $company->smtp_username = $data['smtp_username'];
        }

        if(isset($data['smtp_password'])) {
            $company->smtp_password = $data['smtp_password'];
        }
        
        if (isset($data['e_invoice'])) {
            $company->e_invoice = $data['e_invoice'];
        }

        $company->save();

        return $company;
    }

    /**
     * parseCustomFields
     *
     * @param  array $fields
     * @return array
     */
    private function parseCustomFields($fields): array
    {
        foreach ($fields as &$value) {
            $value = (string) $value;
        }

        return $fields;
    }
}

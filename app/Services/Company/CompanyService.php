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

namespace App\Services\Company;

use App\Factory\TaxRateFactory;
use App\Models\Company;
use App\Models\User;

class CompanyService
{
    public function __construct(public Company $company)
    {
    }

    public function localizeCompany(User $user)
    {
        try {

            $taxes = [];

            switch ($this->company->settings->country_id) {

                case '36':  // Australia
                    $taxes[] = ['name' => 'GST', 'rate' => 10];
                    break;
                case '40':  // Austria
                    $taxes[] = ['name' => 'USt', 'rate' => 20];
                    break;
                case '56':  // Belgium
                    $taxes[] = ['name' => 'BTW', 'rate' => 21];
                    break;
                case '100': // Bulgaria
                    $taxes[] = ['name' => 'ДДС', 'rate' => 20];
                    break;
                case '250': // France
                    $taxes[] = ['name' => 'TVA', 'rate' => 20];
                    break;
                case '276': // Germany
                    $taxes[] = ['name' => 'MwSt', 'rate' => 19];
                    break;
                case '554': // New Zealand
                    $taxes[] = ['name' => 'GST', 'rate' => 15];
                    break;
                case '710': // South Africa
                    $taxes[] = ['name' => 'VAT', 'rate' => 15];
                    break;
                case '724': // Spain
                    $taxes[] = ['name' => 'IVA', 'rate' => 21];
                    break;
                case '554': // New Zealand
                    $taxes[] = ['name' => 'GST', 'rate' => 15];
                    break;

                default:
                    return;
            }

            foreach ($taxes as $tax) {
                $tax_rate = TaxRateFactory::create($this->company->id, $user->id);
                $tax_rate->fill($tax);
                $tax_rate->save();
            }

        } catch (\Exception $e) {
            nlog("Exception:: CompanyService::" . $e->getMessage());
            nlog($e->getMessage());
        }

    }

}

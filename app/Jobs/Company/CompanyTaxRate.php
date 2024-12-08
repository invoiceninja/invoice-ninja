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

namespace App\Jobs\Company;

use App\DataMapper\Tax\ZipTax\Response;
use App\DataProviders\USStates;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Services\Tax\Providers\TaxProvider;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class CompanyTaxRate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @param Company $company
     */
    public function __construct(public Company $company)
    {
    }

    public function handle()
    {

        MultiDB::setDB($this->company->db);

        $tp = new TaxProvider($this->company);
        $tp->updateCompanyTaxData();

        if (!$tp->updatedTaxStatus() && $this->company->settings->country_id == '840') {

            $calculated_state = false;

            /** State must be calculated else default to the company state for taxes */
            if (array_key_exists($this->company->settings->state, USStates::get())) {
                $calculated_state = $this->company->settings->state;
            } else {

                try {
                    $calculated_state = USStates::getState($this->company->settings->postal_code);
                } catch (\Exception $e) {
                    nlog("Exception:: CompanyTaxRate::" . $e->getMessage());
                    nlog("could not calculate state from postal code => {$this->company->settings->postal_code} or from state {$this->company->settings->state}");
                }

                if (!$calculated_state && $this->company->tax_data?->seller_subregion) { //@phpstan-ignore-line
                    $calculated_state = $this->company->tax_data->seller_subregion;
                }

                if (!$calculated_state) {
                    return;
                }

            }

            $data = [
                'seller_subregion' => $this->company->origin_tax_data?->seller_subregion ?: '',
                'geoPostalCode' => $this->company->settings->postal_code ?? '',
                'geoCity' => $this->company->settings->city ?? '',
                'geoState' => $calculated_state,
                'taxSales' => $this->company->tax_data->regions->US->subregions?->{$calculated_state}?->taxSales ?? 0,
            ];

            $tax_data = new Response($data);

            $this->company->origin_tax_data = $tax_data;
            $this->company->saveQuietly();

        }

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->company_key)];
    }

    public function failed($e)
    {
        nlog($e->getMessage());
    }
}

<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

use App\Models\Company;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Country;
use Illuminate\Database\Eloquent\Model;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
    
        Schema::table('countries', function (Blueprint $table) {
            $table->string('iso_3166_2',5)->change();
            $table->string('country_code', 4)->change();
        });

        $regions = [
            [
                'id' => 1000,  // INE code for Canary Islands
                'capital' => 'Las Palmas de Gran Canaria / Santa Cruz de Tenerife',
                'citizenship' => 'Spanish',
                'country_code' => '1000',
                'currency' => 'euro',
                'currency_code' => 'EUR',
                'currency_sub_unit' => 'cent',
                'full_name' => 'Canary Islands',
                'iso_3166_2' => 'ES-CN',
                'iso_3166_3' => 'ESP',  // Spain's ISO 3166-3 code
                'name' => 'Canary Islands',
                'region_code' => '142',
                'sub_region_code' => '024',
                'eea' => true,
                'swap_postal_code' => false,
                'swap_currency_symbol' => false,
                'thousand_separator' => '',
                'decimal_separator' => '',
            ],
            [
                'id' => 1001,  // INE code for Ceuta
                'capital' => 'Ceuta',
                'citizenship' => 'Spanish',
                'country_code' => '1001',
                'currency' => 'euro',
                'currency_code' => 'EUR',
                'currency_sub_unit' => 'cent',
                'full_name' => 'Ceuta',
                'iso_3166_2' => 'ES-CE',
                'iso_3166_3' => 'ESP',  // Spain's ISO 3166-3 code
                'name' => 'Ceuta',
                'region_code' => '142',
                'sub_region_code' => '020',
                'eea' => true,
                'swap_postal_code' => false,
                'swap_currency_symbol' => false,
                'thousand_separator' => '',
                'decimal_separator' => '',
            ],
            [
                'id' => 1002,  // INE code for Melilla
                'capital' => 'Melilla',
                'citizenship' => 'Spanish',
                'country_code' => '1002',
                'currency' => 'euro',
                'currency_code' => 'EUR',
                'currency_sub_unit' => 'cent',
                'full_name' => 'Melilla',
                'iso_3166_2' => 'ES-ML',
                'iso_3166_3' => 'ESP',  // Spain's ISO 3166-3 code
                'name' => 'Melilla',
                'region_code' => '142',
                'sub_region_code' => '021',
                'eea' => true,
                'swap_postal_code' => false,
                'swap_currency_symbol' => false,
                'thousand_separator' => '',
                'decimal_separator' => '',
            ],
        ];

        Model::unguard();

        foreach ($regions as $region) {

            if(!Country::find($region['id']))
            {
                Country::create($region);
            }
        }

        Model::reguard();

        // Company::query()->cursor()->each(function ($company) {
        //     $company->tax_data = new \App\DataMapper\Tax\TaxModel($company->tax_data);
        //     $company->save();
        // });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

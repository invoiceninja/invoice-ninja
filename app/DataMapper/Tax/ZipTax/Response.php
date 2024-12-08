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

namespace App\DataMapper\Tax\ZipTax;

class Response
{
    public string $version = 'v40';

    public int $rCode = 100;

    /**
    *  [
    *            "geoPostalCode" => "92582",
    *            "geoCity" => "SAN JACINTO",
    *            "geoCounty" => "RIVERSIDE",
    *            "geoState" => "CA",
    *            "taxSales" => 0.0875,
    *            "taxUse" => 0.0875,
    *            "txbService" => "N",
    *            "txbFreight" => "N",
    *            "stateSalesTax" => 0.06,
    *            "stateUseTax" => 0.06,
    *            "citySalesTax" => 0.01,
    *            "cityUseTax" => 0.01,
    *            "cityTaxCode" => "874",
    *            "countySalesTax" => 0.0025,
    *            "countyUseTax" => 0.0025,
    *            "countyTaxCode" => "",
    *            "districtSalesTax" => 0.015,
    *            "districtUseTax" => 0.015,
    *            "district1Code" => "26",
    *            "district1SalesTax" => 0,
    *            "district1UseTax" => 0,
    *            "district2Code" => "26",
    *            "district2SalesTax" => 0.005,
    *            "district2UseTax" => 0.005,
    *            "district3Code" => "",
    *            "district3SalesTax" => 0,
    *            "district3UseTax" => 0,
    *            "district4Code" => "33",
    *            "district4SalesTax" => 0.01,
    *            "district4UseTax" => 0.01,
    *            "district5Code" => "",
    *            "district5SalesTax" => 0,
    *            "district5UseTax" => 0,
    *            "originDestination" => "D",
    *
    *    ];
    *
    */
    public string $seller_subregion = "";
    //US

    public string $geoPostalCode = "";
    public string $geoCity = "";
    public string $geoCounty = "";
    public string $geoState = "";
    public float $taxSales = 0;
    public string $taxName = "";
    public float $taxUse = 0;
    public string $txbService = "Y"; // N = No, Y = Yes
    public string $txbFreight = "Y"; // N = No, Y = Yes
    public float $stateSalesTax = 0;
    public float $stateUseTax = 0;
    public float $citySalesTax = 0;
    public float $cityUseTax = 0;
    public string $cityTaxCode = "";

    /* US SPECIFIC TAX CODES */
    public float $countySalesTax = 0;
    public float $countyUseTax = 0;
    public string $countyTaxCode = "";
    public float $districtSalesTax = 0;
    public float $districtUseTax = 0;
    public string $district1Code = "";
    public float $district1SalesTax = 0;
    public float $district1UseTax = 0;
    public string $district2Code = "";
    public float $district2SalesTax = 0;
    public float $district2UseTax = 0;
    public string $district3Code = "";
    public float $district3SalesTax = 0;
    public float $district3UseTax = 0;
    public string $district4Code = "";
    public float $district4SalesTax = 0;
    public float $district4UseTax = 0;
    public string $district5Code = "";
    public float $district5SalesTax = 0;
    public float $district5UseTax = 0;
    /* US SPECIFIC TAX CODES */

    public string $originDestination = "D"; // defines if the client origin is the locale where the tax is remitted to

    public function __construct($data = null)
    {

        if ($data) {

            foreach ($data as $key => $value) {
                $this->{$key} = $value;
            }

        }

    }

}

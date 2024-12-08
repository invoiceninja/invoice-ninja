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

namespace App\Services\Tax;

use App\Models\Client;

class TaxService
{
    public function __construct(public Client $client)
    {
    }

    public function validateVat(): self
    {
        if (!extension_loaded('soap')) {
            nlog("Install the PHP SOAP extension if you wish to check VAT Numbers. See https://www.php.net/manual/en/soap.installation.php for more information on installing the PHP");
            return $this;
        }

        $client_country_code = $this->client->shipping_country ? $this->client->shipping_country->iso_3166_2 : $this->client->country->iso_3166_2;

        $vat_check = (new VatNumberCheck($this->client->vat_number, $client_country_code))->run();

        nlog($vat_check);

        if ($vat_check->isValid()) {

            $this->client->has_valid_vat_number = true;

            if (!$this->client->name && strlen($vat_check->getName()) > 2) {
                $this->client->name = $vat_check->getName();
            }

            if (empty($this->client->private_notes) && strlen($vat_check->getAddress()) > 2) {
                $this->client->private_notes = $vat_check->getAddress();
            }

            $this->client->saveQuietly();
        }

        return $this;

    }

    public function initTaxProvider()
    {

    }
}

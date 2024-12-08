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

namespace App\Services\Tax\Providers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class ZipTax implements TaxProviderInterface
{
    private string $endpoint = 'https://api.zip-tax.com/request/v40';

    private string $api_key = '';

    public function __construct(protected array $address)
    {
    }

    public function run()
    {
        $string_address = implode(" ", $this->address);

        $response = $this->callApi(['key' => $this->api_key, 'address' => $string_address]);

        if ($response->successful()) {
            return $this->parseResponse($response->json());
        }

        if (isset($this->address['postal_code'])) {
            $response = $this->callApi(['key' => $this->api_key, 'address' => $this->address['postal_code']]);

            if ($response->successful()) {
                return $this->parseResponse($response->json());
            }

        }

        return null;
    }

    public function setApiCredentials($api_key): self
    {
        $this->api_key = $api_key;

        return $this;
    }

    /**
     * callApi
     *
     * @param  array $parameters
     */
    private function callApi(array $parameters)
    {

        return Http::retry(3, 1000)->withHeaders([])->get($this->endpoint, $parameters);

    }

    private function parseResponse($response)
    {

        if (isset($response['rCode']) && $response['rCode'] == 100 && isset($response['results']['0'])) {
            return $response['results']['0'];
        }

        if (isset($response['rCode']) && class_exists(\Modules\Admin\Events\TaxProviderException::class)) {
            event(new \Modules\Admin\Events\TaxProviderException($response['rCode']));
        }

        return null;

    }
}

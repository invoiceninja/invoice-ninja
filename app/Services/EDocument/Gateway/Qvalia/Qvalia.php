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

namespace App\Services\EDocument\Gateway\Qvalia;

use App\DataMapper\Analytics\LegalEntityCreated;
use App\Models\Company;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use Illuminate\Http\Client\RequestException;
use Turbo124\Beacon\Facades\LightLogs;

class Qvalia
{
    /** @var string $base_url */
    private string $base_url = 'https://api.qvalia.com';

    /** @var string $sandbox_base_url */
    private string $sandbox_base_url = 'https://api-qa.qvalia.com';

    private bool $test_mode = true;

    /** @var array $peppol_discovery */
    private array $peppol_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "peppol",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "de:lwid",
        "identifier" => "DE:VAT"
    ];

    /** @var array $dbn_discovery */
    private array $dbn_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "dbnalliance",
        "metaScheme" =>  "iso6523-actorid-upis",
        "scheme" =>  "gln",
        "identifier" => "1200109963131"
    ];

    private ?int $legal_entity_id;

    public Partner $partner;

    public Invoice $invoice;

    public Mutator $mutator;
    //integrationid - returned in headers

    public function __construct()
    {
        $this->init();
        $this->partner = new Partner($this);
        $this->invoice = new Invoice($this);
        $this->mutator = new Mutator($this);
    }

    private function init(): self
    {

        if ($this->test_mode) {
            $this->base_url = $this->sandbox_base_url;
        }

        return $this;
    }

    public function sendDocument($legal_entity_id)
    {
        $uri = "/transaction/{$legal_entity_id}/invoices/outgoing";
        $verb = 'POST';
    }


    /**
    * httpClient
    *
    * @param  string $uri
    * @param  string $verb
    * @param  array $data
    * @param  array $headers
    * @return \Illuminate\Http\Client\Response
    */
    public function httpClient(string $uri, string $verb, array $data, ?array $headers = [])
    {

        try {
            $r = Http::withToken(config('ninja.qvalia_api_key'))
                ->withHeaders($this->getHeaders($headers))
            ->{$verb}("{$this->base_url}{$uri}", $data)->throw();
        } catch (ClientException $e) {
            // 4xx errors

            nlog("LEI:: {$this->legal_entity_id}");
            nlog("Client error: " . $e->getMessage());
            nlog("Response body: " . $e->getResponse()->getBody()->getContents());
        } catch (ServerException $e) {
            // 5xx errors

            nlog("LEI:: {$this->legal_entity_id}");
            nlog("Server error: " . $e->getMessage());
            nlog("Response body: " . $e->getResponse()->getBody()->getContents());
        } catch (\Illuminate\Http\Client\RequestException $e) {

            nlog("LEI:: {$this->legal_entity_id}");
            nlog("Request error: {$e->getCode()}: " . $e->getMessage());
            $responseBody = $e->response->body();
            nlog("Response body: " . $responseBody);

            return $e->response;

        }

        return $r; // @phpstan-ignore-line
    }
}

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

namespace App\Services\EDocument\Gateway\Storecove;

use App\Models\Company;
use Illuminate\Support\Facades\Http;
use Turbo124\Beacon\Facades\LightLogs;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use App\DataMapper\Analytics\LegalEntityCreated;
use App\Services\EDocument\Gateway\Transformers\StorecoveExpense;

enum HttpVerb: string
{
    case POST = 'post';
    case PUT = 'put';
    case GET = 'get';
    case PATCH = 'patch';
    case DELETE = 'delete';
}

class Storecove
{    
    /** @var string $base_url */
    private string $base_url = 'https://api.storecove.com/api/v2/';
    
    /** @var array $peppol_discovery */
    private array $peppol_discovery = [
            "documentTypes" =>  ["invoice"],
            "network" =>  "peppol",
            "metaScheme" =>  "iso6523-actorid-upis",
            // "scheme" =>  "de:lwid",
            // "identifier" => "DE:VAT",
    ];
    
    /** @var array $dbn_discovery */
    private array $dbn_discovery = [
        "documentTypes" =>  ["invoice"],
        "network" =>  "dbnalliance",
        "metaScheme" =>  "iso6523-actorid-upis",
        // "scheme" =>  "gln",
        // "identifier" => "1200109963131",
    ];

    private ?int $legal_entity_id = null;

    public StorecoveRouter $router;

    public Mutator $mutator;

    public StorecoveAdapter $adapter;

    public StorecoveExpense $expense;

    public StorecoveProxy $proxy;

    public function __construct()
    {
        $this->router = new StorecoveRouter();
        $this->mutator = new Mutator($this);
        $this->adapter = new StorecoveAdapter($this);
        $this->expense = new StorecoveExpense($this);
        $this->proxy = new StorecoveProxy($this);
    }
        
    /**
     * build
     *
     * @param  \App\Models\Invoice $model
     * @return mixed
     */
    public function build($model): mixed
    {
        // return 
        $this->adapter
             ->transform($model)
             ->decorate()
             ->validate();

        return $this;
    }

    public function getResult(): array
    {
        return $this->adapter->getDocument();
    }

    /**
     * Discovery
     *
     * @param  string $identifier
     * @param  string $scheme
     * @param  string $network
     * @return bool
     */
    public function discovery(string $identifier, string $scheme, string $network = 'peppol'): bool
    {
        $network_data = [];

        $network_data = match ($network) {
            'peppol' => [
                    ...$this->peppol_discovery,
                    'scheme' => $scheme,
                    'identifier' => $identifier
            ],
            'dbn' => array_merge(
                $this->dbn_discovery,
                ['scheme' => $scheme, 'identifier' => $identifier]
            ),
            default => [
                    ...$this->peppol_discovery,
                    'scheme' => $scheme,
                    'identifier' => $identifier
            ],
        };

        $uri =  "discovery/receives";
        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $network_data, $this->getHeaders());
        // nlog($network_data);
        // nlog($r->json());
        // nlog($r->body());

        return ($r->successful() && $r->json()['code'] == 'OK') ? true : false;

    }

    /**
     * Discovery - attempts to find the identifier on the network
     *
     * @param  string $identifier
     * @param  string $scheme
     * @param  string $network
     * @return bool
     */
    public function exists(string $identifier, string $scheme, string $network = 'peppol'): bool
    {
        $network_data = [];

        match ($network) {
            'peppol' => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            'dbn' => $network_data = array_merge($this->dbn_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
            default => $network_data = array_merge($this->peppol_discovery, ['scheme' => $scheme, 'identifier' => $identifier]),
        };

        $uri =  "discovery/exists";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $network_data, $this->getHeaders());

        // nlog($r->json());
        return ($r->successful() && $r->json()['code'] == 'OK') ? true : false;

    }

    /**
     * Unused as yet
     * @todo
     * @param  array $payload
     */
    public function sendJsonDocument(array $payload): string|\Illuminate\Http\Client\Response
    {
        
        $uri = "document_submissions";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        if($r->successful()) {
            nlog("sent! GUID = {$r->json()['guid']}");
            return $r->json()['guid'];
        }

        nlog($payload);
        nlog($r->body());

        return $r;

    }
    
    /**
     * Send Raw UBL Document via StoreCove
     *
     * @param  string $document
     * @param  int $routing_id
     * @param  array $override_payload
     * 
     * @return string|\Illuminate\Http\Client\Response
     */
    public function sendDocument(string $document, int $routing_id, array $override_payload = [])
    {
        $this->legal_entity_id = $routing_id;

        $payload = [
            "legalEntityId" => $routing_id,
            "idempotencyGuid" => \Illuminate\Support\Str::uuid(),
            "routing" => [
                "eIdentifiers" => [],
                "emails" => ["peppol@mail.invoicing.co"]
            ],
            "document" => [

            ],
        ];

        $payload = array_merge($payload, $override_payload);

        $payload['document']['documentType'] = 'invoice';
        $payload['document']["rawDocumentData"] = [
                    "document" => base64_encode($document),
                    "parse" => true,
                    "parseStrategy" => "ubl",
        ];

        $uri = "document_submissions";

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload, $this->getHeaders());

        if($r->successful()) {
            return $r->json()['guid'];
        }

        return $r;

    }

    /**
     * Get Sending Evidence
     *
     * 
     * "guid" => "661c079d-0c2b-4b45-8263-678ed81224af",
    "sender" => "9930:DE923356489",
    "receiver" => "9930:DE321281763",
    "documents" => [
      [
        "mime_type" => "application/xml",
        "document" => "html URL to fileg",
        "expires_at" => "2024-11-17 21:46:47+00:00",
      ],
    ],
    "evidence" => [
      "receiving_accesspoint" => "CN=PNL000151, OU=PEPPOL TEST AP, O=Storecove (Datajust B.V.), C=NL",
      
     * @param  string $guid
     * @return mixed
     */
    public function getSendingEvidence(string $guid)
    {
        $uri = "document_submissions/{$guid}/evidence";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, [], $this->getHeaders());

        if($r->successful())
            return $r->json();

        return $r;
    }

    public function setupLegalEntity(array $data): array|\Illuminate\Http\Client\Response
    {
        $legal_entity_response = $this->createLegalEntity($data);

        if (! is_array($legal_entity_response)) {
            return $legal_entity_response;
        }

        $scheme = $this->router->resolveRouting($data['country'], $data['classification']);

        $add_identifier_response = $this->addIdentifier(
            legal_entity_id: $legal_entity_response['id'],
            identifier: $data['classification'] === 'individual' ? str_replace('/','', $data['id_number']) : str_replace(" ", "", $data['vat_number']),
            scheme: $scheme,
        );

        if (! is_array($add_identifier_response)) {
            return $add_identifier_response;
        }

        return [
            'legal_entity_id' => $legal_entity_response['id'],
            'tax_data' => [
                'acts_as_sender' => $data['acts_as_sender'],
                'acts_as_receiver' => $data['acts_as_receiver'],
            ],
        ];
    }

    /**
     * CreateLegalEntity
     *
     * Creates a legal entity for a Company. 
     * 
     * Following creation, you will also need to create a Peppol Identifier
     * 
     * @url https://www.storecove.com/docs/#_openapi_legalentitycreate
     * 
     * @return mixed
     */
    public function createLegalEntity(array $data, ?Company $company = null)
    {
        $uri = 'legal_entities';

        if($company){

            $data = array_merge([            
                'city' => $company->settings->city,
                'country' => $company->country()->iso_3166_2,
                'county' => $company->settings->state,
                'line1' => $company->settings->address1,
                'line2' => $company->settings->address2,
                'party_name' => $company->settings->name,
                'tax_registered' => (bool)strlen($company->settings->vat_number ?? '') > 2,
                'tenant_id' => $company->company_key,
                'zip' => $company->settings->postal_code,
            ], $data);

        }

        //@todo - $data should contain the send/receive configuration for the next array
        $company_defaults = [
            'acts_as_receiver' => true,
            'acts_as_sender' => true,
            'advertisements' => ['invoice'],
        ];

        $payload = array_merge($company_defaults, $data);

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $payload);

        if($r->successful()) {
            $data = $r->object();
            LightLogs::create(new LegalEntityCreated($data->id, $data->tenant_id))->batch();
            return $r->json();
        }

        return $r;

    }
    
    /**
     * GetLegalEntity
     *
     * @param  int $id
     * @return array|\Illuminate\Http\Client\Response
     */
    public function getLegalEntity($id): array|\Illuminate\Http\Client\Response
    {

        $uri = "legal_entities/{$id}";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, []);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }
    
    /**
     * UpdateLegalEntity
     *
     * @param  int $id
     * @param  array $data
     * @return mixed
     */
    public function updateLegalEntity(int $id, array $data)
    {

        $uri = "legal_entities/{$id}";

        $r = $this->httpClient($uri, (HttpVerb::PATCH)->value, $data);

        if($r->successful()) {
            return $r->json();
        }

        return $r;

    }
    
    /**
     * AddIdentifier
     * 
     * Add a Peppol identifier to the legal entity
     *
     * @param  int $legal_entity_id
     * @param  string $identifier
     * @param  string $scheme
     * @return array|\Illuminate\Http\Client\Response
     */
    public function addIdentifier(int $legal_entity_id, string $identifier, string $scheme): array|\Illuminate\Http\Client\Response
    {
        $uri = "legal_entities/{$legal_entity_id}/peppol_identifiers";

        $data = [
            "identifier" => $identifier,
            "scheme" => $scheme,
            "superscheme" => "iso6523-actorid-upis",
        ];

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $data);

        if($r->successful()) {
            $data = $r->json();
            
            return $data;
        }
        nlog($r->body());

        return $r;
    }
    
    /**
     * addAdditionalTaxIdentifier
     *
     * Adds an additional TAX identifier to the legal entity, where they are selling cross border
     * and are required to be registered in the destination country.
     *
     * @param  int $legal_entity_id
     * @param  string $identifier
     * @param  string $scheme
     * @return array|\Illuminate\Http\Client\Response
     */

    public function addAdditionalTaxIdentifier(int $legal_entity_id, string $identifier, string $scheme): array|\Illuminate\Http\Client\Response
    {

        $uri = "legal_entities/{$legal_entity_id}/additional_tax_identifiers";

        // if(
        // stripos($identifier, 'AT') !== false  
        // stripos($identifier, 'BE') !== false  
        // || stripos($identifier, 'IS') !== false
        // ){
            // $identifier = preg_replace('/^[^0-9]{2}/', '', $identifier);
        // }

        $data = [
            "identifier" => $identifier,
            "scheme" => $scheme,
            "superscheme" => "iso6523-actorid-upis",
        ];

        nlog($data);

        $r = $this->httpClient($uri, (HttpVerb::POST)->value, $data);

        if ($r->successful()) {
            $data = $r->json();

            return $data;
        }

        return $r;

    }

    /**
     * removeAdditionalTaxIdentifier
     *
     * Adds an additional TAX identifier to the legal entity, where they are selling cross border
     * and are required to be registered in the destination country.
     *
     * @param  int $legal_entity_id
     * @param  string $tax_identifier
     * @return mixed
     */

    public function removeAdditionalTaxIdentifier(int $legal_entity_id, string $tax_identifier)
    {
        $legal_entity = $this->getLegalEntity($legal_entity_id);

        if(isset($legal_entity['additional_tax_identifiers']) && is_array($legal_entity['additional_tax_identifiers']))
        {

            foreach($legal_entity['additional_tax_identifiers'] as $ati)
            {

                if($ati['identifier'] == $tax_identifier)
                {

                    $uri = "legal_entities/{$legal_entity_id}/additional_tax_identifiers/{$ati['id']}";

                    $r = $this->httpClient($uri, (HttpVerb::DELETE)->value, []);

                    if ($r->successful()) {
                        $data = $r->json();

                        return $data;
                    }

                    return $r;

                }
            }


        }

        return false;
    }

    /**
     * Delete Legal Entity Identifier
     * 
     * Remove the entity from the network
     *
     * @param  int $legal_entity_id
     * @return array|\Illuminate\Http\Client\Response
     */
    public function deleteIdentifier(int $legal_entity_id): array|\Illuminate\Http\Client\Response
    {
        $uri = "/legal_entities/{$legal_entity_id}";

        $r = $this->httpClient($uri, (HttpVerb::DELETE)->value, []);

        if ($r->successful()) {
            return [];
        }

        return $r;
    }
    
    /**
     * getDocument
     *
     * @param  string $guid
     * @param  string $format json|original
     * @return mixed
     */
    public function getDocument(string $guid, string $format = 'json')
    {

        $uri = "/received_documents/{$guid}/{$format}";

        $r = $this->httpClient($uri, (HttpVerb::GET)->value, []);

        if ($r->successful()) {
            $data = $r->json();
// nlog($data);
// nlog(json_encode($data));
nlog($r->body());
    return $data;
        }

        return $r;

    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    
        
    /**
     * getHeaders
     * 
     * Base request headers
     * 
     * @param  array $headers
     * @return array
     */
    private function getHeaders(array $headers = []): array
    {
        return array_merge([
            'Accept' => 'application/json',
            'Content-type' => 'application/json',
        ], $headers);

    }
    
    /**
     * Http Client
     *
     * @param  string $uri
     * @param  string $verb
     * @param  array $data
     * @param  array $headers
     * @return \Illuminate\Http\Client\Response
     */
    private function httpClient(string $uri, string $verb, array $data, ?array $headers = [])
    {

        try {            
            $r = Http::withToken(config('ninja.storecove_api_key'))
                ->withHeaders($this->getHeaders($headers))
            ->{$verb}("{$this->base_url}{$uri}", $data)->throw();
        }
        catch (ClientException $e) {
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

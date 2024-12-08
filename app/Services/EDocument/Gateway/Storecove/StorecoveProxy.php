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

use App\Utils\Ninja;
use App\Models\Company;
use Illuminate\Support\Facades\Http;

class StorecoveProxy
{
    public Company $company;

    public function __construct(public Storecove $storecove)
    {
    }

    public function setCompany(Company $company): self
    {
        $this->company = $company;
        return $this;
    }

    /**
     * Example refactor.
     * getLegalEntity
     *
     * @param  int $legal_entity_id
     * @return array
     */
    public function getLegalEntity(int $legal_entity_id): array
    {
        if (Ninja::isHosted()) {
            $response = $this->storecove->getLegalEntity($legal_entity_id);

            if (is_array($response)) { //successful response is the array
                return $response;
            }

            return $this->handleResponseError($response); //otherwise need to handle the http response returned
        }

        $uri = '/api/einvoice/peppol/legal_entity';
        $payload = ['legal_entity_id' => $legal_entity_id];

        return $this->remoteRequest($uri, $payload); //abstract the http calls
    }

    public function setup(array $data): array
    {
        $data = [
            ...$data,
            'classification' => $data['classification'] ?? $this->company->settings->classification,
            'vat_number' => $data['vat_number'] ?? $this->company->settings->vat_number,
            'id_number' => $data['id_number'] ?? $this->company->settings->id_number,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->setupLegalEntity($data);

            if (is_array($response)) {

                if ($this->company->account->companies()->whereNotNull('legal_entity_id')->count() == 1) {
                    \Modules\Admin\Jobs\Storecove\SendWelcomeEmail::dispatch($this->company);
                }

                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/setup', $data);
    }

    public function disconnect(): array
    {
        $data = [
            'company_key' => $this->company->company_key,
            'legal_entity_id' => $this->company->legal_entity_id,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->deleteIdentifier(
                legal_entity_id: $data['legal_entity_id'],
            );

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/disconnect', $data);
    }

    public function updateLegalEntity(array $data): array
    {
        $data = [
            ...$data,
            'legal_entity_id' => $this->company->legal_entity_id,
        ];

        if (Ninja::isHosted()) {
            $response = $this->storecove->updateLegalEntity($data['legal_entity_id'], $data);

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/update', $data);
    }

    public function addAdditionalTaxIdentifier(array $data): array
    {
        $scheme = $this->storecove->router->resolveTaxScheme($data['country'], $this->company->settings->classification);

        $data = [
            ...$data,
            'classification' => $this->company->settings->classification,
            'legal_entity_id' => $this->company->legal_entity_id,
            'scheme' => $scheme,
        ];

        if (Ninja::isHosted()) {

            $response = $this->storecove->addAdditionalTaxIdentifier($data['legal_entity_id'], $data['vat_number'], $scheme);

            if (is_array($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/add_additional_legal_identifier', $data);
    }

    public function removeAdditionalTaxIdentifier(array $data): array|false
    {
        $data['legal_entity_id'] = $this->company->legal_entity_id;

        if (Ninja::isHosted()) {
            $response = $this->storecove->removeAdditionalTaxIdentifier($data['legal_entity_id'], $data['vat_number']);

            if (is_array($response) || is_bool($response)) {
                return $response;
            }

            return $this->handleResponseError($response);
        }

        return $this->remoteRequest('/api/einvoice/peppol/remove_additional_legal_identifier', $data);
    }

    /**
     * handleResponseError
     *
     * Generic error handler that can return an array response
     *
     * @param  mixed $response
     * @return array
     */
    public function handleResponseError($response): array
    {
        $error = [
            'status' => 'error',
            'message' => 'Unknown error occurred',
            'code' => $response->status() ?? 500,
        ];

        if ($response->json()) {
            $body = $response->json();

            $error['message'] = $body['error'] ?? $body['message'] ?? $response->body();

            if (isset($body['errors']) && is_array($body['errors'])) {
                $error['errors'] = $body['errors'];
            }
        }

        if ($response->status() === 401) {
            $error['message'] = 'Authentication failed';
        }

        if ($response->status() === 403) {
            $error['message'] = 'Access forbidden';
        }

        if ($response->status() === 404) {
            $error['message'] = 'Resource not found';
        }

        nlog([
            'Storecove API Error' => [
                'status' => $response->status(),
                'body' => $response->body(),
                'error' => $error,
            ],
        ]);

        return $error;
    }

    private function remoteRequest(string $uri, array $payload = []): array
    {
        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders($this->getHeaders())
            ->post($uri, $payload);

        if ($response->successful()) {
            if ($response->hasHeader('X-EINVOICE-QUOTA')) {
                // @dave is there any case this will run when user is not logged in? (async)

                /**
                 * @var \App\Models\Account $account
                 */
                $account = auth()->user()->company->account;

                $account->e_invoice_quota = (int) $response->header('X-EINVOICE-QUOTA');
                $account->save();
            }

            return $response->json();
        }

        return $this->handleResponseError($response);
    }

    private function getHeaders(): array
    {

        return [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'X-EInvoice-Token' => $this->company->account->e_invoicing_token,
            "X-Requested-With" => "XMLHttpRequest",
        ];

    }
}

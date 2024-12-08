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

namespace App\Export\CSV;

use App\Export\Decorators\Decorator;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\Company;
use App\Transformers\ClientContactTransformer;
use App\Transformers\ClientTransformer;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ClientExport extends BaseExport
{
    private $client_transformer;

    private $contact_transformer;

    public Writer $csv;

    public string $date_key = 'created_at';

    private Decorator $decorator;

    public array $entity_keys = [
        'address1' => 'client.address1',
        'address2' => 'client.address2',
        'balance' => 'client.balance',
        'city' => 'client.city',
        'country' => 'client.country_id',
        'custom_value1' => 'client.custom_value1',
        'custom_value2' => 'client.custom_value2',
        'custom_value3' => 'client.custom_value3',
        'custom_value4' => 'client.custom_value4',
        'id_number' => 'client.id_number',
        'industry' => 'client.industry_id',
        'last_login' => 'client.last_login',
        'name' => 'client.name',
        'number' => 'client.number',
        'paid_to_date' => 'client.paid_to_date',
        'client_phone' => 'client.phone',
        'postal_code' => 'client.postal_code',
        'private_notes' => 'client.private_notes',
        'public_notes' => 'client.public_notes',
        'shipping_address1' => 'client.shipping_address1',
        'shipping_address2' => 'client.shipping_address2',
        'shipping_city' => 'client.shipping_city',
        'shipping_country' => 'client.shipping_country_id',
        'shipping_postal_code' => 'client.shipping_postal_code',
        'shipping_state' => 'client.shipping_state',
        'state' => 'client.state',
        'vat_number' => 'client.vat_number',
        'website' => 'client.website',
        'currency' => 'client.currency',
        'first_name' => 'contact.first_name',
        'last_name' => 'contact.last_name',
        'contact_phone' => 'contact.phone',
        'contact_custom_value1' => 'contact.custom_value1',
        'contact_custom_value2' => 'contact.custom_value2',
        'contact_custom_value3' => 'contact.custom_value3',
        'contact_custom_value4' => 'contact.custom_value4',
        'email' => 'contact.email',
        'status' => 'status',
        'payment_balance' => 'client.payment_balance',
        'credit_balance' => 'client.credit_balance',
        'classification' => 'client.classification',

    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->client_transformer = new ClientTransformer();
        $this->contact_transformer = new ClientContactTransformer();
        $this->decorator = new Decorator();

    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $report = $query->cursor()
                ->map(function ($client) {

                    /** @var \App\Models\Client $client */
                    $row = $this->buildRow($client);
                    return $this->processMetaData($row, $client);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }



    public function init(): Builder
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->client_report_keys);
        }

        $query = Client::query()->with('contacts')
                                ->withTrashed()
                                ->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) {
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, ' clients');

        if ($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;

    }

    public function run()
    {
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
              ->each(function ($client) {

                  /** @var \App\Models\Client $client */
                  $this->csv->insertOne($this->buildRow($client));
              });

        return $this->csv->toString();
    }

    private function buildRow(Client $client): array
    {
        $transformed_contact = false;

        $transformed_client = $this->client_transformer->transform($client);

        $transformed_contact = [];

        if ($contact = $client->contacts()->first()) {
            $transformed_contact = $this->contact_transformer->transform($contact);
        }

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'client' && array_key_exists($parts[1], $transformed_client)) {
                $entity[$key] = $transformed_client[$parts[1]];
            } elseif (is_array($parts) && $parts[0] == 'contact' && array_key_exists($parts[1], $transformed_contact)) {
                $entity[$key] = $transformed_contact[$parts[1]];
            } else {
                $entity[$key] = $this->decorator->transform($key, $client);
            }
        }

        $entity = $this->decorateAdvancedFields($client, $entity);
        return $this->convertFloats($entity);
    }

    public function processMetaData(array $row, $resource): array
    {
        $clean_row = [];
        foreach (array_values($this->input['report_keys']) as $key => $value) {

            $report_keys = explode(".", $value);

            $column_key = $value;
            $clean_row[$key]['entity'] = $report_keys[0];
            $clean_row[$key]['id'] = $report_keys[1] ?? $report_keys[0];
            $clean_row[$key]['hashed_id'] = $report_keys[0] == 'client' ? null : $resource->{$report_keys[0]}->hashed_id ?? null;
            $clean_row[$key]['value'] = $row[$column_key];
            $clean_row[$key]['identifier'] = $value;

            if (in_array($clean_row[$key]['id'], ['paid_to_date', 'balance', 'credit_balance','payment_balance'])) {
                $clean_row[$key]['display_value'] = Number::formatMoney($row[$column_key], $resource);
            } else {
                $clean_row[$key]['display_value'] = $row[$column_key];
            }

        }

        return $clean_row;
    }

    private function decorateAdvancedFields(Client $client, array $entity): array
    {
        if (in_array('client.user', $this->input['report_keys'])) {
            $entity['client.user'] = $client->user->present()->name();
        }

        if (in_array('client.assigned_user', $this->input['report_keys'])) {
            $entity['client.assigned_user'] = $client->assigned_user ? $client->user->present()->name() : '';
        }

        if (in_array('client.classification', $this->input['report_keys']) && isset($client->classification)) {
            $entity['client.classification'] = ctrans("texts.{$client->classification}") ?? '';
        }

        if (in_array('client.industry_id', $this->input['report_keys']) && isset($client->industry_id)) {
            $entity['client.industry_id'] = ctrans("texts.industry_{$client->industry->name}") ?? '';
        }

        if (in_array('client.country_id', $this->input['report_keys']) && isset($client->country_id)) {
            $entity['client.country_id'] = $client->country ? $client->country->full_name : '';
        }

        if (in_array('client.shipping_country_id', $this->input['report_keys']) && isset($client->shipping_country_id)) {
            $entity['client.shipping_country_id'] = $client->shipping_country ? $client->shipping_country->full_name : '';
        }

        return $entity;
    }

    // private function calculateStatus($client)
    // {
    //     if ($client->is_deleted) {
    //         return ctrans('texts.deleted');
    //     }

    //     if ($client->deleted_at) {
    //         return ctrans('texts.archived');
    //     }

    //     return ctrans('texts.active');
    // }
}

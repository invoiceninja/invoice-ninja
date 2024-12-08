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

use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Document;
use App\Transformers\DocumentTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class DocumentExport extends BaseExport
{
    private $entity_transformer;

    public string $date_key = 'created_at';

    public Writer $csv;

    public array $entity_keys = [
        'record_type' => 'record_type',
        'name' => 'name',
        'type' => 'type',
        'created_at' => 'created_at',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new DocumentTransformer();
    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $report = $query->cursor()
                ->map(function ($document) {

                    /** @var \App\Models\Document $document */
                    $row = $this->buildRow($document);
                    return $this->processMetaData($row, $document);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        $query = Document::query()->where('company_id', $this->company->id);

        $query = $this->addDateRange($query, 'documents');

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
              ->each(function ($entity) {
                  /** @var mixed $entity */
                  $this->csv->insertOne($this->buildRow($entity));
              });

        return $this->csv->toString();
    }

    private function buildRow(Document $document): array
    {
        $transformed_entity = $this->entity_transformer->transform($document);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } else {
                $entity[$keyval] = '';
            }
        }

        return $this->decorateAdvancedFields($document, $entity);
    }

    private function decorateAdvancedFields(Document $document, array $entity): array
    {
        if (in_array('record_type', $this->input['report_keys'])) {
            $entity['record_type'] = class_basename($document->documentable);
        }

        // if(in_array('record_name', $this->input['report_keys']))
        //     $entity['record_name'] = $document->hashed_id;

        return $entity;
    }
}

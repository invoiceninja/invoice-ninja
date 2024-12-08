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

use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Models\Product;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use App\Transformers\ProductTransformer;
use Illuminate\Database\Eloquent\Builder;

class ProductExport extends BaseExport
{
    private $entity_transformer;

    public string $date_key = 'created_at';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->entity_transformer = new ProductTransformer();
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
                ->map(function ($resource) {

                    /** @var \App\Models\Product $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
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
            $this->input['report_keys'] = array_values($this->product_report_keys);
        }

        $query = Product::query()
                        ->withTrashed()
                        ->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) { //@phpstan-ignore-line
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'products');

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

                  /** @var \App\Models\Product $entity */
                  $this->csv->insertOne($this->buildRow($entity));
              });

        return $this->csv->toString();
    }

    private function buildRow(Product $product): array
    {
        $transformed_entity = $this->entity_transformer->transform($product);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->product_report_keys);

            if (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $product);
            }
        }

        return $this->convertFloats($entity);
    }

}

<?php
/**
 * Quote Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Export\Decorators\Decorator;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Quote;
use App\Transformers\QuoteTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class QuoteItemExport extends BaseExport
{
    private $quote_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    private array $storage_array = [];

    private array $storage_item_array = [];

    private array $decorate_keys = [
        'client',
        'currency',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->quote_transformer = new QuoteTransformer();
        $this->decorator = new Decorator();
    }

    public function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->mergeItemsKeys('quote_report_keys'));
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        $query = Quote::query()
                            ->withTrashed()
                            ->whereHas('client', function ($q) {
                                $q->where('is_deleted', false);
                            })
                            ->with('client')->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) {
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'quotes');

        $clients = &$this->input['client_id'];

        if ($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        $query = $this->addQuoteStatusFilter($query, $this->input['status'] ?? '');

        if ($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;

    }

    public function returnJson()
    {
        $query = $this->init();

        $headerdisplay = $this->buildHeader();

        $header = collect($this->input['report_keys'])->map(function ($key, $value) use ($headerdisplay) {
            return ['identifier' => $key, 'display_value' => $headerdisplay[$value]];
        })->toArray();

        $query->cursor()
            ->each(function ($resource) {

                /** @var \App\Models\Quote $resource */
                $this->iterateItems($resource);

                foreach ($this->storage_array as $row) {
                    $this->storage_item_array[] = $this->processItemMetaData($row, $resource);
                }

                $this->storage_array = [];

            });

        return array_merge(['columns' => $header], $this->storage_item_array);

    }


    public function run()
    {

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        $query = $this->init();

        //insert the header
        $this->csv->insertOne($this->buildHeader());


        $query->cursor()
            ->each(function ($quote) {

                /** @var \App\Models\Quote $quote */
                $this->iterateItems($quote);
            });

        $this->csv->insertAll($this->storage_array);

        return $this->csv->toString();

    }

    private function iterateItems(Quote $quote)
    {
        $transformed_quote = $this->buildRow($quote);

        $transformed_items = [];
        $currency = $this->company->currency();

        foreach ($quote->line_items as $item) {
            $item_array = [];

            foreach (array_values(array_intersect($this->input['report_keys'], $this->item_report_keys)) as $key) { //items iterator produces item array

                if (str_contains($key, "item.")) {

                    $tmp_key = str_replace("item.", "", $key);

                    if ($tmp_key == 'type_id') {
                        $tmp_key = 'type';
                    }

                    if ($tmp_key == 'tax_id') {
                        $tmp_key = 'tax_category';
                    }

                    if (property_exists($item, $tmp_key)) {
                        $item_array[$key] = $item->{$tmp_key};
                    } else {
                        $item_array[$key] = '';
                    }
                }
            }

            $transformed_items = array_merge($transformed_quote, $item_array);
            $entity = $this->decorateAdvancedFields($quote, $transformed_items);
            $entity = array_merge(array_flip(array_values($this->input['report_keys'])), $entity);
            $entity = $this->convertFloats($entity);

            $this->storage_array[] = $entity;
        }
    }

    private function buildRow(Quote $quote): array
    {
        $transformed_quote = $this->quote_transformer->transform($quote);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'item') {
                continue;
            }

            if (is_array($parts) && $parts[0] == 'quote' && array_key_exists($parts[1], $transformed_quote)) {
                $entity[$key] = $transformed_quote[$parts[1]];
            } elseif (array_key_exists($key, $transformed_quote)) {
                $entity[$key] = $transformed_quote[$key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $quote);
            }
        }

        $entity = $this->decorateAdvancedFields($quote, $entity);
        return $entity;

    }
    private function decorateAdvancedFields(Quote $quote, array $entity): array
    {

        if (in_array('quote.assigned_user_id', $this->input['report_keys'])) {
            $entity['quote.assigned_user_id'] = $quote->assigned_user ? $quote->assigned_user->present()->name() : '';
        }

        if (in_array('quote.user_id', $this->input['report_keys'])) {
            $entity['quote.user_id'] = $quote->user ? $quote->user->present()->name() : '';
        }



        return $entity;
    }
}

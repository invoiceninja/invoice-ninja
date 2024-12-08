<?php
/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://quoteninja.com)
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

class QuoteExport extends BaseExport
{
    private $quote_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->quote_transformer = new QuoteTransformer();
        $this->decorator = new Decorator();
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));


        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->quote_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        $query = Quote::query()
                        ->withTrashed()
                        ->with('client')
                        ->whereHas('client', function ($q) {
                            $q->where('is_deleted', false);
                        })
                        ->where('company_id', $this->company->id);

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

        if ($this->input['pdf_email_attachment'] ?? false) {
            $this->queuePdfs($query);
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

        $report = $query->cursor()
                ->map(function ($resource) {

                    /** @var \App\Models\Quote $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);


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
                $this->csv->insertOne($this->buildRow($quote));
            });

        return $this->csv->toString();
    }

    private function buildRow(Quote $quote): array
    {
        $transformed_invoice = $this->quote_transformer->transform($quote);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'quote' && array_key_exists($parts[1], $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$parts[1]];
            } else {
                $entity[$key] = $this->decorator->transform($key, $quote);
            }

        }

        $entity = $this->decorateAdvancedFields($quote, $entity);
        return $this->convertFloats($entity);


    }

    private function decorateAdvancedFields(Quote $quote, array $entity): array
    {
        if (in_array('quote.currency_id', $this->input['report_keys'])) {
            $entity['quote.currency'] = $quote->client->currency()->code;
        }

        if (in_array('quote.client_id', $this->input['report_keys'])) {
            $entity['quote.client'] = $quote->client->present()->name();
        }

        if (in_array('quote.status', $this->input['report_keys'])) {
            $entity['quote.status'] = $quote->stringStatus($quote->status_id);
        }

        if (in_array('quote.invoice_id', $this->input['report_keys'])) {
            $entity['quote.invoice'] = $quote->invoice ? $quote->invoice->number : '';
        }

        if (in_array('quote.assigned_user_id', $this->input['report_keys'])) {
            $entity['quote.assigned_user_id'] = $quote->assigned_user ? $quote->assigned_user->present()->name() : '';
        }

        if (in_array('quote.user_id', $this->input['report_keys'])) {
            $entity['quote.user_id'] = $quote->user ? $quote->user->present()->name() : '';
        }


        return $entity;
    }
}

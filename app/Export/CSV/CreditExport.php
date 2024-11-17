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
use App\Models\Company;
use App\Models\Credit;
use App\Transformers\CreditTransformer;
use App\Utils\Ninja;
use App\Utils\Number;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class CreditExport extends BaseExport
{
    private CreditTransformer $credit_transformer;

    private Decorator $decorator;

    public string $date_key = 'created_at';

    public Writer $csv;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->credit_transformer = new CreditTransformer();
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
                ->map(function ($credit) {

                    /** @var \App\Models\Credit $credit */
                    $row = $this->buildRow($credit);
                    return $this->processMetaData($row, $credit);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    public function processMetaData(array $row, $resource): array
    {
        $clean_row = [];
        foreach (array_values($this->input['report_keys']) as $key => $value) {

            $report_keys = explode(".", $value);

            $column_key = $value;
            $clean_row[$key]['entity'] = $report_keys[0];
            $clean_row[$key]['id'] = $report_keys[1] ?? $report_keys[0];
            $clean_row[$key]['hashed_id'] = $report_keys[0] == 'credit' ? null : $resource->{$report_keys[0]}->hashed_id ?? null;
            $clean_row[$key]['value'] = $row[$column_key];
            $clean_row[$key]['identifier'] = $value;

            if(in_array($clean_row[$key]['id'], ['paid_to_date','total_taxes','amount', 'balance', 'partial', 'refunded', 'applied','unit_cost','cost','price'])) {
                $clean_row[$key]['display_value'] = Number::formatMoney($row[$column_key], $resource->client);
            } else {
                $clean_row[$key]['display_value'] = $row[$column_key];
            }

        }

        return $clean_row;
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->credit_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        $query = Credit::query()
                        ->withTrashed()
                        ->with('client')
                        ->whereHas('client', function ($q) {
                            $q->where('is_deleted', false);
                        })
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', $this->input['include_deleted'] ?? false);

        $query = $this->addDateRange($query, 'credits');

        $clients = &$this->input['client_id'];

        if($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        if($this->input['status'] ?? false) {
            $query = $this->addCreditStatusFilter($query, $this->input['status']);
        }

        if($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        if ($this->input['pdf_email_attachment'] ?? false) {
            $this->queuePdfs($query);
        }

        return $query;
    }

    public function run(): string
    {
        $query = $this->init();
        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
            ->each(function ($credit) {
                /** @var \App\Models\Credit $credit */
                $this->csv->insertOne($this->buildRow($credit));
            });

        return $this->csv->toString();
    }

    private function buildRow(Credit $credit): array
    {
        $transformed_credit = $this->credit_transformer->transform($credit);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $keyval = $key;
            $credit_key = str_replace("credit.", "", $key);
            $searched_credit_key = array_search(str_replace("credit.", "", $key), $this->credit_report_keys) ?? $key;

            if (isset($transformed_credit[$credit_key])) {
                $entity[$keyval] = $transformed_credit[$credit_key];
            } elseif (isset($transformed_credit[$keyval])) {
                $entity[$keyval] = $transformed_credit[$keyval];
            } elseif(isset($transformed_credit[$searched_credit_key])) {
                $entity[$keyval] = $transformed_credit[$searched_credit_key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $credit);
            }

        }

        $entity = $this->decorateAdvancedFields($credit, $entity);
        return $this->convertFloats($entity);
    }

    public function addCreditStatusFilter($query, $status): Builder
    {

        $status_parameters = explode(',', $status);

        if (in_array('all', $status_parameters)) {
            return $query;
        }

        $credit_filters = [];

        if (in_array('draft', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_DRAFT;
        }

        if (in_array('sent', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_SENT;
        }

        if (in_array('partial', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_PARTIAL;
        }

        if (in_array('applied', $status_parameters)) {
            $credit_filters[] = Credit::STATUS_APPLIED;
        }

        if (count($credit_filters) >= 1) {
            $query->whereIn('status_id', $credit_filters);
        }

        return $query;
    }

    private function decorateAdvancedFields(Credit $credit, array $entity): array
    {
        // if (in_array('country_id', $this->input['report_keys'])) {
        //     $entity['country'] = $credit->client->country ? ctrans("texts.country_{$credit->client->country->name}") : '';
        // }

        // if (in_array('currency_id', $this->input['report_keys'])) {
        //     $entity['currency_id'] = $credit->client->currency() ? $credit->client->currency()->code : $credit->company->currency()->code;
        // }

        // if (in_array('invoice_id', $this->input['report_keys'])) {
        //     $entity['invoice'] = $credit->invoice ? $credit->invoice->number : '';
        // }

        // if (in_array('client_id', $this->input['report_keys'])) {
        //     $entity['client'] = $credit->client->present()->name();
        // }

        // if (in_array('status_id', $this->input['report_keys'])) {
        //     $entity['status'] = $credit->stringStatus($credit->status_id);
        // }

        // if(in_array('credit.status', $this->input['report_keys'])) {
        //     $entity['credit.status'] = $credit->stringStatus($credit->status_id);
        // }

        if (in_array('credit.assigned_user_id', $this->input['report_keys'])) {
            $entity['credit.assigned_user_id'] = $credit->assigned_user ? $credit->assigned_user->present()->name() : '';
        }

        if (in_array('credit.user_id', $this->input['report_keys'])) {
            $entity['credit.user_id'] = $credit->user ? $credit->user->present()->name() : ''; //@phpstan-ignore-line
        }

        return $entity;
    }
}

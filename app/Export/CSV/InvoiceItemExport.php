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
use App\Models\Invoice;
use App\Transformers\InvoiceTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class InvoiceItemExport extends BaseExport
{
    private $invoice_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    private bool $force_keys = false;

    private array $storage_array = [];

    private array $storage_item_array = [];

    private array $decorate_keys = [
        'client',
        'currency_id',
        'status'
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->invoice_transformer = new InvoiceTransformer();
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
            $this->force_keys = true;
            $this->input['report_keys'] = array_values($this->mergeItemsKeys('invoice_report_keys'));
            nlog($this->input['report_keys']);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        nlog("xx");
        nlog($this->input['report_keys']);
        $query = Invoice::query()
                        ->withTrashed()
                        ->with('client')
                        ->whereHas('client', function ($q) {
                            $q->where('is_deleted', false);
                        })
                        ->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) {// @phpstan-ignore-line
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'invoices');

        $clients = &$this->input['client_id'];

        if ($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        if ($this->input['status'] ?? false) {
            $query = $this->addInvoiceStatusFilter($query, $this->input['status']);
        }

        $query = $this->applyProductFilters($query);

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

                /** @var \App\Models\Invoice $resource */
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
        $query = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
            ->each(function ($invoice) {

                /** @var \App\Models\Invoice $invoice */
                $this->iterateItems($invoice);
            });

        $this->csv->insertAll($this->storage_array);

        return $this->csv->toString();
    }

    private function iterateItems(Invoice $invoice)
    {
        $transformed_invoice = $this->buildRow($invoice);

        $transformed_items = [];

        foreach ($invoice->line_items as $item) {
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

            $transformed_items = array_merge($transformed_invoice, $item_array);
            $entity = $this->decorateAdvancedFields($invoice, $transformed_items);

            $entity = array_merge(array_flip(array_values($this->input['report_keys'])), $entity);

            $this->storage_array[] = $this->convertFloats($entity);

        }
    }

    private function buildRow(Invoice $invoice): array
    {
        $transformed_invoice = $this->invoice_transformer->transform($invoice);

        $entity = [];


        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'item') {
                continue;
            }

            if (is_array($parts) && $parts[0] == 'invoice' && array_key_exists($parts[1], $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$parts[1]];
            } elseif (array_key_exists($key, $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $invoice);
            }
        }

        $entity = $this->decorateAdvancedFields($invoice, $entity);
        return $entity;
    }

    private function decorateAdvancedFields(Invoice $invoice, array $entity): array
    {

        if (in_array('invoice.recurring_id', $this->input['report_keys'])) {
            $entity['invoice.recurring_id'] = $invoice->recurring_invoice->number ?? '';
        }

        if (in_array('invoice.assigned_user_id', $this->input['report_keys'])) {
            $entity['invoice.assigned_user_id'] = $invoice->assigned_user ? $invoice->assigned_user->present()->name() : '';
        }

        if (in_array('invoice.user_id', $this->input['report_keys'])) {
            $entity['invoice.user_id'] = $invoice->user ? $invoice->user->present()->name() : '';// @phpstan-ignore-line
        }

        if (in_array('invoice.project', $this->input['report_keys'])) {
            $entity['invoice.project'] = $invoice->project ? $invoice->project->name : '';// @phpstan-ignore-line
        }

        return $entity;
    }

}

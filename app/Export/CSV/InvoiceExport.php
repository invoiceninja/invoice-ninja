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

class InvoiceExport extends BaseExport
{
    private $invoice_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

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
            $this->input['report_keys'] = array_values($this->invoice_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

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

                    /** @var \App\Models\Invoice $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);
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
                $this->csv->insertOne($this->buildRow($invoice));
            });

        return $this->csv->toString();
    }

    private function buildRow(Invoice $invoice): array
    {
        $transformed_invoice = $this->invoice_transformer->transform($invoice);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'invoice' && array_key_exists($parts[1], $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$parts[1]];
            } else {
                $entity[$key] = $this->decorator->transform($key, $invoice);
            }

        }

        $entity = $this->decorateAdvancedFields($invoice, $entity);
        return  $this->convertFloats($entity);
    }

    private function decorateAdvancedFields(Invoice $invoice, array $entity): array
    {

        if (in_array('invoice.project', $this->input['report_keys'])) {
            $entity['invoice.project'] = $invoice->project ? $invoice->project->name : '';
        }

        if (in_array('invoice.recurring_id', $this->input['report_keys'])) {
            $entity['invoice.recurring_id'] = $invoice->recurring_invoice->number ?? '';
        }

        if (in_array('invoice.auto_bill_enabled', $this->input['report_keys'])) {
            $entity['invoice.auto_bill_enabled'] = $invoice->auto_bill_enabled ? ctrans('texts.yes') : ctrans('texts.no');
        }

        if (in_array('invoice.assigned_user_id', $this->input['report_keys'])) {
            $entity['invoice.assigned_user_id'] = $invoice->assigned_user ? $invoice->assigned_user->present()->name() : '';
        }

        if (in_array('invoice.user_id', $this->input['report_keys'])) {
            $entity['invoice.user_id'] = $invoice->user ? $invoice->user->present()->name() : ''; // @phpstan-ignore-line

        }

        return $entity;
    }
}

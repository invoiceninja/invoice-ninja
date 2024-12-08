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
use App\Models\RecurringInvoice;
use App\Transformers\RecurringInvoiceTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class RecurringInvoiceExport extends BaseExport
{
    private $invoice_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->invoice_transformer = new RecurringInvoiceTransformer();
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
            $this->input['report_keys'] = array_values($this->recurring_invoice_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_client_fields, $this->input['report_keys']));

        $query = RecurringInvoice::query()
                        ->withTrashed()
                        ->with('client')
                        ->whereHas('client', function ($q) {
                            $q->where('is_deleted', false);
                        })
                        ->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) {
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'recurring_invoices');

        $clients = &$this->input['client_id'];

        if ($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        $query = $this->addRecurringInvoiceStatusFilter($query, $this->input['status'] ?? '');

        return $query;

    }

    public function run()
    {

        $query  = $this->init();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        //insert the header
        $this->csv->insertOne($this->buildHeader());

        $query->cursor()
            ->each(function ($invoice) {

                /** @var \App\Models\RecurringInvoice $invoice */
                $this->csv->insertOne($this->buildRow($invoice));
            });

        return $this->csv->toString();
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

                    /** @var \App\Models\RecurringInvoice $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }


    private function buildRow(RecurringInvoice $invoice): array
    {
        $transformed_invoice = $this->invoice_transformer->transform($invoice);

        $entity = [];
        $currency = $this->company->currency();

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'recurring_invoice' && array_key_exists($parts[1], $transformed_invoice)) {
                $entity[$key] = $transformed_invoice[$parts[1]];
            } elseif ($parts[0] == 'item') {
                $entity[$key] = '';
            } else {
                $entity[$key] = $this->decorator->transform($key, $invoice);
            }

            if (is_float($entity[$key])) {
                $entity[$key] = \App\Utils\Number::formatValue($entity[$key], $currency);
            }

        }

        // return $entity;
        return $this->decorateAdvancedFields($invoice, $entity);
    }

    private function decorateAdvancedFields(RecurringInvoice $invoice, array $entity): array
    {

        if (in_array('recurring_invoice.frequency_id', $this->input['report_keys']) || in_array('frequency_id', $this->input['report_keys'])) {
            $entity['recurring_invoice.frequency_id'] = $invoice->frequencyForKey($invoice->frequency_id);
        }

        if (in_array('recurring_invoice.auto_bill_enabled', $this->input['report_keys'])) {
            $entity['recurring_invoice.auto_bill_enabled'] = $invoice->auto_bill_enabled ? ctrans('texts.yes') : ctrans('texts.no');
        }

        if (in_array('recurring_invoice.assigned_user_id', $this->input['report_keys'])) {
            $entity['recurring_invoice.assigned_user_id'] = $invoice->assigned_user ? $invoice->assigned_user->present()->name() : '';
        }

        if (in_array('recurring_invoice.user_id', $this->input['report_keys'])) {
            $entity['recurring_invoice.user_id'] = $invoice->user ? $invoice->user->present()->name() : '';
        }


        return $entity;
    }
}

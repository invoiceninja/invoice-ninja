<?php
/**
 * PurchaseOrder Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. PurchaseOrder Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Utils\Ninja;
use League\Csv\Writer;
use App\Models\Company;
use App\Libraries\MultiDB;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\App;
use App\Export\Decorators\Decorator;
use Illuminate\Database\Eloquent\Builder;
use App\Transformers\PurchaseOrderTransformer;

class PurchaseOrderExport extends BaseExport
{
    private $purchase_order_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->purchase_order_transformer = new PurchaseOrderTransformer();
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
            $this->input['report_keys'] = array_values($this->purchase_order_report_keys);
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_vendor_fields, $this->input['report_keys']));

        $query = PurchaseOrder::query()
                        ->withTrashed()
                        ->with('vendor')
                        ->whereHas('vendor', function ($q) {
                            $q->where('is_deleted', false);
                        })
                        ->where('company_id', $this->company->id);

        if(!$this->input['include_deleted'] ?? false) { // @phpstan-ignore-line
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'purchase_orders');


        $clients = &$this->input['client_id'];

        if($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        $query = $this->addPurchaseOrderStatusFilter($query, $this->input['status'] ?? '');

        if($this->input['document_email_attachment'] ?? false) {
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

                    /** @var \App\Models\PurchaseOrder $resource */
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
            ->each(function ($purchase_order) {

                /** @var \App\Models\PurchaseOrder $purchase_order */
                $this->csv->insertOne($this->buildRow($purchase_order));
            });

        return $this->csv->toString();
    }

    private function buildRow(PurchaseOrder $purchase_order): array
    {
        $transformed_purchase_order = $this->purchase_order_transformer->transform($purchase_order);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {

            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'purchase_order' && array_key_exists($parts[1], $transformed_purchase_order)) {
                $entity[$key] = $transformed_purchase_order[$parts[1]];
            } else {
                $entity[$key] = $this->decorator->transform($key, $purchase_order);
            }


        }
        
        $entity = $this->decorateAdvancedFields($purchase_order, $entity);
        
        return $this->convertFloats($entity);

    }

    private function decorateAdvancedFields(PurchaseOrder $purchase_order, array $entity): array
    {

        if (in_array('purchase_order.currency_id', $this->input['report_keys'])) {
            $entity['purchase_order.currency_id'] = $purchase_order->vendor->currency() ? $purchase_order->vendor->currency()->code : $purchase_order->company->currency()->code;
        }

        if (in_array('purchase_order.vendor_id', $this->input['report_keys'])) {
            $entity['purchase_order.vendor_id'] = $purchase_order->vendor->present()->name();
        }

        if (in_array('purchase_order.status', $this->input['report_keys'])) {
            $entity['purchase_order.status'] = $purchase_order->stringStatus($purchase_order->status_id);
        }

        if (in_array('purchase_order.user_id', $this->input['report_keys'])) {
            $entity['purchase_order.user_id'] = $purchase_order->user ? $purchase_order->user->present()->name() : ''; // @phpstan-ignore-line

        }

        if (in_array('purchase_order.assigned_user_id', $this->input['report_keys'])) {
            $entity['purchase_order.assigned_user_id'] = $purchase_order->assigned_user ? $purchase_order->assigned_user->present()->name() : '';
        }


        return $entity;
    }
}

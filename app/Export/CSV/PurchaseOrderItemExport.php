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

use App\Export\Decorators\Decorator;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\PurchaseOrder;
use App\Transformers\PurchaseOrderTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class PurchaseOrderItemExport extends BaseExport
{
    private $purchase_order_transformer;

    public string $date_key = 'date';

    public Writer $csv;

    private Decorator $decorator;

    private bool $force_keys = false;

    private array $storage_array = [];

    private array $storage_item_array = [];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->purchase_order_transformer = new PurchaseOrderTransformer();
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
            $this->input['report_keys'] = array_values($this->mergeItemsKeys('purchase_order_report_keys'));
        }

        $this->input['report_keys'] = array_merge($this->input['report_keys'], array_diff($this->forced_vendor_fields, $this->input['report_keys']));

        $query = PurchaseOrder::query()
                        ->withTrashed()
                        ->whereHas('vendor', function ($q) {
                            $q->where('is_deleted', false);
                        })
                        ->with('vendor')->where('company_id', $this->company->id);

        if (!$this->input['include_deleted'] ?? false) {
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'purchase_orders');

        $clients = &$this->input['client_id'];

        if ($clients) {
            $query = $this->addClientFilter($query, $clients);
        }

        $query = $this->addPurchaseOrderStatusFilter($query, $this->input['status'] ?? '');

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

                  /** @var \App\Models\PurchaseOrder $resource */
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
            ->each(function ($purchase_order) {

                /** @var \App\Models\PurchaseOrder $purchase_order */
                $this->iterateItems($purchase_order);
            });

        $this->csv->insertAll($this->storage_array);

        return $this->csv->toString();

    }

    private function iterateItems(PurchaseOrder $purchase_order)
    {
        $transformed_purchase_order = $this->buildRow($purchase_order);

        $transformed_items = [];

        foreach ($purchase_order->line_items as $item) {
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

            $transformed_items = array_merge($transformed_purchase_order, $item_array);
            $entity = $this->decorateAdvancedFields($purchase_order, $transformed_items);
            $entity = array_merge(array_flip(array_values($this->input['report_keys'])), $entity);

            $this->storage_array[] = $this->convertFloats($entity);
        }

    }

    private function buildRow(PurchaseOrder $purchase_order): array
    {
        $transformed_purchase_order = $this->purchase_order_transformer->transform($purchase_order);

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'item') {
                continue;
            }

            if (is_array($parts) && $parts[0] == 'purchase_order' && array_key_exists($parts[1], $transformed_purchase_order)) {
                $entity[$key] = $transformed_purchase_order[$parts[1]];
            } elseif (array_key_exists($key, $transformed_purchase_order)) {
                $entity[$key] = $transformed_purchase_order[$key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $purchase_order);
            }
        }

        $entity = $this->decorateAdvancedFields($purchase_order, $entity);

        return $entity;

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
            $entity['purchase_order.user_id'] = $purchase_order->user ? $purchase_order->user->present()->name() : '';
        }

        if (in_array('purchase_order.assigned_user_id', $this->input['report_keys'])) {
            $entity['purchase_order.assigned_user_id'] = $purchase_order->assigned_user ? $purchase_order->assigned_user->present()->name() : '';
        }

        return $entity;
    }

}

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
use App\Models\Invoice;
use App\Models\Product;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ProductSalesExport extends BaseExport
{
    public string $date_key = 'date';

    /** @var Collection<\App\Models\Product> $products*/
    protected Collection $products;

    public Writer $csv;

    private \Illuminate\Support\Collection $sales;

    //translations => keys
    public array $entity_keys = [
        'date' => 'date',
        'product_key' => 'product_key',
        'notes' => 'notes',
        'quantity' => 'quantity',
        'currency' => 'currency',
        'cost' => 'price',
        'price' => 'cost',
        'markup' => 'markup',
        'discount' => 'discount',
        'net_total' => 'net_total',
        'profit' => 'profit',
        'line_total' => 'line_total',
        'gross_line_total' => 'gross_line_total',
        'status' => 'status',
        'tax_name1' => 'tax_name1',
        'tax_name2' => 'tax_name2',
        'tax_name3' => 'tax_name3',
        'tax_rate1' => 'tax_rate1',
        'tax_rate2' => 'tax_rate2',
        'tax_rate3' => 'tax_rate3',
        'tax_amount1' => 'tax_amount1',
        'tax_amount2' => 'tax_amount2',
        'tax_amount3' => 'tax_amount3',
        'is_amount_discount' => 'is_amount_discount',
        'currency' => 'currency',
        'client' => 'client',
        'custom_value1' => 'custom_value1',
        'custom_value2' => 'custom_value2',
        'custom_value3' => 'custom_value3',
        'custom_value4' => 'custom_value4',
    ];

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->sales = collect();
    }

    public function filterByProducts($query)
    {

        $product_keys = &$this->input['product_key'];

        if ($product_keys && !empty($this->input['product_key'])) {

            $keys = explode(",", $product_keys);
            $query->where(function ($q) use ($keys) {

                foreach ($keys as $key) {
                    $q->orWhereJsonContains('line_items', ['product_key' => $key]);
                }

            });

        }

        return $query;
    }


    public function run()
    {
        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->products = Product::query()->where('company_id', $this->company->id)->withTrashed()->get();

        //load the CSV document from a string
        $this->csv = Writer::createFromString();
        \League\Csv\CharsetConverter::addTo($this->csv, 'UTF-8', 'UTF-8');

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->entity_keys);
        }

        //insert the header
        $query = Invoice::query()
                        ->withTrashed()
                        ->whereHas('client', function ($q) {
                            $q->where('is_deleted', false);
                        })
                        ->where('company_id', $this->company->id)
                        ->where('is_deleted', 0)
                        ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID]);

        $query = $this->addDateRange($query, 'invoices');

        $query = $this->filterByClients($query);

        $query = $this->filterByProducts($query);

        $this->csv->insertOne($this->buildHeader());

        $product_keys = &$this->input['product_key'];

        if ($product_keys) {
            $product_keys = explode(",", $product_keys);
        }

        $query->cursor()
              ->each(function ($invoice) use ($product_keys) {
                  foreach ($invoice->line_items as $item) {

                      if ($product_keys) {
                          if (in_array($item->product_key, $product_keys)) {
                              $this->csv->insertOne($this->convertFloats($this->buildRow($invoice, $item)));
                          }
                      } else {
                          $this->csv->insertOne($this->convertFloats($this->buildRow($invoice, $item)));
                      }

                  }
              });


        $grouped = $this->sales->groupBy('product_key')->map(function ($key, $value) use ($product_keys) {

            if ($product_keys && !in_array($value, $product_keys)) {
                return false;
            }

            $data =  [
                'product' => $value,
                'quantity' => $key->sum('quantity'),
                'markup' => $key->sum('markup'),
                'profit' => $key->sum('profit'),
                'net_total' => $key->sum('net_total'),
                'discount' => $key->sum('discount'),
                'line_total' => $key->sum('line_total'),
                'tax_name1' => $key->whereNotNull('tax_name1')->where('tax_name1', '!=', '')->first() ? $key->whereNotNull('tax_name1')->where('tax_name1', '!=', '')->first()['tax_name1'] : '',
                'tax_name2' => $key->whereNotNull('tax_name2')->where('tax_name2', '!=', '')->first() ? $key->whereNotNull('tax_name2')->where('tax_name2', '!=', '')->first()['tax_name2'] : '',
                'tax_name3' => $key->whereNotNull('tax_name3')->where('tax_name3', '!=', '')->first() ? $key->whereNotNull('tax_name3')->where('tax_name3', '!=', '')->first()['tax_name3'] : '',
                'tax_amount1' => $key->sum('tax_amount1'),
                'tax_amount2' => $key->sum('tax_amount2'),
                'tax_amount3' => $key->sum('tax_amount3'),
            ];

            return $this->convertFloats($data);

        })->reject(function ($value) {
            return $value === false;
        });
        ;

        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([]);
        $this->csv->insertOne([ctrans('texts.clients'), ctrans('texts.type'), ctrans('texts.start_date'), ctrans('texts.end_date')]);
        $this->csv->insertOne([$this->client_description, ctrans('texts.product_sales'), $this->start_date, $this->end_date]);
        $this->csv->insertOne([]);


        if ($grouped->count() >= 1) {
            $header = [];

            foreach ($grouped->first() as $key => $value) {
                $header[] = ctrans("texts.{$key}");
            }

            $this->csv->insertOne($header);

            $grouped->each(function ($item) {
                $this->csv->insertOne(array_values($item));
            });
        }
        return $this->csv->toString();
    }

    private function buildRow($invoice, $invoice_item): array
    {
        $transformed_entity = (array)$invoice_item;
        $transformed_entity['price'] = ($invoice_item->product_cost ?? 1) * ($invoice->exchange_rate ?? 1) ;

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $keyval = array_search($key, $this->entity_keys);

            if (array_key_exists($key, $transformed_entity)) {
                $entity[$keyval] = $transformed_entity[$key];
            } elseif ($key == 'currency') {
                $entity['currency'] = $invoice->client->currency()->code;
            } else {
                $entity[$keyval] = '';
            }
        }

        $entity = $this->decorateAdvancedFields($invoice, $entity);

        $this->sales->push($entity);

        return $entity;
    }

    private function decorateAdvancedFields(Invoice $invoice, $entity): array
    {

        /** @var float $unit_cost */
        $unit_cost = $entity['cost'] == 0 ? 1 : $entity['cost'];

        $entity['client'] = $invoice->client->present()->name();
        $entity['currency'] = $invoice->client->currency()->code;
        $entity['status'] = $invoice->stringStatus($invoice->status_id);
        $entity['date'] = Carbon::parse($invoice->date)->format($this->company->date_format());

        $entity['discount'] = $this->calculateDiscount($invoice, $entity);
        $entity['markup'] = round(((($entity['price'] - $entity['discount'] - $entity['cost']) / $unit_cost) * 100), 2);

        $entity['net_total'] = $entity['price'] - $entity['discount'];
        $entity['profit'] = $entity['price'] - $entity['discount'] - $entity['cost'];

        if (strlen($entity['tax_name1']) > 1) {
            $entity['tax_name1'] = $entity['tax_name1'] . ' [' . $entity['tax_rate1'] . '%]';
            $entity['tax_amount1'] = $this->calculateTax($invoice, $entity['line_total'], $entity['tax_rate1']);
        } else {
            $entity['tax_amount1'] = 0;
        }

        if (strlen($entity['tax_name2']) > 1) {
            $entity['tax_name2'] = $entity['tax_name2'] . ' [' . $entity['tax_rate2'] . '%]';
            $entity['tax_amount2'] = $this->calculateTax($invoice, $entity['line_total'], $entity['tax_rate2']);
        } else {
            $entity['tax_amount2'] = 0;
        }

        if (strlen($entity['tax_name3']) > 1) {
            $entity['tax_name3'] = $entity['tax_name3'] . ' [' . $entity['tax_rate3'] . '%]';
            $entity['tax_amount3'] = $this->calculateTax($invoice, $entity['line_total'], $entity['tax_rate3']);
        } else {
            $entity['tax_amount3'] = 0;
        }

        return $entity;
    }

    /**
     * calculateTax
     *
     * @param  Invoice $invoice
     * @param  float $amount
     * @param  float $tax_rate
     * @return float
     */
    private function calculateTax(Invoice $invoice, float $amount, float $tax_rate): float
    {
        $amount = $amount - ($amount * ($invoice->discount / 100));

        if ($invoice->uses_inclusive_taxes) {
            return round($amount - ($amount / (1 + ($tax_rate / 100))), 2);
        } else {
            return round(($amount * $tax_rate / 100), 2);
        }
    }



    /**
     * calculateDiscount
     *
     * @param  Invoice $invoice
     * @param  mixed $entity
     * @return float
     */
    private function calculateDiscount(Invoice $invoice, $entity): float
    {
        if ($entity['discount'] == 0) {
            return 0;
        }

        if ($invoice->is_amount_discount && $entity['discount'] != 0) {
            return $entity['discount'];
        } elseif (!$invoice->is_amount_discount && $entity['discount'] != 0) {
            return round($entity['line_total'] * ($entity['discount'] / 100), 2);
        }

        return 0;
    }

    /**
     * getProduct
     *
     * @param  string $product_key
     * @return ?\Illuminate\Database\Eloquent\Model
     */
    // private function getProduct(string $product_key)
    // {
    //     return $this->products->firstWhere('product_key', $product_key);
    // }
}

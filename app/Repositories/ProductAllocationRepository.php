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

namespace App\Repositories;

use App\Factory\ProductAllocationFactory;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductAllocation;
use App\Models\Project;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Support\Carbon;

class ProductAllocationRepository extends BaseRepository
{
    use SavesDocuments;

    /**
     * @param array $data
     * @param ProductAllocation $productAllocation
     * @return ProductAllocation|null
     */
    public function create(string $company_id, string $user_id, array $data): ?ProductAllocation
    {
        $data = $this->prepareData($data);

        /** @var ProductAllocation $productAllocation */
        $productAllocation;

        // aggregation of entries for products, which are likly not equipments and not assigned to an invoice
        if (!empty($data['invoice_aggregation_key']) && empty($data['invoice_id']) && empty($data['serial_number'])) {

            // find unique entry
            $query = ProductAllocation::where('invoice_aggregation_key', $data['invoice_aggregation_key'])
                ->where('company_id', $company_id)
                ->where('user_id', $user_id)
                ->where('product_id', $data['product_id'])
                ->where('client_id', $data['client_id'] ?? null)
                ->where('project_id', $data['project_id'] ?? null)
                ->where('invoice_id', null)
                ->where('recurring_id', $data['recurring_id'] ?? null)
                ->where('subscription_id', $data['subscription_id'] ?? null);

            // custom queries for aggregationKey useable with custom time frames
            if (!empty($data['aggregation_intervall'])) {

                if ($data['aggregation_intervall'] === 'hourly') {
                    $query = $query->where('created_at', '>=', Carbon::now()->subHour());
                } else if ($data['aggregation_intervall'] === 'daily') {
                    $query = $query->where('created_at', '>=', Carbon::now()->subDay());
                } else if ($data['aggregation_intervall'] === 'weekly') {
                    $query = $query->whereBetween('created_at', [
                        Carbon::now()->startOfWeek(Carbon::MONDAY),
                        Carbon::now()->endOfWeek(Carbon::SUNDAY)
                    ]);
                } else if ($data['aggregation_intervall'] === 'monthly') {
                    $query = $query->whereYear('created_at', '=', Carbon::now()->year)
                        ->whereMonth('created_at', '=', Carbon::now()->month);
                } else if ($data['aggregation_intervall'] === 'yearly') {
                    $query = $query->whereYear('created_at', '=', Carbon::now()->year);
                } else if (Carbon::hasFormat(Carbon::now(), $data['invoice_aggregation_key'])) {                 // For unsupported or custom time frame formats
                    $query = $query->whereDate('created_at', '>', Carbon::now()->format($data['invoice_aggregation_key']));
                }
            }

            // fetch data
            $productAllocation = $query->orderBy('created_at', 'desc')->first();

            // checks and modifications to input data
            if (!empty($productAllocation)) {
                // check if its even allowed to aggregate this data
                if (!empty($productAllocation->invoice_id) && $productAllocation->invoice()->status != Invoice::STATUS_DRAFT)
                    throw new \Exception('Invoice already sent. Cannot aggregate.');

                // increment quantity to allow aggregating from original input
                if (!empty($data['quantity']))
                    $data['quantity'] += $productAllocation->quantity;
            }
        }

        // create new entry, when we were not able to fetch an existing one
        if (empty($productAllocation))
            $productAllocation = ProductAllocationFactory::create($company_id, $user_id, $data['product_id']);

        $productAllocation->fill($data);
        $productAllocation->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $productAllocation);
        }

        return $productAllocation;
    }

    /**
     * @param array $data
     * @param ProductAllocation $productAllocation
     * @return ProductAllocation|null
     */
    public function save(array $data, ProductAllocation $productAllocation): ?ProductAllocation
    {
        $data = $this->prepareData($data);

        $productAllocation->fill($data);
        $productAllocation->save();

        if (array_key_exists('documents', $data)) {
            $this->saveDocuments($data['documents'], $productAllocation);
        }

        return $productAllocation;
    }

    private function prepareData(array $data): array
    {
        $data['quantity'] ??= 1;
        $data['invoice_aggregation_key'] ??= null;
        $data['aggregation_intervall'] ??= null;
        $data['invoice_id'] ??= null;

        if (!array_key_exists('product_key', $data))
            throw new \Exception('Missing Product key');

        if (array_key_exists('product_key', $data) && is_string($data['product_key'])) {
            $data['product_id'] = Product::where('product_key', $data['product_key'])->first()->id;
        }

        if (array_key_exists('subscription_id', $data) && is_string($data['subscription_id'])) {
            $subscription = Subscription::where('id', $data['subscription_id'])->first();
            if (empty($subscription))
                throw new \Exception('Subscription not found.');

            if (!empty($subscription->client_id) && $subscription->client_id != $data['client_id'])
                throw new \Exception('Subscription does not belong to client.');
            if (!empty($subscription->project_id) && $subscription->project_id != $data['project_id'])
                throw new \Exception('Subscription does not belong to project.');
            if (!empty($subscription->recurring_id) && $subscription->recurring_id != $data['recurring_id'])
                throw new \Exception('Subscription does not belong to project.');
            $data['client_id'] = $subscription->client_id;
            $data['project_id'] = $subscription->project_id;
            $data['recurring_id'] = $subscription->recurring_id;
        } else if (array_key_exists('recurring_id', $data) && is_string($data['recurring_id'])) {
            $recurring = RecurringInvoice::where('id', $data['recurring_id'])->first();
            if (empty($recurring))
                throw new \Exception('RecurringInvoice not found.');

            if (!empty($recurring->client_id) && $recurring->client_id != $data['client_id'])
                throw new \Exception('RecurringInvoice does not belong to client.');
            if (!empty($recurring->project_id) && $recurring->project_id != $data['project_id'])
                throw new \Exception('RecurringInvoice does not belong to project.');
            $data['client_id'] = $recurring->client_id;
            $data['project_id'] = $recurring->project_id;
        } else if (array_key_exists('project_id', $data) && is_string($data['project_id'])) {
            $project = Project::where('id', $data['project_id'])->first();
            if (empty($project))
                throw new \Exception('Project not found.');

            if (!empty($project->client_id) && $project->client_id != $data['client_id'])
                throw new \Exception('Project does not belong to client.');
            $data['client_id'] = $project->client_id;
        }

        if (array_key_exists('invoice_id', $data) && is_string($data['invoice_id'])) {
            $invoice = Invoice::where('id', $data['invoice_id'])->first();
            if (empty($invoice))
                throw new \Exception('Invoice not found.');

            if (!empty($invoice->client_id) && $invoice->client_id != $data['client_id'])
                throw new \Exception('Invoice does not belong to client.');
            if (!empty($invoice->project_id) && $invoice->project_id != $data['project_id'])
                throw new \Exception('Invoice does not belong to project.');
            $data['client_id'] = $invoice->client_id;
            $data['project_id'] = $invoice->project_id;
        }

        return $data;
    }
}

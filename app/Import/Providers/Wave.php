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

namespace App\Import\Providers;

use App\Factory\ClientFactory;
use App\Factory\ExpenseFactory;
use App\Factory\InvoiceFactory;
use App\Factory\VendorFactory;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Vendor\StoreVendorRequest;
use App\Import\ImportException;
use App\Import\Transformer\Wave\ClientTransformer;
use App\Import\Transformer\Wave\ExpenseTransformer;
use App\Import\Transformer\Wave\InvoiceTransformer;
use App\Import\Transformer\Wave\VendorTransformer;
use App\Models\Client;
use App\Repositories\ClientRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\VendorRepository;
use Illuminate\Support\Facades\Validator;

class Wave extends BaseImport implements ImportInterface
{
    public array $entity_count = [];

    public function import(string $entity)
    {
        if (
            in_array($entity, [
                'client',
                'invoice',
                // 'product',
                // 'payment',
                'vendor',
                'expense',
            ])
        ) {
            $this->{$entity}();
        }

        //collate any errors

        // $this->finalizeImport();
    }

    public function client()
    {
        $entity_type = 'client';

        $data = $this->getCsvData($entity_type);

        $data = $this->preTransform($data, $entity_type);

        if (empty($data)) {
            $this->entity_count['clients'] = 0;
            return;
        }

        $this->request_name = StoreClientRequest::class;
        $this->repository_name = ClientRepository::class;
        $this->factory_name = ClientFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new ClientTransformer($this->company);

        $client_count = $this->ingest($data, $entity_type);

        $this->entity_count['clients'] = $client_count;
    }

    public function product()
    {
        //done automatically inside the invoice() method as we need to harvest the products from the line items
    }

    public function invoice()
    {
        //make sure we update and create products with wave
        $initial_update_products_value = $this->company->update_products;
        $this->company->update_products = true;

        $this->company->save();

        $entity_type = 'invoice';

        $data = $this->getCsvData($entity_type);

        $data = $this->preTransform($data, $entity_type);

        if (empty($data)) {
            $this->entity_count['invoices'] = 0;

            return;
        }

        $this->request_name = StoreInvoiceRequest::class;
        $this->repository_name = InvoiceRepository::class;
        $this->factory_name = InvoiceFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new InvoiceTransformer($this->company);

        foreach ($data as $key => $invoice) {
            if (!isset($invoice['Invoice Number']) || empty($invoice['Invoice Number'])) {
                unset($data[$key]);
            }
        }

        $invoice_count = $this->ingestInvoices($data, 'Invoice Number');

        $this->entity_count['invoices'] = $invoice_count;

        $this->company->update_products = $initial_update_products_value;
        $this->company->save();
    }

    public function payment()
    {
        //these are pulled in when processing invoices
    }

    public function vendor()
    {
        $entity_type = 'vendor';

        $data = $this->getCsvData($entity_type);

        if (! is_array($data)) {
            return;
        }

        $data = $this->preTransform($data, $entity_type);

        if (empty($data)) {
            $this->entity_count['vendors'] = 0;

            return;
        }

        $this->request_name = StoreVendorRequest::class;
        $this->repository_name = VendorRepository::class;
        $this->factory_name = VendorFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new VendorTransformer($this->company);

        $vendor_count = $this->ingest($data, $entity_type);

        $this->entity_count['vendors'] = $vendor_count;
    }

    public function expense()
    {
        $entity_type = 'expense';

        $data = $this->getCsvData('invoice');

        if (!$data) {
            $this->entity_count['expense'] = 0;
            return;
        }

        $data = $this->preTransform($data, $entity_type);

        if (empty($data)) {
            $this->entity_count['expense'] = 0;
            return;
        }

        $this->request_name = StoreExpenseRequest::class;
        $this->repository_name = ExpenseRepository::class;
        $this->factory_name = ExpenseFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new ExpenseTransformer($this->company);

        $expense_count = $this->ingestExpenses($data);

        $this->entity_count['expenses'] = $expense_count;
    }

    public function transform(array $data)
    {
    }

    private function groupExpenses($csvData)
    {
        $grouped = [];
        $key = 'Transaction ID';

        foreach ($csvData as $expense) {
            if ($expense['Account Group'] == 'Expense') {
                $grouped[$expense[$key]][] = $expense;
            }
        }

        return $grouped;
    }

    public function ingestExpenses($data)
    {
        $count = 0;

        $key = 'Transaction ID';

        $expense_transformer = $this->transformer;

        $vendor_repository = app()->make(VendorRepository::class);
        $expense_repository = app()->make(ExpenseRepository::class);

        $expenses = $this->groupExpenses($data);

        foreach ($expenses as $raw_expense) {

            if (!is_array($raw_expense)) {
                continue;
            }

            try {
                $expense_data = $expense_transformer->transform($raw_expense);

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (empty($expense_data['vendor_id'])) {
                    $vendor_data['user_id'] = $this->getUserIDForRecord($expense_data);

                    if (isset($raw_expense['Vendor Name']) || isset($raw_expense['Vendor'])) {
                        $vendor_repository->save(
                            ['name' => isset($raw_expense['Vendor Name']) ? $raw_expense['Vendor Name'] : isset($raw_expense['Vendor'])],
                            $vendor = VendorFactory::create(
                                $this->company->id,
                                $vendor_data['user_id']
                            )
                        );
                        $expense_data['vendor_id'] = $vendor->id;
                    }
                }

                $validator = Validator::make(
                    $expense_data,
                    (new StoreExpenseRequest())->rules()
                );
                if ($validator->fails()) {
                    $this->error_array['expense'][] = [
                        'expense' => $expense_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $expense = ExpenseFactory::create(
                        $this->company->id,
                        $this->getUserIDForRecord($expense_data)
                    );

                    $expense_repository->save($expense_data, $expense);
                    $count++;
                }
            } catch (\Exception $ex) {
                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error';
                }

                $this->error_array['expense'][] = [
                    'expense' => $raw_expense,
                    'error' => $message,
                ];
            }
        }

        return $count;
    }
}

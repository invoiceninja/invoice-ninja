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

use App\Factory\BankTransactionFactory;
use App\Factory\ClientFactory;
use App\Factory\ExpenseFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Factory\QuoteFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\TaskFactory;
use App\Factory\VendorFactory;
use App\Http\Requests\BankTransaction\StoreBankTransactionRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Expense\StoreExpenseRequest;
use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Product\StoreProductRequest;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Http\Requests\RecurringInvoice\StoreRecurringInvoiceRequest;
use App\Http\Requests\Task\StoreTaskRequest;
use App\Http\Requests\Vendor\StoreVendorRequest;
use App\Import\Transformer\Bank\BankTransformer;
use App\Import\Transformer\Csv\ClientTransformer;
use App\Import\Transformer\Csv\ExpenseTransformer;
use App\Import\Transformer\Csv\InvoiceTransformer;
use App\Import\Transformer\Csv\PaymentTransformer;
use App\Import\Transformer\Csv\ProductTransformer;
use App\Import\Transformer\Csv\QuoteTransformer;
use App\Import\Transformer\Csv\RecurringInvoiceTransformer;
use App\Import\Transformer\Csv\TaskTransformer;
use App\Import\Transformer\Csv\VendorTransformer;
use App\Repositories\BankTransactionRepository;
use App\Repositories\ClientRepository;
use App\Repositories\ExpenseRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\ProductRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\TaskRepository;
use App\Repositories\VendorRepository;
use App\Services\Bank\BankMatchingService;
use App\Utils\Traits\MakesHash;

class Csv extends BaseImport implements ImportInterface
{
    use MakesHash;

    public array $entity_count = [];

    public function import(string $entity)
    {
        if (
            in_array($entity, [
                'client',
                'product',
                'invoice',
                'payment',
                'vendor',
                'expense',
                'quote',
                'bank_transaction',
                'recurring_invoice',
                'task',
            ])
        ) {
            $this->{$entity}();
        }
    }

    public function bank_transaction()
    {
        $entity_type = 'bank_transaction';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);

            foreach ($data as $key => $value) {
                $data[$key]['transaction.bank_integration_id'] = $this->decodePrimaryKey($this->request['bank_integration_id']);
            }
        }

        if (empty($data)) {
            $this->entity_count['bank_transactions'] = 0;
            return;
        }

        $this->request_name = StoreBankTransactionRequest::class;
        $this->repository_name = BankTransactionRepository::class;
        $this->factory_name = BankTransactionFactory::class;

        $this->repository = app()->make($this->repository_name);

        $this->transformer = new BankTransformer($this->company);
        $bank_transaction_count = $this->ingest($data, $entity_type);
        $this->entity_count['bank_transactions'] = $bank_transaction_count;

        nlog("bank matching co id = {$this->company->id}");

        (new BankMatchingService($this->company->id, $this->company->db))->handle();
    }

    public function client()
    {
        $entity_type = 'client';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

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

        $data = $this->groupClients($data, 'client.name');

        $client_count = $this->ingest($data, $entity_type);

        $this->entity_count['clients'] = $client_count;
    }

    public function product()
    {
        $entity_type = 'product';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

        if (empty($data)) {
            $this->entity_count['products'] = 0;

            return;
        }

        $this->request_name = StoreProductRequest::class;
        $this->repository_name = ProductRepository::class;
        $this->factory_name = ProductFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new ProductTransformer($this->company);

        $product_count = $this->ingestProducts($data, $entity_type);

        $this->entity_count['products'] = $product_count;
    }

    public function recurring_invoice()
    {
        $entity_type = 'recurring_invoice';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

        if (empty($data)) {
            $this->entity_count['recurring_invoices'] = 0;
            return;
        }

        $this->request_name = StoreRecurringInvoiceRequest::class;
        $this->repository_name = RecurringInvoiceRepository::class;
        $this->factory_name = RecurringInvoiceFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new RecurringInvoiceTransformer($this->company);

        $invoice_count = $this->ingestRecurringInvoices($data, 'invoice.number');

        $this->entity_count['recurring_invoices'] = $invoice_count;
    }

    public function invoice()
    {
        $entity_type = 'invoice';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

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

        $invoice_count = $this->ingestInvoices($data, 'invoice.number');

        $this->entity_count['invoices'] = $invoice_count;
    }

    public function quote()
    {
        $entity_type = 'quote';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

        if (empty($data)) {
            $this->entity_count['quotes'] = 0;
            return;
        }

        $this->request_name = StoreQuoteRequest::class;
        $this->repository_name = QuoteRepository::class;
        $this->factory_name = QuoteFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new QuoteTransformer($this->company);

        $quote_count = $this->ingestQuotes($data, 'quote.number');

        $this->entity_count['quotes'] = $quote_count;
    }

    public function payment()
    {
        $entity_type = 'payment';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

        if (empty($data)) {
            $this->entity_count['payments'] = 0;

            return;
        }

        $this->request_name = StorePaymentRequest::class;
        $this->repository_name = PaymentRepository::class;
        $this->factory_name = PaymentFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new PaymentTransformer($this->company);

        $payment_count = $this->ingest($data, $entity_type);

        $this->entity_count['payments'] = $payment_count;
    }

    public function vendor()
    {
        $entity_type = 'vendor';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

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

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

        if (empty($data)) {
            $this->entity_count['expenses'] = 0;

            return;
        }

        $this->request_name = StoreExpenseRequest::class;
        $this->repository_name = ExpenseRepository::class;
        $this->factory_name = ExpenseFactory::class;

        $this->repository = app()->make($this->repository_name);
        $this->repository->import_mode = true;

        $this->transformer = new ExpenseTransformer($this->company);

        $expense_count = $this->ingest($data, $entity_type);

        $this->entity_count['expenses'] = $expense_count;
    }

    public function task()
    {
        $entity_type = 'task';

        $data = $this->getCsvData($entity_type);

        if (is_array($data)) {
            $data = $this->preTransformCsv($data, $entity_type);
        }

        if (empty($data)) {
            $this->entity_count['tasks'] = 0;
            return;
        }

        $this->request_name = StoreTaskRequest::class;
        $this->repository_name = TaskRepository::class;
        $this->factory_name = TaskFactory::class;

        $this->repository = app()->make($this->repository_name);
        // $this->repository->import_mode = true;

        $this->transformer = new TaskTransformer($this->company);

        $task_count = $this->ingestTasks($data, 'task.number');

        $this->entity_count['tasks'] = $task_count;


    }

    public function transform(array $data)
    {
    }
}

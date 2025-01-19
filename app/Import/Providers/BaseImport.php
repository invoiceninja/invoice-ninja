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
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\QuoteFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\TaskFactory;
use App\Http\Requests\Quote\StoreQuoteRequest;
use App\Import\ImportException;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Import\CsvImportCompleted;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\User;
use App\Repositories\ClientRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\QuoteRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\TaskRepository;
use App\Utils\Traits\CleanLineItems;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use League\Csv\Reader;
use League\Csv\Statement;

class BaseImport
{
    use CleanLineItems;

    public Company $company;

    public array $request;

    public array $error_array = [];

    public $request_name;

    public $repository_name;

    public $factory_name;

    public $repository;

    public $transformer;

    public ?array $column_map = [];

    public ?string $hash;

    public ?string $import_type;

    public ?bool $skip_header;

    public array $entity_count = [];

    public function __construct(array $request, Company $company)
    {
        $this->company = $company;
        $this->request = $request;
        $this->hash = $request['hash'];
        $this->import_type = $request['import_type'];
        $this->skip_header = $request['skip_header'] ?? null;
        $this->column_map = ! empty($request['column_map'])
            ? array_combine(
                array_keys($request['column_map']),
                array_column($request['column_map'], 'mapping')
            )
            : null;

        auth()->login($this->company->owner(), true);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $user->setCompany($this->company);
    }

    public function getCsvData($entity_type)
    {
        if (! ini_get('auto_detect_line_endings')) {
            ini_set('auto_detect_line_endings', '1');
        }

        /** @var string $base64_encoded_csv */
        $base64_encoded_csv = Cache::get($this->hash.'-'.$entity_type);

        if (empty($base64_encoded_csv)) {
            return null;
        }

        nlog("found {$entity_type}");

        $csv = base64_decode($base64_encoded_csv);
        $csv = mb_convert_encoding($csv, 'UTF-8', 'UTF-8');

        $csv = Reader::createFromString($csv);
        $csvdelimiter = self::detectDelimiter($csv);

        $csv->setDelimiter($csvdelimiter);
        $stmt = new Statement();
        $data = iterator_to_array($stmt->process($csv));

        if (count($data) > 0) {
            $headers = $data[0];

            // Remove Invoice Ninja headers
            if (
                count($headers) &&
                count($data) > 4 &&
                $this->import_type === 'csv'
            ) {
                $first_cell = $headers[0];
                if (strstr($first_cell, config('ninja.app_name'))) {
                    array_shift($data); // Invoice Ninja...
                    array_shift($data); // <blank line>
                    array_shift($data); // Enitty Type Header
                }
            }
        }

        return $data;
    }

    public function detectDelimiter($csvfile)
    {
        $delimiters = [',', '.', ';', '|'];
        $bestDelimiter = ',';
        $count = 0;

        // 10-01-2024 - A better way to resolve the csv file delimiter.
        $csvfile = substr($csvfile, 0, strpos($csvfile, "\n"));

        foreach ($delimiters as $delimiter) {

            if (substr_count(strstr($csvfile, "\n", true), $delimiter) >= $count) {
                $count = substr_count($csvfile, $delimiter);
                $bestDelimiter = $delimiter;
            }

        }

        /** @phpstan-ignore-next-line **/
        return $bestDelimiter ?? ',';
    }

    public function mapCSVHeaderToKeys($csvData)
    {
        $keys = array_shift($csvData);

        return array_map(function ($values) use ($keys) {
            return array_combine($keys, $values);
        }, $csvData);
    }

    private function groupTasks($csvData, $key)
    {

        if (! $key || !is_array($csvData) || count($csvData) == 0 || !isset($csvData[0]['task.number']) || empty($csvData[0]['task.number'])) {
            return $csvData;
        }

        // Group by tasks.
        $grouped = [];

        foreach ($csvData as $item) {
            if (empty($item[$key])) {
                $this->error_array['task'][] = [
                    'task' => $item,
                    'error' => 'No task number',
                ];
            } else {
                $grouped[$item[$key]][] = $item;
            }
        }

        return $grouped;


    }

    public function groupClients($csvData, $key)
    {
        if (!$key || !isset($csvData[0][$key])) {
            return $csvData;
        }

        $grouped = [];

        // Group by client name / id.
        $grouped = [];

        foreach ($csvData as $contact_item) {
            if (empty($contact_item[$key])) {
                $this->error_array['client'][] = [
                    'client' => $contact_item,
                    'error' => 'No client identifier',
                ];
            } else {
                $grouped[$contact_item[$key]][] = $contact_item;
            }
        }

        return $grouped;


    }

    private function groupInvoices($csvData, $key)
    {
        if (! $key) {
            return $csvData;
        }

        // Group by invoice.
        $grouped = [];

        foreach ($csvData as $line_item) {
            if (empty($line_item[$key])) {
                $this->error_array['invoice'][] = [
                    'invoice' => $line_item,
                    'error' => 'No invoice number',
                ];
            } else {
                $grouped[$line_item[$key]][] = $line_item;
            }
        }

        return $grouped;
    }

    public function getErrors()
    {
        return $this->error_array;
    }


    private function runValidation($data)
    {
        $_syn_request_class = new $this->request_name();
        $_syn_request_class->setContainer(app());
        $_syn_request_class->initialize($data);
        $_syn_request_class->prepareForValidation();

        $validator = Validator::make($_syn_request_class->all(), $_syn_request_class->rules());

        $_syn_request_class->setValidator($validator);

        return $validator;
    }

    public function ingest($data, $entity_type)
    {
        $count = 0;

        $is_free_hosted_client = $this->company->account->isFreeHostedClient();
        $hosted_client_count = $this->company->account->hosted_client_count;

        if ($this->factory_name == 'App\Factory\ClientFactory' && $is_free_hosted_client && (count($data) > $hosted_client_count)) {
            $this->error_array[$entity_type][] = [
                $entity_type => 'client',
                'error' => 'Error, you are attempting to import more clients than your plan allows',
            ];

            return $count;
        }

        foreach ($data as $key => $record) {

            unset($record['']);

            if (!is_array($record)) {
                continue;
            }

            try {
                $entity = $this->transformer->transform($record);

                if (!$entity) {
                    continue;
                }

                $validator = $this->runValidation($entity);

                if ($validator->fails()) {
                    $this->error_array[$entity_type][] = [
                        $entity_type => $record,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $entity = $this->repository->save(
                        array_diff_key($entity, ['user_id' => false]),
                        $this->factory_name::create(
                            $this->company->id,
                            $this->getUserIDForRecord($entity)
                        )
                    );
                    $entity->saveQuietly();
                    $count++;
                }
            } catch (\Exception $ex) {
                if (\DB::connection(config('database.default'))->transactionLevel() > 0) {
                    \DB::connection(config('database.default'))->rollBack();
                }

                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error';
                }

                $this->error_array[$entity_type][] = [
                    $entity_type => $record,
                    'error' => $message,
                ];

                nlog("Ingest {$ex->getMessage()}");
                nlog($record);
            }
        }

        return $count;
    }

    public function ingestProducts($data, $entity_type)
    {
        $count = 0;

        foreach ($data as $key => $record) {

            if (!is_array($record)) {
                continue;
            }

            try {
                $entity = $this->transformer->transform($record);
                $validator = $this->request_name::runFormRequest($entity);

                if ($validator->fails()) {
                    $this->error_array[$entity_type][] = [
                        $entity_type => $record,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    if ($this->transformer->hasProduct($entity['product_key'])) {
                        $product = $this->transformer->getProduct($entity['product_key']);
                    } else {
                        $product = $this->factory_name::create($this->company->id, $this->getUserIDForRecord($entity));
                    }

                    $entity = $this->repository->save(
                        array_diff_key($entity, ['user_id' => false]),
                        $product
                    );

                    $entity->saveQuietly();
                    $count++;
                }
            } catch (\Exception $ex) {
                if (\DB::connection(config('database.default'))->transactionLevel() > 0) {
                    \DB::connection(config('database.default'))->rollBack();
                }

                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error';
                }

                $this->error_array[$entity_type][] = [
                    $entity_type => $record,
                    'error' => $message,
                ];
            }
        }

        return $count;
    }

    public function ingestRecurringInvoices($invoices, $invoice_number_key)
    {
        $count = 0;

        $invoice_transformer = $this->transformer;

        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $invoice_repository = new RecurringInvoiceRepository();
        $invoice_repository->import_mode = true;

        $invoices = $this->groupInvoices($invoices, $invoice_number_key);

        foreach ($invoices as $raw_invoice) {

            if (!is_array($raw_invoice)) {
                continue;
            }

            try {
                $invoice_data = $invoice_transformer->transform($raw_invoice);
                $invoice_data['user_id'] = $this->company->owner()->id;

                $invoice_data['line_items'] = $this->cleanItems(
                    $invoice_data['line_items'] ?? []
                );

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (
                    empty($invoice_data['client_id']) &&
                    ! empty($invoice_data['client'])
                ) {
                    $client_data = $invoice_data['client'];
                    $client_data['user_id'] = $this->getUserIDForRecord(
                        $invoice_data
                    );

                    $client_repository->save(
                        $client_data,
                        $client = ClientFactory::create(
                            $this->company->id,
                            $client_data['user_id']
                        )
                    );
                    $invoice_data['client_id'] = $client->id;
                    unset($invoice_data['client']);
                }

                $validator = $this->request_name::runFormRequest($invoice_data);

                if ($validator->fails()) {
                    $this->error_array['invoice'][] = [
                        'invoice' => $invoice_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $invoice = RecurringInvoiceFactory::create(
                        $this->company->id,
                        $this->getUserIDForRecord($invoice_data)
                    );
                    if (! empty($invoice_data['status_id'])) {
                        $invoice->status_id = $invoice_data['status_id'];
                    }
                    $invoice_repository->save($invoice_data, $invoice);

                    $count++;
                    // If we're doing a generic CSV import, only import payment data if we're not importing a payment CSV.
                    // If we're doing a platform-specific import, trust the platform to only return payment info if there's not a separate payment CSV.


                }
            } catch (\Exception $ex) {
                if (\DB::connection(config('database.default'))->transactionLevel() > 0) {
                    \DB::connection(config('database.default'))->rollBack();
                }

                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error ';
                    nlog($ex->getMessage());
                    nlog($invoice_data);
                }

                $this->error_array['recurring_invoice'][] = [
                    'recurring_invoice' => $raw_invoice,
                    'error' => $message,
                ];
            }
        }

        return $count;
    }

    public function ingestTasks($tasks, $task_number_key)
    {
        $count = 0;

        $task_transformer = $this->transformer;

        $task_repository = new TaskRepository();

        $tasks = $this->groupTasks($tasks, $task_number_key);

        foreach ($tasks as $raw_task) {
            $task_data = [];

            if (!is_array($raw_task)) {
                continue;
            }

            try {
                $task_data = $task_transformer->transform($raw_task);
                $task_data['user_id'] = $this->company->owner()->id;

                $validator = $this->request_name::runFormRequest($task_data);

                if ($validator->fails()) {
                    $this->error_array['task'][] = [
                        'invoice' => $task_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $task = TaskFactory::create(
                        $this->company->id,
                        $this->company->owner()->id
                    );

                    $task_repository->save($task_data, $task);

                    $count++;

                }
            } catch (\Exception $ex) {
                if (\DB::connection(config('database.default'))->transactionLevel() > 0) {
                    \DB::connection(config('database.default'))->rollBack();
                }

                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error ';
                    nlog($ex->getMessage());
                    nlog($task_data);
                }

                $this->error_array['task'][] = [
                    'task' => $task_data,
                    'error' => $message,
                ];
            }
        }

        return $count;
    }



    public function ingestInvoices($invoices, $invoice_number_key)
    {
        $count = 0;

        $invoice_transformer = $this->transformer;

        /** @var PaymentRepository $payment_repository */
        $payment_repository = app()->make(PaymentRepository::class);
        $payment_repository->import_mode = true;

        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $invoice_repository = new InvoiceRepository();
        $invoice_repository->import_mode = true;

        $invoices = $this->groupInvoices($invoices, $invoice_number_key);

        foreach ($invoices as $raw_invoice) {

            if (!is_array($raw_invoice)) {
                continue;
            }

            try {
                $invoice_data = $invoice_transformer->transform($raw_invoice);
                $invoice_data['user_id'] = $this->company->owner()->id;

                $invoice_data['line_items'] = $this->cleanItems(
                    $invoice_data['line_items'] ?? []
                );

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (
                    empty($invoice_data['client_id']) &&
                    ! empty($invoice_data['client'])
                ) {
                    $client_data = $invoice_data['client'];
                    $client_data['user_id'] = $this->getUserIDForRecord(
                        $invoice_data
                    );

                    $client_repository->save(
                        $client_data,
                        $client = ClientFactory::create(
                            $this->company->id,
                            $client_data['user_id']
                        )
                    );
                    $invoice_data['client_id'] = $client->id;
                    unset($invoice_data['client']);
                }

                $validator = $this->request_name::runFormRequest($invoice_data);

                if ($validator->fails()) {
                    $this->error_array['invoice'][] = [
                        'invoice' => $invoice_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $invoice = InvoiceFactory::create(
                        $this->company->id,
                        $this->company->owner()->id
                    );
                    if (! empty($invoice_data['status_id'])) {
                        $invoice->status_id = $invoice_data['status_id'];
                    }

                    nlog($invoice_data);
                    $saveable_invoice_data = $invoice_data;

                    if (array_key_exists('payments', $saveable_invoice_data)) {
                        unset($saveable_invoice_data['payments']);
                    }

                    $invoice_repository->save($saveable_invoice_data, $invoice);

                    $count++;
                    // If we're doing a generic CSV import, only import payment data if we're not importing a payment CSV.
                    // If we're doing a platform-specific import, trust the platform to only return payment info if there's not a separate payment CSV.
                    if (
                        $this->import_type !== 'csv' ||
                        empty($this->column_map['payment'])
                    ) {
                        // Check for payment columns
                        if (! empty($invoice_data['payments'])) {
                            foreach (
                                $invoice_data['payments'] as $payment_data
                            ) {

                                if ($payment_data['amount'] == 0 && $invoice_data['status_id'] == 4) {
                                    $payment_data['amount'] = $invoice->amount;
                                }

                                $payment_data['user_id'] = $invoice->user_id;
                                $payment_data['client_id'] =
                                    $invoice->client_id;
                                $payment_data['invoices'] = [
                                    [
                                        'invoice_id' => $invoice->id,
                                        'amount' => min($invoice->amount, $payment_data['amount']) ?? null,
                                    ],
                                ];

                                /* Make sure we don't apply any payments to invoices with a Zero Amount*/
                                if ($invoice->amount > 0 && $payment_data['amount'] > 0) {

                                    $payment = $payment_repository->save(
                                        $payment_data,
                                        PaymentFactory::create(
                                            $this->company->id,
                                            $invoice->user_id,
                                            $invoice->client_id
                                        )
                                    );

                                    $payment_date = Carbon::parse($payment->date);

                                    if (!$payment_date->isToday()) {

                                        $payment->paymentables()->update(['created_at' => $payment_date]);

                                    }

                                }
                            }
                        }
                    }

                    $this->actionInvoiceStatus(
                        $invoice,
                        $invoice_data,
                        $invoice_repository
                    );
                }
            } catch (\Exception $ex) {
                if (\DB::connection(config('database.default'))->transactionLevel() > 0) {
                    \DB::connection(config('database.default'))->rollBack();
                }

                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error ';
                    nlog($ex->getMessage());
                    nlog($raw_invoice);
                }

                $this->error_array['invoice'][] = [
                    'invoice' => $raw_invoice,
                    'error' => $message,
                ];
            }
        }

        return $count;
    }

    private function actionInvoiceStatus(
        $invoice,
        $invoice_data,
        $invoice_repository
    ) {
        if (! empty($invoice_data['archived'])) {
            $invoice_repository->archive($invoice);
            $invoice->fresh();
        }

        if (! empty($invoice_data['viewed'])) {
            $invoice = $invoice
                ->service()
                ->markViewed()
                ->save();
        }

        if ($invoice->status_id == Invoice::STATUS_DRAFT) {
            return $invoice;
        }

        $invoice = $invoice
            ->service()
            ->markSent()
            ->save();

        if ($invoice->status_id <= Invoice::STATUS_SENT && $invoice->amount > 0) {
            if ($invoice->balance <= 0) {
                $invoice->status_id = Invoice::STATUS_PAID;
                $invoice->save();
            } elseif ($invoice->balance != $invoice->amount) {
                $invoice->status_id = Invoice::STATUS_PARTIAL;
                $invoice->save();
            }
        }

        return $invoice;
    }

    private function actionQuoteStatus(
        $quote,
        $quote_data,
        $quote_repository
    ) {
        if (! empty($quote_data['archived'])) {
            $quote_repository->archive($quote);
            $quote->fresh();
        }

        if (! empty($quote_data['viewed'])) {
            $quote = $quote
                ->service()
                ->markViewed()
                ->save();
        }

        if ($quote->status_id === Quote::STATUS_DRAFT) {
        } elseif ($quote->status_id === Quote::STATUS_SENT) {
            $quote = $quote
                ->service()
                ->markSent()
                ->save();
        }

        return $quote;
    }

    public function ingestQuotes($quotes, $quote_number_key)
    {
        $count = 0;

        $quote_transformer = $this->transformer;

        /** @var ClientRepository $client_repository */
        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $quote_repository = new QuoteRepository();
        $quote_repository->import_mode = true;

        $quotes = $this->groupInvoices($quotes, $quote_number_key);

        foreach ($quotes as $raw_quote) {

            if (!is_array($raw_quote)) {
                continue;
            }

            try {
                $quote_data = $quote_transformer->transform($raw_quote);
                $quote_data['line_items'] = $this->cleanItems(
                    $quote_data['line_items'] ?? []
                );

                // If we don't have a client ID, but we do have client data, go ahead and create the client.
                if (
                    empty($quote_data['client_id']) &&
                    ! empty($quote_data['client'])
                ) {
                    $client_data = $quote_data['client'];
                    $client_data['user_id'] = $this->getUserIDForRecord(
                        $quote_data
                    );

                    $client_repository->save(
                        $client_data,
                        $client = ClientFactory::create(
                            $this->company->id,
                            $client_data['user_id']
                        )
                    );
                    $quote_data['client_id'] = $client->id;
                    unset($quote_data['client']);
                }

                $validator = Validator::make(
                    $quote_data,
                    (new StoreQuoteRequest())->rules()
                );
                if ($validator->fails()) {
                    $this->error_array['invoice'][] = [
                        'quote' => $quote_data,
                        'error' => $validator->errors()->all(),
                    ];
                } else {
                    $quote = QuoteFactory::create(
                        $this->company->id,
                        $this->getUserIDForRecord($quote_data)
                    );
                    if (! empty($quote_data['status_id'])) {
                        $quote->status_id = $quote_data['status_id'];
                    }
                    $quote_repository->save($quote_data, $quote);

                    $count++;

                    $this->actionQuoteStatus(
                        $quote,
                        $quote_data,
                        $quote_repository
                    );
                }
            } catch (\Exception $ex) {
                if ($ex instanceof ImportException) {
                    $message = $ex->getMessage();
                } else {
                    report($ex);
                    $message = 'Unknown error';
                }

                $this->error_array['quote'][] = [
                    'invoice' => $raw_quote,
                    'error' => $message,
                ];
            }
        }

        return $count;
    }

    protected function getUserIDForRecord($record)
    {
        if (! empty($record['user_id'])) {
            return $this->findUser($record['user_id']);
        } else {
            return $this->company->owner()->id;
        }
    }

    protected function findUser($user_hash)
    {
        $user = false;

        if (is_numeric($user_hash)) {

            $user = User::query()
                        ->where('account_id', $this->company->account->id)
                        ->where('id', $user_hash)
                        ->first();

        }

        if ($user) {
            return $user->id;
        }

        $user = User::whereRaw("account_id = ? AND CONCAT_WS(' ', first_name, last_name) like ?", [$this->company->account_id, '%'.$user_hash.'%'])
            ->first();

        if ($user) {
            return $user->id;
        } else {
            return $this->company->owner()->id;
        }
    }

    public function finalizeImport()
    {
        $data = [
            'errors'  => $this->error_array,
            'company' => $this->company,
            'entity_count' => $this->entity_count
        ];

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new CsvImportCompleted($this->company, $data);
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $this->company->owner();

        NinjaMailerJob::dispatch($nmo, true);
    }

    public function preTransform(array $data, $entity_type)
    {
        $keys = array_shift($data);
        ksort($keys);

        return array_map(function ($values) use ($keys) {
            return array_combine($keys, $values);
        }, $data);
    }

    public function preTransformCsv(array $data, $entity_type)
    {
        if (empty($this->column_map[$entity_type])) {
            return false;
        }

        if ($this->skip_header) {
            array_shift($data);
        }

        //sort the array by key
        $keys = $this->column_map[$entity_type];
        ksort($keys);

        $data = array_map(function ($row) use ($keys) {

            /** 12-04-2024 If we do not have matching keys - then this row import is _not_ valid */
            $row_keys = array_keys($row);
            $key_keys = array_keys($keys);

            $diff = array_diff($key_keys, $row_keys);

            if (count($key_keys) > count($row_keys)) {
                // Truncate key_keys to match the length of row_keys
                $key_keys = array_slice($key_keys, 0, count($row_keys));
                // Rebuild the $keys array with only the kept columns
                $keys = array_intersect_key($keys, array_flip($key_keys));
            }else if (!empty($diff)) {
                return false;
            }

            /** 12-04-2024 If we do not have matching keys - then this row import is _not_ valid */

            return array_combine($keys, array_intersect_key($row, $keys));
        }, $data);

        return $data;
    }

    private function convertData(array $data): array
    {

        // List of encodings to check against
        $encodings = [
            'UTF-8',
            'ISO-8859-1',  // Latin-1
            'ISO-8859-2',  // Latin-2
            'WINDOWS-1252', // CP1252
            'SHIFT-JIS',
            'EUC-JP',
            'GB2312',
            'GBK',
            'BIG5',
            'ISO-2022-JP',
            'KOI8-R',
            'KOI8-U',
            'WINDOWS-1251', // CP1251
            'UTF-16',
            'UTF-32',
            'ASCII'
        ];

        foreach ($data as $key => $value) {
            // Only process strings
            if (is_string($value)) {
                // Detect the encoding of the string
                $detectedEncoding = mb_detect_encoding($value, $encodings, true);

                // If encoding is detected and it's not UTF-8, convert it to UTF-8
                if ($detectedEncoding && $detectedEncoding !== 'UTF-8') {
                    $array[$key] = mb_convert_encoding($value, 'UTF-8', $detectedEncoding);
                }
            }
        }

        return $data;
    }

}

<?php
/**
 * Expense Ninja (https://expenseninja.com).
 *
 * @link https://github.com/expenseninja/expenseninja source repository
 *
 * @copyright Copyright (c) 2022. Expense Ninja LLC (https://expenseninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Export\CSV;

use App\Export\Decorators\Decorator;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\Expense;
use App\Transformers\ExpenseTransformer;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\App;
use League\Csv\Writer;

class ExpenseExport extends BaseExport
{
    private $expense_transformer;

    private Decorator $decorator;

    public string $date_key = 'date';

    public Writer $csv;

    public function __construct(Company $company, array $input)
    {
        $this->company = $company;
        $this->input = $input;
        $this->expense_transformer = new ExpenseTransformer();
        $this->decorator = new Decorator();
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

                    /** @var \App\Models\Expense $resource */
                    $row = $this->buildRow($resource);
                    return $this->processMetaData($row, $resource);
                })->toArray();

        return array_merge(['columns' => $header], $report);
    }

    private function init(): Builder
    {

        MultiDB::setDb($this->company->db);
        App::forgetInstance('translator');
        App::setLocale($this->company->locale());
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        if (count($this->input['report_keys']) == 0) {
            $this->input['report_keys'] = array_values($this->expense_report_keys);
        }

        $tax_keys = [
            'expense.tax_amount',
            'expense.net_amount'
        ];

        $this->input['report_keys'] = array_unique(array_merge($this->input['report_keys'], $tax_keys));

        $query = Expense::query()
                        ->with('client')
                        ->withTrashed()
                        ->where('company_id', $this->company->id);


        if (!$this->input['include_deleted'] ?? false) { // @phpstan-ignore-line
            $query->where('is_deleted', 0);
        }

        $query = $this->addDateRange($query, 'expenses');

        if ($this->input['status'] ?? false) {
            $query = $this->addExpenseStatusFilter($query, $this->input['status']);
        }

        if (isset($this->input['clients'])) {
            $query = $this->addClientFilter($query, $this->input['clients']);
        }

        if (isset($this->input['vendors'])) {
            $query = $this->addVendorFilter($query, $this->input['vendors']);
        }

        if (isset($this->input['projects'])) {
            $query = $this->addProjectFilter($query, $this->input['projects']);
        }

        if (isset($this->input['categories'])) {
            $query = $this->addCategoryFilter($query, $this->input['categories']);
        }

        if ($this->input['document_email_attachment'] ?? false) {
            $this->queueDocuments($query);
        }

        return $query;

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
                ->each(function ($expense) {

                    /** @var \App\Models\Expense $expense */
                    $this->csv->insertOne($this->buildRow($expense));
                });

        return $this->csv->toString();
    }

    private function buildRow(Expense $expense): array
    {
        $transformed_expense = $this->expense_transformer->transform($expense);
        $transformed_expense['currency_id'] =  $expense->currency ? $expense->currency->code : $expense->company->currency()->code;

        $entity = [];

        foreach (array_values($this->input['report_keys']) as $key) {
            $parts = explode('.', $key);

            if (is_array($parts) && $parts[0] == 'expense' && array_key_exists($parts[1], $transformed_expense)) {
                $entity[$key] = $transformed_expense[$parts[1]];
            } elseif (array_key_exists($key, $transformed_expense)) {
                $entity[$key] = $transformed_expense[$key];
            } else {
                $entity[$key] = $this->decorator->transform($key, $expense);
            }

        }

        $entity = $this->decorateAdvancedFields($expense, $entity);
        return $this->convertFloats($entity);
    }

    protected function addExpenseStatusFilter($query, $status): Builder
    {

        $status_parameters = explode(',', $status);

        if (in_array('all', $status_parameters)) {
            return $query;
        }

        $query->where(function ($query) use ($status_parameters) {
            if (in_array('logged', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->where('amount', '>', 0)
                          ->whereNull('invoice_id')
                          ->whereNull('payment_date')
                          ->where('should_be_invoiced', false);
                });
            }

            if (in_array('pending', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->where('should_be_invoiced', true)
                          ->whereNull('invoice_id');
                });
            }

            if (in_array('invoiced', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('invoice_id');
                });
            }

            if (in_array('paid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNotNull('payment_date');
                });
            }

            if (in_array('unpaid', $status_parameters)) {
                $query->orWhere(function ($query) {
                    $query->whereNull('payment_date');
                });
            }

        });

        return $query;
    }

    private function decorateAdvancedFields(Expense $expense, array $entity): array
    {
        // if (in_array('expense.currency_id', $this->input['report_keys'])) {
        //     $entity['expense.currency_id'] = $expense->currency ? $expense->currency->code : '';
        // }

        // if (in_array('expense.client_id', $this->input['report_keys'])) {
        //     $entity['expense.client'] = $expense->client ? $expense->client->present()->name() : '';
        // }

        if (in_array('expense.invoice_id', $this->input['report_keys'])) {
            $entity['expense.invoice_id'] = $expense->invoice ? $expense->invoice->number : '';
        }

        // if (in_array('expense.category', $this->input['report_keys'])) {
        //     $entity['expense.category'] = $expense->category ? $expense->category->name : '';
        // }

        if (in_array('expense.vendor_id', $this->input['report_keys'])) {
            $entity['expense.vendor'] = $expense->vendor ? $expense->vendor->name : '';
        }

        if (in_array('expense.payment_type_id', $this->input['report_keys'])) {
            $entity['expense.payment_type_id'] = $expense->payment_type ? $expense->payment_type->name : '';
        }

        if (in_array('expense.project_id', $this->input['report_keys'])) {
            $entity['expense.project_id'] = $expense->project ? $expense->project->name : '';
        }

        if (in_array('expense.user', $this->input['report_keys'])) {
            $entity['expense.user'] = $expense->user ? $expense->user->present()->name() : '';
        }

        if (in_array('expense.assigned_user', $this->input['report_keys'])) {
            $entity['expense.assigned_user'] = $expense->assigned_user ? $expense->assigned_user->present()->name() : '';
        }

        if (in_array('expense.category_id', $this->input['report_keys'])) {
            $entity['expense.category_id'] = $expense->category ? $expense->category->name : '';
        }

        return $this->calcTaxes($entity, $expense);
    }

    private function calcTaxes($entity, $expense): array
    {
        $precision = $expense->currency->precision ?? 2;

        if ($expense->calculate_tax_by_amount) {

            $total_tax_amount = round($expense->tax_amount1 + $expense->tax_amount2 + $expense->tax_amount3, $precision);

            if ($expense->uses_inclusive_taxes) {
                $entity['expense.net_amount'] = round($expense->amount, $precision) - $total_tax_amount;
            } else {
                $entity['expense.net_amount'] = round($expense->amount, $precision);
            }

        } else {

            if ($expense->uses_inclusive_taxes) {
                $total_tax_amount = ($this->calcInclusiveLineTax($expense->tax_rate1 ?? 0, $expense->amount, $precision)) + ($this->calcInclusiveLineTax($expense->tax_rate2 ?? 0, $expense->amount, $precision)) + ($this->calcInclusiveLineTax($expense->tax_rate3 ?? 0, $expense->amount, $precision));
                $entity['expense.net_amount'] = round(($expense->amount - round($total_tax_amount, $precision)), $precision);
            } else {
                $total_tax_amount = ($expense->amount * (($expense->tax_rate1 ?? 0) / 100)) + ($expense->amount * (($expense->tax_rate2 ?? 0) / 100)) + ($expense->amount * (($expense->tax_rate3 ?? 0) / 100));
                $entity['expense.net_amount'] = round(($expense->amount + round($total_tax_amount, $precision)), $precision);
            }
        }

        $entity['expense.tax_amount'] = round($total_tax_amount, $precision);

        return $entity;

    }

    private function calcInclusiveLineTax($tax_rate, $amount, $precision): float
    {
        return round($amount - ($amount / (1 + ($tax_rate / 100))), $precision);
    }
}

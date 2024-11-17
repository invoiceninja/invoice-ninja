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

use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Models\Product;
use App\Models\Project;

/**
 * Class for project repository.
 */
class ProjectRepository extends BaseRepository
{
    public function invoice(Project $project)
    {
        $invoice = InvoiceFactory::create($project->company_id, $project->user_id);
        $invoice->client_id = $project->client_id;
        $invoice->project_id = $project->id;

        $lines = [];

        $uninvoiced_tasks = $project->tasks()
                                    ->withTrashed()
                                    ->whereNull('invoice_id')
                                    ->where('is_deleted',0)
                                    ->cursor()
                                    ->each(function ($task) use(&$lines){

                                        $item = new InvoiceItem();
                                        $item->quantity = $task->getQuantity();
                                        $item->cost = $task->getRate();
                                        $item->product_key = '';
                                        $item->notes = $task->description();
                                        $item->task_id = $task->hashed_id;
                                        $item->tax_id = (string) Product::PRODUCT_TYPE_SERVICE;
                                        $item->type_id = '2';

                                        $lines[] = $item;

                                    });
                
        $unpaid_expenses = $project->expenses()
                                   ->withTrashed()
                                   ->where('should_be_invoiced', true)
                                   ->whereNull('payment_date')
                                   ->cursor()
                                   ->each(function ($expense) use(&$lines){
                            
                                        $item = new InvoiceItem();
                                        $item->quantity = 1;
                                        $item->cost = $expense->foreign_amount > 0 ? $expense->foreign_amount : $expense->amount;
                                        $item->product_key = $expense->category()->exists() ? $expense->category->name : '';
                                        $item->notes = $expense->public_notes ?? '';
                                        $item->line_total = round($item->cost * $item->quantity,2);
                                        $item->tax_name1 = $expense->tax_name1;
                                        $item->tax_rate1 = $expense->calculatedTaxRate($expense->tax_amount1, $expense->tax_rate1);
                                        $item->tax_name2 = $expense->tax_name2;
                                        $item->tax_rate2 = $expense->calculatedTaxRate($expense->tax_amount2, $expense->tax_rate2);
                                        $item->tax_name3 = $expense->tax_name3;
                                        $item->tax_rate3 = $expense->calculatedTaxRate($expense->tax_amount3, $expense->tax_rate3);
                                        $item->tax_id = (string) Product::PRODUCT_TYPE_PHYSICAL;
                                        $item->expense_id = $expense->hashed_id;
                                        $item->type_id = '1';

                                        $lines[] = $item;
                                   });

        $invoice->uses_inclusive_taxes = $project->company->settings->inclusive_taxes ?? false;
        $invoice->line_items = $lines;
        return $invoice;
        
    }
}

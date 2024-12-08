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

namespace App\Services\PurchaseOrder;

use App\Factory\ExpenseFactory;
use App\Models\PurchaseOrder;
use App\Utils\Traits\GeneratesCounter;

class PurchaseOrderExpense
{
    use GeneratesCounter;

    private PurchaseOrder $purchase_order;

    public function __construct(PurchaseOrder $purchase_order)
    {
        $this->purchase_order = $purchase_order;
    }

    public function run()
    {
        $expense = ExpenseFactory::create($this->purchase_order->company_id, $this->purchase_order->user_id);

        $expense->amount = $this->purchase_order->uses_inclusive_taxes ? $this->purchase_order->amount : ($this->purchase_order->amount - $this->purchase_order->total_taxes);

        $expense->date = now();
        $expense->vendor_id = $this->purchase_order->vendor_id;
        $expense->public_notes = $this->purchase_order->public_notes;
        $expense->uses_inclusive_taxes = $this->purchase_order->uses_inclusive_taxes;
        $expense->calculate_tax_by_amount = true;
        $expense->private_notes = ctrans('texts.purchase_order_number_short') . " " . $this->purchase_order->number;
        $expense->currency_id = $this->purchase_order->vendor->currency_id;

        $line_items = $this->purchase_order->line_items;

        $expense->public_notes = '';

        foreach ($line_items as $line_item) {
            $expense->public_notes .= $line_item->quantity . " x " . $line_item->product_key. " [ " .$line_item->notes . " ]\n";
        }

        $tax_map = $this->purchase_order->calc()->getTaxMap();

        if ($this->purchase_order->total_taxes > 0) {
            $expense->tax_amount1 = $this->purchase_order->total_taxes;
            $expense->tax_name1 = ctrans("texts.tax");
        }

        $expense->number = empty($expense->number) ? $this->getNextExpenseNumber($expense) : $expense->number;

        if ($this->purchase_order->project_id) {
            $expense->project_id = $this->purchase_order->project_id;
            $expense->client_id = $this->purchase_order->project->client_id;
        } elseif ($this->purchase_order->client_id) {
            $expense->client_id = $this->purchase_order->client_id;
        }

        $expense->saveQuietly();
        event('eloquent.created: App\Models\Expense', $expense);

        $this->purchase_order->expense_id = $expense->id;
        $this->purchase_order->saveQuietly();

        return $expense;
    }
}

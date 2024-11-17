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

namespace App\Transformers;

use App\Models\Client;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Project;
use App\Models\Vendor;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\Eloquent\SoftDeletes;
use League\Fractal\Resource\Item;

/**
 * class ExpenseTransformer.
 */
class ExpenseTransformer extends EntityTransformer
{
    use MakesHash;
    use SoftDeletes;

    protected array $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'client',
        'vendor',
        'category',
        'invoice',
        'project',
    ];

    public function includeDocuments(Expense $expense)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($expense->documents, $transformer, Document::class);
    }

    public function includeProject(Expense $expense): ?Item
    {
        $transformer = new ProjectTransformer($this->serializer);

        if (!$expense->project) {
            return null;
        }

        return $this->includeItem($expense->project, $transformer, Project::class);
    }

    public function includeClient(Expense $expense): ?Item
    {
        $transformer = new ClientTransformer($this->serializer);

        if (!$expense->client) {
            return null;
        }

        return $this->includeItem($expense->client, $transformer, Client::class);
    }

    public function includeInvoice(Expense $expense): ?Item
    {
        $transformer = new InvoiceTransformer($this->serializer);

        if (!$expense->invoice) {
            return null;
        }

        return $this->includeItem($expense->invoice, $transformer, Invoice::class);
    }

    public function includeCategory(Expense $expense): ?Item
    {
        $transformer = new ExpenseCategoryTransformer($this->serializer);

        if (!$expense->category) {
            return null;
        }

        return $this->includeItem($expense->category, $transformer, ExpenseCategory::class);
    }

    public function includeVendor(Expense $expense): ?Item
    {
        $transformer = new VendorTransformer($this->serializer);

        if (!$expense->vendor) {
            return null;
        }

        return $this->includeItem($expense->vendor, $transformer, Vendor::class);
    }

    /**
     * @param Expense $expense
     *
     * @return array
     */
    public function transform(Expense $expense)
    {
        return [
            'id' => $this->encodePrimaryKey($expense->id),
            'user_id' => $this->encodePrimaryKey($expense->user_id),
            'assigned_user_id' => $this->encodePrimaryKey($expense->assigned_user_id),
            'vendor_id' => $this->encodePrimaryKey($expense->vendor_id),
            'invoice_id' => $this->encodePrimaryKey($expense->invoice_id),
            'client_id' => $this->encodePrimaryKey($expense->client_id),
            'bank_id' => (string) $expense->bank_id ?: '',
            'invoice_currency_id' => (string) $expense->invoice_currency_id ?: '',
            'expense_currency_id' => '', //todo remove redundant in 5.0.25
            'currency_id' => (string) $expense->currency_id ?: '',
            'category_id' => $this->encodePrimaryKey($expense->category_id),
            'payment_type_id' => (string) $expense->payment_type_id ?: '',
            'recurring_expense_id' => (string) $this->encodePrimaryKey($expense->recurring_expense_id) ?: '',
            'is_deleted' => (bool) $expense->is_deleted,
            'should_be_invoiced' => (bool) $expense->should_be_invoiced,
            'invoice_documents' => (bool) $expense->invoice_documents,
            'amount' => (float) $expense->amount ?: 0,
            'foreign_amount' => (float) $expense->foreign_amount ?: 0,
            'exchange_rate' => (float) $expense->exchange_rate ?: 0,
            'tax_name1' => $expense->tax_name1 ? $expense->tax_name1 : '',
            'tax_rate1' => (float) $expense->tax_rate1,
            'tax_name2' => $expense->tax_name2 ? $expense->tax_name2 : '',
            'tax_rate2' => (float) $expense->tax_rate2,
            'tax_name3' => $expense->tax_name3 ? $expense->tax_name3 : '',
            'tax_rate3' => (float) $expense->tax_rate3,
            'private_notes' => (string) $expense->private_notes ?: '',
            'public_notes' => (string) $expense->public_notes ?: '',
            'transaction_reference' => (string) $expense->transaction_reference ?: '',
            'transaction_id' => (string) $this->encodePrimaryKey($expense->transaction_id) ?: '',
            'date' => $expense->date ?: '',
            'number' => (string)$expense->number ?: '',
            'payment_date' => $expense->payment_date ?: '',
            'custom_value1' => $expense->custom_value1 ?: '',
            'custom_value2' => $expense->custom_value2 ?: '',
            'custom_value3' => $expense->custom_value3 ?: '',
            'custom_value4' => $expense->custom_value4 ?: '',
            'updated_at' => (int) $expense->updated_at,
            'archived_at' => (int) $expense->deleted_at,
            'created_at' => (int) $expense->created_at,
            'project_id' => $this->encodePrimaryKey($expense->project_id),
            'tax_amount1' => (float) $expense->tax_amount1,
            'tax_amount2' => (float) $expense->tax_amount2,
            'tax_amount3' => (float) $expense->tax_amount3,
            'uses_inclusive_taxes' => (bool) $expense->uses_inclusive_taxes,
            'calculate_tax_by_amount' => (bool) $expense->calculate_tax_by_amount,
            'entity_type' => 'expense',
            'e_invoice' => $expense->e_invoice ?: new \stdClass(),

        ];
    }
}

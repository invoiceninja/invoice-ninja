<?php

/**
 * Invoice Ninja (https://clientninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Quickbooks\Transformers;

use App\Factory\ExpenseCategoryFactory;
use App\Models\ExpenseCategory;
use Illuminate\Support\Carbon;

/**
 * Class ExpenseTransformer.
 */
class ExpenseTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb()
    {
    }

    public function transform(mixed $data): array
    {

        $expense = [
            'amount' => data_get($data, 'TotalAmt'),
            'date' => Carbon::parse(data_get($data, 'TxnDate', ''))->format('Y-m-d'),
            'currency_id' => $this->resolveCurrency(data_get($data, 'CurrencyRef.value', '')),
            'private_notes' => data_get($data, 'PrivateNote', null),
            'public_notes' => null,
            'number' => data_get($data, 'Id.value', null),
            'transaction_reference' => data_get($data, 'DocNumber', ''),
            'should_be_invoiced' => false,
            'invoice_documents' => false,
            'uses_inclusive_taxes' => false,
            'calculate_tax_by_amount' => false,
            'category_id' => $this->getCategoryId(data_get($data, 'AccountRef.name', '')),
        ];


        $mergeable = $this->resolveAttachedEntity($data);

        $expense =  array_merge($expense, $mergeable);

        nlog($expense);

        return $expense;

    }

    private function resolveAttachedEntity($entity)
    {

        $related = data_get($entity, 'EntityRef.type');
        $entity_id = data_get($entity, 'EntityRef.value');

        switch ($related) {
            case 'Vendor':
                return ['vendor_id' => $this->getVendorId($entity_id)];
            case 'Client':
                return ['client_id' => $this->getClientId($entity_id)];
            default:
                return [];
        }

    }

    private function getCategoryId($name): ?int
    {

        if (strlen($name) == 0) {
            return null;
        }

        $category = ExpenseCategory::where('company_id', $this->company->id)
                                    ->where('name', $name)
                                    ->first();

        if (!$category) {
            $category = ExpenseCategoryFactory::create($this->company->id, $this->company->owner()->id);
            $category->name = $name;
            $category->save();
        }

        return $category->id;
    }

}

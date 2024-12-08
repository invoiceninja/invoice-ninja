<?php

namespace App\Repositories\Import\Quickbooks\Transformers;

use Illuminate\Support\Collection;

class Transformer
{
    public function transform(array $items, string $type): Collection
    {
        if (!method_exists($this, ($method = "transform{$type}s"))) {
            throw new \InvalidArgumentException("Unknown type: $type");
        }

        return call_user_func([$this, $method], $items);
    }

    protected function transformCustomers(array $items): Collection
    {
        return $this->transformation($items, [
            'CompanyName',
            'PrimaryPhone',
            'BillAddr',
            'ShipAddr',
            'Notes',
            'GivenName',
            'FamilyName',
            'PrimaryEmailAddr',
            'CurrencyRef',
            'MetaData'
        ]);
    }

    protected function transformInvoices(array $items): Collection
    {
        return $this->transformation($items, [
            "TotalAmt",
            "Line",
            "DueDate",
            "Deposit",
            "Balance",
            "CustomerMemo",
            "DocNumber",
            "CustomerRef",
            "BillEmail",
            'MetaData',
            "BillAddr",
            "ShipAddr",
            "LinkedTxn",
            "Id",
            "CurrencyRef",
            "TxnTaxDetail",
            "TxnDate"
        ]);
    }

    protected function transformPayments(array $items): Collection
    {
        return $this->transformation($items, [
            "PaymentRefNum",
            "TotalAmt",
            "CustomerRef",
            "CurrencyRef",
            "TxnDate",
            "Line",
            "PrivateNote",
            "MetaData"
        ]);
    }

    protected function transformItems(array $items): Collection
    {
        return $this->transformation($items, [
            'Name',
            'Description',
            'PurchaseCost',
            'UnitPrice',
            'QtyOnHand',
            'MetaData'
        ]);
    }

    protected function transformation(array $items, array $keys): Collection
    {
        return collect($items)->select($keys);
    }

}

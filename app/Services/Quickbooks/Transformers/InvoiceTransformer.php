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

use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Product;
use App\DataMapper\InvoiceItem;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    public function qbToNinja(mixed $qb_data)
    {
        return $this->transform($qb_data);
    }

    public function ninjaToQb()
    {
    }

    public function transform($qb_data)
    {
        $client_id = $this->getClientId(data_get($qb_data, 'CustomerRef.value', null));

        return $client_id ? [
            'id' => data_get($qb_data, 'Id.value', false),
            'client_id' => $client_id,
            'number' => data_get($qb_data, 'DocNumber', false),
            'date' => data_get($qb_data, 'TxnDate', now()->format('Y-m-d')),
            'private_notes' => data_get($qb_data, 'PrivateNote', ''),
            'public_notes' => data_get($qb_data, 'CustomerMemo.value', false),
            'due_date' => data_get($qb_data, 'DueDate', null),
            'po_number' => data_get($qb_data, 'PONumber', ""),
            'partial' => (float)data_get($qb_data, 'Deposit', 0),
            'line_items' => $this->getLineItems(data_get($qb_data, 'Line', []), data_get($qb_data, 'ApplyTaxAfterDiscount', 'true')),
            'payment_ids' => $this->getPayments($qb_data),
            'status_id' => Invoice::STATUS_SENT,
            'tax_rate1' => $rate = $this->calculateTotalTax($qb_data),
            'tax_name1' => $rate > 0 ? "Sales Tax" : "",
            'custom_surcharge1' => $this->checkIfDiscountAfterTax($qb_data),

        ] : false;
    }

    private function checkIfDiscountAfterTax($qb_data)
    {

        if ($qb_data->ApplyTaxAfterDiscount == 'true') {
            return 0;
        }

        foreach (data_get($qb_data, 'Line', []) as $line) {

            if (data_get($line, 'DetailType.value') == 'DiscountLineDetail') {

                if (!isset($this->company->custom_fields->surcharge1)) {
                    $this->company->custom_fields->surcharge1 = ctrans('texts.discount');
                    $this->company->save();
                }

                return (float)data_get($line, 'Amount', 0) * -1;
            }
        }

        return 0;
    }

    private function calculateTotalTax($qb_data)
    {
        $taxLines = data_get($qb_data, 'TxnTaxDetail.TaxLine', []);

        if (!is_array($taxLines)) {
            $taxLines = [$taxLines];
        }

        $totalTaxRate = 0;

        foreach ($taxLines as $taxLine) {
            $taxRate = data_get($taxLine, 'TaxLineDetail.TaxPercent', 0);
            $totalTaxRate += $taxRate;
        }

        return (float)$totalTaxRate;
    }


    private function getPayments(mixed $qb_data)
    {
        $payments = [];

        $qb_payments = data_get($qb_data, 'LinkedTxn', false);

        if (!$qb_payments) {
            return [];
        }

        if (!is_array($qb_payments) && data_get($qb_payments, 'TxnType', false) == 'Payment') {
            return [data_get($qb_payments, 'TxnId.value', false)];
        }


        foreach ($qb_payments as $payment) {
            if (data_get($payment, 'TxnType', false) == 'Payment') {
                $payments[] = data_get($payment, 'TxnId.value', false);
            }
        }

        return $payments;

    }

    private function getLineItems(mixed $qb_items, string $include_discount = 'true')
    {
        $items = [];

        foreach ($qb_items as $qb_item) {

            if (data_get($qb_item, 'DetailType.value') == 'SalesItemLineDetail') {
                $item = new InvoiceItem();
                $item->product_key = data_get($qb_item, 'SalesItemLineDetail.ItemRef.name', '');
                $item->notes = data_get($qb_item, 'Description', '');
                $item->quantity = (float)data_get($qb_item, 'SalesItemLineDetail.Qty', 0);
                $item->cost = (float)data_get($qb_item, 'SalesItemLineDetail.UnitPrice', 0);
                $item->discount = (float)data_get($item, 'DiscountRate', data_get($qb_item, 'DiscountAmount', 0));
                $item->is_amount_discount = data_get($qb_item, 'DiscountAmount', 0) > 0 ? true : false;
                $item->type_id = stripos(data_get($qb_item, 'ItemAccountRef.name') ?? '', 'Service') !== false ? '2' : '1';
                $item->tax_id = data_get($qb_item, 'TaxCodeRef.value', '') == 'NON' ? Product::PRODUCT_TYPE_EXEMPT : $item->type_id;
                $item->tax_rate1 = (float)data_get($qb_item, 'TxnTaxDetail.TaxLine.TaxLineDetail.TaxPercent', 0);
                $item->tax_name1 = $item->tax_rate1 > 0 ? "Sales Tax" : "";
                $items[] = (object)$item;
            }

            if (data_get($qb_item, 'DetailType.value') == 'DiscountLineDetail' && $include_discount == 'true') {

                $item = new InvoiceItem();
                $item->product_key = ctrans('texts.discount');
                $item->notes = ctrans('texts.discount');
                $item->quantity = 1;
                $item->cost = (float)data_get($qb_item, 'Amount', 0) * -1;
                $item->discount = 0;
                $item->is_amount_discount = true;
                $item->type_id = '1';
                $item->tax_id = Product::PRODUCT_TYPE_PHYSICAL;
                $items[] = (object)$item;

            }
        }

        return $items;

    }







    // public function getTotalAmt($data)
    // {
    //     return (float) $this->getString($data, 'TotalAmt');
    // }

    // public function getLine($data)
    // {
    //     return array_map(function ($item) {
    //         return [
    //             'description' => $this->getString($item, 'Description'),
    //             'product_key' => $this->getString($item, 'Description'),
    //             'quantity' => (int) $this->getString($item, 'SalesItemLineDetail.Qty'),
    //             'unit_price' => (float) $this->getString($item, 'SalesItemLineDetail.UnitPrice'),
    //             'line_total' => (float) $this->getString($item, 'Amount'),
    //             'cost' => (float) $this->getString($item, 'SalesItemLineDetail.UnitPrice'),
    //             'product_cost' => (float) $this->getString($item, 'SalesItemLineDetail.UnitPrice'),
    //             'tax_amount' => (float) $this->getString($item, 'TxnTaxDetail.TotalTax'),
    //         ];
    //     }, array_filter($this->getString($data, 'Line'), function ($item) {
    //         return $this->getString($item, 'DetailType') !== 'SubTotalLineDetail';
    //     }));
    // }

    // public function getInvoiceClient($data, $field = null)
    // {
    //     /**
    //      *  "CustomerRef": {
    //             "value": "23",
    //             "name": ""Barnett Design
    //             },
    //             "CustomerMemo": {
    //             "value": "Thank you for your business and have a great day!"
    //             },
    //             "BillAddr": {
    //             "Id": "58",
    //             "Line1": "Shara Barnett",
    //             "Line2": "Barnett Design",
    //             "Line3": "19 Main St.",
    //             "Line4": "Middlefield, CA  94303",
    //             "Lat": "37.4530553",
    //             "Long": "-122.1178261"
    //             },
    //             "ShipAddr": {
    //             "Id": "24",
    //             "Line1": "19 Main St.",
    //             "City": "Middlefield",
    //             "CountrySubDivisionCode": "CA",
    //             "PostalCode": "94303",
    //             "Lat": "37.445013",
    //             "Long": "-122.1391443"
    //             },"BillEmail": {
    //             "Address": "Design@intuit.com"
    //             },
    //                 [
    //                 'name'              => 'CompanyName',
    //                 'phone'             => 'PrimaryPhone.FreeFormNumber',
    //                 'country_id'        => 'BillAddr.Country',
    //                 'state'             => 'BillAddr.CountrySubDivisionCode',
    //                 'address1'          => 'BillAddr.Line1',
    //                 'city'              => 'BillAddr.City',
    //                 'postal_code'       => 'BillAddr.PostalCode',
    //                 'shipping_country_id' => 'ShipAddr.Country',
    //                 'shipping_state'    => 'ShipAddr.CountrySubDivisionCode',
    //                 'shipping_address1' => 'ShipAddr.Line1',
    //                 'shipping_city'     => 'ShipAddr.City',
    //                 'shipping_postal_code' => 'ShipAddr.PostalCode',
    //                 'public_notes'      => 'Notes'
    //             ];

    //      */
    //     $bill_address = (object) $this->getString($data, 'BillAddr');
    //     $ship_address = $this->getString($data, 'ShipAddr');
    //     $customer = explode(" ", $this->getString($data, 'CustomerRef.name'));
    //     $customer = ['GivenName' => $customer[0], 'FamilyName' => $customer[1]];
    //     $has_company = property_exists($bill_address, 'Line4');
    //     $address = $has_company ? $bill_address->Line4 : $bill_address->Line3;
    //     $address_1 = substr($address, 0, stripos($address, ','));
    //     $address = array_filter([$address_1] + (explode(' ', substr($address, stripos($address, ",") + 1))));
    //     $client_id = null;
    //     $client =
    //     [
    //         "CompanyName" => $has_company ? $bill_address->Line2 : $bill_address->Line1,
    //         "BillAddr" => array_combine(['City','CountrySubDivisionCode','PostalCode'], array_pad($address, 3, 'N/A')) + ['Line1' => $has_company ? $bill_address->Line3 : $bill_address->Line2 ],
    //         "ShipAddr" => $ship_address
    //     ] + $customer + ['PrimaryEmailAddr' => ['Address' => $this->getString($data, 'BillEmail.Address') ]];
    //     if($this->hasClient($client['CompanyName'])) {
    //         $client_id = $this->getClient($client['CompanyName'], $this->getString($client, 'PrimaryEmailAddr.Address'));
    //     }


    //     return ['client' => (new ClientTransformer($this->company))->transform($client), 'client_id' => $client_id ];
    // }

    // public function getDueDate($data)
    // {
    //     return $this->parseDateOrNull($data, 'DueDate');
    // }

    // public function getDeposit($data)
    // {
    //     return (float) $this->getString($data, 'Deposit');
    // }

    // public function getBalance($data)
    // {
    //     return (float) $this->getString($data, 'Balance');
    // }

    // public function getCustomerMemo($data)
    // {
    //     return $this->getString($data, 'CustomerMemo.value');
    // }

    // public function getDocNumber($data, $field = null)
    // {
    //     return sprintf(
    //         "%s-%s",
    //         $this->getString($data, 'DocNumber'),
    //         $this->getString($data, 'Id.value')
    //     );
    // }

    // public function getLinkedTxn($data)
    // {
    //     $payments = $this->getString($data, 'LinkedTxn');
    //     if(empty($payments)) {
    //         return [];
    //     }

    //     return [[
    //          'amount' => $this->getTotalAmt($data),
    //          'date' => $this->parseDateOrNull($data, 'TxnDate')
    //      ]];

    // }
}

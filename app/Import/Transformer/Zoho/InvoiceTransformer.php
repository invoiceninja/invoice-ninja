<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Zoho;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\Invoice;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    /**
     * @param $line_items_data
     *
     * @return bool|array
     */
    public function transform($line_items_data)
    {
        $invoice_data = reset($line_items_data);

        if ($this->hasInvoice($invoice_data['Invoice Number'])) {
            throw new ImportException('Invoice number already exists');
        }

        $invoiceStatusMap = [
            'sent'  => Invoice::STATUS_SENT,
            'draft' => Invoice::STATUS_DRAFT,
        ];

        $transformed = [
            'company_id'   => $this->company->id,
            // 'client_id'    => $this->getClient($this->getString($invoice_data, 'Customer ID'), $this->getString($invoice_data, 'Primary Contact EmailID')),
            'client_id'    => $this->harvestClient($invoice_data),
            'number'       => $this->getString($invoice_data, 'Invoice Number'),
            'date'         => isset($invoice_data['Invoice Date']) ? $this->parseDate($invoice_data['Invoice Date']) : null,
            'due_date'     => isset($invoice_data['Due Date']) ? $this->parseDate($invoice_data['Due Date']) : null,
            'po_number'    => $this->getString($invoice_data, 'PurchaseOrder'),
            'public_notes' => $this->getString($invoice_data, 'Notes'),
            // 'currency_id'  => $this->getCurrencyByCode($invoice_data, 'Currency'),
            'amount'       => $this->getFloat($invoice_data, 'Total'),
            'balance'      => $this->getFloat($invoice_data, 'Balance'),
            'status_id'    => $invoiceStatusMap[$status =
                    strtolower($this->getString($invoice_data, 'Invoice Status'))] ?? Invoice::STATUS_SENT,
            'terms'        => $this->getString($invoice_data, 'Terms & Conditions'),

            // 'viewed'       => $status === 'viewed',
        ];

        $line_items = [];
        foreach ($line_items_data as $record) {
            $item_notes_key = array_key_exists('Item Description', $record) ? 'Item Description' : 'Item Desc';

            $line_items[] = [
                'product_key'        => $this->getString($record, 'Item Name'),
                'notes'              => $this->getString($record, $item_notes_key),
                'cost'               => round($this->getFloat($record, 'Item Price'), 2),
                'quantity'           => $this->getFloat($record, 'Quantity'),
                'discount'           => $this->getString($record, 'Discount Amount'),
                'is_amount_discount' => true,
            ];
        }
        $transformed['line_items'] = $line_items;

        if ($transformed['balance'] < $transformed['amount']) {
            $transformed['payments'] = [[
                'date'   => isset($invoice_data['Last Payment Date']) ? $this->parseDate($invoice_data['Invoice Date']) : date('Y-m-d'),
                'amount' => $transformed['amount'] - $transformed['balance'],
            ]];
        }

        return $transformed;
    }

    private function harvestClient($invoice_data)
    {

        $client_email = $this->getString($invoice_data, 'Primary Contact EmailID');

        if (strlen($client_email) > 2) {
            $contacts = \App\Models\ClientContact::whereHas('client', function ($query) {
                $query->where('is_deleted', false);
            })
            ->where('company_id', $this->company->id)
            ->where('email', $client_email);

            if ($contacts->count() >= 1) {
                return $contacts->first()->client_id;
            }
        }

        $client_name = $this->getString($invoice_data, 'Customer Name');

        if (strlen($client_name) >= 2) {
            $client_name_search = \App\Models\Client::query()->where('company_id', $this->company->id)
                ->where('is_deleted', false)
                ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                    strtolower(str_replace(' ', '', $client_name)),
                ]);

            if ($client_name_search->count() >= 1) {
                return $client_name_search->first()->id;
            }
        }

        $customer_id = $this->getString($invoice_data, 'Customer ID');

        $client_id_search = \App\Models\Client::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->where('id_number', trim($customer_id));

        if ($client_id_search->count() >= 1) {
            return $client_id_search->first()->id;
        }

        $client_repository = app()->make(\App\Repositories\ClientRepository::class);
        $client_repository->import_mode = true;

        $client = $client_repository->save(
            [
                'name' => $client_name,
                'contacts' => [
                    [
                        'first_name' => $client_name,
                        'email' => $client_email,
                    ],
                ],
                'address1' => $this->getString($invoice_data, 'Billing Address'),
                'city' => $this->getString($invoice_data, 'Billing City'),
                'state' => $this->getString($invoice_data, 'Billing State'),
                'postal_code' => $this->getString($invoice_data, 'Billing Code'),
                'country_id' => $this->getCountryId($this->getString($invoice_data, 'Billing Country')),
            ],
            \App\Factory\ClientFactory::create(
                $this->company->id,
                $this->company->owner()->id
            )
        );

        $client_repository = null;

        return $client->id;

    }
}

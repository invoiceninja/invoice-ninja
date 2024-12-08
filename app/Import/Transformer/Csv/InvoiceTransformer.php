<?php
/**
 * client Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. client Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Import\Transformer\Csv;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\Invoice;
use App\Utils\Traits\CleanLineItems;

/**
 * Class InvoiceTransformer.
 */
class InvoiceTransformer extends BaseTransformer
{
    use CleanLineItems;
    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($line_items_data)
    {
        $invoice_data = reset($line_items_data);

        if ($this->hasInvoice($invoice_data['invoice.number'])) {
            throw new ImportException('Invoice number already exists');
        }

        $invoiceStatusMap = [
            'sent' => Invoice::STATUS_SENT,
            'draft' => Invoice::STATUS_DRAFT,
            'paid' => Invoice::STATUS_PAID,
        ];

        $transformed = [
            'company_id' => $this->company->id,
            'number' => $this->getString($invoice_data, 'invoice.number'),
            'user_id' => $this->getString($invoice_data, 'invoice.user_id'),
            'amount' => ($amount = $this->getFloat(
                $invoice_data,
                'invoice.amount'
            )),
            'balance' => isset($invoice_data['invoice.balance'])
                ? $this->getFloat($invoice_data, 'invoice.balance')
                : $amount,
            'client_id' => $this->getClient(
                $this->getString($invoice_data, 'client.name'),
                $this->getString($invoice_data, 'client.email')
            ),
            'discount' => $this->getFloat($invoice_data, 'invoice.discount'),
            'po_number' => $this->getString($invoice_data, 'invoice.po_number'),
            'date' => isset($invoice_data['invoice.date'])
                ? $this->parseDate($invoice_data['invoice.date'])
                : now()->format('Y-m-d'),
            'due_date' => isset($invoice_data['invoice.due_date'])
                ? $this->parseDate($invoice_data['invoice.due_date'])
                : null,
            'terms' => $this->getString($invoice_data, 'invoice.terms'),
            'public_notes' => $this->getString(
                $invoice_data,
                'invoice.public_notes'
            ),
            'private_notes' => $this->getString(
                $invoice_data,
                'invoice.private_notes'
            ),
            'tax_name1' => $this->getString($invoice_data, 'invoice.tax_name1'),
            'tax_rate1' => $this->getFloat($invoice_data, 'invoice.tax_rate1'),
            'tax_name2' => $this->getString($invoice_data, 'invoice.tax_name2'),
            'tax_rate2' => $this->getFloat($invoice_data, 'invoice.tax_rate2'),
            'tax_name3' => $this->getString($invoice_data, 'invoice.tax_name3'),
            'tax_rate3' => $this->getFloat($invoice_data, 'invoice.tax_rate3'),
            'custom_value1' => $this->getString(
                $invoice_data,
                'invoice.custom_value1'
            ),
            'custom_value2' => $this->getString(
                $invoice_data,
                'invoice.custom_value2'
            ),
            'custom_value3' => $this->getString(
                $invoice_data,
                'invoice.custom_value3'
            ),
            'custom_value4' => $this->getString(
                $invoice_data,
                'invoice.custom_value4'
            ),
            'footer' => $this->getString($invoice_data, 'invoice.footer'),
            'partial' => $this->getFloat($invoice_data, 'invoice.partial') > 0 ? $this->getFloat($invoice_data, 'invoice.partial') : null,
            'partial_due_date' =>  isset($invoice_data['invoice.partial_due_date']) ? $this->parseDate($invoice_data['invoice.partial_due_date']) : null,
            'custom_surcharge1' => $this->getFloat(
                $invoice_data,
                'invoice.custom_surcharge1'
            ),
            'custom_surcharge2' => $this->getFloat(
                $invoice_data,
                'invoice.custom_surcharge2'
            ),
            'custom_surcharge3' => $this->getFloat(
                $invoice_data,
                'invoice.custom_surcharge3'
            ),
            'custom_surcharge4' => $this->getFloat(
                $invoice_data,
                'invoice.custom_surcharge4'
            ),
            'exchange_rate' => $this->getFloatOrOne(
                $invoice_data,
                'invoice.exchange_rate'
            ),
            'status_id' => $invoiceStatusMap[
                    ($status = strtolower(
                        $this->getString($invoice_data, 'invoice.status')
                    ))
                ] ?? Invoice::STATUS_SENT,
            // 'archived' => $status === 'archived',
        ];

        /* If we can't find the client, then lets try and create a client */
        if (! $transformed['client_id']) {
            $client_transformer = new ClientTransformer($this->company);

            $transformed['client'] = $client_transformer->transform(
                $invoice_data
            );
        }

        if (isset($invoice_data['payment.amount'])) {
            $transformed['payments'] = [
                [
                    'date' => isset($invoice_data['payment.date'])
                        ? $this->parseDate($invoice_data['payment.date'])
                        : date('y-m-d'),
                    'transaction_reference' => $this->getString(
                        $invoice_data,
                        'payment.transaction_reference'
                    ),
                    'amount' => $this->getFloat(
                        $invoice_data,
                        'payment.amount'
                    ),
                ],
            ];
        } elseif ($status === 'paid') {
            $transformed['payments'] = [
                [
                    'date' => isset($invoice_data['payment.date'])
                        ? $this->parseDate($invoice_data['payment.date'])
                        : date('y-m-d'),
                    'transaction_reference' => $this->getString(
                        $invoice_data,
                        'payment.transaction_reference'
                    ),
                    'amount' => $this->getFloat(
                        $invoice_data,
                        'invoice.amount'
                    ),
                ],
            ];
        }
        // elseif (
        //     isset($transformed['amount']) &&
        //     isset($transformed['balance']) &&
        //     $transformed['amount'] != $transformed['balance']
        // ) {
        //     $transformed['payments'] = [
        //         [
        //             'date' => isset($invoice_data['payment.date'])
        //                 ? $this->parseDate($invoice_data['payment.date'])
        //                 : date('y-m-d'),
        //             'transaction_reference' => $this->getString(
        //                 $invoice_data,
        //                 'payment.transaction_reference'
        //             ),
        //             'amount' => $transformed['amount'] - $transformed['balance'],
        //         ],
        //     ];
        // }

        $line_items = [];
        foreach ($line_items_data as $record) {
            $line_items[] = [
                'quantity' => $this->getFloat($record, 'item.quantity'),
                'cost' => $this->getFloat($record, 'item.cost'),
                'product_key' => $this->getString($record, 'item.product_key'),
                'notes' => $this->getString($record, 'item.notes'),
                'discount' => $this->getFloat($record, 'item.discount'),
                'is_amount_discount' => filter_var(
                    $this->getString($record, 'item.is_amount_discount'),
                    FILTER_VALIDATE_BOOLEAN,
                    FILTER_NULL_ON_FAILURE
                ),
                'tax_name1' => $this->getString($record, 'item.tax_name1'),
                'tax_rate1' => $this->getFloat($record, 'item.tax_rate1'),
                'tax_name2' => $this->getString($record, 'item.tax_name2'),
                'tax_rate2' => $this->getFloat($record, 'item.tax_rate2'),
                'tax_name3' => $this->getString($record, 'item.tax_name3'),
                'tax_rate3' => $this->getFloat($record, 'item.tax_rate3'),
                'custom_value1' => $this->getString(
                    $record,
                    'item.custom_value1'
                ),
                'custom_value2' => $this->getString(
                    $record,
                    'item.custom_value2'
                ),
                'custom_value3' => $this->getString(
                    $record,
                    'item.custom_value3'
                ),
                'custom_value4' => $this->getString(
                    $record,
                    'item.custom_value4'
                ),
                'type_id' => $this->getInvoiceTypeId($record, 'item.type_id'),
            ];
        }

        $transformed['line_items'] = $this->cleanItems($line_items);

        return $transformed;
    }
}

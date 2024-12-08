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

namespace App\Import\Transformer\Csv;

use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\Quote;
use App\Utils\Traits\CleanLineItems;

/**
 * Class QuoteTransformer.
 */
class QuoteTransformer extends BaseTransformer
{
    use CleanLineItems;

    /**
     * @param $data
     *
     * @return bool|array
     */
    public function transform($line_items_data)
    {
        $quote_data = reset($line_items_data);

        if ($this->hasQuote($quote_data['quote.number'])) {
            throw new ImportException('Quote number already exists');
        }

        $quoteStatusMap = [
            'sent' => Quote::STATUS_SENT,
            'draft' => Quote::STATUS_DRAFT,
        ];

        $transformed = [
            'company_id' => $this->company->id,
            'number' => $this->getString($quote_data, 'quote.number'),
            'user_id' => $this->getString($quote_data, 'quote.user_id'),
            'amount' => ($amount = $this->getFloat(
                $quote_data,
                'quote.amount'
            )),
            'balance' => isset($quote_data['quote.balance'])
                ? $this->getFloat($quote_data, 'quote.balance')
                : $amount,
            'client_id' => $this->getClient(
                $this->getString($quote_data, 'client.name'),
                $this->getString($quote_data, 'client.email')
            ),
            'discount' => $this->getFloat($quote_data, 'quote.discount'),
            'po_number' => $this->getString($quote_data, 'quote.po_number'),
            'date' => isset($quote_data['quote.date'])
                ? $this->parseDate($quote_data['quote.date'])
                : now()->format('Y-m-d'),
            'due_date' => isset($quote_data['quote.due_date'])
                ? $this->parseDate($quote_data['quote.due_date'])
                : null,
            'terms' => $this->getString($quote_data, 'quote.terms'),
            'public_notes' => $this->getString(
                $quote_data,
                'quote.public_notes'
            ),
            'private_notes' => $this->getString(
                $quote_data,
                'quote.private_notes'
            ),
            'tax_name1' => $this->getString($quote_data, 'quote.tax_name1'),
            'tax_rate1' => $this->getFloat($quote_data, 'quote.tax_rate1'),
            'tax_name2' => $this->getString($quote_data, 'quote.tax_name2'),
            'tax_rate2' => $this->getFloat($quote_data, 'quote.tax_rate2'),
            'tax_name3' => $this->getString($quote_data, 'quote.tax_name3'),
            'tax_rate3' => $this->getFloat($quote_data, 'quote.tax_rate3'),
            'custom_value1' => $this->getString(
                $quote_data,
                'quote.custom_value1'
            ),
            'custom_value2' => $this->getString(
                $quote_data,
                'quote.custom_value2'
            ),
            'custom_value3' => $this->getString(
                $quote_data,
                'quote.custom_value3'
            ),
            'custom_value4' => $this->getString(
                $quote_data,
                'quote.custom_value4'
            ),
            'footer' => $this->getString($quote_data, 'quote.footer'),
            'partial' => $this->getFloat($quote_data, 'quote.partial'),
            'partial_due_date' =>  isset($quote_data['quote.partial_due_date']) ? $this->parseDate($quote_data['quote.partial_due_date']) : null,
            'custom_surcharge1' => $this->getString(
                $quote_data,
                'quote.custom_surcharge1'
            ),
            'custom_surcharge2' => $this->getString(
                $quote_data,
                'quote.custom_surcharge2'
            ),
            'custom_surcharge3' => $this->getString(
                $quote_data,
                'quote.custom_surcharge3'
            ),
            'custom_surcharge4' => $this->getString(
                $quote_data,
                'quote.custom_surcharge4'
            ),
            'exchange_rate' => $this->getFloatOrOne(
                $quote_data,
                'quote.exchange_rate'
            ),
            'status_id' => $quoteStatusMap[
                    ($status = strtolower(
                        $this->getString($quote_data, 'quote.status')
                    ))
                ] ?? Quote::STATUS_SENT,
            // 'archived' => $status === 'archived',
        ];

        /* If we can't find the client, then lets try and create a client */
        if (! $transformed['client_id']) {
            $client_transformer = new ClientTransformer($this->company);

            $transformed['client'] = $client_transformer->transform(
                $quote_data
            );
        }

        if (isset($quote_data['payment.amount'])) {
            $transformed['payments'] = [
                [
                    'date' => isset($quote_data['payment.date'])
                        ? $this->parseDate($quote_data['payment.date'])
                        : date('y-m-d'),
                    'transaction_reference' => $this->getString(
                        $quote_data,
                        'payment.transaction_reference'
                    ),
                    'amount' => $this->getFloat(
                        $quote_data,
                        'payment.amount'
                    ),
                ],
            ];
        } elseif ($status === 'paid') {
            $transformed['payments'] = [
                [
                    'date' => isset($quote_data['payment.date'])
                        ? $this->parseDate($quote_data['payment.date'])
                        : date('y-m-d'),
                    'transaction_reference' => $this->getString(
                        $quote_data,
                        'payment.transaction_reference'
                    ),
                    'amount' => $this->getFloat(
                        $quote_data,
                        'quote.amount'
                    ),
                ],
            ];
        } elseif (
            isset($transformed['amount']) &&
            isset($transformed['balance']) &&
            $transformed['amount'] != $transformed['balance']
        ) {
            $transformed['payments'] = [
                [
                    'date' => isset($quote_data['payment.date'])
                        ? $this->parseDate($quote_data['payment.date'])
                        : date('y-m-d'),
                    'transaction_reference' => $this->getString(
                        $quote_data,
                        'payment.transaction_reference'
                    ),
                    'amount' => $transformed['amount'] - $transformed['balance'],
                ],
            ];
        }

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
                'type_id' => '1', //$this->getQuoteTypeId( $record, 'item.type_id' ),
            ];
        }
        $transformed['line_items'] = $this->cleanItems($line_items);

        return $transformed;
    }
}

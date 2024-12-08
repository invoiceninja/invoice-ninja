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

namespace App\Import\Transformer\Invoice2Go;

use App\DataMapper\InvoiceItem;
use App\Import\ImportException;
use App\Import\Transformer\BaseTransformer;
use App\Models\Invoice;
use Illuminate\Support\Str;

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
    private $is_amount_discount = false;

    public function transform($invoice_data)
    {
        if (!isset($invoice_data['DocumentNumber'])) {
            throw new ImportException('DocumentNumber key not found in this import file.');
        }

        if ($this->hasInvoice($invoice_data['DocumentNumber'])) {
            throw new ImportException('Invoice number already exists');
        }

        $invoiceStatusMap = [
            'unsent' => Invoice::STATUS_DRAFT,
            'sent'   => Invoice::STATUS_SENT,
            'fully_paid' => Invoice::STATUS_PAID,
        ];

        $this->is_amount_discount = $this->getFloat($invoice_data, 'Discount') > 0 ? true : false;

        $transformed = [
            'is_amount_discount' => $this->is_amount_discount,
            'discount' => $this->getFloat($invoice_data, 'Discount'),
            'company_id'  => $this->company->id,
            'number'      => $this->getString($invoice_data, 'DocumentNumber'),
            'notes'       => $this->getString($invoice_data, 'Comment'),
            'date'        => isset($invoice_data['DocumentDate']) ? $this->parseDate($invoice_data['DocumentDate']) : null,
            // 'currency_id' => $this->getCurrencyByCode( $invoice_data, 'Currency' ),
            'amount'      => $this->getFloat($invoice_data, 'TotalAmount'),
            'status_id'   => $invoiceStatusMap[$status =
                    strtolower($this->getString($invoice_data, 'DocumentStatus'))] ?? Invoice::STATUS_SENT,
            // 'viewed'      => $status === 'viewed',
            'line_items'  => $this->harvestLineItems($invoice_data),
        ];

        $client_id = null;

        // if($this->hasClient($this->getString($invoice_data, 'Name') || $this->getContact($this->getString($invoice_data, 'EmailRecipient')))) {

        $client_id = $this->getClient($this->getString($invoice_data, 'Name'), $this->getString($invoice_data, 'EmailRecipient'));

        // }

        if ($client_id) {
            $transformed['client_id'] = $client_id;
        }

        $settings = new \stdClass();
        $settings->currency_id = $this->getCurrencyByCode($invoice_data, 'Currency');

        $transformed['client'] = [
            'name'              => $this->getString($invoice_data, 'Name'),
            'address1'          => $this->getString($invoice_data, 'DocumentRecipientAddress'),
            'shipping_address1' => $this->getString($invoice_data, 'ShipAddress'),
            'credit_balance'    => 0,
            'settings'          => $settings,
            'client_hash'       => Str::random(40),
            'contacts'          => [
                [
                    'email' => $this->getString($invoice_data, 'EmailRecipient'),
                ],
            ],
        ];

        $addresses = $this->harvestAddresses($invoice_data);

        $transformed['client'] = array_merge($transformed['client'], $addresses);

        if (! empty($invoice_data['Date Paid'])) {
            $transformed['payments'] = [
                [
                    'date'   => $this->parseDate($invoice_data['DatePaid']),
                    'amount' => $this->getFloat($invoice_data, 'Payments'),
                ],
            ];
        } elseif (isset($invoice_data['AmountPaidAmount']) && isset($invoice_data['DatePaid'])) {
            $transformed['payments'] = [
                [
                    'date'   => $this->parseDate($invoice_data['DatePaid']),
                    'amount' => $this->getFloat($invoice_data, 'AmountPaidAmount'),
                ]
            ];
        } elseif (isset($invoice_data['DocumentStatus']) && $invoice_data['DocumentStatus'] == 'fully_paid') {

            $transformed['payments'] = [
                [
                    'date'   => isset($invoice_data['DatePaid']) ? $this->parseDate($invoice_data['DatePaid']) : ($this->parseDate($invoice_data['DocumentDate']) ?? now()->format('Y-m-d')),
                    'amount' => $this->getFloat($invoice_data, 'TotalAmount'),
                ]
            ];

        }
        return $transformed;
    }


    private function harvestAddresses($invoice_data)
    {
        $address = $invoice_data['DocumentRecipientAddress'];

        $lines = explode("\n", $address);

        $billing_address = [];
        if (count($lines) == 2) {
            $billing_address['address1'] = $lines[0];

            $parts = explode(",", $lines[1]);

            if (count($parts) == 3) {
                $billing_address['city'] = $parts[0];
                $billing_address['state'] = $parts[1];
                $billing_address['postal_code'] = $parts[2];
            }

        }

        $shipaddress = $invoice_data['ShipAddress'] ?? '';
        $shipping_address = [];

        $lines = explode("\n", $shipaddress);

        if (count($lines) == 2) {
            $shipping_address['address1'] = $lines[0];

            $parts = explode(",", $lines[1]);

            if (count($parts) == 3) {
                $shipping_address['shipping_city'] = $parts[0];
                $shipping_address['shipping_state'] = $parts[1];
                $shipping_address['shipping_postal_code'] = $parts[2];
            }


        }

        return array_merge($billing_address, $shipping_address);

    }


    /*

    Sample invoice2go line item
      "code" => "",
      "description" => "",
      "qty" => "1",
      "unit_type" => "parts",
      "withholding_tax_applies" => "false",
      "applied_taxes" => "",
      "unit_price" => "150",
      "discount_percentage" => "50",
      "discount_type" => "percentage", //amount
      "discount_amount" => "0",

    */



    private function harvestLineItems(array $invoice_data): array
    {

        $default_data =
            [
                [
                    'cost'             	  => $this->getFloat($invoice_data, 'TotalAmount'),
                    'quantity'           => 1,
                    'discount'           => $this->getFloat($invoice_data, 'DiscountValue'),
                    'is_amount_discount' => false,
                ],
            ];

        if (!isset($invoice_data['Items'])) {
            return $default_data;
        }

        // Parse the main CSV data
        $processed = $this->parseCsvWithNestedCsv($invoice_data['Items']);

        $line_items = [];

        foreach ($processed as $item) {
            $_item['cost'] = $item['unit_price'];
            $_item['quantity'] = $item['qty'] ?? 1;
            $_item['discount'] = $item['discount_percentage'] > $item['discount_amount'] ? $item['discount_percentage'] : $item['discount_amount'];
            $_item['is_amount_discount'] = $item['discount_type'] == 'percentage' ? false : true;
            $_item['product_key'] = $item['code'] ?? '';
            $_item['notes'] = $item['description'] ?? '';

            $_item = $this->parseTaxes($_item, $item);
            $this->is_amount_discount = $_item['is_amount_discount'];

            $line_items[] = $_item;
            $_item = [];

        }

        return $line_items;
    }

    private function parseTaxes($ninja_item, $i2g_item): array
    {
        if (is_string($i2g_item['applied_taxes'])) {
            return $ninja_item;
        }

        $ninja_item['tax_name1'] = 'Tax';
        $ninja_item['tax_rate1'] = $i2g_item['applied_taxes']['rate'];

        return $ninja_item;

    }


    public function parseCsvWithNestedCsv($csvString, $delimiter = ',', $enclosure = '"', $lineEnding = ';')
    {
        // Regular expression to find nested CSVs
        $nestedCsvPattern = '/"([^"]*(?:""[^"]*)*)"/';
        preg_match_all($nestedCsvPattern, $csvString, $matches);

        // Replace nested CSVs with placeholders
        $placeholders = [];
        foreach ($matches[0] as $index => $match) {
            $placeholder = '___PLACEHOLDER_' . $index . '___';
            $placeholders[$placeholder] = $match;
            $csvString = str_replace($match, $placeholder, $csvString);
        }

        // Parse the main CSV
        $rows = explode($lineEnding, $csvString);
        $parsedRows = [];
        foreach ($rows as $row) {
            $parsedRow = str_getcsv($row, $delimiter, $enclosure);
            $parsedRows[] = $parsedRow;
        }

        // Replace placeholders with parsed nested CSVs
        foreach ($parsedRows as &$row) {
            foreach ($row as &$field) {
                if (isset($placeholders[$field])) {
                    $field = str_getcsv($placeholders[$field], $delimiter, $enclosure);
                }
            }
        }


        foreach ($parsedRows as $key => &$row) {

            if ($key == 0) {
                continue;
            }
            /** @var array $row */
            if (is_array($row[5])) {
                $csv = str_getcsv($row[5][0], ";");
                $row[5] = array_combine(explode(",", $csv[0]), explode(",", $csv[1]));

            }

            if (is_array($row[1])) {
                $row[1] = $row[1][0];
            }

            $row = array_combine($parsedRows[0], $row);
        }

        unset($parsedRows[0]);

        return $parsedRows;
    }


}

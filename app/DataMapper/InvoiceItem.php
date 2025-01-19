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

namespace App\DataMapper;

class InvoiceItem
{
    public $quantity = 0;

    public $net_cost = 0;

    public $cost = 0;

    public $product_key = '';

    public $product_cost = 0;

    public $notes = '';

    public $discount = 0;

    public $is_amount_discount = false;

    public $tax_name1 = '';

    public $tax_rate1 = 0;

    public $tax_name2 = '';

    public $tax_rate2 = 0;

    public $tax_name3 = '';

    public $tax_rate3 = 0;

    public $sort_id = '0';

    public $line_total = 0;

    public $gross_line_total = 0;

    public $tax_amount = 0;

    public $date = '';

    public $custom_value1 = '';

    public $custom_value2 = '';

    public $custom_value3 = '';

    public $custom_value4 = '';

    public $type_id = '1'; //1 = product, 2 = service, 3 unpaid gateway fee, 4 paid gateway fee, 5 late fee, 6 expense

    public $tax_id = '';

    public $task_id = '';

    public $expense_id = '';

    public $unit_code = 'C62';

    public static $casts = [
        'net_cost' => 'float',
        'task_id' => 'string',
        'expense_id' => 'string',
        'tax_id' => 'string',
        'type_id' => 'string',
        'quantity' => 'float',
        'cost' => 'float',
        'product_cost' => 'float',
        'product_key' => 'string',
        'notes' => 'string',
        'discount' => 'float',
        'is_amount_discount' => 'bool',
        'tax_name1' => 'string',
        'tax_name2' => 'string',
        'tax_name3' => 'string',
        'tax_rate1' => 'float',
        'tax_rate2' => 'float',
        'tax_rate3' => 'float',
        'sort_id' => 'string',
        'line_total' => 'float',
        'gross_line_total' => 'float',
        'tax_amount' => 'float',
        'date' => 'string',
        'custom_value1' => 'string',
        'custom_value2' => 'string',
        'custom_value3' => 'string',
        'custom_value4' => 'string',
        'unit_code' => 'string',
    ];
}

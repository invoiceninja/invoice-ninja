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

namespace App\Factory;

use Faker\Factory;
use stdClass;

//use Faker\Generator as Faker;

class InvoiceItemFactory
{
    public static function create(): stdClass
    {
        $item = new stdClass();
        $item->quantity = 0;
        $item->cost = 0;
        $item->product_key = '';
        $item->notes = '';
        $item->discount = 0;
        $item->is_amount_discount = true;
        $item->tax_name1 = '';
        $item->tax_rate1 = 0;
        $item->tax_name2 = '';
        $item->tax_rate2 = 0;
        $item->tax_name3 = '';
        $item->tax_rate3 = 0;
        $item->sort_id = 0;
        $item->line_total = 0;
        $item->gross_line_total = 0;
        $item->custom_value1 = '';
        $item->custom_value2 = '';
        $item->custom_value3 = '';
        $item->custom_value4 = '';
        $item->type_id = '1';
        $item->tax_id = '1';

        return $item;
    }

    /**
     * Generates an array of dummy data for invoice items.
     * @param  int    $items Number of line items to create
     * @return array        array of objects
     */
    public static function generate(int $items = 1): array
    {
        $faker = Factory::create();

        $data = [];

        for ($x = 0; $x < $items; $x++) {
            $item = self::create();
            $item->quantity = rand(1, 10);
            $item->cost = $faker->randomFloat(2, 1, 1000);
            $item->line_total = $item->quantity * $item->cost;
            $item->is_amount_discount = true;
            $item->discount = rand(1, 10);
            $item->notes = str_replace(['"',"'"], ['',""], $faker->realText(20));
            $item->product_key = $faker->word();
            // $item->custom_value1 = $faker->realText(10);
            // $item->custom_value2 = $faker->realText(10);
            // $item->custom_value3 = $faker->realText(10);
            // $item->custom_value4 = $faker->realText(10);
            $item->tax_name1 = 'GST';
            $item->tax_rate1 = 10.00;
            $item->type_id = '1';

            $data[] = $item;
        }


            $item = self::create();
            $item->quantity = rand(1, 10);
            $item->cost = $faker->randomFloat(2, 1, 1000);
            $item->line_total = $item->quantity * $item->cost;
            $item->is_amount_discount = true;
            $item->discount = rand(1, 10);
            $item->notes = str_replace(['"',"'"], ['',""], $faker->realText(20));
            $item->product_key = $faker->word();
            // $item->custom_value1 = $faker->realText(10);
            // $item->custom_value2 = $faker->realText(10);
            // $item->custom_value3 = $faker->realText(10);
            // $item->custom_value4 = $faker->realText(10);
            $item->tax_name1 = 'GST';
            $item->tax_rate1 = 10.00;
            $item->type_id = '2';

            $data[] = $item;


        return $data;
    }

    /**
     * Generates an array of dummy data for invoice items.
     * @param  int    $items Number of line items to create
     * @return array        array of objects
     */
    public static function generateCredit(int $items = 1): array
    {
        $faker = Factory::create();

        $data = [];

        for ($x = 0; $x < $items; $x++) {
            $item = self::create();
            $item->quantity = rand(-1, -10);
            $item->cost = $faker->randomFloat(2, -1, -1000);
            $item->line_total = $item->quantity * $item->cost;
            $item->is_amount_discount = true;
            $item->discount = 0;
            $item->notes = $faker->realText(20);
            $item->product_key = $faker->word();
            // $item->custom_value1 = $faker->realText(10);
            // $item->custom_value2 = $faker->realText(10);
            // $item->custom_value3 = $faker->realText(10);
            // $item->custom_value4 = $faker->realText(10);
            $item->tax_name1 = '';
            $item->tax_rate1 = 0;
            $item->type_id = '1';

            $data[] = $item;
        }

        return $data;
    }
}

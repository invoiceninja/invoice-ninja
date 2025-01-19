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

namespace App\Utils\Traits;

use App\Models\Invoice;
use App\Utils\Helpers;
use App\Utils\Number;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * Class MakesInvoiceValues.
 */
trait MakesInvoiceValues
{
    /**
     * Master list of columns used
     * for invoice tables.
     * @var array
     */
    private static $master_columns = [
        'date',
        'discount',
        'product_key',
        'notes',
        'cost',
        'quantity',
        'tax_name1',
        'tax_name2',
        'tax_name3',
        'line_total',
        'custom_label1',
        'custom_label2',
        'custom_label3',
        'custom_label4',
    ];

    private static $custom_label_fields = [
        'invoice1',
        'invoice2',
        'invoice3',
        'invoice4',
        'surcharge1',
        'surcharge2',
        'surcharge3',
        'surcharge4',
        'client1',
        'client2',
        'client3',
        'client4',
        'contact1',
        'contact2',
        'contact3',
        'contact4',
        'company1',
        'company2',
        'company3',
        'company4',
    ];

    private function findCustomType($field)
    {
        $custom_fields = $this->company->custom_fields;

        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};
            $custom_field_parts = explode('|', $custom_field);

            return $custom_field_parts[1];
        }

        return '';
    }

    public function makeLabels($contact = null): array
    {
        $data = [];

        $values = $this->makeLabelsAndValues($contact);

        foreach ($values as $key => $value) {
            $data[$key.'_label'] = $value['label'];
        }

        return $data;
    }

    /**
     * Transforms all placeholders
     * to invoice values.
     *
     * @param null $contact
     * @return array returns an array
     * of keyed labels (appended with _label)
     */
    public function makeValues($contact = null): array
    {
        $data = [];

        $values = $this->makeLabelsAndValues($contact);

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }

    /**
     * V2 of building a table header for PDFs.
     * @param  array $columns The array (or string of column headers)
     * @return string  injectable HTML string
     */
    public function buildTableHeader($columns): ?string
    {
        $data = $this->makeLabels();

        $table_header = '<tr>';

        foreach ($columns as $key => $column) {
            $table_header .= '<td class="table_header_td_class">'.$column.'_label</td>';
        }

        $table_header .= '</tr>';

        $table_header = strtr($table_header, $data); // str_replace(array_keys($data), array_values($data), $table_header);

        return $table_header;
    }

    /**
     * V2 of building a table body for PDFs.
     * @param array $default_columns
     * @param $user_columns
     * @param string $table_prefix
     * @return string  injectable HTML string
     */
    public function buildTableBody(array $default_columns, $user_columns, string $table_prefix): ?string
    {
        $items = $this->transformLineItems($this->line_items, $table_prefix);

        if (count($items) == 0) {
            return '';
        }

        $data = $this->makeValues();

        $output = '';

        if (strlen($user_columns) > 1) {
            foreach ($items as $key => $item) {
                $tmp = strtr($user_columns, $data);
                $tmp = strtr($tmp, $item);

                $output .= $tmp;
            }
        } else {
            $table_row = '<tr>';

            foreach ($default_columns as $key => $column) {
                $table_row .= '<td class="table_header_td_class">'.$column.'</td>';
            }

            $table_row .= '</tr>';

            foreach ($items as $key => $item) {
                $tmp = strtr($table_row, $item);
                $tmp = strtr($tmp, $data);

                $output .= $tmp;
            }
        }

        return $output;
    }

    /**
     * Transform the column headers into translated header values.
     *
     * @param  array  $columns The column header values
     * @return array          The new column header variables
     */
    private function transformColumnsForHeader(array $columns): array
    {
        if (count($columns) == 0) {
            return [];
        }

        $pre_columns = $columns;
        $columns = array_intersect($columns, self::$master_columns);

        return str_replace(
            [
                'tax_name1',
                'tax_name2',
                'tax_name3',
                'tax_amount',
            ],
            [
                'tax',
                'tax',
                'tax',
                'tax',
            ],
            $columns
        );
    }

    /**
     * Transform the column headers into invoice variables.
     *
     * @param  array  $columns The column header values
     * @return array          The invoice variables
     */
    private function transformColumnsForLineItems(array $columns): array
    {
        /* Removes any invalid columns the user has entered. */
        $columns = array_intersect($columns, self::$master_columns);

        return str_replace(
            [
                'custom_invoice_label1',
                'custom_invoice_label2',
                'custom_invoice_label3',
                'custom_invoice_label4',
                'tax_name1',
                'tax_name2',
                'tax_name3',
            ],
            [
                'custom_invoice_value1',
                'custom_invoice_value2',
                'custom_invoice_value3',
                'custom_invoice_value4',
                'tax_rate1',
                'tax_rate2',
                'tax_rate3',
            ],
            $columns
        );
    }

    /**
     * Formats the line items for display.
     *
     * @param mixed $items
     * @param string $table_type
     * @param mixed|null $custom_fields
     *
     * @return array
     */
    public function transformLineItems($items, $table_type = '$product'): array
    {   //$start = microtime(true);
        $entity = $this->client ? $this->client : $this->vendor;

        $data = [];

        if (! is_array($items)) {
        }

        $locale_info = localeconv();

        $entity_currency = $entity->currency();

        foreach ($items as $key => $item) {
            if ($table_type == '$product' && $item->type_id != 1) {
                if ($item->type_id != 4 && $item->type_id != 6 && $item->type_id != 5) {
                    continue;
                }
            }

            if (($table_type == '$task' && $item->type_id != 2)) {
                continue;
            }

            $helpers = new Helpers();
            $_table_type = ltrim($table_type, '$'); // From $product -> product.

            $data[$key][$table_type.'.product_key'] = is_null(optional($item)->product_key) ? $item->item : $item->product_key;
            $data[$key][$table_type.'.item'] = is_null(optional($item)->item) ? $item->product_key : $item->item;
            $data[$key][$table_type.'.service'] = is_null(optional($item)->service) ? $item->product_key : $item->service;

            $currentDateTime = null;
            if (isset($this->entity->next_send_date)) {
                $currentDateTime = Carbon::parse($this->entity->next_send_date);
            }

            $data[$key][$table_type.'.notes'] = Helpers::processReservedKeywords($item->notes, $entity, $currentDateTime);
            $data[$key][$table_type.'.description'] = Helpers::processReservedKeywords($item->notes, $entity, $currentDateTime);

            $data[$key][$table_type.".{$_table_type}1"] = strlen($item->custom_value1) >= 1 ? $helpers->formatCustomFieldValue($this->company->custom_fields, "{$_table_type}1", $item->custom_value1, $entity) : '';
            $data[$key][$table_type.".{$_table_type}2"] = strlen($item->custom_value2) >= 1 ? $helpers->formatCustomFieldValue($this->company->custom_fields, "{$_table_type}2", $item->custom_value2, $entity) : '';
            $data[$key][$table_type.".{$_table_type}3"] = strlen($item->custom_value3) >= 1 ? $helpers->formatCustomFieldValue($this->company->custom_fields, "{$_table_type}3", $item->custom_value3, $entity) : '';
            $data[$key][$table_type.".{$_table_type}4"] = strlen($item->custom_value4) >= 1 ? $helpers->formatCustomFieldValue($this->company->custom_fields, "{$_table_type}4", $item->custom_value4, $entity) : '';

            if ($item->quantity > 0 || $item->cost > 0) {
                $data[$key][$table_type.'.quantity'] = Number::formatValueNoTrailingZeroes($item->quantity, $entity);

                $data[$key][$table_type.'.unit_cost'] = Number::formatMoneyNoRounding($item->cost, $entity);

                $data[$key][$table_type.'.cost'] = Number::formatMoney($item->cost, $entity);

                $data[$key][$table_type.'.line_total'] = Number::formatMoney($item->line_total, $entity);
            } else {
                $data[$key][$table_type.'.quantity'] = '';

                $data[$key][$table_type.'.unit_cost'] = '';

                $data[$key][$table_type.'.cost'] = '';

                $data[$key][$table_type.'.line_total'] = '';
            }

            if (property_exists($item, 'gross_line_total')) {
                $data[$key][$table_type.'.gross_line_total'] = ($item->gross_line_total == 0) ? '' : Number::formatMoney($item->gross_line_total, $entity);
            } else {
                $data[$key][$table_type.'.gross_line_total'] = '';
            }

            if (property_exists($item, 'tax_amount')) {
                $data[$key][$table_type.'.tax_amount'] = ($item->tax_amount == 0) ? '' : Number::formatMoney($item->tax_amount, $entity);
            } else {
                $data[$key][$table_type.'.tax_amount'] = '';
            }

            if (isset($item->discount) && $item->discount > 0) {
                if ($item->is_amount_discount) {
                    $data[$key][$table_type.'.discount'] = Number::formatMoney($item->discount, $entity);
                } else {
                    $data[$key][$table_type.'.discount'] = floatval($item->discount).'%';
                }
            } else {
                $data[$key][$table_type.'.discount'] = '';
            }

            // Previously we used to check for tax_rate value,
            // but that's no longer necessary.

            if (isset($item->tax_rate1)) {
                $data[$key][$table_type.'.tax_rate1'] = floatval($item->tax_rate1).'%';
                $data[$key][$table_type.'.tax1'] = &$data[$key][$table_type.'.tax_rate1'];
            }

            if (isset($item->tax_rate2)) {
                $data[$key][$table_type.'.tax_rate2'] = floatval($item->tax_rate2).'%';
                $data[$key][$table_type.'.tax2'] = &$data[$key][$table_type.'.tax_rate2'];
            }

            if (isset($item->tax_rate3)) {
                $data[$key][$table_type.'.tax_rate3'] = floatval($item->tax_rate3).'%';
                $data[$key][$table_type.'.tax3'] = &$data[$key][$table_type.'.tax_rate3'];
            }

            $data[$key]['task_id'] = property_exists($item, 'task_id') ? $item->task_id : '';
        }

        //nlog(microtime(true) - $start);

        return $data;
    }

    /**
     * Due to the way we are compiling the blade template we
     * have no ability to iterate, so in the case
     * of line taxes where there are multiple rows,
     * we use this function to format a section of rows.
     *
     * @return string a collection of <tr> rows with line item
     * aggregate data
     */
    private function makeLineTaxes(): string
    {
        $tax_map = $this->calc()->getTaxMap();
        $entity = $this->client ? $this->client : $this->vendor;

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<tr class="line_taxes">';
            $data .= '<td>'.$tax['name'].'</td>';
            $data .= '<td>'.Number::formatMoney($tax['total'], $entity).'</td></tr>';
        }

        return $data;
    }

    /**
     * @return string a collectino of <tr> with
     * itemised total tax data
     */
    private function makeTotalTaxes(): string
    {
        $data = '';
        $entity = $this->client ? $this->client : $this->vendor;

        if (! $this->calc()->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->calc()->getTotalTaxMap() as $tax) {
            $data .= '<tr class="total_taxes">';
            $data .= '<td>'.$tax['name'].'</td>';
            $data .= '<td>'.Number::formatMoney($tax['total'], $entity).'</td></tr>';
        }

        return $data;
    }

    private function totalTaxLabels(): string
    {
        $data = '';

        if (! $this->calc()->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->calc()->getTotalTaxMap() as $tax) {
            $data .= '<span>'.$tax['name'].'</span>';
        }

        return $data;
    }

    private function totalTaxValues(): string
    {
        $data = '';
        $entity = $this->client ? $this->client : $this->vendor;

        if (! $this->calc()->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->calc()->getTotalTaxMap() as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $entity).'</span>';
        }

        return $data;
    }

    private function lineTaxLabels(): string
    {
        $tax_map = $this->calc()->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.$tax['name'].'</span>';
        }

        return $data;
    }

    private function lineTaxValues(): string
    {
        $tax_map = $this->calc()->getTaxMap();
        $entity = $this->client ? $this->client : $this->vendor;

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $entity).'</span>';
        }

        return $data;
    }

    /*
    | Ensures the URL doesn't have duplicated trailing slash
    */
    public function generateAppUrl()
    {
        //return rtrim(config('ninja.app_url'), "/");
        return config('ninja.app_url');
    }

    /**
     * Builds CSS to assist with the generation
     * of Repeating headers and footers on the PDF.
     * @return string The css string
     */
    public function generateCustomCSS(): string
    {
        $settings = $this->client ? $this->client->getMergedSettings() : $this->company->settings;

        $header_and_footer = '
.header, .header-space {
  height: 160px;
}

.footer, .footer-space {
  height: 160px;
}

.footer {
  position: fixed;
  bottom: 0;
  width: 100%;
}

.header {
  position: fixed;
  top: 0mm;
  width: 100%;
}

@media print {
   thead {display: table-header-group;}
   tfoot {display: table-footer-group;}
   button {display: none;}
   body {margin: 0;}
}';

        $header = '
.header, .header-space {
  height: 160px;
}

.header {
  position: fixed;
  top: 0mm;
  width: 100%;
}

@media print {
   thead {display: table-header-group;}
   button {display: none;}
   body {margin: 0;}
}';

        $footer = '

.footer, .footer-space {
  height: 160px;
}

.footer {
  position: fixed;
  bottom: 0;
  width: 100%;
}

@media print {
   tfoot {display: table-footer-group;}
   button {display: none;}
   body {margin: 0;}
}';
        $css = '';

        if ($settings->all_pages_header && $settings->all_pages_footer) {
            $css .= $header_and_footer;
        } elseif ($settings->all_pages_header && ! $settings->all_pages_footer) {
            $css .= $header;
        } elseif (! $settings->all_pages_header && $settings->all_pages_footer) {
            $css .= $footer;
        }

        $css .= '
.page {
  page-break-after: always;
}

@page {
  margin: 0mm
}

html {
        ';

        $css .= 'font-size:'.$settings->font_size.'px;';
        //        $css .= 'font-size:14px;';

        $css .= '}';

        return $css;
    }
}

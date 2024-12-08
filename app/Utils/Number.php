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

namespace App\Utils;

use App\Models\Company;
use App\Models\Currency;

/**
 * Class Number.
 */
class Number
{
    /**
     * @param float $value
     * @param int   $precision
     *
     * @return float
     */
    public static function roundValue(float $value, int $precision = 2): float
    {
        return round($value, $precision, PHP_ROUND_HALF_UP);
    }

    /**
     * Formats a given value based on the clients currency.
     *
     * @param  float  $value    The number to be formatted
     * @param  object $currency The client currency object
     *
     * @return string           The formatted value
     */
    public static function formatValue($value, $currency): string
    {
        $value = floatval($value);

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;

        return number_format($value, $precision, $decimal, $thousand);
    }

    /**
     * Formats a given value based on the clients currency.
     *
     * @param  float  $value    The number to be formatted
     *
     * @return string           The formatted value
     */
    public static function formatValueNoTrailingZeroes($value, $entity): string
    {
        $value = floatval($value);

        $currency = $entity->currency();

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        // $precision = $currency->precision;

        if ($entity instanceof Company) {
            $country = $entity->country();
        } else {
            $country = $entity->country;
        }

        /* Country settings override client settings */
        if (isset($country->thousand_separator) && strlen($country->thousand_separator) >= 1) {
            $thousand = $country->thousand_separator;
        }

        if (isset($country->decimal_separator) && strlen($country->decimal_separator) >= 1) {
            $decimal = $country->decimal_separator;
        }

        $precision = 10;

        return rtrim(rtrim(number_format($value, $precision, $decimal, $thousand), '0'), $decimal);
    }

    public static function parseFloat($value)
    {

        if (!$value) {
            return 0;
        }

        //remove everything except for numbers, decimals, commas and hyphens
        $value = preg_replace('/[^0-9.,-]+/', '', $value);

        $decimal = strpos($value, '.');
        $comma = strpos($value, ',');

        if ($comma === false) { //no comma must be a decimal number already
            return (float) $value;
        }

        if (!$decimal && substr($value, -3, 1) != ",") {
            $value = $value.".00";
        }

        $decimal = strpos($value, '.');

        if ($decimal < $comma) { //decimal before a comma = euro
            $value = str_replace(['.',','], ['','.'], $value);
            return (float) $value;
        }

        //comma first = traditional thousand separator
        $value = str_replace(',', '', $value);

        return (float)$value;


    }

    /**
     * Formats a given value based on the clients currency
     * BACK to a float.
     *
     * @param string $value The formatted number to be converted back to float
     * @return float            The formatted value
     */
    public static function parseFloatXX($value)
    {

        if (!$value) {
            return 0;
        }

        $multiplier = false;

        if (substr($value, 0, 1) == '-') {
            $multiplier = -1;
        }

        $s = str_replace(',', '.', $value);

        $s = preg_replace("/[^0-9\.]/", '', $s);

        if ($s < 1) {
            return (float) $s;
        }

        $s = str_replace('.', '', substr($s, 0, -3)).substr($s, -3);

        if ($multiplier) {
            $s = floatval($s) * -1;
        }

        return (float) $s;
    }


    //next iteration of float parsing
    public static function parseFloat2($value)
    {

        if (!$value) {
            return 0;
        }

        //remove everything except for numbers, decimals, commas and hyphens
        $value = preg_replace('/[^0-9.,-]+/', '', $value);

        $decimal = strpos($value, '.');
        $comma = strpos($value, ',');

        //check the 3rd last character
        if (!in_array(substr($value, -3, 1), [".", ","])) {

            if ($comma && (substr($value, -3, 1) != ".")) {
                $value .= ".00";
            } elseif ($decimal && (substr($value, -3, 1) != ",")) {
                $value .= ",00";
            }

        }

        $decimal = strpos($value, '.');
        $comma = strpos($value, ',');

        if ($comma === false) { //no comma must be a decimal number already
            return (float) $value;
        }

        if ($decimal < $comma) { //decimal before a comma = euro
            $value = str_replace(['.',','], ['','.'], $value);
            return (float) $value;
        }

        //comma first = traditional thousand separator
        $value = str_replace(',', '', $value);

        return (float)$value;

    }


    public static function parseStringFloat($value)
    {
        $value = preg_replace('/[^0-9-.]+/', '', $value);

        // check for comma as decimal separator
        if (preg_match('/,[\d]{1,2}$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        $value = preg_replace('/[^0-9\.\-]/', '', $value);

        return floatval($value);
    }

    /**
     * Formats a given value based on the clients currency AND country.
     *
     * @param $value            The number to be formatted
     * @param $entity
     * @return string           The formatted value
     */
    public static function formatMoney($value, $entity): string
    {
        $value = floatval($value);

        $_value = $value;

        $currency = $entity->currency();

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;
        $code = $currency->code;
        $swapSymbol = $currency->swap_currency_symbol;

        if ($entity instanceof Company) {
            $country = $entity->country();
        } else {
            $country = $entity->country;
        }

        /* Country settings override client settings */
        if (isset($country->thousand_separator) && strlen($country->thousand_separator) >= 1) {
            $thousand = $country->thousand_separator;
        }

        if (isset($country->decimal_separator) && strlen($country->decimal_separator) >= 1) {
            $decimal = $country->decimal_separator;
        }

        if (isset($country->swap_currency_symbol) && $country->swap_currency_symbol == 1) {
            $swapSymbol = $country->swap_currency_symbol;
        }

        $value = number_format($value, $precision, $decimal, $thousand);
        $symbol = $currency->symbol;

        if ($entity->getSetting('show_currency_code') === true && $currency->code == 'CHF') {
            return "{$code} {$value}";
        } elseif ($entity->getSetting('show_currency_code') === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} ".trim($symbol);
        } elseif ($entity->getSetting('show_currency_code') === false) {
            /* Ensures we place the negative symbol ahead of the currency symbol*/
            if ($_value < 0) {
                $value = substr($value, 1);
                $symbol = "-{$symbol}";
            }

            return "{$symbol}{$value}";
        } else {
            return self::formatValue($value, $currency); //@phpstan-ignore-line
        }
    }

    /**
     * Formats a given value based on the clients currency AND country.
     *
     * @param float $value The number to be formatted
     * @param mixed $entity
     * @return string           The formatted value
     */
    public static function formatMoneyNoRounding($value, $entity): string
    {
        $currency = $entity->currency();

        $_value = $value;

        $thousand = $currency->thousand_separator;
        $decimal = $currency->decimal_separator;
        $precision = $currency->precision;
        $code = $currency->code;
        $swapSymbol = $currency->swap_currency_symbol;

        if ($entity instanceof Company) {
            $country = $entity->country();
        } else {
            $country = $entity->country;
        }

        /* Country settings override client settings */
        if (isset($country->thousand_separator) && strlen($country->thousand_separator) >= 1) {
            $thousand = $country->thousand_separator;
        }

        if (isset($country->decimal_separator) && strlen($country->decimal_separator) >= 1) {
            $decimal = $country->decimal_separator;
        }

        if (isset($country->swap_currency_symbol) && strlen($country->swap_currency_symbol) >= 1) {
            $swapSymbol = $country->swap_currency_symbol;
        }

        /* 08-01-2022 allow increased precision for unit price*/
        $v = rtrim(sprintf('%f', $value), '0');
        $parts = explode('.', $v);

        /* 08-02-2023 special if block to render $0.5 to $0.50*/
        if ($v < 1 && strlen($v) == 3) {
            $precision = 2;
        } elseif ($v < 1) {
            $precision = strlen($v) - strrpos($v, '.') - 1;
        }

        if (is_array($parts) && $parts[0] != 0) {
            $precision = 2;
        }

        //04-04-2023 if currency = JPY override precision to 0
        if ($currency->code == 'JPY') {
            $precision = 0;
        }

        $value = number_format($v, $precision, $decimal, $thousand);//@phpstan-ignore-line
        $symbol = $currency->symbol;

        if ($entity->getSetting('show_currency_code') === true && $currency->code == 'CHF') {
            return "{$code} {$value}";
        } elseif ($entity->getSetting('show_currency_code') === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} ".trim($symbol);
        } elseif ($entity->getSetting('show_currency_code') === false) {
            if ($_value < 0) {
                $value = substr($value, 1);
                $symbol = "-{$symbol}";
            }

            return "{$symbol}{$value}";
        } else {
            return self::formatValue($value, $currency); //@phpstan-ignore-line
        }
    }
}

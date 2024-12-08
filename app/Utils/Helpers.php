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

use App\Models\Client;
use App\Utils\Traits\MakesDates;
use Carbon\Carbon;
use Illuminate\Support\Str;
use stdClass;

//30-10-2023: due to HTML encoding, need to remove </ from string when searching for matches
class Helpers
{
    use MakesDates;

    public static function sharedEmailVariables(?Client $client, array $settings = null): array
    {
        if (! $client) {
            $elements['signature'] = '';
            $elements['settings'] = new stdClass();
            $elements['whitelabel'] = true;
            $elements['company'] = '';

            return $elements;
        }

        $_settings = is_null($settings) ? $client->getMergedSettings() : $settings;

        $elements['signature'] = $_settings->email_signature;
        $elements['settings'] = $_settings;
        $elements['whitelabel'] = $client->company->account->isPaid() ? true : false;
        $elements['company'] = $client->company;

        return $elements;
    }

    /**
     * A centralised method to format the custom fields content.
     *
     * @param mixed|null $custom_fields
     * @param mixed $field
     * @param mixed $value
     * @param \App\Models\Client|null $entity
     *
     * @return null|string
     */
    public function formatCustomFieldValue($custom_fields, $field, $value, $entity = null): ?string
    {
        $custom_field = '';
        $quote_or_credit_field = false;

        if ($custom_fields && stripos($field, 'quote') !== false && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};
            $custom_field_parts = explode('|', $custom_field);

            if (count($custom_field_parts) >= 2) {
                $custom_field = $custom_field_parts[1];
            }

            $quote_or_credit_field = true;

        } elseif ($custom_fields && stripos($field, 'credit') !== false && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};
            $custom_field_parts = explode('|', $custom_field);

            if (count($custom_field_parts) >= 2) {
                $custom_field = $custom_field_parts[1];
            }

            $quote_or_credit_field = true;

        } elseif ($custom_fields && stripos($field, 'credit') !== false) {
            $field = str_replace("credit", "invoice", $field);
        } elseif ($custom_fields && stripos($field, 'quote') !== false) {
            $field = str_replace("quote", "invoice", $field);
        }

        if (!$quote_or_credit_field && $custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};
            $custom_field_parts = explode('|', $custom_field);

            if (count($custom_field_parts) >= 2) {
                $custom_field = $custom_field_parts[1];
            }
        }

        switch ($custom_field) {
            case 'date':
                return is_null($entity) ? $value : $this->translateDate($value, $entity->date_format(), $entity->locale());


            case 'switch':
                return trim($value ?? '') == 'yes' ? ctrans('texts.yes') : ctrans('texts.no');


            default:
                return is_null($value) ? '' : $this->processReservedKeywords($value, $entity);

        }
    }

    /**
     * A centralised method to make custom field.
     * @param mixed|null $custom_fields
     * @param mixed $field
     *
     * @return string
     */
    public function makeCustomField($custom_fields, $field): string
    {

        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};

            $custom_field_parts = explode('|', $custom_field);

            return $custom_field_parts[0];
        }

        $field = str_replace(["quote","credit"], ["invoice", "invoice"], $field);

        if ($custom_fields && property_exists($custom_fields, $field)) {
            $custom_field = $custom_fields->{$field};

            $custom_field_parts = explode('|', $custom_field);

            return $custom_field_parts[0];
        }

        return '';
    }

    /**
     * Process reserved keywords on PDF.
     *
     * @param string $value
     * @param \App\Models\Client|\App\Models\Company|\App\Models\Vendor $entity
     * @param null|Carbon $currentDateTime
     * @return null|string
     */
    public static function processReservedKeywords(?string $value, $entity, $currentDateTime = null): ?string
    {
        if (! $value) {
            return '';
        }

        // 04-10-2022 Return Early if no reserved keywords are present, this is a very expensive process
        $string_hit = false;

        foreach ([':MONTH',':YEAR',':QUARTER',':WEEK'] as $string) {
            if (stripos($value, $string) !== false) {
                $string_hit = true;
            }
        }

        if (!$string_hit) {
            return $value;
        }

        // 04-10-2022 Return Early if no reserved keywords are present, this is a very expensive process
        Carbon::setLocale($entity->locale());

        if (!$currentDateTime) {
            $currentDateTime = Carbon::now()->timezone($entity->timezone()->name);
        }

        $replacements = [
            'literal' => [
                ':MONTH_BEFORE' => \sprintf(
                    '%s %s %s',
                    $currentDateTime->copy()->subMonth()->translatedFormat($entity->date_format()),
                    ctrans('texts.to'),
                    $currentDateTime->copy()->subDay()->translatedFormat($entity->date_format()),
                ),
                ':YEAR_BEFORE' => \sprintf(
                    '%s %s %s',
                    $currentDateTime->copy()->subYear()->translatedFormat($entity->date_format()),
                    ctrans('texts.to'),
                    $currentDateTime->copy()->subDay()->translatedFormat($entity->date_format()),
                ),
                ':MONTH_AFTER' => \sprintf(
                    '%s %s %s',
                    $currentDateTime->translatedFormat($entity->date_format()),
                    ctrans('texts.to'),
                    $currentDateTime->copy()->addMonth()->subDay()->translatedFormat($entity->date_format()),
                ),
                ':YEAR_AFTER' => \sprintf(
                    '%s %s %s',
                    $currentDateTime->translatedFormat($entity->date_format()),
                    ctrans('texts.to'),
                    $currentDateTime->copy()->addYear()->subDay()->translatedFormat($entity->date_format()),
                ),
                ':MONTHYEAR' => \sprintf(
                    '%s %s',
                    Carbon::createFromDate($currentDateTime->month)->translatedFormat('F'),
                    $currentDateTime->year,
                ),
                ':MONTH' => Carbon::createFromDate($currentDateTime->year, $currentDateTime->month)->translatedFormat('F'),
                ':YEAR' => $currentDateTime->year,
                ':QUARTER' => 'Q'.$currentDateTime->quarter,
                ':WEEK_BEFORE' => \sprintf(
                    '%s %s %s',
                    $currentDateTime->copy()->subDays(7)->translatedFormat($entity->date_format()),
                    ctrans('texts.to'),
                    $currentDateTime->copy()->subDays(1)->translatedFormat($entity->date_format())
                ),
                ':WEEK_AHEAD' => \sprintf(
                    '%s %s %s',
                    $currentDateTime->copy()->addDays(7)->translatedFormat($entity->date_format()),
                    ctrans('texts.to'),
                    $currentDateTime->copy()->addDays(13)->translatedFormat($entity->date_format())
                ),
                ':WEEK' => \sprintf(
                    '%s %s %s',
                    $currentDateTime->translatedFormat($entity->date_format()),
                    ctrans('texts.to'),
                    $currentDateTime->copy()->addDays(6)->translatedFormat($entity->date_format())
                ),
            ],
            'raw' => [
                ':MONTHYEAR' => $currentDateTime->month,
                ':MONTH' => $currentDateTime->month,
                ':YEAR' => $currentDateTime->year,
                ':QUARTER' => $currentDateTime->quarter,
            ],
            'ranges' => [
                'MONTHYEAR' => Carbon::createFromDate($currentDateTime->year, $currentDateTime->month),
            ],
            'ranges_raw' => [
                'MONTH' => $currentDateTime->month,
                'YEAR' => $currentDateTime->year,
            ],
        ];

        // First case, with ranges.
        preg_match_all('/\[(.*?)]/', $value, $ranges);

        $matches = array_shift($ranges);

        foreach ($matches as $match) {
            if (! Str::contains($match, '|')) {
                continue;
            }

            // if (Str::contains($match, '|')) {
            $parts = explode('|', $match); // [ '[MONTH', 'MONTH+2]' ]

            $left = substr($parts[0], 1); // 'MONTH'
            $right = substr($parts[1], 0, -1); // MONTH+2

            // If left side is not part of replacements, skip.
            if (! array_key_exists($left, $replacements['ranges'])) {
                continue;
            }

            $_left = Carbon::createFromDate($currentDateTime->year, $currentDateTime->month)->translatedFormat('F Y');
            $_right = '';

            // If right side doesn't have any calculations, replace with raw ranges keyword.
            if (! Str::contains(str_replace("</", "", $right), ['-', '+', '/', '*'])) {
                $_right = Carbon::createFromDate($currentDateTime->year, $currentDateTime->month)->translatedFormat('F Y');
            }

            // If right side contains one of math operations, calculate.
            if (Str::contains(str_replace("</", "", $right), ['+'])) {
                $operation = preg_match_all('/(?!^-)[+*\/-](\s?-)?/', $right, $_matches);

                $_operation = array_shift($_matches)[0]; // + -

                $_value = explode($_operation, $right); // [MONTHYEAR, 4]

                $_right = Carbon::createFromDate($currentDateTime->year, $currentDateTime->month)->addMonths($_value[1])->translatedFormat('F Y'); //@phpstan-ignore-line
            }

            $replacement = sprintf('%s to %s', $_left, $_right);

            $value = preg_replace(
                sprintf('/%s/', preg_quote($match)),
                $replacement,
                $value,
                1
            );
            // }
        }

        // Second case with more common calculations.
        preg_match_all('/:([^:\s]+)/', $value, $common);

        $matches = array_shift($common);

        foreach ($matches as $match) {
            $matches = collect($replacements['literal'])->filter(function ($value, $key) use ($match) {
                return Str::startsWith($match, $key);
            });

            if ($matches->count() === 0) {
                continue;
            }

            if (! Str::contains(str_replace("</", "", $match), ['-', '+', '/', '*'])) {
                $value = preg_replace(
                    sprintf('/%s/', $matches->keys()->first()),
                    $replacements['literal'][$matches->keys()->first()],
                    $value,
                    1
                );
            }

            if (Str::contains(str_replace("</", "", $match), ['-', '+', '/', '*'])) {
                $operation = preg_match_all('/(?!^-)[+*\/-](\s?-)?/', $match, $_matches);

                $_operation = array_shift($_matches)[0];

                $_value = explode($_operation, $match); // [:MONTH, 4]

                $raw = strtr($matches->keys()->first(), $replacements['raw']); // :MONTH => 1

                $number = $res = preg_replace('/[^0-9]/', '', $_value[1]); // :MONTH+1. || :MONTH+2! => 1 || 2

                $target = "/{$matches->keys()->first()}\\{$_operation}{$number}/"; // /:$KEYWORD\\$OPERATION$VALUE => /:MONTH\\+1

                $output = (int) $raw + (int) $_value[1];

                if ($operation == '+') {
                    $output = (int) $raw + (int) $_value[1]; // 1 (:MONTH) + 4
                }

                if ($_operation == '-') {
                    $output = (int) $raw - (int) $_value[1]; // 1 (:MONTH) - 4
                }

                if ($_operation == '/' && (int) $_value[1] != 0) {
                    $output = (int) $raw / (int) $_value[1]; // 1 (:MONTH) / 4
                }

                if ($_operation == '*') {
                    $output = (int) $raw * (int) $_value[1]; // 1 (:MONTH) * 4
                }

                if ($matches->keys()->first() == ':MONTH') {
                    $output = \Carbon\Carbon::create()->month($output)->translatedFormat('F');
                }

                if ($matches->keys()->first() == ':MONTHYEAR') {
                    $final_date = $currentDateTime->copy()->addMonths($output - $currentDateTime->month);

                    $output = \sprintf(
                        '%s %s',
                        $final_date->translatedFormat('F'),
                        $final_date->year,
                    );
                }

                $value = preg_replace(
                    $target,
                    $output,
                    $value,
                    1
                );
            }
        }

        return $value;
    }

    /**
     * Resolve the font from the supported fonts array.
     *
     * @param string $font
     * @return array
     */
    public static function resolveFont(string $font = 'Arial'): array
    {
        return $font
            ? ['name' => str_replace('_', ' ', $font), 'url' => sprintf('https://fonts.googleapis.com/css2?family=%s&display=swap', str_replace('_', '+', $font))]
            : ['name' => 'Arial', 'url' => ''];
    }
}

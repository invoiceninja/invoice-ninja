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

use App\DataMapper\CompanySettings;

/**
 * Class SettingsSaver.
 */
trait SettingsSaver
{
    private array $string_ids = [
        'payment_refund_design_id',
        'payment_receipt_design_id',
        'delivery_note_design_id',
        'statement_design_id',
        'besr_id',
        'gmail_sending_user_id',
    ];

    /**
     * Used for custom validation of inbound
     * settings request.
     *
     * Returns an array of errors, or boolean TRUE
     * on successful validation
     * @param  array $settings The request() settings array
     * @return array|bool      Array on failure, boolean TRUE on success
     */
    public function validateSettings($settings)
    {
        $settings = (object) $settings;
        $casts = CompanySettings::$casts;

        ksort($casts);

        foreach ($casts as $key => $value) {
            //try casting floats here
            if ($value == 'float' && property_exists($settings, $key)) {
                $settings->{$key} = floatval($settings->{$key});
            }

            if (in_array($key, CompanySettings::$string_casts)) {
                $value = 'string';
                if (! property_exists($settings, $key)) {
                    continue;
                } elseif (! $this->checkAttribute($value, $settings->{$key})) {
                    return [$key, $value, $settings->{$key}];
                }

                continue;
            }
            /*Separate loop if it is a _id field which is an integer cast as a string*/ elseif (substr($key, -3) == '_id' || substr($key, -14) == 'number_counter' || ($key == 'payment_terms' && property_exists($settings, $key) && strlen($settings->{$key}) >= 1) || ($key == 'valid_until' && property_exists($settings, $key) && strlen($settings->{$key}) >= 1)) {
                $value = 'integer';

                if (in_array($key, $this->string_ids)) {
                    // if ($key == 'gmail_sending_user_id' || $key == 'besr_id') {
                    $value = 'string';
                }

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif (! $this->checkAttribute($value, $settings->{$key})) {
                    return [$key, $value, $settings->{$key}];
                }

                continue;
            } elseif ($key == 'pdf_variables') {
                continue;
            }

            /* Handles unset settings or blank strings */
            if (! property_exists($settings, $key) || is_null($settings->{$key}) || ! isset($settings->{$key}) || $settings->{$key} == '') {
                continue;
            }

            /*Catch all filter */
            if (! $this->checkAttribute($value, $settings->{$key})) {
                return [$key, $value, $settings->{$key}];
            }
        }

        return true;
    }

    /**
     * Type checks a object property.
     * @param  string $key   The type
     * @param  string $value The object property
     * @return bool        TRUE if the property is the expected type
     */
    private function checkAttribute($key, $value): bool
    {
        switch ($key) {
            case 'int':
            case 'integer':
                return is_numeric($value) && ctype_digit(strval(abs($value)));
            case 'real':
            case 'float':
            case 'double':
                return !is_string($value) && (is_float($value) || is_numeric(strval($value)));
            case 'string':
                return !is_int($value) || (is_string($value) && method_exists($value, '__toString')) || is_null($value) || is_string($value);
            case 'bool':
            case 'boolean':
                return is_bool($value) || (int) filter_var($value, FILTER_VALIDATE_BOOLEAN);
            case 'object':
                return is_object($value);
            case 'array':
                return is_array($value);
            case 'json':
                json_decode($value);

                return json_last_error() == JSON_ERROR_NONE;
            default:
                return false;
        }
    }
}

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
use stdClass;

/**
 * Class ClientGroupSettingsSaver.
 *
 * Whilst it may appear that this CompanySettingsSaver and ClientGroupSettingsSaver
 * could be duplicates, they are not.
 *
 * Each requires their own approach to saving and attempts to
 * merge the two code paths should be avoided.
 */
trait ClientGroupSettingsSaver
{
    /**
     * Saves a setting object.
     *
     * Works for groups|clients|companies
     * @param  array|object $settings The request input settings array
     * @param  object $entity   The entity which the settings belongs to
     * @return array|object
     */
    public function saveSettings($settings, $entity)
    {
        if (! $settings) {
            return;
        }

        $entity_settings = $this->settings;

        //unset protected properties.
        foreach (CompanySettings::$protected_fields as $field) {
            unset($settings[$field]);
        }

        $company_settings_stub = new CompanySettings();

        /*
         * for clients and group settings, if a field is not set or is set to a blank value,
         * we unset it from the settings object
         */
        foreach ($settings as $key => $value) {
            if (! isset($settings->{$key}) || empty($settings->{$key})  || !property_exists($company_settings_stub, $key) || (! is_object($settings->{$key}) && strlen($settings->{$key}) == 0)) {
                unset($settings->{$key});
            }
        }

        $settings = $this->checkSettingType($settings);

        //iterate through set properties with new values;
        foreach ($settings as $key => $value) {
            $entity_settings->{$key} = $value;
        }

        return $entity_settings;
    }

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

        if (property_exists($settings, 'translations')) {
            unset($settings->translations);
        }

        foreach (['translations','pdf_variables'] as $key) {
            if (property_exists($settings, $key)) {
                unset($settings->{$key});
            }
        }

        //18-07-2022 removed || empty($settings->{$key}) from this check to allow "0" values to persist
        foreach ($settings as $key => $value) {
            if (! isset($settings->{$key}) || (! is_object($settings->{$key}) && strlen($settings->{$key}) == 0)) {
                unset($settings->{$key});
            }
        }

        foreach ($casts as $key => $value) {
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
            /*Separate loop if it is a _id field which is an integer cast as a string*/ elseif (substr($key, -3) == '_id' ||
                substr($key, -14) == 'number_counter' ||
                ($key == 'payment_terms' && property_exists($settings, 'payment_terms') && strlen($settings->{$key}) >= 1) ||
                ($key == 'valid_until' && property_exists($settings, 'valid_until') && strlen($settings->{$key}) >= 1)) {
                $value = 'integer';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif (! $this->checkAttribute($value, $settings->{$key})) {
                    return [$key, $value, $settings->{$key}];
                }

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
     * Checks the settings object for
     * correct property types.
     *
     * The method will drop invalid types from
     * the object and will also settype() the property
     * so that it can be saved cleanly
     *
     * @param  array $settings The settings request() array
     * @return stdClass          stdClass object
     */
    private function checkSettingType($settings): stdClass
    {
        $settings = (object) $settings;
        $casts = CompanySettings::$casts;

        foreach ($casts as $key => $value) {
            if ($value == 'float' && property_exists($settings, $key)) {
                $settings->{$key} = floatval($settings->{$key});
            }

            /*Separate loop if it is a _id field which is an integer cast as a string*/
            if (substr($key, -3) == '_id' ||
                substr($key, -14) == 'number_counter' ||
                ($key == 'payment_terms' && property_exists($settings, 'payment_terms') && strlen($settings->{$key}) >= 1) ||
                ($key == 'valid_until' && property_exists($settings, 'valid_until') && strlen($settings->{$key}) >= 1)) {
                $value = 'integer';

                if (! property_exists($settings, $key)) {
                    continue;
                } elseif ($this->checkAttribute($value, $settings->{$key})) {
                    if (substr($key, -3) == '_id' ||
                        ($key == 'payment_terms' && property_exists($settings, 'payment_terms') && strlen($settings->{$key}) >= 1) ||
                        ($key == 'valid_until' && property_exists($settings, 'valid_until') && strlen($settings->{$key}) >= 1)) {
                        settype($settings->{$key}, 'string');
                    } else {
                        settype($settings->{$key}, $value);
                    }
                } else {
                    unset($settings->{$key});
                }

                continue;
            }

            /* Handles unset settings or blank strings */
            if (! property_exists($settings, $key) || is_null($settings->{$key}) || ! isset($settings->{$key}) || $settings->{$key} == '') {
                continue;
            }

            /*Catch all filter */
            if ($this->checkAttribute($value, $settings->{$key})) {
                if ($value == 'string' && is_null($settings->{$key})) {
                    $settings->{$key} = '';
                }

                settype($settings->{$key}, $value);
            } else {
                unset($settings->{$key});
            }
        }

        return $settings;
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
                return ! is_string($value) && (is_float($value) || is_numeric(strval($value)));
                //return is_float($value) || is_numeric(strval($value));
            case 'string':
                return (is_string($value) && method_exists($value, '__toString')) || is_null($value) || is_string($value);
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

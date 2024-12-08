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

namespace App\Helpers;

class Sanitizer
{
    public static function removeBlanks($input): array
    {
        foreach ($input as &$value) {
            if (is_array($value)) {
                // Recursively apply the filter to nested arrays
                $value = self::removeBlanks($value);
            }
        }
        // Use array_filter to remove empty or null values
        return array_filter($input);
    }
}

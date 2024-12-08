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

namespace App\Casts;

use App\DataMapper\QuickbooksSettings;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class QuickbooksSettingsCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {
        if (is_null($value)) {
            return new QuickbooksSettings();
        }

        $data = json_decode($value, true);
        return QuickbooksSettings::fromArray($data);
    }

    public function set($model, string $key, $value, array $attributes)
    {
        if ($value instanceof QuickbooksSettings) {
            return json_encode(get_object_vars($value));
        }

        return json_encode($value);
    }
}

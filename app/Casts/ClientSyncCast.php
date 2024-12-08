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

use App\DataMapper\ClientSync;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class ClientSyncCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes)
    {

        if (is_null($value)) {
            return null; // Return null if the value is null
        }

        $data = json_decode($value, true);

        if (!is_array($data)) {
            return null;
        }

        $is = new ClientSync();
        $is->qb_id =  $data['qb_id'];

        return $is;
    }

    public function set($model, string $key, $value, array $attributes)
    {
        return [
            $key => json_encode([
                'qb_id' => $value->qb_id,
            ])
        ];
    }
}

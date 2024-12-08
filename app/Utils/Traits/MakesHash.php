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

use App\Libraries\MultiDB;
use Hashids\Hashids;
use Illuminate\Support\Str;

/**
 * Class MakesHash.
 */
trait MakesHash
{
    /**
     * Creates a simple alphanumeric Hash.
     * @return string - asd89f7as89df6asf78as6fds
     */
    public function createHash(): string
    {
        return Str::random(config('ninja.key_length'));
    }

    /**
     * Creates a simple alphanumeric Hash which is prepended with a encoded database prefix.
     *
     * @param $db - Full database name
     * @return string 01-asfas8df76a78f6a78dfsdf
     */
    public function createDbHash($db): string
    {
        if (config('ninja.db.multi_db_enabled')) {
            return  $this->getDbCode($db).'-'.Str::random(config('ninja.key_length'));
        }

        return Str::random(config('ninja.key_length'));
    }

    /**
     * @param $db - Full database name
     * @return string - hashed and encoded int 01,02,03,04
     */
    public function getDbCode($db): string
    {
        $hashids = new Hashids(config('ninja.hash_salt'), 10);

        return $hashids->encode(str_replace(MultiDB::DB_PREFIX, '', $db));
    }

    public function encodePrimaryKey($value): string
    {
        $hashids = new Hashids(config('ninja.hash_salt'), 10);

        return $hashids->encode($value);
    }

    public function decodePrimaryKey($value, $return_string_failure = false)
    {

        try {
            $hashids = new Hashids(config('ninja.hash_salt'), 10);

            $decoded_array = $hashids->decode($value);

            if (isset($decoded_array[0]) ?? false) {
                return $decoded_array[0];
            } elseif ($return_string_failure) {
                return "Invalid Primary Key";
            } else {
                throw new \Exception('Invalid Primary Key');
            }

        } catch (\Exception $e) {
            return response()->json(['error' => 'Invalid primary key'], 400);
        }

        /*
        try {
            $hashids = new Hashids(config('ninja.hash_salt'), 10);

            $decoded_array = $hashids->decode($value);

            if (! is_array($decoded_array)) {
                throw new \Exception('Invalid Primary Key');
            }

            return $decoded_array[0];
        } catch (\Exception $e) {
            return response()->json(['error'=>'Invalid primary key'], 400);
        }
        */
    }

    public function transformKeys($keys)
    {
        if (is_array($keys)) {
            foreach ($keys as &$value) {
                $value = $this->decodePrimaryKey($value);
            }

            return $keys;
        } else {
            return $this->decodePrimaryKey($keys);
        }
    }
}

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

namespace App\Jobs\Ninja;

use App\DataMapper\Analytics\EmailCount;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Turbo124\Beacon\Facades\LightLogs;

class AdjustEmailQuota implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        if (! Ninja::isHosted()) {
            return;
        }

        //multiDB environment, need to
        foreach (MultiDB::$dbs as $db) {
            MultiDB::setDB($db);

            $this->adjust();
        }
    }

    public function adjust()
    {
        Account::query()->cursor()->each(function ($account) {
            nlog("resetting email quota for {$account->key}");

            $email_count = Cache::get("email_quota".$account->key);

            if ($email_count > 0) {
                try {
                    LightLogs::create(new EmailCount($email_count, $account->key))->send(); // this runs syncronously
                } catch (\Exception $e) {
                    nlog("Exception:: AdjustEmailQuota::" . $e->getMessage());
                    nlog($e->getMessage());
                }
            }
        });

        /** Use redis pipelines to execute bulk deletes efficiently */
        $redis = Redis::connection('sentinel-cache');
        $prefix =  config('cache.prefix'). "email_quota*";

        $keys = $redis->keys($prefix);

        if (is_array($keys)) {
            $redis->pipeline(function ($pipe) use ($keys) {
                foreach ($keys as $key) {
                    $pipe->del($key);
                }
            });
        }
        $keys = null;

        $prefix =  config('cache.prefix'). "throttle_notified*";

        $keys = $redis->keys($prefix);

        if (is_array($keys)) {
            $redis->pipeline(function ($pipe) use ($keys) {
                foreach ($keys as $key) {
                    $pipe->del($key);
                }
            });
        }
    }
}

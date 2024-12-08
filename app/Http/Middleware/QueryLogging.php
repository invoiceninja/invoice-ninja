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

namespace App\Http\Middleware;

use App\DataMapper\Analytics\DbQuery;
use App\Utils\Ninja;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Turbo124\Beacon\Facades\LightLogs;

/**
 * Class QueryLogging.
 */
class QueryLogging
{
    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {

        // Enable query logging for development
        if (! Ninja::isHosted() || ! config('beacon.enabled')) {
            return $next($request);
        }

        DB::enableQueryLog();
        return $next($request);

    }

    public function terminate($request, $response)
    {
        if (! Ninja::isHosted() || ! config('beacon.enabled')) {
            return;
        }

        // hide requests made by debugbar
        if (strstr($request->url(), '_debugbar') === false) {

            $queries = DB::getQueryLog();
            $count = count($queries);
            $timeEnd = microtime(true);
            $time = $timeEnd - LARAVEL_START;

            if ($count > 175) {
                nlog("Query count = {$count}");
                nlog($queries);
            }

            $ip = '';

            if ($request->hasHeader('Cf-Connecting-Ip')) {
                $ip = $request->header('Cf-Connecting-Ip');
            } elseif ($request->hasHeader('X-Forwarded-For')) {
                $ip = $request->header('Cf-Connecting-Ip');
            } else {
                $ip = $request->ip();
            }

            $client_version = $request->server('HTTP_USER_AGENT');
            $platform = '';

            if ($request->hasHeader('X-CLIENT-PLATFORM')) {
                $platform = $request->header('X-CLIENT-PLATFORM');
            } elseif ($request->hasHeader('X-React')) {
                $platform = 'react';
            }

            if ($request->hasHeader('X-CLIENT-VERSION')) {
                $client_version = $request->header('X-CLIENT-VERSION');
            }

            LightLogs::create(new DbQuery($request->method(), substr(urldecode($request->url()), 0, 180), $count, $time, $ip, $client_version, $platform))
                ->batch();
        }

    }
}

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

use App\Libraries\MultiDB;
use Closure;
use Illuminate\Http\Request;
use stdClass;

class SetDomainNameDb
{
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        $error = [
            'message' => 'Invalid token',
            'errors' => new stdClass(),
        ];
        /*
         * Use the host name to set the active DB
         **/

        if (! config('ninja.db.multi_db_enabled')) {
            return $next($request);
        }

        $domain_name = $request->getHost();

        if (strpos($domain_name, config('ninja.app_domain')) !== false) {
            $subdomain = explode('.', $domain_name)[0];

            $query = [
                'subdomain' => $subdomain,
                'portal_mode' => 'subdomain',
            ];

            if ($company = MultiDB::findAndSetDbByDomain($query)) {
                session()->put('company_key', $company->company_key);
            } else {
                if ($request->json) {
                    return response()->json($error, 403);
                } else {
                    MultiDB::setDb('db-ninja-01');
                    nlog('SetDomainNameDb:: I could not set the DB - defaulting to DB1');
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }
        } else {
            $query = [
                'portal_domain' => $request->getSchemeAndHttpHost(),
                'portal_mode' => 'domain',
            ];

            if ($company = MultiDB::findAndSetDbByDomain($query)) {
                session()->put('company_key', $company->company_key);
            } else {
                if ($request->json) {
                    return response()->json($error, 403);
                } else {
                    MultiDB::setDb('db-ninja-01');
                    nlog('SetDomainNameDb:: I could not set the DB - defaulting to DB1');
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
            }
        }

        return $next($request);
    }
}

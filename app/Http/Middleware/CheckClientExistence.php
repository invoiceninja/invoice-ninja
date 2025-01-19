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

use App\Models\ClientContact;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CheckClientExistence
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        if (session()->has('multiple_contacts')) {
            return $next($request);
        }

        auth()->guard('contact')->user()->loadMissing(['company']); // @phpstan-ignore method.notFound

        $multiple_contacts = ClientContact::query()
            ->where('email', auth()->guard('contact')->user()->email)
            ->whereNotNull('email')
            ->where('email', '<>', '')
            ->distinct('client_id')
            ->whereNotNull('company_id')
            ->whereHas('client', function ($query) {
                return $query->where('is_deleted', false);
            })
            ->whereHas('company', function ($query) {
                return $query->where('companies.account_id', auth()->guard('contact')->user()->company->account_id);
            })
            ->get();

        /* This catches deleted clients who don't have access to the app. We automatically log them out here*/
        if (count($multiple_contacts) == 0) {
            Auth::logout();

            return redirect()->route('client.login')->with('message', 'Login disabled');
        }

        if (count($multiple_contacts) == 1 && ! Auth::guard('contact')->check()) {
            
            request()->session()->invalidate();
            Auth::guard('contact')->loginUsingId($multiple_contacts[0]->id, true);

            auth()->guard('contact')->user()->loadMissing(['client' => function ($query) {
                $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
            }]);

        }

        session()->put('multiple_contacts', $multiple_contacts);
        session()->put('is_silent', request()->has('silent'));

        return $next($request);
    }
}

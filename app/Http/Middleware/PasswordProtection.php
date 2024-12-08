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
use App\Libraries\OAuth\Providers\Google;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use stdClass;

class PasswordProtection
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
            'message' => 'Invalid Password',
            'errors' => new stdClass(),
        ];

        /** @var \App\Models\User auth()->user() */
        $user = auth()->user();
        $timeout = $user->company()->default_password_timeout;

        if ($timeout == 0) {
            $timeout = 30 * 60 * 1000 * 1000;
        } else {
            $timeout = $timeout / 1000;
        }

        //test if password if base64 encoded
        $x_api_password = $request->header('X-API-PASSWORD');

        if ($request->header('X-API-PASSWORD-BASE64')) {
            $x_api_password = base64_decode($request->header('X-API-PASSWORD-BASE64'));
        }

        // If no password supplied - then we just check if their authentication is in cache //
        if (Cache::get(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in') && !$x_api_password) {
            Cache::put(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in', Str::random(64), $timeout);

            return $next($request);
        } elseif (strlen(auth()->user()->oauth_provider_id) > 2 && !auth()->user()->company()->oauth_password_required) {
            return $next($request);
        } elseif ($request->header('X-API-OAUTH-PASSWORD') && strlen($request->header('X-API-OAUTH-PASSWORD')) > 1) {
            //user is attempting to reauth with OAuth - check the token value
            //todo expand this to include all OAuth providers
            if (auth()->user()->oauth_provider_id == 'google') {
                $user = false;
                $google = new Google();
                $user = $google->getTokenResponse(request()->header('X-API-OAUTH-PASSWORD'));

                if (is_array($user)) {
                    $query = [
                        'oauth_user_id' => $google->harvestSubField($user),
                        'oauth_provider_id' => 'google'
                    ];

                    //If OAuth and user also has a password set  - check both
                    if ($existing_user = MultiDB::hasUser($query) && auth()->user()->company()->oauth_password_required && auth()->user()->has_password && Hash::check(auth()->user()->password, $x_api_password)) {
                        nlog("existing user with password");

                        Cache::put(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in', Str::random(64), $timeout);

                        return $next($request);
                    } elseif ($existing_user = MultiDB::hasUser($query) && !auth()->user()->company()->oauth_password_required) {
                        nlog("existing user without password");

                        Cache::put(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in', Str::random(64), $timeout);
                        return $next($request);
                    }
                }
            } elseif (auth()->user()->oauth_provider_id == 'microsoft') {
                try {
                    $payload = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', request()->header('X-API-OAUTH-PASSWORD'))[1]))));
                } catch (\Exception $e) {
                    nlog("Exception:: PasswordProtection::" . $e->getMessage());
                    nlog("could not decode microsoft response");
                    return response()->json(['message' => 'Could not decode the response from Microsoft'], 412);
                }

                if ($payload->preferred_username == auth()->user()->email) {
                    Cache::put(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in', Str::random(64), $timeout);
                    return $next($request);
                }
            } elseif (auth()->user()->oauth_provider_id == 'apple') {
                $user = Socialite::driver('apple')->userFromToken($request->header('X-API-OAUTH-PASSWORD'));

                if ($user && ($user->email == auth()->user()->email)) {
                    Cache::put(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in', Str::random(64), $timeout);
                    return $next($request);
                }
            }


            return response()->json($error, 412);
        } elseif ($x_api_password && Hash::check($x_api_password, auth()->user()->password)) {
            Cache::put(auth()->user()->hashed_id.'_'.auth()->user()->account_id.'_logged_in', Str::random(64), $timeout);

            return $next($request);
        } else {
            return response()->json($error, 412);
        }
    }
}

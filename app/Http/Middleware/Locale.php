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

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;

class Locale
{
    private array $locales = [
                        'en',
                        'it',
                        'de',
                        'fr',
                        'pt_BR',
                        'nl',
                        'es',
                        'nb_NO',
                        'da',
                        'ja',
                        'sv',
                        'es_ES',
                        'fr_CA',
                        'lt',
                        'pl',
                        'cs',
                        'hr',
                        'sq',
                        'el',
                        'en_GB',
                        'pt_PT',
                        'sl',
                        'fi',
                        'ro',
                        'tr_TR',
                        'th',
                        'mk_MK',
                        'zh_TW',
                        'ru_RU',
                        'ar',
                        'fa',
                        'lv_LV',
                        'sr',
                        'sk',
                        'et',
                        'bg',
                        'he',
                        'km_KH',
                        'lo_LA',
                        'hu',
                        'fr_CH',
                    ];
    /**
     * Handle an incoming request.
     *
     * @param  Request  $request
     * @param Closure $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        /*LOCALE SET */
        if ($request->has('lang') && in_array($request->input('lang', 'en'), $this->locales)) {
            $locale = $request->input('lang');
            App::setLocale($locale);
        } elseif (auth()->guard('contact')->user()) {

            auth()->guard('contact')->user()->loadMissing(['client' => function ($query) { // @phpstan-ignore method.undefined
                $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
            }]);

            App::setLocale(auth()->guard('contact')->user()->client->locale());
        } elseif (auth()->user()) {
            try {
                App::setLocale(auth()->user()->company()->getLocale()); // @phpstan-ignore method.undefined

            } catch (\Exception $e) {
            }
        } else {
            App::setLocale(config('ninja.i18n.locale'));
        }

        return $next($request);
    }
}

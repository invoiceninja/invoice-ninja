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

namespace App\Providers;

use App\Utils\Ninja;
use App\Models\Scheduler;
use Illuminate\Http\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Route;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use App\Http\Middleware\ThrottleRequestsWithPredis;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException as ModelNotFoundException;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;

class RouteServiceProvider extends ServiceProvider
{
    use MakesHash;

    /**
     * Define your route model bindings, pattern filters, etc.
     *
     * @return void
     */
    public function boot()
    {
        parent::boot();

        if (Ninja::isHosted() && !config('ninja.testvars.travis')) {
            app('router')->aliasMiddleware('throttle', ThrottleRequestsWithPredis::class);
        } else {
            app('router')->aliasMiddleware('throttle', ThrottleRequests::class);
        }

        Route::bind('task_scheduler', function ($value) {
            if (is_numeric($value)) {
                throw new ModelNotFoundException("Record with value {$value} not found");
            }

            return Scheduler::query()
                ->withTrashed()
                ->company()
                ->where('id', $this->decodePrimaryKey($value))->firstOrFail();
        });

        RateLimiter::for('login', function (Request $request) {
            if (Ninja::isSelfHost()) {
                return Limit::none();
            } else {
                return Limit::perMinute(30)->by($request->ip());
            }
        });

        RateLimiter::for('api', function (Request $request) {
            if (Ninja::isSelfHost()) {
                return Limit::none();
            } else {
                return Limit::perMinute(100)->by($request->ip());
            }
        });

        RateLimiter::for('refresh', function (Request $request) {
            if (Ninja::isSelfHost()) {
                return Limit::none();
            } else {
                return Limit::perMinute(200)->by($request->ip());
            }
        });

        RateLimiter::for('404', function (Request $request) {
            if (Ninja::isSelfHost()) {
                return Limit::none();
            } else {
                return Limit::perMinute(10)->by($request->ip());
            }
        });

        RateLimiter::for('honeypot', function (Request $request) {
            return Limit::perMinute(2)->by($request->ip());
        });

        RateLimiter::for('portal', function (Request $request) {
            return Limit::perMinute(15)->by($request->ip());
        });

    }

    /**
     * Define the routes for the application.
     *
     * @return void
     */
    public function map()
    {
        $this->mapApiRoutes();

        $this->mapWebRoutes();

        $this->mapContactApiRoutes();

        $this->mapVendorsApiRoutes();

        $this->mapClientApiRoutes();

        $this->mapShopApiRoutes();
    }

    /**
     * Define the "web" routes for the application.
     *
     * These routes all receive session state, CSRF protection, etc.
     *
     * @return void
     */
    protected function mapWebRoutes()
    {
        Route::middleware('web')
             ->group(base_path('routes/web.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapApiRoutes()
    {
        Route::prefix('')
             ->middleware('api')
             ->group(base_path('routes/api.php'));
    }

    /**
     * Define the "api" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapContactApiRoutes()
    {
        Route::prefix('')
             ->middleware('contact')
             ->group(base_path('routes/contact.php'));
    }

    /**
     * Define the "client" routes for the application.
     *
     * These routes are typically stateless.
     *
     * @return void
     */
    protected function mapClientApiRoutes()
    {
        Route::prefix('')
             ->middleware('client')
             ->group(base_path('routes/client.php'));
    }

    protected function mapShopApiRoutes()
    {
        Route::prefix('')
             ->middleware('shop')
             ->group(base_path('routes/shop.php'));
    }

    protected function mapVendorsApiRoutes()
    {
        Route::prefix('')
            ->middleware('client')
            ->group(base_path('routes/vendor.php'));
    }
}

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
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Redis\Factory as Redis;
use Illuminate\Redis\Limiters\DurationLimiter;

class ThrottleRequestsWithPredis extends \Illuminate\Routing\Middleware\ThrottleRequests
{
    /**
     * The Redis factory implementation.
     *
     * @var \Illuminate\Contracts\Redis\Factory
     */
    protected $redis; /** @phpstan-ignore-line */

    /**
     * The timestamp of the end of the current duration by key.
     *
     * @var array
     */
    public $decaysAt = [];

    /**
     * The number of remaining slots by key.
     *
     * @var array
     */
    public $remaining = [];

    /**
     * Create a new request throttler.
     * @return void
     */

    /** @phpstan-ignore-next-line */
    public function __construct(RateLimiter $limiter, Redis $redis)
    {

        /** @phpstan-ignore-next-line */
        parent::__construct($limiter); /** @phpstan-ignore-line */

        /** @phpstan-ignore-next-line */
        $this->redis = \Illuminate\Support\Facades\Redis::connection('sentinel-cache'); /** @phpstan-ignore-line */
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  array  $limits
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Illuminate\Http\Exceptions\ThrottleRequestsException
     */
    protected function handleRequest($request, Closure $next, array $limits)
    {
        foreach ($limits as $limit) {
            if ($this->tooManyAttempts($limit->key, $limit->maxAttempts, $limit->decaySeconds)) {
                throw $this->buildException($request, $limit->key, $limit->maxAttempts, $limit->responseCallback);
            }
        }

        $response = $next($request);

        foreach ($limits as $limit) {
            $response = $this->addHeaders(
                $response,
                $limit->maxAttempts,
                $this->calculateRemainingAttempts($limit->key, $limit->maxAttempts)
            );
        }

        return $response;
    }

    /**
     * Determine if the given key has been "accessed" too many times.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int  $decaySeconds
     * @return mixed
     */
    protected function tooManyAttempts($key, $maxAttempts, $decaySeconds)
    {
        $limiter = new DurationLimiter(
            $this->getRedisConnection(),
            $key,
            $maxAttempts,
            $decaySeconds
        );

        return tap(! $limiter->acquire(), function () use ($key, $limiter) {
            [$this->decaysAt[$key], $this->remaining[$key]] = [
                $limiter->decaysAt, $limiter->remaining,
            ];
        });
    }

    /**
     * Calculate the number of remaining attempts.
     *
     * @param  string  $key
     * @param  int  $maxAttempts
     * @param  int|null  $retryAfter
     * @return int
     */
    protected function calculateRemainingAttempts($key, $maxAttempts, $retryAfter = null)
    {
        return is_null($retryAfter) ? $this->remaining[$key] : 0;
    }

    /**
     * Get the number of seconds until the lock is released.
     *
     * @param  string  $key
     * @return int
     */
    protected function getTimeUntilNextRetry($key)
    {
        return $this->decaysAt[$key] - $this->currentTime();
    }

    /**
     * Get the Redis connection that should be used for throttling.
     *
     */
    protected function getRedisConnection()
    {
        return $this->redis;
    }
}

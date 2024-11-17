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

use Illuminate\Support\Str;

trait WithSecureContext
{
    public const CONTEXT_UPDATE = 'secureContext.updated';

    /**
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function getContext(): mixed
    {

        return \Illuminate\Support\Facades\Cache::get(session()->getId()) ?? [];
        // return session()->get('secureContext.invoice-pay');
    }

    public function setContext(string $property, $value): array
    {
        $clone = $this->getContext();
        // $clone = session()->pull('secureContext.invoice-pay', default: []);

        data_set($clone, $property, $value);

        // session()->put('secureContext.invoice-pay', $clone);

        \Illuminate\Support\Facades\Cache::put(session()->getId(), $clone, now()->addHour());
        $this->dispatch(self::CONTEXT_UPDATE);

        return $clone;
    }

    public function resetContext(): void
    {
        \Illuminate\Support\Facades\Cache::forget(session()->getId());
        session()->forget('secureContext.invoice-pay');
    }
}

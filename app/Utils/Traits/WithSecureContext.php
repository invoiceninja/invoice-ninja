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

        $context = \Illuminate\Support\Facades\Cache::get(session()->getId()) ?? false;

        if(!$context){

            usleep(300000); //@monitor - inject delay to catch delays in cache updating

            $context = \Illuminate\Support\Facades\Cache::get(session()->getId()) ?? [];

        }
        
        return $context;

    }

    public function setContext(string $property, $value): array
    {
        $clone = $this->getContext();

        data_set($clone, $property, $value);

        \Illuminate\Support\Facades\Cache::put(session()->getId(), $clone, now()->addHour());

        $this->dispatch(self::CONTEXT_UPDATE);

        return $clone;
    }

    public function resetContext(): void
    {
        \Illuminate\Support\Facades\Cache::forget(session()->getId());
    }
}

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

namespace App\Export\Decorators;

use App\Models\Client;

class ClientDecorator extends Decorator implements DecoratorInterface
{
    private $entity_key = 'client';

    public function transform(string $key, mixed $entity): mixed
    {
        $client = false;

        if ($entity instanceof Client) {
            $client = $entity;
        } elseif ($entity->client) {
            $client = $entity->client;
        }

        if ($client && method_exists($this, $key)) {
            return $this->{$key}($client);
        } elseif ($client && ($client->{$key} ?? false)) {
            return $client->{$key};
        }

        return '';
    }

    public function name(Client $client)
    {
        return $client->present()->name();
    }

    public function user(Client $client)
    {
        return $client->user->present()->name();
    }

    public function assigned_user(Client $client)
    {
        return $client->assigned_user ? $client->user->present()->name() : '';
    }

    public function currency_id(Client $client)
    {
        return $client->currency() ? $client->currency()->code : $client->company->currency()->code;
    }

    public function private_notes(Client $client)
    {
        return strip_tags($client->private_notes  ?? '');
    }

    public function industry_id(Client $client)
    {
        return $client->industry ? ctrans("texts.industry_{$client->industry->name}") : '';
    }

    public function size_id(Client $client)
    {
        return $client->size ? ctrans("texts.size_{$client->size->name}") : '';
    }

    public function country_id(Client $client)
    {
        return $client->country ? ctrans("texts.country_{$client->country->name}") : '';
    }

    public function shipping_country_id(Client $client)
    {
        return $client->shipping_country ? ctrans("texts.country_{$client->shipping_country->name}") : '';
    }

    public function payment_terms(Client $client)
    {
        return $client?->settings?->payment_terms ?? $client->company->settings->payment_terms;
    }

    public function public_notes(Client $client)
    {
        return strip_tags($client->public_notes ?? '');
    }

    public function classification(Client $client)
    {
        ctrans("texts.{$client->classification}") ?? '';
    }

    public function status(Client $client)
    {
        if ($client->is_deleted) {
            return ctrans('texts.deleted');
        }

        if ($client->deleted_at) {
            return ctrans('texts.archived');
        }

        return ctrans('texts.active');
    }

}

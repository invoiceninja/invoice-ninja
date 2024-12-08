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

use App\Models\ClientContact;

class ContactDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $contact = false;

        if ($entity instanceof ClientContact) {
            $contact = $entity;
        } elseif ($entity->contacts) {
            $contact = $entity->contacts()->first();
        } elseif ($entity->client) {
            $contact = $entity->client->primary_contact->first() ?? $entity->client->contacts()->whereNotNull('email')->first();
        } elseif ($entity->vendor) {
            $contact = $entity->vendor->primary_contact->first() ?? $entity->vendor->contacts()->whereNotNull('email')->first();
        }


        if ($contact && method_exists($this, $key)) {
            return $this->{$key}($contact);
        } elseif ($contact && ($contact->{$key} ?? false)) {
            return $contact->{$key};
        }

        return '';

    }

}

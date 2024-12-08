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

use App\Models\Quote;

class QuoteDecorator extends Decorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $quote = false;

        if ($entity instanceof Quote) {
            $quote = $entity;
        } elseif ($entity->quote) {
            $quote = $entity->quote;
        }

        if ($quote && method_exists($this, $key)) {
            return $this->{$key}($quote);
        } elseif ($quote->{$key} ?? false) {
            return $quote->{$key} ?? '';
        }

        return '';

    }

    public function status(Quote $quote)
    {
        return $quote->stringStatus($quote->status_id);
    }

    public function uses_inclusive_taxes(Quote $quote)
    {
        return $quote->uses_inclusive_taxes ? ctrans('texts.yes') : ctrans('texts.no');
    }

    public function is_amount_discount(Quote $quote)
    {
        return $quote->is_amount_discount ? ctrans('texts.yes') : ctrans('texts.no');
    }

    public function assigned_user_id(Quote $quote)
    {
        return $quote->assigned_user ? $quote->assigned_user->present()->name() : '';
    }

    public function user_id(Quote $quote)
    {
        return $quote->user->present()->name();
    }

}

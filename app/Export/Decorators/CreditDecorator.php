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

use App\Models\Credit;

class CreditDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $credit = false;

        if ($entity instanceof Credit) {
            $credit = $entity;
        } elseif ($entity->credit) {
            $credit = $entity->credit;
        }

        if ($credit && method_exists($this, $key)) {
            return $this->{$key}($credit);
        } elseif ($credit && ($credit->{$key} ?? false)) {
            return $credit->{$key};
        }

        return '';

    }

    public function date(Credit $credit)
    {
        return $credit->date ?? '';
    }
    public function due_date(Credit $credit)
    {
        return $credit->due_date ?? '';
    }
    public function terms(Credit $credit)
    {
        return strip_tags($credit->terms ?? '');
    }
    public function discount(Credit $credit)
    {
        return $credit->discount ?? 0;
    }
    public function footer(Credit $credit)
    {
        return $credit->footer ?? '';
    }
    public function status(Credit $credit)
    {
        return $credit->stringStatus($credit->status_id);
    }
    public function public_notes(Credit $credit)
    {
        return $credit->public_notes ?? '';
    }
    public function private_notes(Credit $credit)
    {
        return $credit->private_notes ?? '';
    }
    public function uses_inclusive_taxes(Credit $credit)
    {
        return $credit->uses_inclusive_taxes ? ctrans('texts.yes') : ctrans('texts.no');
    }
    public function is_amount_discount(Credit $credit)
    {
        return $credit->is_amount_discount ? ctrans('texts.yes') : ctrans('texts.no');
    }
    public function partial(Credit $credit)
    {
        return $credit->partial ?? 0;
    }
    public function partial_due_date(Credit $credit)
    {
        return $credit->partial_due_date ?? '';
    }
    public function custom_surcharge1(Credit $credit)
    {
        return $credit->custom_surcharge1 ?? 0;
    }
    public function custom_surcharge2(Credit $credit)
    {
        return $credit->custom_surcharge2 ?? 0;
    }
    public function custom_surcharge3(Credit $credit)
    {
        return $credit->custom_surcharge3 ?? 0;
    }
    public function custom_surcharge4(Credit $credit)
    {
        return $credit->custom_surcharge4 ?? 0;
    }
    public function custom_value1(Credit $credit)
    {
        return $credit->custom_value1 ?? '';
    }
    public function custom_value2(Credit $credit)
    {
        return $credit->custom_value2 ?? '';
    }
    public function custom_value3(Credit $credit)
    {
        return $credit->custom_value3 ?? '';
    }
    public function custom_value4(Credit $credit)
    {
        return $credit->custom_value4 ?? '';
    }
    public function exchange_rate(Credit $credit)
    {
        return $credit->exchange_rate ?? 0;
    }
    public function total_taxes(Credit $credit)
    {
        return $credit->total_taxes ?? 0;
    }
    public function assigned_user_id(Credit $credit)
    {
        return $credit->assigned_user ? $credit->assigned_user->present()->name() : '';
    }
    public function user_id(Credit $credit)
    {
        return $credit->user ? $credit->user->present()->name() : '';
    }

}

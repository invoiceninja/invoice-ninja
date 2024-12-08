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

use App\Models\Payment;

class PaymentDecorator extends Decorator implements DecoratorInterface
{
    private $entity_key = 'payment';

    public function transform(string $key, $entity): mixed
    {
        $payment = false;

        if ($entity instanceof Payment) {
            $payment = $entity;
        } elseif ($entity->payment) {
            $payment = $entity->payment;
        } elseif ($entity->payments()->exists()) {
            $payment = $entity->payments()->first();
        }

        if ($key == 'amount' && (!$entity instanceof Payment)) {
            return $entity->payments()->exists() ? $entity->payments()->withoutTrashed()->sum('paymentables.amount') : ctrans('texts.unpaid');
        } elseif ($key == 'refunded' && (!$entity instanceof Payment)) {
            return $entity->payments()->exists() ? $entity->payments()->withoutTrashed()->sum('paymentables.refunded') : '';
        } elseif ($key == 'applied' && (!$entity instanceof Payment)) {
            $refunded = $entity->payments()->withoutTrashed()->sum('paymentables.refunded');
            $amount = $entity->payments()->withoutTrashed()->sum('paymentables.amount');
            return $entity->payments()->withoutTrashed()->exists() ? ($amount - $refunded) : '';
        }

        if ($payment && method_exists($this, $key)) {
            return $this->{$key}($payment);
        } elseif ($payment && ($payment->{$key} ?? false)) {
            return $payment->{$key};
        }

        return '';
    }

    public function date(Payment $payment)
    {
        return $payment->date ?? '';
    }

    public function amount(Payment $payment)
    {
        return $payment->amount ?? '';
    }

    public function refunded(Payment $payment)
    {
        return $payment->refunded ?? '';
    }

    public function applied(Payment $payment)
    {
        return $payment->applied ?? '';
    }
    public function transaction_reference(Payment $payment)
    {
        return $payment->transaction_reference ?? '';
    }
    public function currency(Payment $payment)
    {
        return $payment->currency()->exists() ? $payment->currency->code : $payment->company->currency()->code;
    }

    public function exchange_rate(Payment $payment)
    {
        return $payment->exchange_rate ?? 1;
    }

    public function method(Payment $payment)
    {
        return $payment->translatedType();
    }

    public function status(Payment $payment)
    {
        return $payment->stringStatus($payment->status_id);
    }

    public function private_notes(Payment $payment)
    {
        return strip_tags($payment->private_notes) ?? '';
    }

    public function user_id(Payment $payment)
    {
        return $payment->user ? $payment->user->present()->name() : '';
    }

    public function assigned_user_id(Payment $payment)
    {
        return $payment->assigned_user ? $payment->assigned_user->present()->name() : '';
    }

    public function project_id(Payment $payment)
    {
        return $payment->project()->exists() ? $payment->project->name : '';
    }

    ///////////////////////////////////////////////////

    public function vendor_id(Payment $payment)
    {
        return $payment->vendor()->exists() ? $payment->vendor->name : '';
    }

    public function exchange_currency(Payment $payment)
    {
        return $payment->exchange_currency()->exists() ? $payment->exchange_currency->code : '';
    }

    public function gateway_type_id(Payment $payment)
    {
        return $payment->gateway_type ? $payment->gateway_type->name : 'Unknown Type';
    }

    public function client_id(Payment $payment)
    {
        return $payment->client->present()->name();
    }

    public function type_id(Payment $payment)
    {
        return $payment->translatedType();
    }

}

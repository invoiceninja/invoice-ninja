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

use App\Models\RecurringInvoice;

class RecurringInvoiceDecorator extends Decorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $recurring_invoice = false;

        if ($entity instanceof RecurringInvoice) {
            $recurring_invoice = $entity;
        } elseif ($entity->recurring_invoice) {
            $recurring_invoice = $entity->recurring_invoice;
        }

        if ($recurring_invoice && method_exists($this, $key)) {
            return $this->{$key}($recurring_invoice);
        } elseif ($recurring_invoice->{$key} ?? false) {
            return $recurring_invoice->{$key} ?? '';
        }

        return '';

    }

    public function status(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->stringStatus($recurring_invoice->status_id);
    }

    public function uses_inclusive_taxes(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->uses_inclusive_taxes ? ctrans('texts.yes') : ctrans('texts.no');
    }

    public function is_amount_discount(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->is_amount_discount ? ctrans('texts.yes') : ctrans('texts.no');
    }

    public function assigned_user_id(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->assigned_user ? $recurring_invoice->assigned_user->present()->name() : '';
    }

    public function user_id(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->user->present()->name() ?? '';
    }

    public function frequency_id(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->frequency_id ? $recurring_invoice->frequencyForKey($recurring_invoice->frequency_id) : '';
    }

    public function auto_bill(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->auto_bill ? ctrans("texts.{$recurring_invoice->auto_bill}") : '';
    }

    public function auto_bill_enabled(RecurringInvoice $recurring_invoice)
    {
        return $recurring_invoice->auto_bill_enabled ? ctrans('texts.yes') : ctrans('texts.no');
    }

}

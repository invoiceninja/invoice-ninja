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

use App\Models\Invoice;

class InvoiceDecorator extends Decorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $invoice = false;

        if ($entity instanceof Invoice) {
            $invoice = $entity;
        } elseif ($entity->invoice) {
            $invoice = $entity->invoice;
        } elseif (method_exists($entity, 'invoices') && $entity->invoices()->exists()) {
            $invoice = $entity->invoices()->first();
        }

        if ($invoice && method_exists($this, $key)) {
            return $this->{$key}($invoice);
        } elseif ($invoice && ($invoice->{$key} ?? false)) {
            return $invoice->{$key};
        }

        return '';

    }

    public function date(Invoice $invoice)
    {
        return $invoice->date ?? '';
    }
    public function due_date(Invoice $invoice)
    {
        return $invoice->due_date ?? '';
    }
    public function terms(Invoice $invoice)
    {
        return strip_tags($invoice->terms ?? '');
    }
    public function footer(Invoice $invoice)
    {
        return strip_tags($invoice->footer ?? '');
    }
    public function status(Invoice $invoice)
    {
        return $invoice->stringStatus($invoice->status_id);
    }
    public function public_notes(Invoice $invoice)
    {
        return strip_tags($invoice->public_notes ?? '');
    }
    public function private_notes(Invoice $invoice)
    {
        return $invoice->private_notes ?? '';
    }
    public function uses_inclusive_taxes(Invoice $invoice)
    {
        return $invoice->uses_inclusive_taxes ? ctrans('texts.yes') : ctrans('texts.no');
    }
    public function is_amount_discount(Invoice $invoice)
    {
        return $invoice->is_amount_discount ? ctrans('texts.yes') : ctrans('texts.no');
    }

    public function partial_due_date(Invoice $invoice)
    {
        return $invoice->partial_due_date ?? '';
    }

    public function assigned_user_id(Invoice $invoice)
    {
        return $invoice->assigned_user ? $invoice->assigned_user->present()->name() : '';
    }
    public function user_id(Invoice $invoice)
    {
        return $invoice->user ? $invoice->user->present()->name() : '';
    }

    public function recurring_id(Invoice $invoice)
    {
        return $invoice->recurring_invoice ? $invoice->recurring_invoice->number : '';
    }

    public function auto_bill_enabled(Invoice $invoice)
    {
        return $invoice->auto_bill_enabled ? ctrans('texts.yes') : ctrans('texts.no');
    }

}

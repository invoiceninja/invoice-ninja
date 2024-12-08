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

use App\Models\Expense;

class ExpenseDecorator implements DecoratorInterface
{
    public function transform(string $key, mixed $entity): mixed
    {
        $expense = false;

        if ($entity instanceof Expense) {
            $expense = $entity;
        } elseif ($entity->expense) {
            $expense = $entity->expense;
        }

        if ($expense && method_exists($this, $key)) {
            return $this->{$key}($expense);
        } elseif ($expense && ($expense->{$key} ?? false)) {
            return $expense->{$key};
        }

        return '';

    }

    public function category(Expense $expense)
    {
        return $this->category_id($expense);
    }

    public function category_id(Expense $expense)
    {
        return $expense->category ? $expense->category->name : '';
    }

    public function client_id(Expense $expense)
    {
        return $expense->client ? $expense->client->present()->name() : '';
    }

    public function currency_id(Expense $expense)
    {
        return $expense->currency ? $expense->currency->code : $expense->company->currency()->code;
    }
    public function date(Expense $expense)
    {
        return $expense->date ?? '';
    }
    public function exchange_rate(Expense $expense)
    {
        return $expense->exchange_rate ?? 0;
    }
    public function foreign_amount(Expense $expense)
    {
        return $expense->foreign_amount ?? 0;
    }
    public function invoice_currency_id(Expense $expense)
    {
        return $expense->invoice_currency ? $expense->invoice_currency->code : $expense->company->currency()->code;
    }
    public function payment_date(Expense $expense)
    {
        return $expense->payment_date ?? '';
    }

    public function payment_type_id(Expense $expense)
    {
        return $expense->payment_type ? $expense->payment_type->name : '';
    }
    public function private_notes(Expense $expense)
    {
        return strip_tags($expense->private_notes ?? '');
    }
    public function project_id(Expense $expense)
    {
        return $expense->project ? $expense->project->name : '';
    }
    public function public_notes(Expense $expense)
    {
        return strip_tags($expense->public_notes ?? '');
    }

    public function vendor_id(Expense $expense)
    {
        return $expense->vendor ? $expense->vendor->name : '';
    }
    public function invoice_id(Expense $expense)
    {
        return $expense->invoice ? $expense->invoice->number : '';
    }
    public function user(Expense $expense)
    {
        return $expense->user ? $expense->user->present()->name() : '';
    }
    public function assigned_user(Expense $expense)
    {
        return $expense->assigned_user ? $expense->assigned_user->present()->name() : '';
    }

}

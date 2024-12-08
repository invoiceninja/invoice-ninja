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

namespace App\Transformers;

use App\Models\Activity;
use App\Models\Backup;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Utils\Traits\MakesHash;

class InvoiceTransformer extends EntityTransformer
{
    use MakesHash;

    protected array $defaultIncludes = [
        'invitations',
        'documents',
    ];

    protected array $availableIncludes = [
        'payments',
        'client',
        'activities',
    ];

    public function includeInvitations(Invoice $invoice)
    {
        $transformer = new InvoiceInvitationTransformer($this->serializer);

        return $this->includeCollection($invoice->invitations, $transformer, InvoiceInvitation::class);
    }

    public function includeHistory(Invoice $invoice)
    {
        $transformer = new InvoiceHistoryTransformer($this->serializer);

        return $this->includeCollection($invoice->history, $transformer, Backup::class);
    }

    public function includeClient(Invoice $invoice)
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeItem($invoice->client, $transformer, Client::class);
    }

    public function includePayments(Invoice $invoice)
    {
        $transformer = new PaymentTransformer($this->serializer);

        return $this->includeCollection($invoice->payments, $transformer, Payment::class);
    }

    public function includeCredits(Invoice $invoice)
    {
        $transformer = new CreditTransformer($this->serializer);

        return $this->includeCollection($invoice->credits, $transformer, Credit::class);
    }

    /*
        public function includeExpenses(Invoice $invoice)
        {
            $transformer = new ExpenseTransformer($this->account, $this->serializer);

            return $this->includeCollection($invoice->expenses, $transformer, ENTITY_EXPENSE);
        }
    */
    public function includeDocuments(Invoice $invoice)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($invoice->documents, $transformer, Document::class);
    }

    public function includeActivities(Invoice $invoice)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($invoice->activities, $transformer, Activity::class);
    }

    public function transform(Invoice $invoice)
    {
        $data = [
            'id' => $this->encodePrimaryKey($invoice->id),
            'user_id' => $this->encodePrimaryKey($invoice->user_id),
            'project_id' => $this->encodePrimaryKey($invoice->project_id),
            'assigned_user_id' => $this->encodePrimaryKey($invoice->assigned_user_id),
            'amount' => (float) $invoice->amount,
            'balance' => (float) $invoice->balance,
            'client_id' => (string) $this->encodePrimaryKey($invoice->client_id),
            'vendor_id' => (string) $this->encodePrimaryKey($invoice->vendor_id),
            'status_id' => (string) ($invoice->status_id ?: '1'),
            'design_id' => (string) $this->encodePrimaryKey($invoice->design_id),
            'recurring_id' => (string) $this->encodePrimaryKey($invoice->recurring_id),
            'created_at' => (int) $invoice->created_at,
            'updated_at' => (int) $invoice->updated_at,
            'archived_at' => (int) $invoice->deleted_at,
            'is_deleted' => (bool) $invoice->is_deleted,
            'number' => $invoice->number ?: '',
            'discount' => (float) $invoice->discount,
            'po_number' => $invoice->po_number ?: '',
            'date' => $invoice->date ?: '',
            'last_sent_date' => $invoice->last_sent_date ?: '',
            'next_send_date' => $invoice->next_send_date ?: '',
            'due_date' => $invoice->due_date ?: '',
            'terms' => $invoice->terms ?: '',
            'public_notes' => $invoice->public_notes ?: '',
            'private_notes' => $invoice->private_notes ?: '',
            'uses_inclusive_taxes' => (bool) $invoice->uses_inclusive_taxes,
            'tax_name1' => $invoice->tax_name1 ? $invoice->tax_name1 : '',
            'tax_rate1' => (float) $invoice->tax_rate1,
            'tax_name2' => $invoice->tax_name2 ? $invoice->tax_name2 : '',
            'tax_rate2' => (float) $invoice->tax_rate2,
            'tax_name3' => $invoice->tax_name3 ? $invoice->tax_name3 : '',
            'tax_rate3' => (float) $invoice->tax_rate3,
            'total_taxes' => (float) $invoice->total_taxes,
            'is_amount_discount' => (bool) ($invoice->is_amount_discount ?: false),
            'footer' => $invoice->footer ?: '',
            'partial' => (float) ($invoice->partial ?: 0.0),
            'partial_due_date' => ($invoice->partial_due_date && $invoice->partial_due_date != "-0001-11-30") ? $invoice->partial_due_date->format('Y-m-d') : '',
            'custom_value1' => (string) $invoice->custom_value1 ?: '',
            'custom_value2' => (string) $invoice->custom_value2 ?: '',
            'custom_value3' => (string) $invoice->custom_value3 ?: '',
            'custom_value4' => (string) $invoice->custom_value4 ?: '',
            'has_tasks' => (bool) false, //@deprecated v5.0.23
            'has_expenses' => (bool) false, //@deprecated v5.0.23
            'custom_surcharge1' => (float) $invoice->custom_surcharge1,
            'custom_surcharge2' => (float) $invoice->custom_surcharge2,
            'custom_surcharge3' => (float) $invoice->custom_surcharge3,
            'custom_surcharge4' => (float) $invoice->custom_surcharge4,
            'exchange_rate' => (float) $invoice->exchange_rate,
            'custom_surcharge_tax1' => (bool) $invoice->custom_surcharge_tax1,
            'custom_surcharge_tax2' => (bool) $invoice->custom_surcharge_tax2,
            'custom_surcharge_tax3' => (bool) $invoice->custom_surcharge_tax3,
            'custom_surcharge_tax4' => (bool) $invoice->custom_surcharge_tax4,
            'line_items' => $invoice->line_items ?: (array) [],
            'entity_type' => 'invoice',
            'reminder1_sent' => $invoice->reminder1_sent ?: '',
            'reminder2_sent' => $invoice->reminder2_sent ?: '',
            'reminder3_sent' => $invoice->reminder3_sent ?: '',
            'reminder_last_sent' => $invoice->reminder_last_sent ?: '',
            'paid_to_date' => (float) $invoice->paid_to_date,
            'subscription_id' => $this->encodePrimaryKey($invoice->subscription_id),
            'auto_bill_enabled' => (bool) $invoice->auto_bill_enabled,
            'tax_info' => $invoice->tax_data ?: new \stdClass(),
            'e_invoice' => $invoice->e_invoice ?: new \stdClass(),
            'backup' => $invoice->backup ?: new \stdClass(),
        ];

        if (request()->has('reminder_schedule') && request()->query('reminder_schedule') == 'true') {
            $data['reminder_schedule'] = (string) $invoice->reminderSchedule();
        }

        if (request()->has('is_locked') && request()->query('is_locked') == 'true') {
            $data['is_locked'] = (bool) $invoice->isLocked();
        }

        return $data;

    }
}

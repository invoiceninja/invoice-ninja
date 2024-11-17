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

namespace App\Factory;

use App\Models\RecurringInvoice;

class RecurringInvoiceFactory
{
    public static function create(int $company_id, int $user_id): RecurringInvoice
    {
        $invoice = new RecurringInvoice();
        $invoice->status_id = RecurringInvoice::STATUS_DRAFT;
        $invoice->discount = 0;
        $invoice->is_amount_discount = true;
        $invoice->po_number = '';
        // $invoice->number = '';
        $invoice->footer = '';
        $invoice->terms = '';
        $invoice->public_notes = '';
        $invoice->private_notes = '';
        $invoice->date = null;
        $invoice->due_date = null;
        $invoice->due_date_days = 'terms';
        $invoice->partial_due_date = null;
        $invoice->is_deleted = false;
        $invoice->line_items = json_encode([]);
        $invoice->tax_name1 = '';
        $invoice->tax_rate1 = 0;
        $invoice->tax_name2 = '';
        $invoice->tax_rate2 = 0;
        $invoice->tax_name3 = '';
        $invoice->tax_rate3 = 0;
        $invoice->uses_inclusive_taxes = false;
        $invoice->custom_surcharge_tax1 = false;
        $invoice->custom_surcharge_tax2 = false;
        $invoice->custom_surcharge_tax3 = false;
        $invoice->custom_surcharge_tax4 = false;
        $invoice->custom_value1 = '';
        $invoice->custom_value2 = '';
        $invoice->custom_value3 = '';
        $invoice->custom_value4 = '';
        $invoice->amount = 0;
        $invoice->balance = 0;
        $invoice->partial = 0;
        $invoice->user_id = $user_id;
        $invoice->company_id = $company_id;
        $invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $invoice->last_sent_date = null;
        $invoice->next_send_date = null;
        $invoice->next_send_date_client = null;
        $invoice->remaining_cycles = -1;
        $invoice->paid_to_date = 0;
        $invoice->auto_bill_enabled = false;
        $invoice->is_proforma = false;
        $invoice->auto_bill = 'off';

        return $invoice;

    }
}

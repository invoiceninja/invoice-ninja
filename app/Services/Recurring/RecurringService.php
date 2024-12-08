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

namespace App\Services\Recurring;

use App\Utils\Ninja;
use App\Models\Subscription;
use App\Models\RecurringQuote;
use Illuminate\Support\Carbon;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use Illuminate\Support\Facades\Storage;
use App\Jobs\RecurringInvoice\SendRecurring;

class RecurringService
{
    use MakesHash;

    public function __construct(public RecurringInvoice | RecurringExpense | RecurringQuote $recurring_entity)
    {
    }

    //set schedules - update next_send_dates

    /**
     * Stops a recurring invoice
     *
     * @return $this RecurringService object
     */
    public function stop()
    {
        if ($this->recurring_entity->status_id < RecurringInvoice::STATUS_PAUSED) {
            $this->recurring_entity->status_id = RecurringInvoice::STATUS_PAUSED;
        }

        return $this;
    }

    public function createInvitations()
    {
        $this->recurring_entity = (new CreateRecurringInvitations($this->recurring_entity))->run();

        return $this;
    }

    public function start()
    {
        if ($this->recurring_entity->remaining_cycles == 0 || $this->recurring_entity->is_deleted) {
            return $this;
        }

        if ($this->recurring_entity->trashed()) {
            $this->recurring_entity->restore();
        }

        $this->setStatus(RecurringInvoice::STATUS_ACTIVE);

        return $this;
    }

    public function setStatus($status)
    {
        $this->recurring_entity->status_id = $status;

        return $this;
    }

    /**
     * Applies the invoice number.
     * @return $this InvoiceService object
     */
    public function applyNumber()
    {
        $this->recurring_entity = (new ApplyNumber($this->recurring_entity->client, $this->recurring_entity))->run();

        return $this;
    }

    public function getInvoicePdf($contact = null)
    {
        return (new GetInvoicePdf($this->recurring_entity, $contact))->run();
    }

    public function deletePdf()
    {
        $this->recurring_entity->invitations->each(function ($invitation) {

            //30-06-2023
            try {
                Storage::disk(config('filesystems.default'))->delete($this->recurring_entity->client->recurring_invoice_filepath($invitation) . $this->recurring_entity->numberFormatter().'.pdf');
                Storage::disk('public')->delete($this->recurring_entity->client->recurring_invoice_filepath($invitation) . $this->recurring_entity->numberFormatter().'.pdf');
                if (Ninja::isHosted()) {
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }

        });


        return $this;
    }

    public function triggeredActions($request)
    {
        if ($request->has('start') && $request->input('start') == 'true') {
            $this->start();
        }

        if ($request->has('stop') && $request->input('stop') == 'true') {
            $this->stop();
        }

        if ($request->has('send_now') && $request->input('send_now') == 'true' && $this->recurring_entity->invoices()->count() == 0) {
            $this->sendNow();

            return $this;
        }

        if (isset($this->recurring_entity->client)) {
            $offset = $this->recurring_entity->client->timezone_offset();
            $this->recurring_entity->next_send_date = Carbon::parse($this->recurring_entity->next_send_date_client)->startOfDay()->addSeconds($offset);
        }

        return $this;
    }

    public function sendNow()
    {
        if ($this->recurring_entity instanceof RecurringInvoice && $this->recurring_entity->status_id == RecurringInvoice::STATUS_DRAFT) {
            $this->start()->save();
            (new SendRecurring($this->recurring_entity, $this->recurring_entity->company->db))->handle();
        }

        $this->recurring_entity = $this->recurring_entity->fresh();

        return $this;
    }

    public function fillDefaults()
    {
        return $this;
    }

    public function increasePrice(float $percentage)
    {
        (new IncreasePrice($this->recurring_entity, $percentage))->run();

        return $this;
    }

    public function updatePrice()
    {
        (new UpdatePrice($this->recurring_entity))->run();

        return $this;
    }

    public function setPaymentLink(string $subscription_id): self
    {

        $sub_id = $this->decodePrimaryKey($subscription_id);

        if (Subscription::withTrashed()->where('id', $sub_id)->where('company_id', $this->recurring_entity->company_id)->exists()) {
            $this->recurring_entity->subscription_id = $sub_id;
        }

        return $this;

    }

    public function save()
    {
        $this->recurring_entity->saveQuietly();

        return $this->recurring_entity;
    }
}

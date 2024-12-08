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

namespace App\Services\Subscription;

use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Support\Carbon;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class SubscriptionStatus extends AbstractService
{
    public function __construct(public Subscription $subscription, protected RecurringInvoice $recurring_invoice)
    {
    }

    /** @var bool $is_trial */
    public bool $is_trial = false;

    /** @var bool $is_refundable */
    public bool $is_refundable = false;

    /** @var bool $is_in_good_standing */
    public bool $is_in_good_standing = false;

    /** @var Invoice $refundable_invoice */
    public Invoice $refundable_invoice;

    public function run(): self
    {
        $this->checkTrial()
            ->checkRefundable()
            ->checkInGoodStanding();

        return $this;
    }

    /**
     * GetProRataRefund
     *
     * @return float
     */
    public function getProRataRefund(): float
    {

        $subscription_interval_end_date = Carbon::parse($this->recurring_invoice->next_send_date_client);
        $subscription_interval_start_date = $subscription_interval_end_date->copy()->subDays($this->recurring_invoice->subscription->service()->getDaysInFrequency())->subDay();

        $primary_invoice =  Invoice::query()
                                    ->where('company_id', $this->recurring_invoice->company_id)
                                    ->where('client_id', $this->recurring_invoice->client_id)
                                    ->where('recurring_id', $this->recurring_invoice->id)
                                    ->whereIn('status_id', [Invoice::STATUS_PAID])
                                    ->whereBetween('date', [$subscription_interval_start_date, $subscription_interval_end_date])
                                    ->where('is_deleted', 0)
                                    ->where('is_proforma', 0)
                                    ->orderBy('id', 'desc')
                                    ->first();

        $this->refundable_invoice = $primary_invoice;

        return $primary_invoice ? max(0, round(($primary_invoice->paid_to_date * $this->getProRataRatio()), 2)) : 0;

    }

    /**
     * GetProRataRatio
     *
     * The ratio of days used / days in interval
     * @return float
     */
    public function getProRataRatio(): float
    {

        $subscription_interval_end_date = Carbon::parse($this->recurring_invoice->next_send_date_client);
        $subscription_interval_start_date = $subscription_interval_end_date->copy()->subDays($this->recurring_invoice->subscription->service()->getDaysInFrequency())->subDay();

        $primary_invoice = Invoice::query()
                                ->where('company_id', $this->recurring_invoice->company_id)
                                ->where('client_id', $this->recurring_invoice->client_id)
                                ->where('recurring_id', $this->recurring_invoice->id)
                                ->whereIn('status_id', [Invoice::STATUS_PAID])
                                ->whereBetween('date', [$subscription_interval_start_date, $subscription_interval_end_date])
                                ->where('is_deleted', 0)
                                ->where('is_proforma', 0)
                                ->orderBy('id', 'desc')
                                ->first();

        if (!$primary_invoice) {
            return 0;
        }

        $subscription_start_date = Carbon::parse($primary_invoice->date)->startOfDay();

        $days_of_subscription_used = intval(abs($subscription_start_date->copy()->diffInDays(now())));

        return 1 - ($days_of_subscription_used / $this->recurring_invoice->subscription->service()->getDaysInFrequency());

    }

    /**
     * CheckInGoodStanding
     *
     * Are there any outstanding invoices?
     *
     * @return self
     */
    private function checkInGoodStanding(): self
    {

        $this->is_in_good_standing = Invoice::query()
                                     ->where('company_id', $this->recurring_invoice->company_id)
                                     ->where('client_id', $this->recurring_invoice->client_id)
                                     ->where('recurring_id', $this->recurring_invoice->id)
                                     ->where('is_deleted', 0)
                                     ->where('is_proforma', 0)
                                     ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                     ->where('balance', '>', 0)
                                     ->doesntExist();

        return $this;

    }

    /**
     * CheckTrial
     *
     * Check if this subscription is in its trial window.
     *
     * Trials do not have an invoice yet - only a pending recurring invoice.
     *
     * @return self
     */
    private function checkTrial(): self
    {

        if (!$this->subscription->trial_enabled) {
            return $this->setIsTrial(false);
        }

        $primary_invoice = Invoice::query()
                            ->where('company_id', $this->recurring_invoice->company_id)
                            ->where('client_id', $this->recurring_invoice->client_id)
                            ->where('recurring_id', $this->recurring_invoice->id)
                            ->where('is_deleted', 0)
                            ->where('is_proforma', 0)
                            ->orderBy('id', 'asc')
                            ->doesntExist();

        if ($primary_invoice && Carbon::parse($this->recurring_invoice->next_send_date_client)->gte(now()->startOfDay()->addSeconds($this->recurring_invoice->client->timezone_offset()))) {
            return $this->setIsTrial(true);
        }

        $this->setIsTrial(false);

        return $this;

    }

    /**
     * Determines if this subscription
     * is eligible for a refund.
     *
     * @return self
     */
    private function checkRefundable(): self
    {
        if (!$this->recurring_invoice->subscription->refund_period || (int)$this->recurring_invoice->subscription->refund_period == 0) {//@phpstan-ignore-line
            return $this->setRefundable(false);
        }

        $primary_invoice = $this->recurring_invoice
                                ->invoices()
                                ->where('is_deleted', 0)
                                ->where('is_proforma', 0)
                                ->orderBy('id', 'desc')
                                ->first();

        if ($primary_invoice &&
        $primary_invoice->status_id == Invoice::STATUS_PAID &&
        Carbon::parse($primary_invoice->date)->addSeconds($this->recurring_invoice->subscription->refund_period)->lte(now()->startOfDay()->addSeconds($primary_invoice->client->timezone_offset()))
        ) {
            return $this->setRefundable(true);
        }

        return $this->setRefundable(false);

    }

    /**
     * setRefundable
     *
     * @param  bool $refundable
     * @return self
     */
    private function setRefundable(bool $refundable): self
    {
        $this->is_refundable = $refundable;

        return $this;
    }

    /**
     * Sets the is_trial flag
     *
     * @param  bool $is_trial
     * @return self
     */
    private function setIsTrial(bool $is_trial): self
    {
        $this->is_trial = $is_trial;

        return $this;
    }

}

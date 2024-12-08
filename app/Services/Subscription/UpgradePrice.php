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

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;

class UpgradePrice extends AbstractService
{
    protected \App\Services\Subscription\SubscriptionStatus $status;

    public float $upgrade_price = 0;

    public float $refund = 0;

    public float $outstanding_credit = 0;

    public function __construct(protected RecurringInvoice $recurring_invoice, public Subscription $subscription)
    {
    }

    public function run(): self
    {

        $this->status = $this->recurring_invoice
                       ->subscription
                       ->status($this->recurring_invoice);

        if ($this->status->is_in_good_standing) {
            $this->calculateUpgrade();
        } else {
            $this->upgrade_price = $this->subscription->price;
        }

        return $this;

    }

    private function calculateUpgrade(): self
    {
        $ratio = $this->status->getProRataRatio();

        $last_invoice = $this->recurring_invoice
                             ->invoices()
                             ->where('is_deleted', 0)
                             ->where('is_proforma', 0)
                             ->orderBy('id', 'desc')
                             ->first();

        $this->refund = $this->getRefundableAmount($last_invoice, $ratio);
        $this->outstanding_credit = $this->getCredits();

        nlog("{$this->subscription->price} - {$this->refund} - {$this->outstanding_credit}");

        $this->upgrade_price = $this->subscription->price - $this->refund - $this->outstanding_credit;

        return $this;
    }

    private function getRefundableAmount(?Invoice $invoice, float $ratio): float
    {
        if (!$invoice || !$invoice->date || $invoice->status_id != Invoice::STATUS_PAID || $ratio == 0) {
            return 0;
        }

        return max(0, round(($invoice->paid_to_date * $ratio), 2));
    }

    private function getCredits(): float
    {
        $outstanding_credits = 0;

        $use_credit_setting = $this->recurring_invoice->client->getSetting('use_credits_payment');

        if ($use_credit_setting) {

            $outstanding_credits = Credit::query()
                               ->where('client_id', $this->recurring_invoice->client_id)
                               ->whereIn('status_id', [Credit::STATUS_SENT,Credit::STATUS_PARTIAL])
                               ->where('is_deleted', 0)
                               ->where('balance', '>', 0)
                               ->sum('balance');

        }

        return $outstanding_credits;
    }

}

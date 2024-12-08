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
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Utils\Traits\MakesHash;
use App\Helpers\Invoice\ProRata;
use App\Repositories\InvoiceRepository;

/**
 * SubscriptionCalculator.
 */
class SubscriptionCalculator
{
    use MakesHash;

    public function __construct(public Subscription $subscription)
    {
    }

    /**
     * BuildPurchaseInvoice
     *
     * @param  array $context
     * @return Invoice
     */
    public function buildPurchaseInvoice(array $context): Invoice
    {

        $invoice_repo = new InvoiceRepository();

        $invoice = InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $invoice->subscription_id = $this->subscription->id;
        $invoice->client_id = $this->decodePrimaryKey($context['client_id']);
        $invoice->is_proforma = true;
        $invoice->number = "####" . ctrans('texts.subscription') . "_" . now()->format('Y-m-d') . "_" . rand(0, 100000);
        $invoice->line_items = $this->buildItems($context);

        if (isset($context['valid_coupon']) && $context['valid_coupon']) {
            $invoice->discount = $this->subscription->promo_discount;
            $invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        return $invoice_repo->save([], $invoice);

    }

    /**
     * Build Line Items
     *
     * @param array $context
     *
     * @return array
     */
    private function buildItems(array $context): array
    {

        $bundle = $context['bundle'];

        $recurring = array_merge(isset($bundle['recurring_products']) ? $bundle['recurring_products'] : [], isset($bundle['optional_recurring_products']) ? $bundle['optional_recurring_products'] : []);
        $one_time = array_merge(isset($bundle['one_time_products']) ? $bundle['one_time_products'] : [], isset($bundle['optional_one_time_products']) ? $bundle['optional_one_time_products'] : []);

        $items = [];

        foreach ($recurring as $item) {

            if ($item['quantity'] < 1) {
                continue;
            }

            $line_item = new InvoiceItem();
            $line_item->product_key = $item['product']['product_key'];
            $line_item->quantity = (float) $item['quantity'];
            $line_item->cost = (float) $item['product']['price'];
            $line_item->notes = $item['product']['notes'];
            $line_item->tax_id = (string)$item['product']['tax_id'] ?? '1';
            $items[] = $line_item;

        }

        foreach ($one_time as $item) {

            if ($item['quantity'] < 1) {
                continue;
            }

            $line_item = new InvoiceItem();
            $line_item->product_key = $item['product']['product_key'];
            $line_item->quantity = (float) $item['quantity'];
            $line_item->cost = (float) $item['product']['price'];
            $line_item->notes = $item['product']['notes'];
            $line_item->tax_id = (string)$item['product']['tax_id'] ?? '1'; //@phpstan-ignore-line
            $items[] = $line_item;

        }

        return $items;
    }



































    /**
     * Tests if the user is currently up
     * to date with their payments for
     * a given recurring invoice
     *
     * @return bool
     */
    public function isPaidUp(Invoice $invoice): bool
    {
        $outstanding_invoices_exist = Invoice::query()->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                             ->where('subscription_id', $invoice->subscription_id)
                                             ->where('client_id', $invoice->client_id)
                                             ->where('balance', '>', 0)
                                             ->exists();

        return ! $outstanding_invoices_exist;
    }

    public function calcUpgradePlan(Invoice $invoice)
    {
        //set the starting refund amount
        $refund_amount = 0;

        $refund_invoice = false;

        //are they paid up to date.

        //yes - calculate refund
        if ($this->isPaidUp($invoice)) {
            $refund_invoice = $this->getRefundInvoice($invoice);
        }

        if ($refund_invoice) {
            /** @var \App\Models\Subscription $subscription **/
            $subscription = Subscription::find($invoice->subscription_id);
            $pro_rata = new ProRata();

            $to_date = $subscription->service()->getNextDateForFrequency(Carbon::parse($refund_invoice->date), $subscription->frequency_id);

            $refund_amount = $pro_rata->refund($refund_invoice->amount, now(), $to_date, $subscription->frequency_id);

            $charge_amount = $pro_rata->charge($this->subscription->price, now(), $to_date, $this->subscription->frequency_id);

            return $charge_amount - $refund_amount;
        }

        //no - return full freight charge.
        return $this->subscription->price;
    }

    public function executeUpgradePlan()
    {
    }

    private function getRefundInvoice(Invoice $invoice)
    {
        return Invoice::where('subscription_id', $invoice->subscription_id)
                      ->where('client_id', $invoice->client_id)
                      ->where('is_deleted', 0)
                      ->orderBy('id', 'desc')
                      ->first();
    }
}

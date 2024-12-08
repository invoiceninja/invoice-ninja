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
use App\Factory\CreditFactory;
use App\DataMapper\InvoiceItem;
use App\Factory\InvoiceFactory;
use App\Models\RecurringInvoice;
use App\Services\AbstractService;
use App\Repositories\CreditRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\SubscriptionRepository;

class ChangePlanInvoice extends AbstractService
{
    protected \App\Services\Subscription\SubscriptionStatus $status;

    public function __construct(protected RecurringInvoice $recurring_invoice, public Subscription $target, public string $hash)
    {
    }

    public function run(): Invoice | Credit
    {

        $this->status = $this->recurring_invoice
                    ->subscription
                    ->status($this->recurring_invoice);

        //refund
        $refund = $this->status->getProRataRefund();

        //newcharges
        $new_charge = $this->target->price;

        $invoice = $this->generateInvoice($refund);

        if ($refund >= $new_charge) {
            $invoice = $invoice->markPaid()->save();

            //generate new recurring invoice at this point as we know the user has succeeded with their upgrade.
        }

        if ($refund > $new_charge) {
            return $this->generateCredit($refund - $new_charge);
        }

        return $invoice;
    }

    private function generateCredit(float $credit_balance): Credit
    {

        $credit_repo = new CreditRepository();

        $credit = CreditFactory::create($this->target->company_id, $this->target->user_id);
        $credit->status_id = Credit::STATUS_SENT;
        $credit->date = now()->addSeconds($this->recurring_invoice->client->timezone_offset())->format('Y-m-d');
        $credit->subscription_id = $this->target->id;

        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = '1';
        $invoice_item->product_key = ctrans('texts.credit');
        $invoice_item->notes = ctrans('texts.credit') . " # {$this->recurring_invoice->subscription->name} #";
        $invoice_item->quantity = 1;
        $invoice_item->cost = $credit_balance;

        $invoice_items = [];
        $invoice_items[] = $invoice_item;

        $data = [
            'client_id' => $this->recurring_invoice->client_id,
            'date' => now()->format('Y-m-d'),
        ];

        return $credit_repo->save($data, $credit)->service()->markSent()->fillDefaults()->save();

    }

    //Careful with Invoice Numbers.
    private function generateInvoice(float $refund): Invoice
    {

        $subscription_repo = new SubscriptionRepository();
        $invoice_repo = new InvoiceRepository();

        $invoice = InvoiceFactory::create($this->target->company_id, $this->target->user_id);
        $invoice->date = now()->format('Y-m-d');
        $invoice->subscription_id = $this->target->id;

        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = '1';
        $invoice_item->product_key = ctrans('texts.refund');
        $invoice_item->notes = ctrans('texts.refund'). " #{$this->status->refundable_invoice->number}";
        $invoice_item->quantity = 1;
        $invoice_item->cost = $refund;

        $invoice_items = [];
        $invoice_items[] = $subscription_repo->generateLineItems($this->target);
        $invoice_items[] = $invoice_item;
        $invoice->line_items = $invoice_items;
        $invoice->is_proforma = true;

        $data = [
            'client_id' => $this->recurring_invoice->client_id,
            'date' => now()->addSeconds($this->recurring_invoice->client->timezone_offset())->format('Y-m-d'),
        ];

        $invoice = $invoice_repo->save($data, $invoice)
                                ->service()
                                ->markSent()
                                ->fillDefaults()
                                ->save();

        return $invoice;

    }
}

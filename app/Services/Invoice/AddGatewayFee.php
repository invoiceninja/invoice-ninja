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

namespace App\Services\Invoice;

use App\DataMapper\InvoiceItem;
use App\Models\CompanyGateway;
use App\Models\Invoice;
use App\Services\AbstractService;
use App\Utils\Ninja;
use Illuminate\Support\Facades\App;
use App\Models\Product;

class AddGatewayFee extends AbstractService
{
    public function __construct(private CompanyGateway $company_gateway, private int $gateway_type_id, public Invoice $invoice, private float $amount, private string $payment_hash_string)
    {
    }

    public function run()
    {
        $gateway_fee = round($this->company_gateway->calcGatewayFee($this->amount, $this->gateway_type_id, $this->invoice->uses_inclusive_taxes), $this->invoice->client->currency()->precision);

        if (! $gateway_fee || $gateway_fee == 0) {
            return $this->invoice;
        }

        return $gateway_fee > 0 ? $this->processGatewayFee($gateway_fee) : $this->processGatewayDiscount($gateway_fee);

    }

    private function processGatewayFee($gateway_fee)
    {
        $balance = $this->invoice->balance;

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->invoice->company->settings));
        App::setLocale($this->invoice->client->locale());

        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = '3';
        $invoice_item->product_key = ctrans('texts.surcharge');
        $invoice_item->notes = ctrans('texts.online_payment_surcharge');
        $invoice_item->quantity = 1;
        $invoice_item->cost = $gateway_fee;
        $invoice_item->unit_code = $this->payment_hash_string;

        if ($fees_and_limits = $this->company_gateway->getFeesAndLimits($this->gateway_type_id)) {
            $invoice_item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
            $invoice_item->tax_name1 = $fees_and_limits->fee_tax_name1;
            $invoice_item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
            $invoice_item->tax_name2 = $fees_and_limits->fee_tax_name2;
            $invoice_item->tax_rate3 = $fees_and_limits->fee_tax_rate3;
            $invoice_item->tax_name3 = $fees_and_limits->fee_tax_name3;
            $invoice_item->tax_id = (string)Product::PRODUCT_TYPE_OVERRIDE_TAX;
        }

        $invoice_items = (array) $this->invoice->line_items;
        $invoice_items[] = $invoice_item;

        $this->invoice->line_items = $invoice_items;

        /**Refresh Invoice values*/
        $this->invoice = $this->invoice->calc()->getInvoice();

        $new_balance = $this->invoice->balance;

        if (floatval($new_balance) - floatval($balance) != 0) {
            $adjustment = $new_balance - $balance;

            $this->invoice
            ->ledger()
            ->updateInvoiceBalance($adjustment, 'Adjustment for adding gateway fee');

            $this->invoice->client->service()->calculateBalance();

        }

        return $this->invoice;
    }

    private function processGatewayDiscount($gateway_fee)
    {
        $balance = $this->invoice->balance;

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->invoice->company->settings));

        $invoice_item = new InvoiceItem();
        $invoice_item->type_id = '3';
        $invoice_item->product_key = ctrans('texts.discount');
        $invoice_item->notes = ctrans('texts.online_payment_discount');
        $invoice_item->quantity = 1;
        $invoice_item->cost = $gateway_fee;

        if ($fees_and_limits = $this->company_gateway->getFeesAndLimits($this->gateway_type_id)) {
            $invoice_item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
            $invoice_item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
            $invoice_item->tax_rate3 = $fees_and_limits->fee_tax_rate3;
        }

        $invoice_items = (array) $this->invoice->line_items;
        $invoice_items[] = $invoice_item;

        $this->invoice->line_items = $invoice_items;

        $this->invoice = $this->invoice->calc()->getInvoice();

        $new_balance = $this->invoice->balance;

        if (floatval($new_balance) - floatval($balance) != 0) {
            $adjustment = $new_balance - $balance;

            $this->invoice
            ->ledger()
            ->updateInvoiceBalance($adjustment * -1, 'Adjustment for adding gateway DISCOUNT');

            $this->invoice->client->service()->calculateBalance();

        }

        return $this->invoice;
    }
}

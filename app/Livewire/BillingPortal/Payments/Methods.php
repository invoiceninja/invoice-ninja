<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal\Payments;

use Livewire\Component;
use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;

class Methods extends Component
{
    use MakesHash;

    public string $subscription_id;

    public array $context;

    public array $methods;

    public function mount(): void
    {
        $total = collect($this->context['products'])->sum('total_raw');

        $methods = auth()->guard('contact')->user()->client->service()->getPaymentMethods($total); //@todo this breaks down when the cart is in front of the login - we have no context on the user - nor their country/currency()

        $this->methods = $methods;

    }

    public function handleSelect(string $company_gateway_id, string $gateway_type_id)
    {
        /** @var \App\Models\ClientContact $contact */
        $contact = auth()->guard('contact')->user();

        $sub = Subscription::find($this->decodePrimaryKey($this->subscription_id));

        $this->dispatch('purchase.context', property: 'client_id', value: $contact->client->hashed_id);

        $this->context['client_id'] = $contact->client->hashed_id;

        $invoice = $sub
            ->calc()
            ->buildPurchaseInvoice($this->context)
            ->service()
            ->markSent()
            ->fillDefaults()
            ->adjustInventory()
            ->save();

        Cache::put($this->context['hash'], [
            'subscription_id' => $sub->hashed_id,
            'email' => $contact->email,
            'client_id' => $contact->client->hashed_id,
            'invoice_id' => $invoice->hashed_id,
            'context' => 'purchase',
            'campaign' => $this->context['campaign'],
            'bundle' => $this->context['bundle'],
        ], now()->addMinutes(60));

        $payable_amount = $invoice->partial > 0
            ? \App\Utils\Number::formatValue($invoice->partial, $invoice->client->currency())
            : \App\Utils\Number::formatValue($invoice->balance, $invoice->client->currency());

        $this->dispatch('purchase.context', property: 'form.company_gateway_id', value: $company_gateway_id);
        $this->dispatch('purchase.context', property: 'form.payment_method_id', value: $gateway_type_id);
        $this->dispatch('purchase.context', property: 'form.invoice_hashed_id', value: $invoice->hashed_id);
        $this->dispatch('purchase.context', property: 'form.payable_amount', value: $payable_amount);

        $this->dispatch('purchase.next');
    }

    public function render()
    {
        return view('billing-portal.v3.payments.methods');
    }
}

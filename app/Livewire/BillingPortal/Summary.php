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

namespace App\Livewire\BillingPortal;

use App\Utils\Ninja;
use App\Utils\Number;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\App;

class Summary extends Component
{
    use MakesHash;

    public string $subscription_id;

    public array $context;

    #[Computed()]
    public function subscription()
    {
        return Subscription::find($this->decodePrimaryKey($this->subscription_id))->withoutRelations()->makeHidden(['webhook_configuration','steps']);
    }

    public function mount()
    {
        $subscription = Subscription::find($this->decodePrimaryKey($this->subscription_id));

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($subscription->company->settings));
        App::setLocale($subscription->company->locale());

        $bundle = $this->context['bundle'] ?? [
            'recurring_products' => [],
            'optional_recurring_products' => [],
            'one_time_products' => [],
            'optional_one_time_products' => [],
        ];

        foreach ($subscription->service()->recurring_products() as $key => $product) {
            $bundle['recurring_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['recurring_products'][$product->hashed_id]['quantity'] ?? 1,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['recurring_products'][$product->hashed_id]['product']['is_recurring'] = true;
        }

        foreach ($subscription->service()->products() as $key => $product) {
            $bundle['one_time_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['one_time_products'][$product->hashed_id]['quantity'] ?? 1,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['one_time_products'][$product->hashed_id]['product']['is_recurring'] = false;
        }

        foreach ($subscription->service()->optional_recurring_products() as $key => $product) {
            $bundle['optional_recurring_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['optional_recurring_products'][$product->hashed_id]['quantity'] ?? 0,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['optional_recurring_products'][$product->hashed_id]['product']['is_recurring'] = true;
        }

        foreach ($subscription->service()->optional_products() as $key => $product) {
            $bundle['optional_one_time_products'][$product->hashed_id] = [
                'product' => $product,
                'quantity' => $bundle['optional_one_time_products'][$product->hashed_id]['quantity'] ?? 0,
                'notes' => $product->markdownNotes(),
            ];
            $bundle['optional_one_time_products'][$product->hashed_id]['product']['is_recurring'] = false;
        }

        $this->dispatch('purchase.context', property: 'bundle', value: $bundle);

    }

    /**
      * Base calculations for one-time purchases
      */
    #[Computed]
    public function oneTimePurchasesTotal(): float
    {
        if (!isset($this->context['bundle']['one_time_products'])) {
            return 0.0;
        }

        $one_time = collect($this->context['bundle']['one_time_products'])->sum(function ($item) {
            return (float)$item['product']['price'] * (float)$item['quantity'];
        });

        $one_time_optional = collect($this->context['bundle']['optional_one_time_products'])->sum(function ($item) {
            return (float)$item['product']['price'] * (float)$item['quantity'];
        });

        return (float)$one_time + (float)$one_time_optional;
    }

    /**
     * Base calculations for recurring purchases
     */
    #[Computed]
    public function recurringPurchasesTotal(): float
    {
        if (!isset($this->context['bundle']['recurring_products'])) {
            return 0.0;
        }

        $recurring = collect($this->context['bundle']['recurring_products'])->sum(function ($item) {
            return (float)$item['product']['price'] * (float)$item['quantity'];
        });

        $recurring_optional = collect($this->context['bundle']['optional_recurring_products'])->sum(function ($item) {
            return (float)$item['product']['price'] * (float)$item['quantity'];
        });

        return (float)$recurring + (float)$recurring_optional;
    }

    /**
     * Calculate subtotal before any discounts
     */
    #[Computed]
    protected function calculateSubtotal(): float
    {
        return $this->oneTimePurchasesTotal() + $this->recurringPurchasesTotal();
    }

    /**
     * Calculate discount amount based on subtotal
     */
    #[Computed]
    public function discount(): float
    {
        if (!isset($this->context['valid_coupon']) ||
            $this->context['valid_coupon'] != $this->subscription()->promo_code) {
            return 0.0;
        }

        $subscription = $this->subscription();
        $discount = $subscription->promo_discount;

        return $subscription->is_amount_discount
            ? $discount
            : ($this->calculateSubtotal() * $discount / 100);
    }

    /**
     * Format subtotal for display
     */
    #[Computed]
    public function subtotal(): string
    {
        return Number::formatMoney(
            $this->calculateSubtotal(),
            $this->subscription()->company
        );
    }

    /**
     * Calculate and format final total
     */
    #[Computed]
    public function total(): string
    {
        return Number::formatMoney(
            $this->calculateSubtotal() - $this->discount(),
            $this->subscription()->company
        );
    }

    public function items()
    {
        if (isset($this->context['bundle']) === false) {
            return [];
        }

        $products = [];

        foreach ($this->context['bundle']['recurring_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'notes' => strip_tags(\Illuminate\Support\Str::markdown($item['product']['notes'] ?? '')),
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription()->company) . ' / ' . RecurringInvoice::frequencyForKey($this->subscription()->frequency_id),
            ];
        }

        foreach ($this->context['bundle']['optional_recurring_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'notes' => strip_tags(\Illuminate\Support\Str::markdown($item['product']['notes'] ?? '')),
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription()->company) . ' / ' . RecurringInvoice::frequencyForKey($this->subscription()->frequency_id),
            ];
        }

        foreach ($this->context['bundle']['one_time_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'notes' => strip_tags(\Illuminate\Support\Str::markdown($item['product']['notes'] ?? '')),
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription()->company),
            ];
        }

        foreach ($this->context['bundle']['optional_one_time_products'] as $key => $item) {
            $products[] = [
                'product_key' => $item['product']['product_key'],
                'notes' => strip_tags(\Illuminate\Support\Str::markdown($item['product']['notes'] ?? '')),
                'quantity' => $item['quantity'],
                'total_raw' => $item['product']['price'] * $item['quantity'],
                'total' => Number::formatMoney($item['product']['price'] * $item['quantity'], $this->subscription()->company),
            ];
        }

        $this->dispatch('purchase.context', property: 'products', value: $products);

        return $products;
    }

    #[On('summary.refresh')]
    public function refresh()
    {

    }

    public function render()
    {
        return view('billing-portal.v3.summary');
    }
}

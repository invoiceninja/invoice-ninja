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

namespace App\Livewire\BillingPortal\Cart;

use Livewire\Component;
use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use Livewire\Attributes\Computed;

class Coupon extends Component
{
    use MakesHash;

    public array $context;

    public string $subscription_id;

    public ?string $couponCode = null;

    public bool $showCouponCode = false;

    public function mount()
    {
        $subscription = $this->subscription();
        $this->showCouponCode = ($subscription->promo_discount > 0) && (!array_key_exists('valid_coupon', $this->context));

        if (isset($this->context['request_data']['coupon']) && $this->context['request_data']['coupon'] == $this->subscription()->promo_code) {
            $this->showCouponCode = false;
            $this->dispatch('purchase.context', property: "valid_coupon", value: $this->context['request_data']['coupon']);
        }

    }

    #[Computed()]
    public function subscription()
    {
        return Subscription::find($this->decodePrimaryKey($this->subscription_id))->withoutRelations()->makeHidden(['webhook_configuration','steps']);
    }

    public function applyCoupon()
    {

        $this->validate([
            'couponCode' => ['required', 'string', 'min:3'],
        ]);

        try {

            if ($this->couponCode == $this->subscription()->promo_code) {
                $this->showCouponCode = false;
                $this->dispatch('purchase.context', property: "valid_coupon", value: $this->couponCode);
                $this->dispatch('summary.refresh');
            } else {
                $this->addError('couponCode', ctrans('texts.invalid_coupon'));
            }

        } catch (\Exception $e) {
            $this->addError('couponCode', ctrans('texts.invalid_coupon'));
        }


    }

    public function render(): \Illuminate\View\View
    {
        return view('billing-portal.v3.cart.coupon');
    }
}

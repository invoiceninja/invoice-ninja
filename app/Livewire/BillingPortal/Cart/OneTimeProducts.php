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

class OneTimeProducts extends Component
{
    use MakesHash;

    public array $context;

    public string $subscription_id;

    #[Computed()]
    public function subscription()
    {
        return Subscription::find($this->decodePrimaryKey($this->subscription_id))->withoutRelations()->makeHidden(['webhook_configuration','steps']);
    }

    public function quantity($id, $value): void
    {
        $this->dispatch('purchase.context', property: "bundle.one_time_products.{$id}.quantity", value: $value);
    }

    public function render()
    {
        return view('billing-portal.v3.cart.one-time-products');
    }
}

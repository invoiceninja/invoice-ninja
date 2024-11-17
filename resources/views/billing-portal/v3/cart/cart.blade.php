<div>
    <div>
        <h1 class="text-4xl font-bold text-gray-900 mb-6">{{ $this->subscription->name }}</h1>
    </div>

    <livewire:billing-portal.cart.one-time-products
        :subscription_id="$this->subscription->hashed_id"
        :context="$context"
    />

    <div class="mt-5"></div>

    <livewire:billing-portal.cart.recurring-products
        :subscription_id="$this->subscription->hashed_id"
        :context="$context"
    />

    @if($this->showOptionalProductsLabel())
        <p class="text-xl mt-5 mb-4">{{ ctrans('texts.optional_products') }}</p>
    @endif

    <livewire:billing-portal.cart.optional-recurring-products
        :subscription_id="$this->subscription->hashed_id"
        :context="$context"
    />

    <div class="mt-5"></div>

    <livewire:billing-portal.cart.optional-one-time-products
        :subscription_id="$this->subscription->hashed_id"
        :context="$context"
    />

    <livewire:billing-portal.cart.coupon
        :subscription_id="$this->subscription->hashed_id"
        :context="$context"
    />
    
    <div class="mt-3">
        <form wire:submit="handleSubmit">
            <button
                type="submit"
                class="button button-block bg-primary text-white mt-4"
            >
                {{ ctrans('texts.next') }}
            </button>
        </form>
    </div>
</div>

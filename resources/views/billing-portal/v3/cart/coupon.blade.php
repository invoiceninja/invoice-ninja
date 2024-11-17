<div class="space-y-10">
    @if($showCouponCode)
    <div>
        <div class="flex rounded-lg overflow-hidden border border-gray-300">
            <div class="relative flex-grow">
                <input
                    type="text"
                    wire:model="couponCode"
                    placeholder="{{ __('texts.promo_code') }}"
                    class="block w-full px-4 py-2 border-0 outline-none focus:ring-0 sm:text-sm"
                >
            </div>
            <button
                wire:click="applyCoupon"
                wire:loading.attr="disabled"
                class="inline-flex items-center border-l border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-primary-500"
            >
                <span wire:loading.remove wire:target="applyCoupon">{{ __('texts.apply') }}</span>
                <span wire:loading wire:target="applyCoupon">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"/>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"/>
                    </svg>
                </span>
            </button>
        </div>
        @error('couponCode')
            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </div>
    @endif
</div>
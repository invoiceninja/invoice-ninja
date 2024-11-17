<div class="space-y-4">
    <h1 class="text-2xl">{{ ctrans('texts.order') }}</h1>

    @isset($this->context['bundle'])
    <div class="space-y-2">
        @foreach($this->items() as $item)
            @if($item['quantity'] > 0)
                <div class="flex justify-between text-sm">
                    <span class="truncate max-w-[350px]">{{  $item['quantity'] }} x {{ $item['notes'] }}</span>
                    <span>{{ $item['total'] }}</span>
                </div>
            @endif
        @endforeach
    </div>

        <div class="space-y-2 mt-4 border-t pt-2">
            <div class="flex justify-between text-sm">
                <span class="uppercase">{{ ctrans('texts.one_time_purchases') }}</span>
                <span>{{ \App\Utils\Number::formatMoney($this->oneTimePurchasesTotal(), $this->subscription->company) }}</span>
            </div>

            <div class="flex justify-between text-sm">
                <span class="uppercase">{{ ctrans('texts.recurring_purchases') }}</span>
                <span>{{ \App\Utils\Number::formatMoney($this->recurringPurchasesTotal(), $this->subscription->company) }}</span>
            </div>

            <div class="flex justify-between text-sm uppercase border-t pt-2">
                <span>{{ ctrans('texts.subtotal') }}</span>
                <span>{{ $this->subtotal() }}</span>
            </div>

            @if($this->discount() > 0)
            <div class="flex justify-between text-sm uppercase">
                <span>{{ ctrans('texts.discount') }}</span>
                <span class="font-semibold">{{ \App\Utils\Number::formatMoney($this->discount(), $this->subscription->company) }}</span>
            </div>
            @endif

            <div class="flex justify-between text-sm uppercase border-t pt-2">
                <span>{{ ctrans('texts.total') }}</span>
                <span class="font-semibold">{{ $this->total() }}</span>
            </div>
        </div>
    @endif
</div>

<div class="space-y-8">
    @isset($context['bundle']['recurring_products'])
        @foreach($context['bundle']['recurring_products'] as $key => $entry)
        @php
            $product = $entry['product'];
        @endphp

        <div class="border border-gray-200 rounded-lg p-6">
            @if($product['notes'])
                <article class="prose prose-sm mb-4 text-gray-600">
                    {!! \App\Models\Product::markdownHelp($product['notes']) !!}
                </article>
            @endif

            <div class="flex items-center justify-between">
                <div class="flex items-start space-x-4">
                    @if(filter_var($product['product_image'], FILTER_VALIDATE_URL))
                        <div class="h-20 w-20 flex-shrink-0 overflow-hidden rounded-lg border border-gray-200">
                            <img
                                src="{{ $product['product_image'] }}"
                                alt="{{ $product['product_key'] }}"
                                class="h-full w-full object-cover object-center"
                            />
                        </div>
                    @endif

                    <div class="flex flex-col">
                        <p class="mt-1 text-base text-gray-600">
                            {{ \App\Utils\Number::formatMoney($product['price'], $this->subscription['company']) }} / 
                            <span class="lowercase">{{ App\Models\RecurringInvoice::frequencyForKey($this->subscription->frequency_id) }}</span>
                        </p>
                    </div>
                </div>

                <div class="flex items-center">
                    @if($this->subscription->per_seat_enabled)
                        <div class="flex items-center space-x-2">
                            @if($this->subscription->use_inventory_management && $product['in_stock_quantity'] < 1)
                                <p class="text-sm font-medium text-red-600">{{ ctrans('texts.out_of_stock') }}</p>
                            @else
                                <label for="{{ $product['hashed_id'] }}" class="text-sm font-medium text-gray-700">
                                    {{ ctrans('texts.qty') }}
                                </label>
                            @endif

                            <select 
                                id="{{ $product['hashed_id'] }}" 
                                class="block w-20 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm" 
                                wire:change="quantity($event.target.id, $event.target.value)" 
                                {{ $this->subscription->use_inventory_management && $product['in_stock_quantity'] < 1 ? 'disabled' : '' }}
                            >
                                <option {{ $entry['quantity'] == '1' ? 'selected' : '' }} value="1">1</option>
                                @for ($i = 1; $i <= $this->subscription->maxQuantity($product); $i++)
                                    <option {{ $entry['quantity'] == $i ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    @endif
                </div>
            </div>
        </div>
        @endforeach 
    @endisset
</div>
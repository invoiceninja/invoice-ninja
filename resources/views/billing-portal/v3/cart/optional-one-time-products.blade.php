<div class="space-y-8">
    @isset($context['bundle']['optional_one_time_products'])
        @foreach($context['bundle']['optional_one_time_products'] as $key => $entry)
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
                            {{ \App\Utils\Number::formatMoney($product['price'], $this->subscription['company']) }}
                        </p>
                    </div>
                </div>

                <div class="flex items-center">
                    <div class="flex items-center space-x-2">
                        @if($this->subscription->use_inventory_management && $product['in_stock_quantity'] <= 0)
                            <p class="text-sm font-medium text-red-600">{{ ctrans('texts.out_of_stock') }}</p>
                        @else
                            <label for="{{ $product['hashed_id'] }}" class="text-sm font-medium text-gray-700">
                                {{ ctrans('texts.qty') }}
                            </label>
                        @endif

                        <select 
                            id="{{ $product['hashed_id'] }}" 
                            wire:change="quantity($event.target.id, $event.target.value)" 
                            class="block w-20 rounded-md border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 sm:text-sm"
                            {{ $this->subscription->use_inventory_management && $product['in_stock_quantity'] < 1 ? 'disabled' : '' }}
                        >
                            <option {{ $entry['quantity'] == '0' ? 'selected' : '' }} value="0">0</option>
                            @for ($i = 1; $i <= $this->subscription->maxQuantity($product); $i++)
                                <option {{ $entry['quantity'] == $i ? 'selected' : '' }} value="{{ $i }}">{{ $i }}</option>
                            @endfor
                        </select>
                    </div>
                </div>
            </div>
        </div>
        @endforeach 
    @endisset
</div>
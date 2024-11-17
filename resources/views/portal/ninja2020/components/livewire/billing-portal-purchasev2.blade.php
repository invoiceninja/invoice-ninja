<div class="grid grid-cols-12">
    <div class="col-span-8 bg-gray-50 flex flex-col max-h-100px items-center min-h-screen">
        <div class="w-full p-4 md:max-w-3xl">
            <div class="w-full mb-4">
                <img class="object-scale-down" style="max-height: 100px;"src="{{ $this->subscription->company->present()->logo }}" alt="{{ $this->subscription->company->present()->name }}">
                <h1 id="billing-page-company-logo" class="text-3xl font-bold tracking-wide mt-6  border-b-2">
                {{ $this->subscription->name }}
                </h1>
            </div>

            <div class="flex items-center mt-4 text-sm">
                <form action="{{ route('client.payments.process', ['hash' => $hash, 'sidebar' => 'hidden']) }}"
                      method="post"
                      id="payment-method-form">
                    @csrf

                        <input type="hidden" name="invoices[]" value="{{ $invoice_hashed_id }}">
                        <input type="hidden" name="payable_invoices[0][amount]" value="{{ $payable_amount }}">
                        <input type="hidden" name="payable_invoices[0][invoice_id]" value="{{ $invoice_hashed_id }}">

                    <input type="hidden" name="action" value="payment">
                    <input type="hidden" name="company_gateway_id" value="{{ $company_gateway_id }}"/>
                    <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}"/>
                    <input type="hidden" name="contact_first_name" value="{{ $this->contact ? $this->contact->first_name : '' }}">
                    <input type="hidden" name="contact_last_name" value="{{ $this->contact ? $this->contact->last_name : '' }}">
                    <input type="hidden" name="contact_email" value="{{ $this->contact ? $this->contact->email : '' }}">
                    <input type="hidden" name="client_city" value="{{ $this->contact ? $this->contact->client->city : '' }}">
                    <input type="hidden" name="client_postal_code" value="{{ $this->contact ? $this->contact->client->postal_code : '' }}">
                </form>
            </div>

            <form wire:submit="submit">
            <!-- Recurring Plan Products-->
            <ul role="list" class="-my-6 divide-y divide-gray-200">
            @if(!empty($this->subscription->recurring_product_ids))
                @foreach($recurring_products as $index => $product)
                    <li class="flex py-6">
                      @if(filter_var($product->product_image, FILTER_VALIDATE_URL))
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2">
                        <img src="{{$product->product_image}}" alt="" class="h-full w-full object-cover object-center p-2">
                      </div>
                      @endif
                      <div class="ml-0 flex flex-1 flex-col">
                        <div>
                          <div class="flex justify-between text-base font-medium text-gray-900">
                            <h3>
                                <article class="prose">
                                    {!! $product->markdownNotes() !!}
                                </article>
                            </h3>
                            <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $this->subscription->company) }} / {{ App\Models\RecurringInvoice::frequencyForKey($this->subscription->frequency_id) }}</p>
                          </div>
                          <p class="mt-1 text-sm text-gray-500"></p>
                        </div>
                        <div class="flex justify-between text-sm mt-1">
                            @if($this->subscription->per_seat_enabled)
                            <p class="text-gray-500 w-3/4"></p>
                            <div class="flex place-content-end">
                                @if($this->subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                <p class="text-sm font-light text-red-500 text-right mr-2 mt-2">Out of stock</p>
                                @else
                                <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                @endif
                                <select wire:model.live.debounce.300ms="data.{{ $index }}.recurring_qty" class="rounded-md border-gray-300 shadow-sm sm:text-sm" 
                                    @if($this->subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                    disabled 
                                    @endif
                                    >
                                    <option value="1" selected="selected">1</option>

                                    @if($this->subscription->max_seats_limit > 1)
                                    {
                                        @for ($i = 2; $i <= ($this->subscription->use_inventory_management ? min($this->subscription->max_seats_limit,$product->in_stock_quantity) : $this->subscription->max_seats_limit); $i++)
                                        <option value="{{$i}}">{{$i}}</option>
                                        @endfor
                                    }
                                    @else
                                        @for ($i = 2; $i <= $this->subscription->maxQuantity($product); $i++)
                                        <option value="{{$i}}">{{$i}}</option>
                                        @endfor
                                    @endif
                                </select>
                            </div>
                            @endif
                        </div>
                        @error("data.{$index}.recurring_qty") 
                        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                            <span class="block sm:inline">{{ $message }} </span>
                            <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                        </div>
                        @enderror
                      </div>
                    </li>
                @endforeach
            @endif
            <!-- One Time Plan Products-->
            @if(!empty($this->subscription->product_ids))
                @foreach($products as $product)
                    <li class="flex py-6">
                      @if(filter_var($product->product_image, FILTER_VALIDATE_URL))
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2">
                        <img src="{{$product->product_image}}" alt="" class="h-full w-full object-cover object-center  p-2">
                      </div>
                      @endif
                      <div class="ml-0 flex flex-1 flex-col">
                        <div>
                          <div class="flex justify-between text-base font-medium text-gray-900">
                            <h3>
                                <article class="prose">
                                    {!! $product->markdownNotes() !!}
                                </article>
                            </h3>
                            <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $this->subscription->company) }}</p>
                          </div>
                          <p class="mt-1 text-sm text-gray-500"></p>
                        </div>
                        <div class="flex flex-1 items-end justify-between text-sm">
                          <p class="text-gray-500"></p>
                          <div class="flex">
                          </div>
                        </div>
                      </div>
                    </li>
                @endforeach
            @endif
            </ul>
        </div>

        @if(!empty($this->subscription->optional_recurring_product_ids) || !empty($this->subscription->optional_product_ids))
        <div class="w-full p-4 md:max-w-3xl">
            <h2 class="text-2xl font-normal text-left border-b-2">{{ ctrans('texts.optional_products') }}</h2>
        </div>
        @endif
        <div class="w-full px-4 md:max-w-3xl">

            <!-- Optional Recurring Products-->
            <ul role="list" class="-my-6 divide-y divide-gray-200">
                @if(!empty($this->subscription->optional_recurring_product_ids))
                    @foreach($optional_recurring_products as $index => $product)
                        <li class="flex py-6">
                          @if(filter_var($product->product_image, FILTER_VALIDATE_URL))
                          <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2">
                            <img src="{{$product->product_image}}" alt="" class="h-full w-full object-cover object-center p-2">
                          </div>
                          @endif
                          <div class="ml-0 flex flex-1 flex-col">
                            <div>
                              <div class="flex justify-between text-base font-medium text-gray-900">
                                <h3>
                                    <article class="prose">
                                        {!! $product->markdownNotes() !!}
                                    </article>
                                </h3>
                                <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $this->subscription->company) }} / {{ App\Models\RecurringInvoice::frequencyForKey($this->subscription->frequency_id) }}</p>
                              </div>
                            </div>
                            <div class="flex justify-between text-sm mt-1">
                                @if(is_numeric($product->max_quantity))
                                <p class="text-gray-500 w-3/4"></p>
                                <div class="flex place-content-end">
                                    @if($this->subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                    <p class="w-full text-sm font-light text-red-500 text-right mr-2 mt-2">Out of stock</p>
                                    @else
                                    <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                    @endif
                                    <select wire:model.live.debounce.300ms="data.{{ $index }}.optional_recurring_qty" class="rounded-md border-gray-300 shadow-sm sm:text-sm" 
                                        @if($this->subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                        disabled 
                                        @endif
                                        >
                                        <option value="0" selected="selected">0</option>
                                        @for ($i = 1; $i <= $this->subscription->maxQuantity($product); $i++)
                                        <option value="{{$i}}">{{$i}}</option>
                                        @endfor
                                    </select>
                                </div>
                                @endif
                            </div>
                          </div>
                        </li>
                    @endforeach    
                @endif
                @if(!empty($this->subscription->optional_product_ids))
                    @foreach($optional_products as $index => $product)
                        <li class="flex py-6">
                      @if(filter_var($product->product_image, FILTER_VALIDATE_URL))
                      <div class="h-24 w-24 flex-shrink-0 overflow-hidden rounded-md border border-gray-200 mr-2">
                        <img src="{{$product->product_image}}" alt="" class="h-full w-full object-cover object-center p-2">
                      </div>
                      @endif
                          <div class="ml-0 flex flex-1 flex-col">
                            <div>
                              <div class="flex justify-between text-base font-medium text-gray-900">
                                <h3>
                                    <article class="prose">
                                        {!! $product->markdownNotes() !!}
                                    </article>
                                </h3>
                                <p class="ml-0">{{ \App\Utils\Number::formatMoney($product->price, $this->subscription->company) }}</p>
                              </div>
                              <p class="mt-1 text-sm text-gray-500"></p>
                            </div>
                            <div class="flex justify-between text-sm mt-1">
                                @if(is_numeric($product->max_quantity))
                                <p class="text-gray-500 w-3/4"></p>
                                <div class="flex place-content-end">
                                    @if($this->subscription->use_inventory_management && $product->in_stock_quantity == 0)
                                    <p class="w-full text-sm font-light text-red-500 text-right mr-2 mt-2">Out of stock</p>
                                    @else
                                    <p class="text-sm font-light text-gray-700 text-right mr-2 mt-2">{{ ctrans('texts.qty') }}</p>
                                    @endif
                                    <select wire:model.live.debounce.300ms="data.{{ $index }}.optional_qty" class="rounded-md border-gray-300 shadow-sm sm:text-sm">
                                        <option value="0" selected="selected">0</option>
                                        @for ($i = 1; $i <= $this->subscription->maxQuantity($product); $i++)
                                        <option value="{{$i}}">{{$i}}</option>
                                        @endfor
                                    </select>
                                </div>

                                @endif
                            </div>
                          </div>
                        </li>
                    @endforeach    
                @endif
                @if(auth()->guard('contact')->check())
                <li class="flex py-6">
                    <div class="flex w-full text-left mt-8">
                        <a href="{{route('client.dashboard')}}" class="button-link text-primary">{{ ctrans('texts.go_back') }}</a>
                    </div>
                </li>
                @endif
            </ul>
        </div>
    </div>

    </form>

    <div class="col-span-4 bg-blue-500 flex flex-col item-center p-2 min-h-screen" wire:init="buildBundle">
        <div class="w-full p-4">
            <div id="summary" class="px-4 text-white">
                <h1 class="font-semibold text-2xl border-b-2 border-gray-200 border-opacity-50 pb-2 text-white">{{ ctrans('texts.order') }}</h1>

                @foreach($bundle->toArray() as $item)
                    <div class="flex justify-between mt-1 mb-1">
                      <span class="font-light text-sm">{{ $item['qty'] }} x {{ $item['product'] }}</span>
                      <span class="font-bold text-sm">{{ $item['price'] }}</span>
                    </div>
                @endforeach

                @if(!empty($this->subscription->promo_code) && !$this->subscription->trial_enabled)
                    <form wire:submit="handleCoupon" class="">
                    @csrf
                        <div class="mt-4">
                          <label for="coupon" class="block text-sm font-medium text-white">{{ ctrans('texts.promo_code') }}</label>
                          <div class="mt-1 flex rounded-md shadow-sm">
                            <div class="relative flex flex-grow items-stretch focus-within:z-10">
                              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                              </div>
                              <input type="text" wire:model="coupon" class="block w-full rounded-none rounded-l-md border-gray-300 pl-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" placeholder="">
                            </div>
                            <button class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                              
                              <span>{{ ctrans('texts.apply') }}</span>
                            </button>
                          </div>
                          @if($errors && $errors->has('coupon'))
                                @error("coupon") 
                                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                                    <span class="block sm:inline text-sm">{{ $message }} </span>
                                    <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                                </div>
                                @enderror
                          @endif
                        </div>
                    </form>
                @endif

                <div class="border-gray-200 border-opacity-50 mt-4">
                    <div class="flex font-semibold justify-between py-1 text-sm uppercase">
                        <span>{{ ctrans('texts.one_time_purchases') }}</span>
                        <span>{{ $non_recurring_total }}</span>
                    </div>
                    
                    <div class="flex font-semibold justify-between py-1 text-sm uppercase">
                        <span>{{ ctrans('texts.recurring_purchases') }}</span>
                        <span>{{ $recurring_total }}</span>
                    </div>


                    @if($discount)
                    <div class="flex font-semibold justify-between py-1 text-sm uppercase">
                        <span>{{ ctrans('texts.discount') }}</span>
                        <span>{{ $discount }}</span>
                    </div>
                    @endif

                    <div class="flex font-semibold justify-between py-1 text-sm uppercase border-t-2">
                        <span>{{ ctrans('texts.total') }}</span>
                        <span>{{ $total }}</span>
                    </div>

                    @if(isset($tax))
                    <div class="flex font-semibold justify-between py-1 text-sm uppercase border-t-2">
                        <span>{{ ctrans('texts.tax') }}</span>
                        <span>{{ $tax }}</span>
                    </div>

                    @endif

                    <div class="mx-auto text-center mt-20 content-center" x-data="{open: @entangle('payment_started').live, toggle: @entangle('payment_confirmed').live, buttonDisabled: false}" x-show.important="open" x-transition>
                    <h2 class="text-2xl font-bold tracking-wide border-b-2 pb-4">{{ $heading_text ?? ctrans('texts.checkout') }}</h2>
                        @if (session()->has('message'))
                            @component('portal.ninja2020.components.message')
                                {{ session('message') }}
                            @endcomponent
                        @endif
                        @if($this->subscription->trial_enabled)
                            <form wire:submit="handleTrial" class="mt-8">
                            @csrf
                            <button class="relative -ml-px inline-flex items-center space-x-2 rounded border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                            {{ ctrans('texts.trial_call_to_action') }}
                            </button>
                            </form>

                        @elseif(count($methods) > 0 && $check_rff)

                            @if($errors->any())
                            <div class="w-full mx-auto text-center bg-red-100 border border-red-400 text-red-700 px-4 py-1 rounded">
                                @foreach($errors->all() as $error)
                                <p class="w-full">{{ $error }}</p>
                                @endforeach
                            </div>
                            @endif
                            <form wire:submit="handleRff">
                                @csrf

                            @if(strlen($this->contact->first_name ?? '') === 0)
                            <div class="col-auto mt-3 flex items-center space-x-0 @if($this->contact->first_name) !== 0) hidden @endif">
                                <label for="first_name" class="w-1/4 text-sm font-medium text-white whitespace-nowrap text-left">{{ ctrans('texts.first_name') }}</label>
                                <input id="first_name" class="w-3/4 rounded-md border-gray-300 pl-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" wire:model="contact_first_name" />
                            </div>
                            @endif

                            @if(strlen($this->contact->last_name ?? '') === 0)
                            <div class="col-auto mt-3 flex items-center space-x-0 @if($this->contact->last_name) !== 0) hidden @endif">
                                <label for="last_name" class="w-1/4 text-sm font-medium text-white whitespace-nowrap  text-left">{{ ctrans('texts.last_name') }}</label>
                                <input id="last_name" class="w-3/4 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" wire:model="contact_last_name" />
                            </div>
                            @endif

                            @if(strlen($this->contact->email ?? '') === 0)
                            <div class="col-auto mt-3 flex items-center space-x-0 @if($this->contact->email) !== 0) hidden @endif">
                                <label for="email" class="w-1/4 text-sm font-medium text-white whitespace-nowrap  text-left">{{ ctrans('texts.email') }}</label>
                                <input id="email" class="w-3/4 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" wire:model="contact_email" />
                            </div>
                            @endif

                            @if(strlen($client_postal_code ?? '') === 0)
                            <div class="col-auto mt-3 flex items-center space-x-0 @if($client_postal_code) !== 0) hidden @endif">
                                <label for="postal_code" class="w-1/4 text-sm font-medium text-white whitespace-nowrap  text-left">{{ ctrans('texts.postal_code') }}</label>
                                <input id="postal_code" class="w-3/4 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" wire:model="client_postal_code" />
                            </div>
                            @endif

                            @if(strlen($client_city ?? '') === 0)
                            <div class="col-auto mt-3 flex items-center space-x-0 @if($client_city) !== 0) hidden @endif">
                                <label for="city" class="w-1/4 text-sm font-medium text-white whitespace-nowrap text-left">{{ ctrans('texts.city') }}</label>
                                <input id="city" class="w-3/4 rounded-md border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" wire:model="client_city" />
                            </div>
                            @endif

                                <button 
                                    type="submit"
                                    class="relative -ml-px inline-flex items-center space-x-2 rounded border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500 mt-4">
                                    {{ ctrans('texts.next') }}
                                </button>
                            </form>

                        @elseif(count($methods) > 0)
                        <div class="mt-4" x-show.important="!toggle" x-transition>
                            @foreach($methods as $method)
                                <button
                                    x-on:click="buttonDisabled = true" x-bind:disabled="buttonDisabled"
                                    wire:click="handleMethodSelectingEvent('{{ $method['company_gateway_id'] }}', '{{ $method['gateway_type_id'] }}',  '{{ $method['is_paypal'] }}')"
                                    class="relative -ml-px inline-flex items-center space-x-2 rounded border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    {{ $method['label'] }}
                                </button>
                            @endforeach
                        </div>
                        @elseif(intval($float_amount_total) == 0)
                            <form wire:submit="handlePaymentNotRequired" class="mt-8">
                                @csrf
                                <button class="relative -ml-px inline-flex items-center space-x-2 rounded border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                                    {{ ctrans('texts.click_to_continue') }}
                                </button>
                            </form>
                        @endif

                        @if($is_eligible)
                        <div class="mt-4 container mx-auto flex w-full justify-center" x-show.important="toggle" x-transition>
                            <span class="">
                                <svg class="animate-spin h-8 w-8 text-primary mx-auto justify-center w-full" xmlns="http://www.w3.org/2000/svg"
                                     fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-75" cx="12" cy="12" r="10" stroke="hsl(210, 70, 75)" stroke-linecap="round"
                                            stroke-width="4" animation="dash 1.5s ease-in-out infinite"></circle>
                                    <path class="opacity-75" fill="#fff"
                                          d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </span>
                        </div>
                        @else
                           <small class="mt-4 block">{{ $this->not_eligible_message }}</small>
                        @endif
                        
                    </div>

                    @if(!$email || $errors->has('email'))
                    <form wire:submit="handleEmail" class="">
                    @csrf
                        <div class="mt-4">
                          <label for="email" class="block text-sm font-medium text-white">{{ ctrans('texts.email') }}</label>
                          <div class="mt-1 flex rounded-md shadow-sm">
                            <div class="relative flex flex-grow items-stretch focus-within:z-10">
                              <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3">
                              </div>
                              <input type="text" wire:model="email" class="block w-full rounded-none rounded-l-md border-gray-300 pl-2 focus:border-indigo-500 focus:ring-indigo-500 sm:text-sm text-gray-700" placeholder="">
                            </div>
                            <button class="relative -ml-px inline-flex items-center space-x-2 rounded-r-md border border-gray-300 bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:border-indigo-500 focus:outline-none focus:ring-1 focus:ring-indigo-500">
                              
                              <span>{{ ctrans('texts.login') }}</span>
                            </button>
                          </div>
                            @error("email") 
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                                <span class="block sm:inline text-sm">{{ $message }} </span>
                                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            </div>
                            @enderror
                        </div>
                    </form>
                    @endif

                    @if($email && !$errors->has('email') && !$authenticated)
                    <div class="w-full mx-auto text-center my-2">
                        <p class="w-full p-2">{{ ctrans('texts.otp_code_message', ['email' => $email])}}</p>
                    </div>
                    <div class="pb-6 px-6 w-80 mx-auto text-center">
                        <form wire:submit="handleLogin" class="" x-data="otpForm()">
                            <p class="mb-4"></p>
                            <div class="flex justify-between">
                              <!-- <template x-for="(input, index) in length" :key="index"> -->
                                <input
                                    id="0"
                                    type="text"
                                    maxlength="1"
                                    class="border border-gray-500 w-10 h-10 text-center text-gray-700"
                                    :x-ref="0"
                                    x-on:input="handleInput($event)"
                                    x-on:paste="handlePaste($event)"
                                    x-on:keydown.backspace="$event.target.value || handleBackspace($event.target.getAttribute('x-ref'))"
                                />
                                <input
                                    id="1"
                                    type="text"
                                    maxlength="1"
                                    class="border border-gray-500 w-10 h-10 text-center text-gray-700"
                                    :x-ref="1"
                                    x-on:input="handleInput($event)"
                                    x-on:paste="handlePaste($event)"
                                    x-on:keydown.backspace="$event.target.value || handleBackspace($event.target.getAttribute('x-ref'))"
                                />
                                <input
                                    id="2"
                                    type="text"
                                    maxlength="1"
                                    class="border border-gray-500 w-10 h-10 text-center text-gray-700"
                                    :x-ref="2"
                                    x-on:input="handleInput($event)"
                                    x-on:paste="handlePaste($event)"
                                    x-on:keydown.backspace="$event.target.value || handleBackspace($event.target.getAttribute('x-ref'))"
                                />
                                <input
                                    id="3"
                                    type="text"
                                    maxlength="1"
                                    class="border border-gray-500 w-10 h-10 text-center text-gray-700"
                                    :x-ref="3"
                                    x-on:input="handleInput($event)"
                                    x-on:paste="handlePaste($event)"
                                    x-on:keydown.backspace="$event.target.value || handleBackspace($event.target.getAttribute('x-ref'))"
                                />
                                <input
                                    id="4"
                                    type="text"
                                    maxlength="1"
                                    class="border border-gray-500 w-10 h-10 text-center text-gray-700"
                                    :x-ref="4"
                                    x-on:input="handleInput($event)"
                                    x-on:paste="handlePaste($event)"
                                    x-on:keydown.backspace="$event.target.value || handleBackspace($event.target.getAttribute('x-ref'))"
                                />
                                <input
                                    id="5"
                                    type="text"
                                    maxlength="1"
                                    class="border border-gray-500 w-10 h-10 text-center text-gray-700"
                                    :x-ref="5"
                                    x-on:input="handleInput($event)"
                                    x-on:paste="handlePaste($event)"
                                    x-on:keydown.backspace="$event.target.value || handleBackspace($event.target.getAttribute('x-ref'))"
                                />
                              <!-- </template> -->
                            </div>
                            
                        </form>
                        @error("login") 
                            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                                <span class="block sm:inline">{{ $message }} </span>
                                <span class="absolute top-0 bottom-0 right-0 px-4 py-3">
                            </div>
                        @enderror
                        <div class="flex w-full place-content-end mb-0 mt-6">
                            <button wire:click="resetEmail" class="relative -ml-px inline-flex items-center space-x-1 rounded border border-red-900 bg-red-500 px-1 py-1 text-sm font-medium text-white-700 hover:bg-red-900 focus:border-red-500 focus:outline-none focus:ring-1 focus:ring-red-500">{{ ctrans('texts.reset') }}</button>
                        </div>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
<script>
    function otpForm() {
        return {
            length: 6,
            login: "",

            handleInput(e) {
                const input = e.target;

                this.login = Array.from(Array(this.length), (element, i) => {
                    return document.getElementById(i.toString()).value || '';
                }).join("");

                if (input.nextElementSibling && input.value) {
                    input.nextElementSibling.focus();
                    input.nextElementSibling.select();
                }

                if(this.login.length == 6){
                    this.$wire.handleLogin(this.login);
                }

            },

            handlePaste(e) {
                const paste = e.clipboardData.getData('text');
                this.value = paste;

                const inputs = Array.from(Array(this.length));

                inputs.forEach((element, i) => {
                    document.getElementById(i.toString()).value = paste[i] || '';
                });

                this.login = Array.from(Array(this.length), (element, i) => {
                    return document.getElementById(i.toString()).value || '';
                }).join("");

                if(this.login.length == 6){
                    this.$wire.handleLogin(this.login);
                }

            },

            handleBackspace(e) {
                const previous = parseInt(e, 10) - 1;
                this.$refs[previous] && this.$refs[previous].focus();
                this.$wire.loginValidation(); 
            },
      };
    }
</script>
</div>


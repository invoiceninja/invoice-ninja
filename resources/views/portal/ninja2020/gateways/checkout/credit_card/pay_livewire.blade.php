<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="checkout-credit-card-payment">
    <meta name="public-key" content="{{ $gateway->getPublishableKey() }}">
    <meta name="customer-email" content="{{ $customer_email }}">
    <meta name="value" content="{{ $value }}">
    <meta name="currency" content="{{ $currency }}">
    <meta name="reference" content="{{ $payment_hash }}">

    @include('portal.ninja2020.gateways.checkout.credit_card.includes.styles')

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card">
        <input type="hidden" name="reference" value="{{ $payment_hash }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="value" value="{{ $value }}">
        <input type="hidden" name="raw_value" value="{{ $raw_value }}">
        <input type="hidden" name="currency" value="{{ $currency }}">
        <input type="hidden" name="pay_with_token" value="false">
        <input type="hidden" name="token" value="">
    </form>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
    {{ ctrans('texts.credit_card') }} (Checkout.com)
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])


        <ul class="list-none space-y-2">
            @if(count($tokens) > 0)
                @foreach($tokens as $token)
                <li class="py-2 hover:bg-gray-100 rounded transition-colors duration-150">
                    <label class="flex items-center cursor-pointer px-2">
                        <input
                            type="radio"
                            data-token="{{ $token->hashed_id }}"
                            name="payment-type"
                            class="form-radio text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"/>
                        <span class="ml-2 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                    </label>
                </li>
                @endforeach
            @endif

            <li class="py-2 hover:bg-gray-100 rounded transition-colors duration-150">
                <label class="flex items-center cursor-pointer px-2">
                    <input
                        type="radio"
                        id="toggle-payment-with-credit-card"
                        class="form-radio text-indigo-600 rounded-full cursor-pointer"
                        name="payment-type"
                        checked/>
                    <span class="ml-2 cursor-pointer">{{ __('texts.new_card') }}</span>
                </label>
            </li>    
        </ul>


    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')

    @component('portal.ninja2020.components.general.card-element-single')
    <div id="checkout--container">
        <form class="xl:flex xl:justify-center" id="payment-form" method="POST" action="#">
            <div class="one-liner">
                <div class="card-frame">
                    <!-- form will be added here -->
                </div>
                <!-- add submit button -->
                <button id="pay-button" disabled>
                    {{ ctrans('texts.pay') }} {{ App\Utils\Number::formatMoney($total['amount_with_fee'], $client) }}
                </button>
            </div>
            <p class="success-payment-message"></p>
        </form>
    </div>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
    <div class="hidden" id="pay-now-with-token--container">
        @include('portal.ninja2020.gateways.includes.pay_now', ['id' => 'pay-now-with-token'])
    </div>
    @endcomponent

    @assets
    <script src="https://cdn.checkout.com/js/framesv2.min.js"></script>
    @vite('resources/js/clients/payments/checkout-credit-card.js')
    @endassets
</div>
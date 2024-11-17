<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="powerboard-credit-card-payment">
    <meta name="public_key" content="{{ $public_key }}" />
    <meta name="gateway_id" content="{{ $gateway_id }}" />
    <meta name="environment" content="{{ $environment }}">
    <meta name="payments_route" content="{{ route('client.payments.response') }}" />
    <meta name="supported_cards" content="{{ json_encode($supported_cards) }}" />

    <form action="javascript:void(0);" id="stepone">
        <input type="hidden" name="gateway_response">
        <button type="submit"   class="hidden" id="stepone_submit">Submit</button>
    </form>

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
        <input type="hidden" name="browser_details">
        <input type="hidden" name="charge">
        <input type="hidden" name="charge_3ds_id">
        <button type="submit" class="hidden" id="stub">Submit</button>
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
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
                        data-token="{{ $token->token }}"
                        name="payment-type"
                        class="form-radio text-indigo-600 rounded-full cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-2 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

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

    <div id="powerboard-payment-container" class="w-full p-4 hidden" style="background-color: rgb(249, 249, 249);">
        <div id="widget" style="block" class="hidden"></div>
        <div id="widget-3dsecure"></div>
    </div>

    @include('portal.ninja2020.gateways.includes.pay_now')

    <style>
        iframe {
            border: 0;
            width: 100%;
            height: 400px;
        }
    </style>
</div>

@assets
    <script src="{{ $widget_endpoint }}"></script>
        
    @vite('resources/js/clients/payments/powerboard-credit-card.js')
@endassets

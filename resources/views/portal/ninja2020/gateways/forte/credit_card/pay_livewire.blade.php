<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="forte-credit-card-payment">
    <meta name="forte-api-login-id" content="{{$gateway->company_gateway->getConfigField("apiLoginId")}}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="card_brand" id="card_brand">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{$payment_method_id}}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue" />
        <input type="hidden" name="dataDescriptor" id="dataDescriptor" />
        <input type="hidden" name="token" id="token" />
        <input type="hidden" name="store_card" id="store_card" />
        <input type="submit" style="display: none" id="form_btn">
    </form>

    <div id="forte_errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => 'Pay with Credit Card'])
        @include('portal.ninja2020.gateways.forte.includes.credit_card')
    @endcomponent

    @include('portal.ninja2020.gateways.includes.pay_now')
</div>

@assets

    @if($gateway->company_gateway->getConfigField('testMode'))
        <script type="text/javascript" src="https://sandbox.forte.net/api/js/v1"></script>
    @else
        <script type="text/javascript" src="https://api.forte.net/js/v1"></script>
    @endif
    
    <script src="{{ asset('vendor/simple-card@0.0.5/simple-card.js') }}"></script> 
    
    @vite('resources/js/clients/payments/forte-credit-card-payment.js')
@endassets

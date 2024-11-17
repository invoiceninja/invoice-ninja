@extends('portal.ninja2020.layout.payments', ['gateway_title' => ctrans('texts.payment_type_credit_card'), 'card_title' => ctrans('texts.payment_type_credit_card')])

@section('gateway_head')
    <meta name="forte-api-login-id" content="{{$gateway->company_gateway->getConfigField("apiLoginId")}}">
    <meta name="instant-payment" content="yes" />

    <script src="https://code.jquery.com/jquery-1.11.3.min.js"></script>
@endsection

@section('gateway_content')
    <form action="{{ route('client.payments.response') }}" method="post" id="server_response">
        @csrf
        <input type="hidden" name="card_brand" id="card_brand">
        <input type="hidden" name="expire_year" id="expire_year">
        <input type="hidden" name="expire_month" id="expire_month">
        <input type="hidden" name="last_4" id="last_4">
        <input type="hidden" name="payment_token" id="payment_token">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{$payment_method_id}}">
        <input type="hidden" name="gateway_response" id="gateway_response">
        <input type="hidden" name="dataValue" id="dataValue"/>
        <input type="hidden" name="dataDescriptor" id="dataDescriptor"/>
        <input type="hidden" name="token" id="token"/>
        <input type="hidden" name="store_card" id="store_card"/>
        <input type="submit" style="display: none" id="form_btn">
    </form>

    <div id="forte_errors"></div>

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.credit_card') }}
     @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
    <ul class="list-none">    
        @if(count($tokens) > 0)
            @foreach($tokens as $token)
            <li class="py-2">
               <label class="mr-4 cursor-pointer">
                    <input
                        type="radio"
                        data-token="{{ $token->hashed_id }}"
                        name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token"/>
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            </li>
            @endforeach
        @endisset

        <li class="py-2">
            <label class="mr-4 cursor-pointer">
                <input
                    type="radio"
                    id="toggle-payment-with-credit-card"
                    class="form-radio cursor-pointer"
                    name="payment-type"
                    checked/>
                <span class="ml-1 cursor-pointer">{{ __('texts.new_card') }}</span>
            </label>
        </li>
    </ul>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')
    @include('portal.ninja2020.gateways.forte.includes.credit_card')
    @include('portal.ninja2020.gateways.includes.pay_now')

@endsection

@section('gateway_footer')
    @if($gateway->company_gateway->getConfigField('testMode'))
        <script type="text/javascript" src="https://sandbox.forte.net/api/js/v1"></script>
    @else
        <script type="text/javascript" src="https://api.forte.net/js/v1"></script>
    @endif

    <script src="{{ asset('vendor/simple-card@0.0.5/simple-card.js') }}"></script> 
    
    @vite('resources/js/clients/payments/forte-credit-card-payment.js')
@endsection

@push('footer')
<script defer>
    document.querySelector('#date').addEventListener('change', (e) => {
        const [month, year] = e.target.value.replace(/\s/g, '').split('/');

        document.getElementsByName('expiry-month')[0].value  = month;
        document.getElementsByName('expiry-year')[0].value = `20${year}`;
    });
</script>
@endpush

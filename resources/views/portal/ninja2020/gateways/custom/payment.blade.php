@extends('portal.ninja2020.layout.payments', ['gateway_title' => $title, 'card_title' => $title])

@section('gateway_content')
 <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
    @csrf
    <input type="hidden" name="gateway_response">
    <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
    <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
    <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
    <input type="hidden" name="token">
</form>
@component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ $title }}
    @endcomponent

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element-single')
        {!! nl2br($instructions) !!}
    @endcomponent
@endsection
<script>
function submitResponse(response){        
    document.querySelector(
                'input[name="gateway_response"]'
            ).value = JSON.stringify(response);

    document.getElementById('server-response').submit();
}
</script>
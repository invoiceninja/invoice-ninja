<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4">

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
</div>
@script
<script>
    function submitResponse(response){
                
        document.querySelector(
                    'input[name="gateway_response"]'
                ).value = JSON.stringify(response);

        document.getElementById('server-response').submit();
     
    }
</script>
@endscript
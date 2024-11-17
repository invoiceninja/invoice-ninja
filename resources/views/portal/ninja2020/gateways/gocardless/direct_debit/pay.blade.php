@extends('portal.ninja2020.layout.payments', ['gateway_title' => 'Direct Debit', 'card_title' => 'Direct Debit'])

@section('gateway_content')
    @if (count($tokens) > 0)
        <div class="alert alert-failure mb-4" hidden id="errors"></div>

        @include('portal.ninja2020.gateways.includes.payment_details')

        <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
            @csrf
            <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
            <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
            <input type="hidden" name="source" value="">
            <input type="hidden" name="amount" value="{{ $amount }}">
            <input type="hidden" name="currency" value="{{ $currency }}">
            <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        </form>

        @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
            @if (count($tokens) > 0)
                @foreach ($tokens as $token)
                    <label class="mr-4 block my-2">
                        <input type="radio" data-token="{{ $token->token }}" name="payment-type"
                            class="form-radio cursor-pointer toggle-payment-with-token" />
                        <span class="ml-1 cursor-pointer">{{ App\Models\GatewayType::getAlias($token->gateway_type_id) }}
                            (#{{ $token->token }})</span>
                    </label>
                @endforeach
            @endisset
        @endcomponent

    @else
        @component('portal.ninja2020.components.general.card-element-single', ['title' => 'Direct Debit', 'show_title' => false])
            <span>{{ ctrans('texts.bank_account_not_linked') }}</span>

            <a class="button button-link text-primary"
                href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method') }}</a>
        @endcomponent
    @endif

    @if (count($tokens) > 0)
        @include('portal.ninja2020.gateways.includes.pay_now')
    @endif
@endsection

@push('footer')
    <script>
        Array
            .from(document.getElementsByClassName('toggle-payment-with-token'))
            .forEach((element) => element.addEventListener('click', (element) => {
                document.querySelector('input[name=source]').value = element.target.dataset.token;
            }));

        document.getElementById('pay-now').addEventListener('click', function() {

            const payNowButton = document.getElementById('pay-now');
            if (payNowButton) {
                payNowButton.addEventListener('click', function() {
                    // Disable button and update UI
                    this.disabled = true;
                    this.querySelector('svg').classList.remove('hidden');
                    this.querySelector('span').classList.add('hidden');

                    // Submit form
                    document.getElementById('server-response').submit();
                });
            }

        });


const first = document.querySelector('input[name="payment-type"]');

if (first) {
    first.click();
}

    </script>
@endpush

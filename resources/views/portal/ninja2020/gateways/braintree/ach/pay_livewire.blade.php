<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="braintree-ach-payment">
    @if(count($tokens) > 0)
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
                        <span class="ml-2 cursor-pointer">{{ ctrans('texts.bank_transfer') }} (*{{ $token->meta->last4 }})</span>
                    </label>
                </li>
                @endforeach
            @endif
        </ul>

        @endcomponent

        @include('portal.ninja2020.gateways.includes.pay_now')

    @else
        @component('portal.ninja2020.components.general.card-element-single', ['title' => 'ACH', 'show_title' => false])
            <span class="block">{{ ctrans('texts.bank_account_not_linked') }}</span>
            <a class="hover:underline text-primary mt-2 block"
               href="{{ route('client.payment_methods.index') }}">{{ ctrans('texts.add_payment_method') }}</a>
        @endcomponent
    @endif
</div>

@script
    <script defer>
        Array
            .from(document.getElementsByClassName('toggle-payment-with-token'))
            .forEach((element) => element.addEventListener('click', (element) => {
                document.querySelector('input[name=source]').value = element.target.dataset.token;
            }));

        document.getElementById('pay-now').addEventListener('click', function (e) {
            e.target.parentElement.disabled = true;

            document.getElementById('server-response').submit();
        });


    /**
    * @type {HTMLInputElement|null}
    */
    const first = document.querySelector('input[name="payment-type"]');

    if (first) {
        first.click();
    }
    </script>
@endscript

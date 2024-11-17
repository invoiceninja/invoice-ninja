<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4" id="stripe-credit-card-payment">
    @if($stripe_account_id)
        <meta name="stripe-account-id" content="{{ $stripe_account_id }}">
        <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
        <meta name="stripe-publishable-key" content="{{ $company_gateway->getPublishableKey() }}">
    @endif

    <meta name="stripe-secret" content="{{ $client_secret }}">
    <meta name="only-authorization" content="">
    <meta name="client-postal-code" content="{{ $client->postal_code ?? '' }}">
    <meta name="stripe-require-postal-code" content="{{ $company_gateway->require_postal_code }}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="store_card" value="{{ $token_billing_string }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">

        <input type="hidden" name="company_gateway_id" value="{{ $company_gateway->id }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">

        <input type="hidden" name="token">
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

    @include('portal.ninja2020.gateways.stripe.includes.card_widget')
    @include('portal.ninja2020.gateways.includes.pay_now')
    
    
    @assets
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-credit-card.js')
    @endassets
</div>
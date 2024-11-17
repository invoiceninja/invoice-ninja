<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4"
    id="stripe-sepa-payment">

    @if($gateway->company_gateway->getConfigField('account_id'))
    <meta name="stripe-account-id" content="{{ $gateway->company_gateway->getConfigField('account_id') }}">
    <meta name="stripe-publishable-key" content="{{ config('ninja.ninja_stripe_publishable_key') }}">
    @else
    <meta name="stripe-publishable-key" content="{{ $gateway->company_gateway->getPublishableKey() }}">
    @endif
    <meta name="amount" content="{{ $stripe_amount }}">
    <meta name="country" content="{{ $country }}">
    <meta name="customer" content="{{ $customer }}">
    <meta name="client_name" content="{{ $client->present()->name() }}">
    <meta name="client_email" content="{{ $client->present()->email() }}">
    <meta name="pi-client-secret" content="{{ $pi_client_secret }}">
    <meta name="si-client-secret" content="{{ $si_client_secret ?? '' }}">

    <meta name="translation-name-required" content="{{ ctrans('texts.missing_account_holder_name') }}">
    <meta name="translation-email-required" content="{{ ctrans('texts.provide_email') }}">
    <meta name="translation-terms-required" content="{{ ctrans('texts.you_need_to_accept_the_terms_before_proceeding') }}">

    <form action="{{ route('client.payments.response') }}" method="post" id="server-response">
        @csrf
        <input type="hidden" name="gateway_response">
        <input type="hidden" name="company_gateway_id" value="{{ $gateway->getCompanyGatewayId() }}">
        <input type="hidden" name="payment_method_id" value="{{ $payment_method_id }}">
        <input type="hidden" name="payment_hash" value="{{ $payment_hash }}">
        <input type="hidden" name="store_card">
        <input type="hidden" name="token">
    </form>

    <div class="alert alert-failure mb-4" hidden id="errors"></div>

    @include('portal.ninja2020.gateways.includes.payment_details')

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.payment_type')])
        {{ ctrans('texts.sepa') }} ({{ ctrans('texts.bank_transfer') }})
    @endcomponent

    @component('portal.ninja2020.components.general.card-element', ['title' => ctrans('texts.pay_with')])
        @if (count($tokens) > 0)
            @foreach ($tokens as $token)
                <label class="flex items-center cursor-pointer px-2 mr-4 mb-2">
                    <input type="radio" data-token="{{ $token->token }}" name="payment-type"
                        class="form-radio cursor-pointer toggle-payment-with-token" />
                    <span class="ml-1 cursor-pointer">**** {{ $token->meta?->last4 }}</span>
                </label>
            @endforeach
        @endisset

        <label class="flex items-center cursor-pointer px-2">
            <input type="radio" id="toggle-payment-with-new-bank-account" class="form-radio cursor-pointer" name="payment-type"
                checked />
            <span class="ml-1 cursor-pointer">{{ __('texts.new_bank_account') }}</span>
        </label>
    @endcomponent

    @component('portal.ninja2020.components.general.card-element-single')
        <div id="stripe--payment-container">
            <label for="sepa-name">
                <input class="input w-full" id="sepa-name" type="text"
                    placeholder="{{ ctrans('texts.bank_account_holder') }}">
            </label>
            <label for="sepa-email" class="mt-4">
                <input class="input w-full" id="sepa-email-address" type="email"
                    placeholder="{{ ctrans('texts.email') }}">
            </label>
            <label>
                <div class="border p-3 rounded mt-2">
                    <div id="sepa-iban"></div>
                </div>
            </label>
            <div id="mandate-acceptance" class="mt-4">
                <input type="checkbox" id="sepa-mandate-acceptance" class="input mr-4">
                <label for="sepa-mandate-acceptance" class="cursor-pointer">
                    {{ ctrans('texts.sepa_mandat', ['company' => $contact->company->present()->name()]) }}
                </label>
            </div>
        </div>
    @endcomponent

    @include('portal.ninja2020.gateways.includes.save_card')
    @include('portal.ninja2020.gateways.includes.pay_now')
</div>

@assets
    <script src="https://js.stripe.com/v3/"></script>
    @vite('resources/js/clients/payments/stripe-sepa.js')
@endassets
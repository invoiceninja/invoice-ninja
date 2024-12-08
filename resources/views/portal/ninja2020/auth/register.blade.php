@extends('portal.ninja2020.layout.clean', ['custom_body_class' => 'bg-gray-100'])
@section('meta_title', ctrans('texts.register'))

@section('body')

    <div class="grid lg:grid-cols-12 py-8">
        <div class="col-span-12 lg:col-span-8 lg:col-start-3 xl:col-span-6 xl:col-start-4 px-6">
            @if($register_company->account && !$register_company->account->isPaid())
            <div class="flex justify-center">
                    <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}"
                         class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo">
                </div>
            @elseif(isset($register_company) && !is_null($register_company))
            <div class="flex justify-center">
                    <img src="{{ $register_company->present()->logo()  }}"
                         class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ $register_company->present()->name() }} logo">
                </div>
            @endif
            <h1 class="text-center text-3xl mt-8">{{ ctrans('texts.register') }}</h1>
            <p class="block text-center text-gray-600">{{ ctrans('texts.register_label') }}</p>

            <form id="register-form" action="{{ route('client.register', request()->route('company_key')) }}" method="POST" x-data="{more: false, busy: false, isSubmitted: false}" x-on:submit="busy = true; isSubmitted = true">
                @if($register_company)
                <input type="hidden" name="company_key" value="{{ $register_company->company_key }}">
                @endif

                <fieldset :disabled="{{ $formed_disabled ?? 'false' }}">
                @csrf

                <div class="grid grid-cols-12 gap-4 mt-10">
                    @if($register_company->client_registration_fields)
                    @foreach($register_company->client_registration_fields as $field)
                        @if(isset($field['visible']) && $field['visible'])
                            <div class="col-span-12 md:col-span-6">
                                <section class="flex items-center">
                                    <label
                                        for="{{ $field['key'] }}"
                                        class="input-label">
                                        @if(in_array($field['key'], ['custom_value1','custom_value2','custom_value3','custom_value4']))
                                        {{ (new App\Utils\Helpers())->makeCustomField($register_company->custom_fields, str_replace("custom_value","client", $field['key']))}}
                                        @else
                                        {{ ctrans("texts.{$field['key']}") }}
                                        @endif
                                    </label>

                                    @if($field['required'])
                                        <section class="text-red-400 ml-1 text-sm">*</section>
                                    @endif
                                </section>

                                @if($field['key'] === 'email')
                                    <input
                                        id="{{ $field['key'] }}"
                                        class="input w-full"
                                        type="email"
                                        name="{{ $field['key'] }}"
                                        value="{{ old($field['key']) }}"
                                         />
                                @elseif($field['key'] === 'password')
                                    <input
                                        id="{{ $field['key'] }}"
                                        class="input w-full"
                                        type="password"
                                        name="{{ $field['key'] }}"
                                         />
                                @elseif($field['key'] === 'currency_id')
                                    <select
                                        id="currency_id"
                                        class="input w-full form-select bg-white"
                                        name="currency_id">
                                        @foreach(App\Utils\TranslationHelper::getCurrencies() as $currency)
                                            <option
                                                {{ $currency->id == $register_company->settings->currency_id ? 'selected' : null }} value="{{ $currency->id }}">
                                                {{ $currency->getName() }}
                                            </option>
                                        @endforeach
                                    </select>
                                @elseif($field['key'] === 'country_id')
                                    <select
                                        id="shipping_country"
                                        class="input w-full form-select bg-white"
                                        name="country_id">
                                            <option value="none"></option>
                                        @foreach(App\Utils\TranslationHelper::getCountries() as $country)
                                            <option value="{{ $country->id }}">
                                                {{ $country->iso_3166_2 }}
                                                ({{ $country->getName() }})
                                            </option>
                                        @endforeach
                                    </select>
                                @else
                                    <input
                                        id="{{ $field['key'] }}"
                                        class="input w-full"
                                        name="{{ $field['key'] }}"
                                        value="{{ old($field['key']) }}"
                                         />
                                @endif

                                @error($field['key'])
                                    <div class="validation validation-fail">
                                        {{ $message }}
                                    </div>
                                @enderror
                            </div>

                            @if($field['key'] === 'password')
                                <div class="col-span-12 md:col-span-6">
                                    <section class="flex items-center">
                                        <label
                                            for="password_confirmation"
                                            class="input-label">
                                            {{ ctrans('texts.password_confirmation') }}
                                        </label>

                                        @if($field['required'])
                                            <section class="text-red-400 ml-1 text-sm">*</section>
                                        @endif
                                    </section>

                                    <input
                                        id="password_confirmation"
                                        type="password"
                                        class="input w-full"
                                        name="password_confirmation"
                                         />
                                </div>
                            @endif
                        @endif
                    @endforeach
                    @endif
                </div>

                <div class="flex justify-between items-center mt-8">

                    <a href="{{route('client.login')}}" class="button button-info bg-emerald-600 text-white">{{ ctrans('texts.login_label') }}</a>

                    <span class="inline-flex items-center" x-data="{ terms_of_service: false, privacy_policy: false }">
                            @if(!empty($register_company->settings->client_portal_terms) || !empty($register_company->settings->client_portal_privacy_policy))
                                <input type="checkbox" name="terms" class="form-checkbox mr-2 cursor-pointer" checked>
                                <span class="text-sm text-gray-800">

                                {{ ctrans('texts.i_agree_to_the') }}
                            @endif

                            @includeWhen(!empty($register_company->settings->client_portal_terms), 'portal.ninja2020.auth.includes.register.popup', ['property' => 'terms_of_service', 'title' => ctrans('texts.terms_of_service'), 'content' => $register_company->settings->client_portal_terms])
                            @includeWhen(!empty($register_company->settings->client_portal_privacy_policy), 'portal.ninja2020.auth.includes.register.popup', ['property' => 'privacy_policy', 'title' => ctrans('texts.privacy_policy'), 'content' => $register_company->settings->client_portal_privacy_policy])
                            @error('terms')
                                <p class="text-red-600">{{ $message }}</p>
                            @enderror
                        </span>
                    </span>

                    @if($show_turnstile)
                        <div class="col-span-12 flex justify-center mt-4">
                            <div class="cf-turnstile" data-sitekey="{{ config('ninja.cloudflare.turnstile.site_key') }}"></div>
                            @error('cf-turnstile-response')
                                <div class="validation validation-fail">
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>
                    @endif
                    
                    <button class="button button-primary bg-blue-600" :disabled={{ $submitsForm == 'true' ? 'isSubmitted' : 'busy'}}>
                        {{ ctrans('texts.register')}}
                    </button>

                </div>
                </fieldset>
            </form>
        </div>
    </div>
@endsection
@push('footer')
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endpush
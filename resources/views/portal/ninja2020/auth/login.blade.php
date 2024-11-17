@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.login'))

@section('head')

@component('portal.ninja2020.components.test')
<input type="hidden" id="test_email" value="{{ config('ninja.testvars.username') }}">
<input type="hidden" id="test_password" value="{{ config('ninja.testvars.password') }}">
@endcomponent

@endsection

@section('body')
    <div class="grid lg:grid-cols-3 mx-6 md:mx-0">
        @if($account && !$account->isPaid())
            <div class="hidden lg:block col-span-1 bg-red-100 h-screen">
                <img src="{{ asset('images/client-portal-new-image.jpg') }}"
                     class="w-full h-screen object-cover"
                     alt="Background image">
            </div>
        @endif

        <div class="{{ $account && !$account->isPaid() ? 'col-span-2' : 'col-span-3' }} h-screen flex">
            <div class="m-auto md:w-1/2 lg:w-1/4">
                @if($account && !$account->isPaid())
                    <div>
                        <img src="{{ asset('images/invoiceninja-black-logo-2.png') }}"
                             class="border-b border-gray-100 h-18 pb-4" alt="Invoice Ninja logo" id="company_logo">
                    </div>
                @elseif(isset($company) && !is_null($company))
                    <div>
                        <img src="{{ $company->present()->logo()  }}"
                             class="mx-auto border-b border-gray-100 h-18 pb-4" alt="{{ $company->present()->name() }} logo">
                    </div>
                @endif

                <div class="flex flex-col">
                    <h1 class="text-center text-3xl">{{ ctrans('texts.client_portal') }}</h1>
                    <form action="{{ route('client.login') }}" method="post" class="mt-6">
                        @csrf
                        <div class="flex flex-col">
                            <label for="email" class="input-label">{{ ctrans('texts.email_address') }}</label>
                            <input type="email" name="email" id="email"
                                   class="input"
                                   value="{{ old('email') }}"
                                   autofocus>
                            @error('email')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="flex flex-col mt-4">
                            <div class="flex justify-between items-center">
                                <label for="password" class="input-label">{{ ctrans('texts.password') }}</label>
                                <a class="text-xs text-gray-600 hover:text-gray-800 ease-in duration-100"
                                   href="{{ route('client.password.request') }}">{{ trans('texts.forgot_password') }}</a>
                            </div>
                            @if(isset($company) && !is_null($company))
                            <input type="hidden" name="company_key" value="{{$company->company_key}}">
                            @endif
                            <input type="password" name="password" id="password"
                                   class="input"
                                   autofocus>
                            @error('password')
                            <div class="validation validation-fail">
                                {{ $message }}
                            </div>
                            @enderror
                        </div>
                        <div class="mt-5">
                            <button id="loginBtn" class="button button-primary button-block bg-blue-600">
                                {{ trans('texts.login') }}
                            </button>
                        </div>
                    </form>

                    @if(!is_null($company) && $company->client_can_register)
                        <div class="mt-5 text-center">
                            <a class="button-link text-sm" href="{{ route('client.register') }}">{{ ctrans('texts.register_label') }}</a>
                        </div>
                    @endif

                    @if(!is_null($company) && !empty($company->present()->website()))
                        <div class="mt-5 text-center">
                            <a class="button-link text-sm" href="{{ $company->present()->website() }}">
                                {{ ctrans('texts.back_to', ['url' => parse_url($company->present()->website())['host'] ?? $company->present()->website() ]) }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
@endsection

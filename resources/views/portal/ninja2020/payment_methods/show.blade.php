@extends('portal.ninja2020.layout.app')
@section('meta_title', App\Models\GatewayType::getAlias($payment_method->gateway_type_id))

@section('body')
    <div class="container mx-auto">
        <div class="overflow-hidden bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                <h3 class="text-lg font-medium leading-6 text-gray-900">
                    {{ App\Models\GatewayType::getAlias($payment_method->gateway_type_id) }}
                </h3>
                <p class="max-w-2xl mt-1 text-sm leading-5 text-gray-500" translate>
                    {{ ctrans('texts.payment_method_details') }}
                </p>
            </div>
            <div>
                <dl>
                    @if(!empty(App\Models\GatewayType::getAlias($payment_method->gateway_type_id)) && !is_null(App\Models\GatewayType::getAlias($payment_method->gateway_type_id)))
                        <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.payment_type') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ App\Models\GatewayType::getAlias($payment_method->gateway_type_id) }}
                            </dd>
                        </div>
                    @endif

                    @if(!empty($payment_method->meta) && !is_null($payment_method->meta))
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.type') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ property_exists($payment_method->meta, 'brand') ? $payment_method->meta?->brand : ''}}
                                {{ property_exists($payment_method->meta, 'scheme') ? $payment_method->meta?->scheme : '' }}
                            </dd>
                        </div>
                    @endif

                    @if(!empty($payment_method->meta->last4) && !is_null($payment_method->meta->last4))
                        <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.card_number') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                **** {{ ucfirst($payment_method->meta->last4) }}
                            </dd>
                        </div>
                    @endif

                    @if(!empty($payment_method->created_at) && !is_null($payment_method->created_at))
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.date_created') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment_method->formatDateTimestamp($payment_method->created_at, auth()->guard('contact')->user()->client->date_format()) }}
                            </dd>
                        </div>
                    @endif

                    @if(!empty($payment_method->is_default) && !is_null($payment_method->is_default))
                        <div class="px-4 py-5 bg-gray-50 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.default') }}
                            </dt>
                            <div class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment_method->is_default ? ctrans('texts.yes') : ctrans('texts.no') }}
                            </div>
                        </div>
                    @endif

                    @isset($payment_method->meta->exp_month)
                        <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                            <dt class="text-sm font-medium leading-5 text-gray-500">
                                {{ ctrans('texts.expires') }}
                            </dt>
                            <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                {{ $payment_method->meta->exp_month }} / {{ $payment_method->meta->exp_year }}
                            </dd>
                        </div>
                    @endisset
                </dl>
            </div>
        </div>

        @if(($payment_method->gateway_type_id == \App\Models\GatewayType::BANK_TRANSFER && property_exists($payment_method->meta, 'state') && ($payment_method->meta?->state === 'unauthorized' || $payment_method->meta?->state === 'pending')))
            <div class="mt-4 mb-4 bg-white shadow sm:rounded-lg">
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg font-medium leading-6 text-gray-900">
                                {{ ctrans('texts.verification')}}
                            </h3>
                            <div class="max-w-xl mt-2 text-sm leading-5 text-gray-500">
                                <p>
                                    {{ ctrans('texts.ach_verification_notification') }}
                                </p>
                            </div>
                        </div>
                        <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                            <div class="inline-flex rounded-md shadow-sm" x-data="{ open: false }">
                            @if (substr($payment_method->token, 0, 2) == 'pm') 
                                <a href="{{ $payment_method->meta?->next_action }}" class="button button-primary bg-primary">
                                    {{ ctrans('texts.complete_verification') }}
                                </a>
                            @elseif(substr($payment_method->gateway_customer_reference, 0, 3) == 'cus') 
                                <a href="{{ route('client.payment_methods.verification', ['payment_method' => $payment_method->hashed_id, 'method' => \App\Models\GatewayType::BANK_TRANSFER]) }}" class="button button-primary bg-primary">
                                    {{ ctrans('texts.complete_verification') }}
                                </a>
                            @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif

        @livewire('payment-methods.update-default-method', ['token_id' => $payment_method->id, 'db' => $client->company->db])
        
        <div class="mt-4 mb-4 bg-white shadow sm:rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            {{ ctrans('texts.remove')}}
                        </h3>
                        <div class="max-w-xl mt-2 text-sm leading-5 text-gray-500">
                            <p>
                                {{ ctrans('texts.permanently_remove_payment_method') }}
                            </p>
                        </div>
                    </div>
                    <div class="mt-5 sm:mt-0 sm:ml-6 sm:flex-shrink-0 sm:flex sm:items-center">
                        <div class="inline-flex rounded-md shadow-sm" x-data="{ open: false }">
                            <button class="button button-danger" @click="open = true" id="open-delete-popup">
                                {{ ctrans('texts.remove_payment_method') }}
                            </button>
                            @include('portal.ninja2020.payment_methods.includes.modals.removal')
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('footer')
    <script>

        document.addEventListener('livewire:init', () => {

            Livewire.on('UpdateDefaultMethod::method-updated', event => {
                document.querySelector('span[data-ref=success-label]').classList.remove('hidden');
            });

        });

        </script>
@endsection

@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.entity_number_placeholder', ['entity' => ctrans('texts.quote'), 'entity_number' => $quote->number]))

@push('head')
    <meta name="show-quote-terms" content="{{ $settings->show_accept_quote_terms ? true : false }}">
    <meta name="require-quote-signature" content="{{ $client->company->account->hasFeature(\App\Models\Account::FEATURE_INVOICE_SETTINGS) && $settings->require_quote_signature }}">
    <meta name="accept-user-input" content="{{ $client->getSetting('accept_client_input_quote_approval') }}">
    <script src="{{ asset('vendor/signature_pad@2.3.2/signature_pad.min.js') }}"></script>
@endpush

@section('body')

    @if(!$quote->isApproved() && $client->getSetting('custom_message_unapproved_quote'))
        @component('portal.ninja2020.components.message')
            <pre>{{ $client->getSetting('custom_message_unapproved_quote') }}</pre>
        @endcomponent
    @endif

    @if(in_array($quote->status_id, [\App\Models\Quote::STATUS_SENT, \App\Models\Quote::STATUS_DRAFT]))
        <div class="mb-4">
            @include('portal.ninja2020.quotes.includes.actions', ['quote' => $quote])
        </div>
    @elseif($quote->status_id == \App\Models\Quote::STATUS_CONVERTED)

        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.approved') }}
                        </h3>
                    </div>

                                @if($quote->invoice()->exists())
                                    <div class="mt-5 sm:mt-0 sm:ml-6 flex justify-end">
                                        <div class="inline-flex rounded-md shadow-sm">
                                            <a class="button button-primary bg-primary" href="/client/invoices/{{ $quote->invoice->hashed_id }}">{{ ctrans('texts.view_invoice') }}</a>
                                        </div>
                                    </div>
                                @endif
                </div>
            </div>
        </div>

    @elseif($quote->status_id == \App\Models\Quote::STATUS_APPROVED)

        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.approved') }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>

    @else

        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.expired') }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('portal.ninja2020.components.entity-documents', ['entity' => $quote])
    @livewire('pdf-slot', ['class' => get_class($quote), 'entity_id' => $quote->id, 'invitation_id' => $invitation->id ?? false, 'db' => $quote->company->db])

@endsection

@section('footer')
    @include('portal.ninja2020.quotes.includes.user-input')
    @include('portal.ninja2020.invoices.includes.terms', ['entities' => [$quote], 'variables' => $variables, 'entity_type' => ctrans('texts.quote')])
    @include('portal.ninja2020.invoices.includes.signature')
@endsection

@push('head')
    @vite('resources/js/clients/quotes/approve.js')

    <script type="text/javascript" defer>

    document.addEventListener('DOMContentLoaded', () => {

        @if($key)
            window.history.pushState({}, "", "{{ url("client/quote/{$key}") }}");
        @endif

    });

    </script>
@endpush

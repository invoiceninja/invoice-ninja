@extends('portal.ninja2020.layout.vendor_app')
@section('meta_title', ctrans('texts.view_purchase_order'))

@push('head')
    <meta name="show-purchase_order-terms" content="false">
    <meta name="require-purchase_order-signature" content="{{ $purchase_order->company->account->hasFeature(\App\Models\Account::FEATURE_INVOICE_SETTINGS) && property_exists($settings, 'require_purchase_order_signature') && $settings->require_purchase_order_signature }}">
    @include('portal.ninja2020.components.no-cache')
    
    <script src="{{ asset('vendor/signature_pad@2.3.2/signature_pad.min.js') }}"></script>

@endpush

@section('body')

    @if($purchase_order->company->getSetting('vendor_portal_enable_uploads'))
        @component('portal.ninja2020.purchase_orders.includes.upload', ['purchase_order' => $purchase_order]) @endcomponent
    @endif

    @if(in_array($purchase_order->status_id, [\App\Models\PurchaseOrder::STATUS_SENT, \App\Models\PurchaseOrder::STATUS_DRAFT]))
    <div class="mb-4">
        @include('portal.ninja2020.purchase_orders.includes.actions', ['purchase_order' => $purchase_order])
    </div>
    @else
        <input type="hidden" id="approve-button">
        <div class="bg-white shadow sm:rounded-lg mb-4">
            <div class="px-4 py-5 sm:p-6">
                <div class="sm:flex sm:items-start sm:justify-between">
                    <div>
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.purchase_order_number_placeholder', ['purchase_order' => $purchase_order->number])}}
                            - {{ \App\Models\PurchaseOrder::stringStatus($purchase_order->status_id) }}
                        </h3>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @include('portal.ninja2020.components.entity-documents', ['entity' => $purchase_order])
    @livewire('pdf-slot', ['class' => get_class($purchase_order), 'entity_id' => $purchase_order->id, 'invitation_id' => $invitation->id ?? false, 'db' => $purchase_order->company->db])

@endsection

@section('footer')
    @include('portal.ninja2020.invoices.includes.terms', ['entities' => [$purchase_order], 'variables' => $variables, 'entity_type' => ctrans('texts.purchase_order')])
    @include('portal.ninja2020.invoices.includes.signature')
@endsection

@push('head')
    @vite('resources/js/clients/purchase_orders/accept.js')

    <script type="text/javascript">

        document.addEventListener('DOMContentLoaded', () => {

            @if($key)
                window.history.pushState({}, "", "{{ url("vendor/purchase_order/{$key}") }}");
            @endif

        });

    </script>
@endpush


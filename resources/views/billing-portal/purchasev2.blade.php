@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.purchase'))

@section('body')
    @livewire('billing-portal-purchasev2', ['subscription_id' => $subscription->id, 'db' => $subscription->company->db, 'hash' => $hash, 'request_data' => $request_data, 'campaign' => request()->query('campaign') ?? null])
@stop

@push('footer')
    <script>

document.addEventListener('livewire:init', () => {

            Livewire.on('beforePaymentEventsCompleted', () => {
                setTimeout(() => {
                    document.getElementById('payment-method-form').submit()
                }, 2500);
            });

        });
        
    </script>
@endpush

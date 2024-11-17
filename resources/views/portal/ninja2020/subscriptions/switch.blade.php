@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.subscriptions'))

@section('body')
    <div class="container mx-auto">
        <!-- Top section showing details between plans -->
        <div class="grid grid-cols-12 gap-8">
            <!-- 1) Subscription we're switching from -->
            <div
                class="col-span-12 md:col-start-2 md:col-span-4 bg-white rounded px-4 py-5 shadow hover:shadow-lg">
                <span class="text-sm uppercase text-gray-900">{{ ctrans('texts.current') }}</span>
                <p class="mt-4">{{ $subscription->name }}</p>
                <div class="flex justify-end mt-2">
                    <span> {{ \App\Utils\Number::formatMoney($subscription->price, $subscription->company) }}</span>
                </div>
            </div>

            <div class="col-span-12 md:col-span-1 flex justify-center items-center">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="hidden md:block">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>

                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
                     stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                     class="md:hidden">
                    <line x1="12" y1="5" x2="12" y2="19"></line>
                    <polyline points="19 12 12 19 5 12"></polyline>
                </svg>
            </div>

            <!-- 2) Subscription we're switching to -->
            <div class="col-span-12 md:col-span-4 bg-white rounded px-4 py-5 shadow border hover:shadow-lg group-hover:border-transparent">
                <span class="text-sm uppercase text-gray-900">{{ ctrans('texts.change') }}</span>
                <p class="mt-4">{{ $target->name }}</p>
                <div class="flex justify-end mt-2">
                    <span> {{ \App\Utils\Number::formatMoney($target->price, $target->company) }}</span>
                </div>
            </div>
        </div>

        <!-- Payment box -->
        @livewire('subscription-plan-switch', ['db' => $company->db, 'recurring_invoice_id' => $recurring_invoice->id, 'subscription_id' => $subscription->id, 'target_id' => $target->id, 'contact_id' => $contact->id,  'amount' => $amount] )
    </div>
@endsection

@push('footer')
    <script>
        
        document.addEventListener('livewire:init', () => {

            Livewire.on('beforePaymentEventsCompleted', () => {
                setTimeout(() => {
                    document.getElementById('payment-method-form').submit()
                }, 2000);
            });

        });

    </script>

@endpush

@extends('portal.ninja2020.layout.clean')
@section('meta_title', ctrans('texts.purchase'))

@section('body')
    @if ($errors->any())
        <div class="alert alert-danger" style="margin: 1rem">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @livewire('billing-portal.purchase', ['subscription_id' => $subscription->hashed_id, 'db' => $subscription->company->db, 'hash' => $hash, 'request_data' => $request_data, 'campaign' => request()->query('campaign') ?? null])
@stop

@push('footer')
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('purchase.submit', (event) => {
                document.getElementById('payment-method-form').submit();
            });

            const target = document.getElementById('container');

            Livewire.on('purchase.next', (event) => {
                document.getElementById('spinner').classList.remove('hidden');
                document.getElementById('container').classList.add('hidden');

                setTimeout(() => {
                    document.getElementById('spinner').classList.add('hidden');
                    document.getElementById('container').classList.remove('hidden');
                }, 1500);
            });

            Livewire.on('update-shipping-data', (event) => {
                for (field in event) {
                    let element = document.querySelector(`input[name=${field}]`);

                    if (element) {
                        element.value = event[field];
                    }
                }
            });
        });
    </script>
@endpush

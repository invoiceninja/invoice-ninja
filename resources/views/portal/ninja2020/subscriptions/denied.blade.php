@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.subscriptions'))

@section('body')
    <div class="container mx-auto">
        <!-- Top section showing details between plans -->
        <div class="grid grid-cols-12 gap-8">

            <div
                class="col-span-12 md:col-start-2 md:col-span-4 bg-white rounded px-4 py-5 shadow hover:shadow-lg">
                <span class="text-sm uppercase text-gray-900">{{ ctrans('texts.current') }}</span>
                <p class="mt-4">{{ $subscription->name }}</p>
                <div class="flex justify-end mt-2">
                    <p> Cannot upgrade / downgrade as you have one of more invoices outstanding</p>
                </div>
            </div>

        </div>

        <!-- Payment box zz-->
    </div>
@endsection

@push('footer')
@endpush

@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.credit'))

@section('body')
    <form action="{{route('client.payments.credit_response')}}" method="post" id="credit-payment">
        @csrf
        <input type="hidden" name="payment_hash" value="{{$payment_hash}}">
        <input type="hidden" name="hash" value="{{ request()->query('hash')}}">
    </form>

    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="alert alert-failure mb-4" hidden id="errors"></div>
                <div class="bg-white shadow overflow-hidden sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg leading-6 font-medium text-gray-900">
                            {{ ctrans('texts.pay_now') }}
                        </h3>
                        <p class="mt-1 max-w-2xl text-sm leading-5 text-gray-500">
                            {{ ctrans('texts.complete_your_payment') }}
                        </p>
                    </div>
                            <div class="bg-white px-4 py-5 sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm leading-5 font-medium text-gray-500">
                                    {{ ctrans('texts.subtotal') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ App\Utils\Number::formatMoney($total['invoice_totals'], $client) }}
                                </dd>
                                @if($total['credit_totals'] > 0)
                                <dt class="text-sm leading-5 font-medium text-gray-500">
                                    {{ ctrans('texts.credit_amount') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ App\Utils\Number::formatMoney($total['credit_totals'], $client) }}
                                </dd>                                
                                @endif
                                <dt class="text-sm leading-5 font-medium text-gray-500">
                                    {{ ctrans('texts.balance') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ App\Utils\Number::formatMoney($total['amount_with_fee'], $client) }}
                                </dd>
                            </div>
                        <div class="bg-white px-4 py-5 flex justify-end">
                            <button form="credit-payment" class="button button-primary bg-primary inline-flex items-center">Pay with credit</button>
                        </div>
                </div>
            </div>
        </div>
    </div>
@endsection
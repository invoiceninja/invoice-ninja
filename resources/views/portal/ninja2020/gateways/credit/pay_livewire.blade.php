<div class="rounded-lg border bg-card text-card-foreground shadow-sm overflow-hidden py-5 bg-white sm:gap-4">

    <form action="{{route('client.payments.credit_response')}}" method="post" id="credit-payment">
    @csrf
    <input type="hidden" name="payment_hash" value="{{$payment_hash}}">
    </form>
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
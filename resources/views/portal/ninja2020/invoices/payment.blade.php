@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.pay_now'))

@push('head')
<meta name="show-invoice-terms" content="{{ $settings->show_accept_invoice_terms ? true : false }}">
<meta name="require-invoice-signature" content="{{ $client->user->account->hasFeature(\App\Models\Account::FEATURE_INVOICE_SETTINGS) && $settings->require_invoice_signature }}">
<script src="{{ asset('vendor/signature_pad@2.3.2/signature_pad.min.js') }}"></script>
@endpush

@section('body')
<form action="{{ route('client.payments.process') }}" method="post" id="payment-form" onkeypress="return event.keyCode != 13;">
    @csrf
    <input type="hidden" name="company_gateway_id" id="company_gateway_id">
    <input type="hidden" name="payment_method_id" id="payment_method_id">
    <input type="hidden" name="signature">
    <input type="hidden" name="pre_payment" value="{{ isset($pre_payment) ? $pre_payment : false }}">
    <input type="hidden" name="is_recurring" value="{{ isset($is_recurring) ? $is_recurring : false }}">
    <input type="hidden" name="frequency_id" value="{{ isset($frequency_id) ? $frequency_id : false }}">
    <input type="hidden" name="remaining_cycles" value="{{ isset($remaining_cycles) ? $remaining_cycles : false }}">
    <input type="hidden" name="contact_first_name" value="{{ auth()->guard('contact')->user()->first_name }}">
    <input type="hidden" name="contact_last_name" value="{{ auth()->guard('contact')->user()->last_name }}">
    <input type="hidden" name="contact_email" value="{{ auth()->guard('contact')->user()->email }}">

    <input type="hidden" name="client_city" value="{{ auth()->guard('contact')->user()->client->city }}">
    <input type="hidden" name="client_postal_code" value="{{ auth()->guard('contact')->user()->client->postal_code }}">

    <div class="container mx-auto">
        <div class="grid grid-cols-6 gap-4">
            <div class="col-span-6 md:col-start-2 md:col-span-4">
                <div class="flex justify-end">
                    <div class="flex justify-end mb-2">
                        @livewire('pay-now-dropdown', ['total' => $total, 'company_id' => $company->id, 'db' => $company->db])
                    </div>
                </div>

                @foreach($invoices as $key => $invoice)
                <input type="hidden" name="payable_invoices[{{$key}}][invoice_id]" value="{{ $invoice->hashed_id }}">
                <div class="mb-4 overflow-hidden bg-white shadow sm:rounded-lg">
                    <div class="px-4 py-5 border-b border-gray-200 sm:px-6">
                        <h3 class="text-lg font-medium leading-6 text-gray-900">
                            {{ ctrans('texts.invoice') }}
                            <a class="button-link text-primary" href="{{ route('client.invoice.show', $invoice->hashed_id) }}">
                                (#{{ $invoice->number }})
                            </a>
                        </h3>
                    </div>
                    <div>
                        <dl>
                            @if(!empty($invoice->number) && !is_null($invoice->number))
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.invoice_number') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $invoice->number }}
                                </dd>
                            </div>
                            @endif

                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                @if($invoice->po_number)
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.po_number') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $invoice->po_number }}
                                </dd>
                                @elseif($invoice->public_notes)
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.public_notes') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {!! html_entity_decode($invoice->public_notes) !!}
                                </dd>
                                @else
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.invoice_date') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $invoice->translateDate($invoice->date, $invoice->client->date_format(), $invoice->client->locale()) }}
                                </dd>
                                @endif
                            </div>

                            @if(!empty($invoice->due_date) && !is_null($invoice->due_date))
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.due_date') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $invoice->translateDate($invoice->due_date, $invoice->client->date_format(), $invoice->client->locale()) }}
                                </dd>
                            </div>
                            @endif

                            @if($settings->client_portal_allow_under_payment || $settings->client_portal_allow_over_payment)
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.amount_due') }}
                                </dt>
                                <dd class="mt-1 text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2">
                                    {{ $invoice->client->currency()->code }} ({{ $invoice->client->currency()->symbol }})
                                    {{ $invoice->partial > 0 ? $invoice->partial : $invoice->balance }}
                                </dd>
                            </div>
                            @endif

                            @if(!empty($invoice->amount) && !is_null($invoice->amount))
                            <div class="px-4 py-5 bg-white sm:grid sm:grid-cols-3 sm:gap-4 sm:px-6">
                                <dt class="text-sm font-medium leading-5 text-gray-500">
                                    {{ ctrans('texts.payment_amount') }}
                                </dt>
                                <dd class="text-sm leading-5 text-gray-900 sm:mt-0 sm:col-span-2 flex flex-col">
                                    <!-- App\Utils\Number::formatMoney($invoice->amount, $invoice->client) -->
                                    <!-- Disabled input field don't send it's value with request. -->
                                    @if(!$settings->client_portal_allow_under_payment && !$settings->client_portal_allow_over_payment)
                                        <label>
                                            {{ $invoice->client->currency()->code }} ({{ $invoice->client->currency()->symbol }})

                                            <input
                                                name="payable_invoices[{{$key}}][amount]"
                                                value="{{ $invoice->partial > 0 ? $invoice->partial : $invoice->balance }}"
                                                class="mt-1 text-sm text-gray-800"
                                                readonly />
                                        </label>
                                    @else
                                    
                                        <div class="flex items-center">
                                            <label>
                                                <span class="mt-2">{{ $invoice->client->currency()->code }} ({{ $invoice->client->currency()->symbol }})</span>
                                                <input
                                                    type="text"
                                                    class="input mt-0 mr-4 relative"
                                                    name="payable_invoices[{{$key}}][amount]"
                                                    value="{{ $invoice->partial > 0 ? $invoice->partial : $invoice->balance }}"/>
                                            </label>
                                        </div>
                                    @endif

                                    @if($settings->client_portal_allow_under_payment)
                                        <span class="mt-1 text-sm text-gray-800">{{ ctrans('texts.minimum_payment') }}: {{ $settings->client_portal_under_payment_minimum }}</span>
                                    @endif
                                </dd>
                            </div>
                            @endif
                        </dl>
                    </div>
                </div>
                @endforeach

                @if(intval($total) == 0)
                    <small>* {{ ctrans('texts.online_payments_minimum_note') }}</small>
                @endif
            </div>
        </div>
    </div>
</form>

@include('portal.ninja2020.invoices.includes.required-fields')
@include('portal.ninja2020.invoices.includes.terms', ['entities' => $invoices, 'variables' => $variables, 'entity_type' => ctrans('texts.invoice')])
@include('portal.ninja2020.invoices.includes.signature')

@endsection

@push('footer')
    @vite('resources/js/clients/invoices/payment.js')
@endpush

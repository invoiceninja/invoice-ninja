@extends('portal.ninja2020.layout.app')
@section('meta_title', ctrans('texts.view_invoice'))

@section('body')
    @if(Route::is('client.invoice.show'))
        <dialog class="w-full bg-white rounded-lg px-4 pt-5 pb-4 shadow-xl transform transition-all sm:p-6" id="dialogPdf">
            @livewire('pdf-slot', ['class' => get_class($invoice), 'entity_id' => $invoice->id, 'invitation_id' => $invitation->id ?? false, 'db' => $invoice->company->db, 'with_close_button' => 'dialog#dialogPdf'])

        </dialog>

        <div class="px-2">
            <div class="bg-white shadow rounded-lg mb-4" translate>
                <div class="px-4 py-5 sm:p-6">
                    <div class="sm:flex sm:items-start sm:justify-between">
                        <div>
                            <h3 class="text-lg leading-6 font-medium text-gray-900">
                                {{ ctrans('texts.invoice_number_placeholder', ['invoice' => $invoice->number])}}
                            </h3>
                        </div>
                        <div class="sm:mt-0 sm:ml-6 flex justify-end">
                            <button @click="document.getElementById('dialogPdf').showModal()" type="button"
                                class="button button-primary bg-primary">{{ ctrans('texts.view_pdf') }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @endif

    @if($invoice->isPayable() && $client->getSetting('custom_message_unpaid_invoice'))
        @component('portal.ninja2020.components.message')
            <pre>{{ $client->getSetting('custom_message_unpaid_invoice') }}</pre>
        @endcomponent
    @elseif($invoice->status_id === 4 && $client->getSetting('custom_message_paid_invoice'))
        @component('portal.ninja2020.components.message')
            <pre>{{ $client->getSetting('custom_message_paid_invoice') }}</pre>
        @endcomponent
    @endif

    @if($invoice->isPayable())
        @livewire('flow2.invoice-pay', ['invoices' => $invoices, 'invitation_id' => $invitation->id, 'db' => $db, 'variables' => $variables])
    @endif
@endsection
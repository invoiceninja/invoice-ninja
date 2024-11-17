<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\Flow2;

use App\Utils\Number;
use Livewire\Component;
use Livewire\Attributes\On;
use App\Utils\Traits\WithSecureContext;

class InvoiceSummary extends Component
{
    use WithSecureContext;

    public $invoices;

    public $amount;

    public $gateway_fee;

    public function mount()
    {
       
        $contact = $this->getContext()['contact'];
        $this->invoices = $this->getContext()['payable_invoices'];
        $this->amount = Number::formatMoney($this->getContext()['amount'], $contact->client);
        $this->gateway_fee = isset($this->getContext()['gateway_fee']) ? Number::formatMoney($this->getContext()['gateway_fee'], $contact->client) : false;

    }

    #[On(self::CONTEXT_UPDATE)]
    public function onContextUpdate(): void
    {
        // refactor logic for updating the price for eg if it changes with under/over pay
        $contact = $this->getContext()['contact'];
        $this->invoices = $this->getContext()['payable_invoices'];
        $this->amount = Number::formatMoney($this->getContext()['amount'], $contact->client);
        $this->gateway_fee = isset($this->getContext()['gateway_fee']) ? Number::formatMoney($this->getContext()['gateway_fee'], $contact->client) : false;

    }

    #[On('payment-view-rendered')] 
    public function handlePaymentViewRendered()
    {
        
        $contact = $this->getContext()['contact'];
        $this->amount = Number::formatMoney($this->getContext()['amount'], $contact->client);
        $this->gateway_fee = isset($this->getContext()['gateway_fee']) ? Number::formatMoney($this->getContext()['gateway_fee'], $contact->client) : false;

    }

    public function downloadDocument($invoice_hashed_id)
    {

        $contact = $this->getContext()['contact'];
        $_invoices = $this->getContext()['invoices'];
        $i = $_invoices->first(function ($i) use($invoice_hashed_id){
            return $i->hashed_id == $invoice_hashed_id;
        });

        $file_name = $i->numberFormatter().'.pdf';

        $file = (new \App\Jobs\Entity\CreateRawPdf($i->invitations()->where('client_contact_id', $contact->id)->first()))->handle();

        $headers = ['Content-Type' => 'application/pdf'];

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        $contact = $this->getContext()['contact'];
        
        return render('flow2.invoices-summary', [
            'client' => $contact->client,
        ]);
        
    }
}

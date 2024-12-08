<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Livewire\BillingPortal;

use App\Models\CompanyGateway;
use App\Models\Subscription;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\On;
use Livewire\Component;

class RFF extends Component
{
    use MakesHash;

    public array $context;

    public string $contact_first_name;

    public string $contact_last_name;

    public string $contact_email;

    #[On('passed-required-fields-check')]
    public function continue(): void
    {
        $this->dispatch('purchase.context', property: 'contact', value: auth()->guard('contact')->user());

        $this->dispatch('purchase.next');
    }

    public function handleSubmit()
    {
        $data = $this->validate([
            'contact_first_name' => 'required',
            'contact_last_name' => 'required',
            'contact_email' => 'required|email:rfc',
        ]);

        $contact = auth()->guard('contact');

        /** @var \App\Models\ClientContact $contact */
        $contact->user()->update([
            'first_name' => $data['contact_first_name'],
            'last_name' => $data['contact_last_name'],
            'email' => $data['contact_email'],
        ]);

        $this->dispatch('purchase.context', property: 'contact', value: auth()->guard('contact')->user());

        $this->dispatch('purchase.next');
    }

    public function mount(): void
    {
        $this->contact_first_name = $this->context['contact']['first_name'] ?? '';
        $this->contact_last_name = $this->context['contact']['last_name'] ?? '';
        $this->contact_email = $this->context['contact']['email'] ?? '';
    }

    public function render()
    {
        /** @var \App\Models\CompanyGateway $gateway */
        $gateway = CompanyGateway::find($this->context['form']['company_gateway_id']);
        $countries = Cache::get('countries');


        if ($gateway === null) {
            return view('billing-portal.v3.rff-basic');
        }

        return view('billing-portal.v3.rff', [
            'gateway' => $gateway->driver(
                auth()->guard('contact')->user()->client
            ),
            'countries' => $countries,
            'company' => $gateway->company,
        ]);
    }
}

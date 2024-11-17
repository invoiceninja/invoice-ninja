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

namespace App\Livewire\BillingPortal\Authentication;

use Livewire\Component;
use App\Models\Subscription;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Validator;

class Register extends Component
{
    use MakesHash;

    public string $subscription_id;

    public array $context;

    public ?string $email;

    public ?string $password;

    public ?int $otp;

    public array $formData = []; 

    public array $state = [
        'initial_completed' => false,
        'register_form' => false,
    ];

    public array $register_fields = [];

    public array $additional_fields = [];


    #[Computed()]
    public function subscription()
    {
        return Subscription::find($this->decodePrimaryKey($this->subscription_id))->withoutRelations()->makeHidden(['webhook_configuration','steps']);
    }
    
    public function initial(): void
    {
        $this->validateOnly('email', ['email' => 'required|bail|email:rfc']);

        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription()->company_id)
            ->first();

        if ($contact) {
            $this->addError('email', ctrans('texts.checkout_only_for_new_customers'));

            return;
        }

        $this->state['initial_completed'] = true;

        $this->registerForm();
    }

    public function register(array $data)
    {

        $data = array_merge($data, [
            'country_id' => $this->formData['country_id'] ?? null,
            'shipping_country_id' => $this->formData['shipping_country_id'] ?? null,
        ]);

        $service = new ClientRegisterService(
            company: $this->subscription()->company,
            additional: $this->additional_fields,
        );

        $rules = $service->rules();
        
        $data = Validator::make($data, $rules)->validate();

        $client = $service->createClient($data);
        $contact = $service->createClientContact($data, $client);

        auth()->guard('contact')->loginUsingId($contact->id, true);

        // $this->dispatch('purchase.context', property: 'contact', value: $contact);
        $this->dispatch('purchase.next');
    }

    public function registerForm()
    {
        $count = collect($this->subscription()->company->client_registration_fields ?? [])
            ->filter(fn ($field) => $field['required'] === true || $field['visible'] === true)
            ->count();

        if ($count === 0) {
            $service = new ClientRegisterService(
                company: $this->subscription()->company,
            );

            $client = $service->createClient([]);
            $contact = $service->createClientContact(['email' => $this->email], $client);

            auth()->guard('contact')->loginUsingId($contact->id, true);

            // $this->dispatch('purchase.context', property: 'contact', value: $contact);
            $this->dispatch('purchase.next');

            return;
        }

        $this->register_fields = [...collect($this->subscription()->company->client_registration_fields ?? [])->toArray()];

        $first_gateway = collect($this->subscription()->company->company_gateways()->withoutTrashed()->get())
            ->sortBy('sort_order')
            ->first();

        $mappings = ClientRegisterService::mappings();

        collect($first_gateway->driver()->getClientRequiredFields() ?? [])
            ->each(function ($field) use ($mappings) {
                $mapping = $mappings[$field['name']] ?? null;

                if ($mapping === null) {
                    return;
                }

                $i = collect($this->register_fields)->search(fn ($field) => $field['key'] == $mapping);

                if ($i !== false) {
                    $this->register_fields[$i]['visible'] = true;
                    $this->register_fields[$i]['required'] = true;


                    $this->additional_fields[] = $this->register_fields[$i];
                } else {
                    $field = [
                        'key' => $mapping,
                        'required' => true,
                        'visible' => true,
                    ];

                    $this->register_fields[] = $field;
                    $this->additional_fields[] = $field;
                }
            })
            ->toArray();

        return $this->state['register_form'] = true;
    }

    public function mount()
    {
        if (auth()->guard('contact')->check()) {
            // $this->dispatch('purchase.context', property: 'contact', value: auth()->guard('contact')->user());
            $this->dispatch('purchase.next');
        }

    }

    public function render()
    {
        return view('billing-portal.v3.authentication.register');
    }
}

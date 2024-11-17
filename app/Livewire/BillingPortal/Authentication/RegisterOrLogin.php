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
use App\Jobs\Mail\NinjaMailerJob;
use Livewire\Attributes\Computed;
use App\Mail\Subscription\OtpCode;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class RegisterOrLogin extends Component
{
    use MakesHash;

    public string $subscription_id;

    public array $context;

    public ?string $email;

    public ?string $password;

    public ?int $otp;

    public array $formData = []; 

    public array $state = [
        'otp' => false, // Use as preference. E-mail/password or OTP.
        'login_form' => false,
        'otp_form' => false,
        'register_form' => false,
        'initial_completed' => false,
    ];

    public array $register_fields = [];

    public array $additional_fields = [];

    #[Computed]
    public function subscription()
    {
        return Subscription::find($this->decodePrimaryKey($this->subscription_id));
    }

    public function initial()
    {
        $this->validateOnly('email', ['email' => 'required|bail|email:rfc']);

        $this->state['initial_completed'] = true;

        if ($this->state['otp']) {
            return $this->withOtp();
        }

        return $this->withPassword();
    }

    public function withPassword()
    {
        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription()->company_id)
            ->first();

        if ($contact) {
            return $this->state['login_form'] = true;
        }

        $this->state['login_form'] = false;
        $this->registerForm();
    }

    public function handlePassword()
    {
        $this->validate([
            'email' => 'required|bail|email:rfc',
            'password' => 'required',
        ]);

        $attempt = auth()->guard('contact')->attempt([
            'email' => $this->email,
            'password' => $this->password,
            'company_id' => $this->subscription()->company_id,
        ]);

        if ($attempt) {
            $this->dispatch('purchase.next');
        }

        session()->flash('message', 'These credentials do not match our records.');
    }

    public function withOtp(): void
    {
        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription()->company_id)
            ->first();

        if ($contact === null) {
            $this->registerForm();

            return;
        }

        $code = rand(100000, 999999);
        $email_hash = "subscriptions:otp:{$this->email}";

        Cache::put($email_hash, $code, 600);

        $cc = new ClientContact();
        $cc->email = $this->email;

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new OtpCode($this->subscription()->company, $contact, $code);
        $nmo->company = $this->subscription()->company;
        $nmo->settings = $this->subscription()->company->settings;
        $nmo->to_user = $contact;

        NinjaMailerJob::dispatch($nmo);

        if (app()->environment('local')) {
            session()->flash('message', "[dev]: Your OTP is: {$code}");
        }

        $this->state['otp_form'] = true;
    }

    public function handleOtp(): void
    {
        $this->validate([
            'otp' => 'required|numeric|digits:6',
        ]);

        $code = Cache::get("subscriptions:otp:{$this->email}");

        if ($this->otp != $code) { //loose comparison prevents edge cases
            $errors = $this->getErrorBag();
            $errors->add('otp', ctrans('texts.invalid_code'));

            return;
        }

        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription()->company_id)
            ->first();

        if ($contact) {
            auth()->guard('contact')->loginUsingId($contact->id, true);

            // $this->dispatch('purchase.context', property: 'contact', value: $contact);
            $this->dispatch('purchase.next');

            return;
        }

        $this->state['otp_form'] = false;
        $this->registerForm();
    }

    public function register(array $data): void
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

            return;
        }
    }

    public function render()
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        return view('billing-portal.v3.authentication.register-or-login', [
            'countries' => $countries,
        ]);
    }
}

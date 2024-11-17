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

class Login extends Component
{
    use MakesHash;

    public string $subscription_id;

    public array $context;

    public ?string $email;

    public ?string $password;

    public ?int $otp;

    public array $state = [
        'otp' => false, // Use as preference. E-mail/password or OTP.
        'login_form' => false,
        'otp_form' => false,
        'initial_completed' => false,
    ];

    #[Computed]
    public function subscription()
    {
        return Subscription::find($this->decodePrimaryKey($this->subscription_id));
    }

    public function initial()
    {
        $this->validateOnly('email', ['email' => 'required|bail|email:rfc|email']);

        $contact = ClientContact::where('email', $this->email)
            ->where('company_id', $this->subscription()->company_id)
            ->first();

        if ($contact === null) {
            $this->addError('email', ctrans('texts.checkout_only_for_existing_customers'));

            return;
        }

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

        $contact = $this->createClientContact();

        auth()->guard('contact')->loginUsingId($contact->id, true);

        // $this->dispatch('purchase.context', property: 'contact', value: $contact);
        $this->dispatch('purchase.next');
    }

    public function withOtp()
    {
        $code = rand(100000, 999999);
        $email_hash = "subscriptions:otp:{$this->email}";
        $contact = auth()->guard('contact')->user();

        Cache::put($email_hash, $code, 600);

        $cc = new ClientContact();
        $cc->email = $this->email;

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new OtpCode($this->subscription()->company, $contact ?? null, $code);
        $nmo->company = $this->subscription()->company;
        $nmo->settings = $this->subscription()->company->settings;
        $nmo->to_user = $cc;

        NinjaMailerJob::dispatch($nmo);

        if (app()->environment('local')) {
            session()->flash('message', "[dev]: Your OTP is: {$code}");
        }

        $this->state['otp_form'] = true;
    }

    public function handleOtp()
    {
        $this->validate([
            'otp' => 'required|numeric|digits:6 ',
            'email' => 'required|bail|email:rfc|exists:client_contacts,email',
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

            // $this->dispatch('purchase.context', property: 'contact', value: auth()->guard('contact')->user());
            $this->dispatch('purchase.next');
        }

        session()->flash('message', 'These credentials do not match our records.');
    }

    public function mount()
    {
        if (auth()->guard('contact')->check()) {
            // $this->dispatch('purchase.context', property: 'contact', value: auth()->guard('contact')->user());
            $this->dispatch('purchase.next');
        }
        
    }

    public function render(): \Illuminate\View\View
    {
        return view('billing-portal.v3.authentication.login');
    }
}

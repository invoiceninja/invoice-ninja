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

namespace App\Livewire\Profile\Settings;

use Livewire\Component;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Hash;

class General extends Component
{
    public $first_name;

    public $last_name;

    public $email;

    public $phone;

    public $password;

    public $password_confirmation;

    public $saved;

    protected $rules = [
        'first_name' => ['sometimes'],
        'last_name' => ['sometimes'],
        'email' => ['required', 'email'],
        'phone' => ['sometimes'],
    ];

    #[Computed]
    public function profile()
    {
        return auth()->guard('contact')->user();
    }

    public function mount()
    {
        $profile = $this->profile();

        $this->fill([
            // 'profile' => $profile,
            'first_name' => $profile->first_name,
            'last_name' => $profile->last_name,
            'email' => $profile->email,
            'phone' => $profile->phone,
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.general');
    }

    public function submit()
    {
        
        $profile = $this->profile();

        if ($profile->email != $this->email) {
            $this->rules['email'][] = 'unique:client_contacts,email';
        }

        if (! empty($this->password)) {
            $this->rules['password'] = ['sometimes', 'nullable', 'required', 'min:6', 'confirmed'];
        }

        $data = $this->validate($this->rules);

        if (! empty($this->password)) {
            $profile->password = Hash::make($this->password);
        }

        $profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}

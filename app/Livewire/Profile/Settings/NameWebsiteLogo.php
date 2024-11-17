<?php

namespace App\Livewire\Profile\Settings;

use Livewire\Component;

class NameWebsiteLogo extends Component
{
    public $name;

    public $vat_number;

    public $website;

    public $phone;

    public $saved;

    public $rules = [
        'name' => ['sometimes', 'min:3'],
        'vat_number' => ['sometimes'],
        'website' => ['sometimes'],
        'phone' => ['sometimes', 'string', 'max:255'],
    ];

    public function mount()
    {
        $this->fill([
            // 'profile' => auth()->guard('contact')->user()->client,
            'name' => auth()->guard('contact')->user()->client->present()->name(),
            'vat_number' => auth()->guard('contact')->user()->client->vat_number ?: '',
            'website' => auth()->guard('contact')->user()->client->website,
            'phone' => auth()->guard('contact')->user()->client->present()->phone(),
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.name-website-logo');
    }

    public function submit()
    {
        $data = $this->validate($this->rules);

        $profile = auth()->guard('contact')->user()->client;

        $profile->name = $data['name'];
        $profile->vat_number = $data['vat_number'];
        $profile->website = $data['website'];
        $profile->phone = $data['phone'];

        $profile->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}

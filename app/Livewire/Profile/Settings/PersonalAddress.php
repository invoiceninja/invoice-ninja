<?php

namespace App\Livewire\Profile\Settings;

use Livewire\Component;

class PersonalAddress extends Component
{

    public $address1;

    public $address2;

    public $city;

    public $state;

    public $postal_code;

    public $country_id;

    public $saved;

    protected $rules = [
        'address1' => ['sometimes'],
        'address2' => ['sometimes'],
        'city' => ['sometimes'],
        'state' => ['sometimes'],
        'postal_code' => ['sometimes'],
        'country_id' => ['sometimes'],
    ];

    public function mount()
    {
        $this->fill([
            // 'profile' => auth()->guard('contact')->user()->client,
            'address1' => auth()->guard('contact')->user()->client->address1,
            'address2' => auth()->guard('contact')->user()->client->address2,
            'city' => auth()->guard('contact')->user()->client->city,
            'state' => auth()->guard('contact')->user()->client->state,
            'postal_code' => auth()->guard('contact')->user()->client->postal_code,
            'country_id' => auth()->guard('contact')->user()->client->country_id,
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.personal-address');
    }

    public function submit()
    {
        $data = $this->validate($this->rules);

        if ($data['country_id'] == 'none') {
            $data['country_id'] = null;
        }

        $profile = auth()->guard('contact')->user()->client;

        $profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}

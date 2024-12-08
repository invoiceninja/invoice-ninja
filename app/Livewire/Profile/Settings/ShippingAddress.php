<?php

namespace App\Livewire\Profile\Settings;

use Livewire\Component;

class ShippingAddress extends Component
{
    public $shipping_address1;

    public $shipping_address2;

    public $shipping_city;

    public $shipping_state;

    public $shipping_postal_code;

    public $shipping_country_id;

    public $saved;

    protected $rules = [
        'shipping_address1' => ['sometimes'],
        'shipping_address2' => ['sometimes'],
        'shipping_city' => ['sometimes'],
        'shipping_state' => ['sometimes'],
        'shipping_postal_code' => ['sometimes'],
        'shipping_country_id' => ['sometimes'],
    ];

    public function mount()
    {
        $this->fill([
            // 'profile' => auth()->guard('contact')->user()->client,
            'shipping_address1' => auth()->guard('contact')->user()->client->shipping_address1,
            'shipping_address2' => auth()->guard('contact')->user()->client->shipping_address2,
            'shipping_city' => auth()->guard('contact')->user()->client->shipping_city,
            'shipping_state' => auth()->guard('contact')->user()->client->shipping_state,
            'shipping_postal_code' => auth()->guard('contact')->user()->client->shipping_postal_code,
            'shipping_country_id' => auth()->guard('contact')->user()->client->shipping_country_id,
            'saved' => ctrans('texts.save'),
        ]);
    }

    public function render()
    {
        return render('profile.settings.shipping-address');
    }

    public function submit()
    {
        $data = $this->validate($this->rules);

        if ($data['shipping_country_id'] == 'none') {
            $data['shipping_country_id'] = null;
        }

        $profile = auth()->guard('contact')->user()->client;

        $profile
            ->fill($data)
            ->save();

        $this->saved = ctrans('texts.saved_at', ['time' => now()->toTimeString()]);
    }
}

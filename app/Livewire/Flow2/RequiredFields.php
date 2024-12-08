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

use Livewire\Component;
use App\Libraries\MultiDB;
use App\Models\CompanyGateway;
use Livewire\Attributes\Computed;
use App\Services\Client\RFFService;
use App\Utils\Traits\WithSecureContext;

class RequiredFields extends Component
{
    use WithSecureContext;

    public ?CompanyGateway $company_gateway;

    public ?string $client_name;
    public ?string $contact_first_name;
    public ?string $contact_last_name;
    public ?string $contact_email;
    public ?string $client_phone;
    public ?string $client_address_line_1;
    public ?string $client_city;
    public ?string $client_state;
    public ?int $client_country_id;
    public ?string $client_postal_code;
    public ?string $client_shipping_address_line_1;
    public ?string $client_shipping_city;
    public ?string $client_shipping_state;
    public ?string $client_shipping_postal_code;
    public ?int $client_shipping_country_id;
    public ?string $client_custom_value1;
    public ?string $client_custom_value2;
    public ?string $client_custom_value3;
    public ?string $client_custom_value4;

    // public $contact;

    /** @var array<int, string> */
    public array $fields = [];

    public bool $is_loading = true;

    public array $errors = [];

    public function mount(): void
    {
        MultiDB::setDB(
            $this->getContext()['db'],
        );

        $this->fields = $this->getContext()['fields'];

        $contact = auth()->guard('contact')->user();

        $this->company_gateway = CompanyGateway::withTrashed()
            ->with('company')
            ->find($this->getContext()['company_gateway_id']);

        $this->client_name = $contact->client->name;
        $this->contact_first_name = $contact->first_name;
        $this->contact_last_name = $contact->last_name;
        $this->contact_email = $contact->email;
        $this->client_phone = $contact->client->phone;
        $this->client_address_line_1 = $contact->client->address1;
        $this->client_city = $contact->client->city;
        $this->client_state = $contact->client->state;
        $this->client_country_id = $contact->client->country_id;
        $this->client_postal_code = $contact->client->postal_code;
        $this->client_shipping_address_line_1 = $contact->client->shipping_address1;
        $this->client_shipping_city = $contact->client->shipping_city;
        $this->client_shipping_state = $contact->client->shipping_state;
        $this->client_shipping_postal_code = $contact->client->shipping_postal_code;
        $this->client_shipping_country_id = $contact->client->shipping_country_id;
        $this->client_custom_value1 = $contact->client->custom_value1;
        $this->client_custom_value2 = $contact->client->custom_value2;
        $this->client_custom_value3 = $contact->client->custom_value3;
        $this->client_custom_value4 = $contact->client->custom_value4;

        $rff = new RFFService(
            fields: $this->getContext()['fields'],
            database: $this->getContext()['db'],
            company_gateway_id: (string)$this->company_gateway->id,
        );

        /** @var \App\Models\ClientContact $contact */
        $rff->check($contact);

        if ($rff->unfilled_fields === 0 && !$this->company_gateway->always_show_required_fields) {
            $this->dispatch('required-fields');
        } else {
            $this->is_loading = false;
        }

    }

    public function handleSubmit(array $data)
    {
        $this->errors = [];
        $this->is_loading = true;

        $rff = new RFFService(
            fields: $this->fields,
            database: $this->getContext()['db'],
            company_gateway_id: (string)$this->company_gateway->id,
        );

        $contact = auth()->guard('contact')->user();

        /** @var \App\Models\ClientContact $contact */
        $errors = $rff->handleSubmit($data, $contact, return_errors: true, callback: function () {
            $this->dispatch('required-fields');
        });

        if (is_array($errors) && count($errors)) {
            $this->errors = $errors;
            $this->is_loading = false;
        }
    }

    public function render(): \Illuminate\Contracts\View\Factory|\Illuminate\View\View
    {
        return render('flow2.required-fields', [
            'contact' => $this->getContext()['contact'],
        ]);
    }

    public function exception($e, $stopPropagation)
    {
        app('sentry')->captureException($e);
        nlog($e->getMessage());
        $stopPropagation();

    }
}

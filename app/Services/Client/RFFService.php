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

namespace App\Services\Client;

use App\Libraries\MultiDB;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use Illuminate\Support\Str;
use Validator;

class RFFService
{
    public array $mappings = [
        'client_name' => 'name',
        'client_website' => 'website',
        'client_phone' => 'phone',

        'client_address_line_1' => 'address1',
        'client_address_line_2' => 'address2',
        'client_city' => 'city',
        'client_state' => 'state',
        'client_postal_code' => 'postal_code',
        'client_country_id' => 'country_id',

        'client_shipping_address_line_1' => 'shipping_address1',
        'client_shipping_address_line_2' => 'shipping_address2',
        'client_shipping_city' => 'shipping_city',
        'client_shipping_state' => 'shipping_state',
        'client_shipping_postal_code' => 'shipping_postal_code',
        'client_shipping_country_id' => 'shipping_country_id',

        'client_custom_value1' => 'custom_value1',
        'client_custom_value2' => 'custom_value2',
        'client_custom_value3' => 'custom_value3',
        'client_custom_value4' => 'custom_value4',

        'contact_first_name' => 'first_name',
        'contact_last_name' => 'last_name',
        'contact_email' => 'email',
        // 'contact_phone' => 'phone',
    ];

    public int $unfilled_fields = 0;

    public function __construct(
        public array $fields,
        public string $database,
        public string $company_gateway_id,
    ) {
    }

    public function check(ClientContact $contact): void
    {
        $_contact = $contact;

        foreach ($this->fields as $index => $field) {
            $_field = $this->mappings[$field['name']];

            if (Str::startsWith($field['name'], 'client_')) {
                if (
                    empty($_contact->client->{$_field})
                    || is_null($_contact->client->{$_field}) //@phpstan-ignore-line
                ) {
                    // $this->show_form = true;
                    $this->unfilled_fields++;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }

            if (Str::startsWith($field['name'], 'contact_')) {
                if (empty($_contact->{$_field}) || is_null($_contact->{$_field}) || str_contains($_contact->{$_field}, '@example.com')) { //@phpstan-ignore-line
                    $this->unfilled_fields++;
                } else {
                    $this->fields[$index]['filled'] = true;
                }
            }
        }
    }

    public function handleSubmit(array $data, ClientContact $contact, callable $callback, bool $return_errors = false): bool|array
    {
        MultiDB::setDb($this->database);

        $rules = [];

        collect($this->fields)->map(function ($field) use (&$rules) {
            if (!array_key_exists('filled', $field)) {
                $rules[$field['name']] = array_key_exists('validation_rules', $field)
                    ? $field['validation_rules']
                    : 'required';
            }
        });

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            if ($return_errors) {
                return $validator->getMessageBag()->getMessages();
            }
            session()->flash('validation_errors', $validator->getMessageBag()->getMessages());

            return false;
        }

        if ($this->update($data, $contact)) {
            $callback();

            return true;
        }

        return false;
    }

    public function update(array $data, ClientContact $_contact): bool
    {
        $client = [];
        $contact = [];

        MultiDB::setDb($this->database);

        foreach ($data as $field => $value) {
            if (Str::startsWith($field, 'client_')) {
                $client[$this->mappings[$field]] = $value;
            }

            if (Str::startsWith($field, 'contact_')) {
                $contact[$this->mappings[$field]] = $value;
            }
        }

        // $_contact->first_name = $data['contact_first_name'] ?? '';
        // $_contact->last_name = $data['contact_last_name'] ?? '';
        // $_contact->client->name = $data['client_name'] ?? '';
        // $_contact->email = $data['contact_email'] ?? '';
        // $_contact->client->phone = $data['client_phone'] ?? '';
        // $_contact->client->address1 = $data['client_address_line_1'] ?? '';
        // $_contact->client->city = $data['client_city'] ?? '';
        // $_contact->client->state = $data['client_state'] ?? '';
        // $_contact->client->country_id = $data['client_country_id'] ?? '';
        // $_contact->client->postal_code = $data['client_postal_code'] ?? '';
        // $_contact->client->shipping_address1 = $data['client_shipping_address_line_1'] ?? '';
        // $_contact->client->shipping_city = $data['client_shipping_city'] ?? '';
        // $_contact->client->shipping_state = $data['client_shipping_state'] ?? '';
        // $_contact->client->shipping_postal_code = $data['client_shipping_postal_code'] ?? '';
        // $_contact->client->shipping_country_id = $data['client_shipping_country_id'] ?? '';
        // $_contact->client->custom_value1 = $data['client_custom_value1'] ?? '';
        // $_contact->client->custom_value2 = $data['client_custom_value2'] ?? '';
        // $_contact->client->custom_value3 = $data['client_custom_value3'] ?? '';
        // $_contact->client->custom_value4 = $data['client_custom_value4'] ?? '';
        // $_contact->push();


        $_contact
            ->fill($contact)
            ->push();

        $_contact->client
            ->fill($client)
            ->push();

        /** @var \App\Models\CompanyGateway $cg */
        $cg = CompanyGateway::find(
            $this->company_gateway_id,
        );

        //@phpstan-ignore-next-line
        if ($cg && $cg->update_details) {
            $payment_gateway = $cg->driver($_contact->client)->init();

            if (method_exists($payment_gateway, "updateCustomer")) {
                $payment_gateway->updateCustomer();
            }
        }

        return true;
    }
}

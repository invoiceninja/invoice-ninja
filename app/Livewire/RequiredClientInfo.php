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

namespace App\Livewire;

use App\Models\Invoice;
use Livewire\Component;
use App\Libraries\MultiDB;
use Illuminate\Support\Str;
use App\Models\ClientContact;
use App\Models\CompanyGateway;
use App\Utils\Traits\MakesHash;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

class RequiredClientInfo extends Component
{
    use MakesHash;

    /**
     * @var bool
     */
    public $show_terms = false;

    public $invoice_terms;

    /**
     * @var bool
     */
    public $terms_accepted = true;

    /**
     * @var array
     */
    public $fields = [];

    public $contact_id;

    public $client_id;

    /**
     * @var array
     */
    public $countries;

    public $client_name;
    public $contact_first_name;
    public $contact_last_name;
    public $contact_email;
    public $client_phone;
    public $client_address_line_1;
    public $client_city;
    public $client_state;
    public $client_country_id;
    public $client_postal_code;
    public $client_shipping_address_line_1;
    public $client_shipping_city;
    public $client_shipping_state;
    public $client_shipping_postal_code;
    public $client_shipping_country_id;
    public $client_custom_value1;
    public $client_custom_value2;
    public $client_custom_value3;
    public $client_custom_value4;
    private ?CompanyGateway $company_gateway;
    private int $unfilled_fields = 0;

    /**
     * Mappings for updating the database. Left side is mapping from gateway,
     * right side is column in database.
     *
     * @var string[]
     */
    private $mappings = [
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

    public $client_address_array = [
        'address1',
        'address2',
        'city',
        'state',
        'postal_code',
        'country_id',
        'shipping_address1',
        'shipping_address2',
        'shipping_city',
        'shipping_state',
        'shipping_postal_code',
        'shipping_country_id',
    ];

    protected $rules = [
        // 'client.address1' => '',
        // 'client.address2' => '',
        // 'client.city' => '',
        // 'client.state' => '',
        // 'client.postal_code' => '',
        // 'client.country_id' => '',
        // 'client.shipping_address1' => '',
        // 'client.shipping_address2' => '',
        // 'client.shipping_city' => '',
        // 'client.shipping_state' => '',
        // 'client.shipping_postal_code' => '',
        // 'client.shipping_country_id' => '',
        // 'contact.first_name' => '',
        // 'contact.last_name' => '',
        // 'contact.email' => '',
        // 'client.name' => '',
        // 'client.website' => '',
        // 'client.phone' => '',
        // 'client.custom_value1' => '',
        // 'client.custom_value2' => '',
        // 'client.custom_value3' => '',
        // 'client.custom_value4' => '',
        'client_name' => '',
        'client_website' => '',
        'client_phone' => '',
        'client_address_line_1' => '',
        'client_address_line_2' => '',
        'client_city' => '',
        'client_state' => '',
        'client_postal_code' => '',
        'client_country_id' => '',
        'client_shipping_address_line_1' => '',
        'client_shipping_address_line_2' => '',
        'client_shipping_city' => '',
        'client_shipping_state' => '',
        'client_shipping_postal_code' => '',
        'client_shipping_country_id' => '',
        'client_custom_value1' => '',
        'client_custom_value2' => '',
        'client_custom_value3' => '',
        'client_custom_value4' => '',
        'contact_first_name' => '',
        'contact_last_name' => '',
        'contact_email' => '',
    ];

    public $show_form = false;

    public $company_id;

    public $company_gateway_id;

    public bool $form_only = false;

    public $db;

    public bool $is_subscription = false;

    public function mount()
    {
        MultiDB::setDb($this->db);
     
        $contact = ClientContact::withTrashed()->with(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }])->find($this->contact_id);

        $this->company_gateway = CompanyGateway::withTrashed()->with('company')->find($this->company_gateway_id);
        
        $company = $this->company_gateway->company;

        $this->client_name = $contact->client->name;
        $this->contact_first_name = $contact->first_name;
        $this->contact_last_name = $contact->last_name;
        $this->contact_email = $contact->email;
        $this->client_phone = $contact->client->phone;
        $this->client_address_line_1 = $contact->client->address1;
        $this->client_city = $contact->client->city ;
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

        if ($company->settings->show_accept_invoice_terms && request()->query('hash')) {
            $this->show_terms = true;
            $this->terms_accepted = false;
            $this->show_form = true;

            $hash = Cache::get(request()->input('hash'));

            /** @var \App\Models\Invoice $invoice */
            $invoice = Invoice::find($this->decodePrimaryKey($hash['invoice_id']));

            $this->invoice_terms = $invoice->terms;
        }

        if(!$this->company_gateway->always_show_required_fields || $this->is_subscription) {
            $this->checkFields();
        }

        if (count($this->fields) === 0) {
            $this->dispatch(
                'passed-required-fields-check',
                client_postal_code: $contact->client->postal_code
            );
        }

        if($this->unfilled_fields > 0 || ($this->company_gateway->always_show_required_fields || $this->is_subscription)) {
            $this->show_form = true;
        }
    }

    #[Computed]
    public function contact()
    {

        MultiDB::setDb($this->db);
        return ClientContact::withTrashed()->find($this->contact_id);

    }

    #[Computed]
    public function client()
    {

        MultiDB::setDb($this->db);
        return ClientContact::withTrashed()->find($this->contact_id)->client;

    }

    public function toggleTermsAccepted()
    {
        $this->terms_accepted = !$this->terms_accepted;
    }

    public function handleSubmit(array $data): bool
    {

        MultiDB::setDb($this->db);

        $contact = ClientContact::withTrashed()->with(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }])->find($this->contact_id);

        $rules = [];

        collect($this->fields)->map(function ($field) use (&$rules) {
            if (! array_key_exists('filled', $field)) {
                $rules[$field['name']] = array_key_exists('validation_rules', $field)
                    ? $field['validation_rules']
                    : 'required';
            }
        });

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            session()->flash('validation_errors', $validator->getMessageBag()->getMessages());

            return false;
        }

        if ($this->updateClientDetails($data)) {
            $this->dispatch(
                'passed-required-fields-check',
                client_postal_code: $contact->client->postal_code
            );

            //if stripe is enabled, we want to update the customer at this point.

            return true;
        }

        // TODO: Throw an exception about not being able to update the profile.
        return false;
    }

    private function updateClientDetails(array $data): bool
    {
        $client = [];
        $contact = [];


        MultiDB::setDb($this->db);
        // $_contact = ClientContact::withTrashed()->with('client')->find($this->contact_id);

        $_contact = ClientContact::withTrashed()->with(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }])->find($this->contact_id);


        foreach ($data as $field => $value) {
            if (Str::startsWith($field, 'client_')) {
                $client[$this->mappings[$field]] = $value;
            }

            if (Str::startsWith($field, 'contact_')) {
                $contact[$this->mappings[$field]] = $value;
            }
        }


        $_contact->first_name = $this->contact_first_name;
        $_contact->last_name = $this->contact_last_name;
        $_contact->client->name = $this->client_name;
        $_contact->email = $this->contact_email;
        $_contact->client->phone = $this->client_phone;
        $_contact->client->address1 = $this->client_address_line_1;
        $_contact->client->city  = $this->client_city;
        $_contact->client->state = $this->client_state;
        $_contact->client->country_id = $this->client_country_id;
        $_contact->client->postal_code = $this->client_postal_code;
        $_contact->client->shipping_address1 = $this->client_shipping_address_line_1;
        $_contact->client->shipping_city = $this->client_shipping_city;
        $_contact->client->shipping_state = $this->client_shipping_state;
        $_contact->client->shipping_postal_code = $this->client_shipping_postal_code;
        $_contact->client->shipping_country_id = $this->client_shipping_country_id;
        $_contact->client->custom_value1 = $this->client_custom_value1;
        $_contact->client->custom_value2 = $this->client_custom_value2;
        $_contact->client->custom_value3 = $this->client_custom_value3;
        $_contact->client->custom_value4 = $this->client_custom_value4;
        $_contact->push();


        $contact_update = $_contact
            ->fill($contact)
            ->push();

        $client_update = $_contact->client
            ->fill($client)
            ->push();

        if ($_contact) {
            /** @var \App\Models\CompanyGateway $cg */
            $cg = CompanyGateway::find($this->company_gateway_id);

            if ($cg && $cg->update_details) {
                $payment_gateway = $cg->driver($_contact->client)->init();

                if (method_exists($payment_gateway, "updateCustomer")) {
                    $payment_gateway->updateCustomer();
                }
            }

            return true;
        }

        return false;
    }

    public function checkFields()
    {
        MultiDB::setDb($this->db);
        $_contact = ClientContact::withTrashed()->find($this->contact_id);

        foreach ($this->fields as $index => $field) {
            $_field = $this->mappings[$field['name']];

            if (Str::startsWith($field['name'], 'client_')) {
                if (empty($_contact->client->{$_field})
                   || is_null($_contact->client->{$_field})
                ) {
                    // $this->show_form = true;
                    $this->unfilled_fields++;
                } else {
                    // $this->fields[$index]['filled'] = true;
                }
            }

            if (Str::startsWith($field['name'], 'contact_')) {
                if (empty($_contact->{$_field}) || is_null($_contact->{$_field}) || str_contains($_contact->{$_field}, '@example.com')) {
                    $this->unfilled_fields++;
                } else {
                    // $this->fields[$index]['filled'] = true;
                }
            }
        }

        if ($this->unfilled_fields === 0 && (!$this->company_gateway->always_show_required_fields || $this->is_subscription)) {
            $this->dispatch(
                'passed-required-fields-check',
                client_postal_code: $_contact->client->postal_code
            );
        }

    }

    public function showCopyBillingCheckbox(): bool
    {
        $fields = [];

        collect($this->fields)->map(function ($field) use (&$fields) {
            if (! array_key_exists('filled', $field)) {
                $fields[] = $field['name'];
            }
        });

        foreach ($fields as $field) {
            if (Str::startsWith($field, 'client_shipping')) {
                return true;
            }
        }

        return false;
    }

    public function handleCopyBilling(): void
    {

        MultiDB::setDb($this->db);
        $_contact = ClientContact::withTrashed()->find($this->contact_id);

        $this->dispatch(
            'update-shipping-data',
            client_shipping_address_line_1: $_contact->client->address1,
            client_shipping_address_line_2: $_contact->client->address2,
            client_shipping_city: $_contact->client->city,
            client_shipping_state: $_contact->client->state,
            client_shipping_postal_code: $_contact->client->postal_code,
            client_shipping_country_id: $_contact->client->country_id,
        );
    }

    public function render()
    {
        return render('components.livewire.required-client-info');
    }
}

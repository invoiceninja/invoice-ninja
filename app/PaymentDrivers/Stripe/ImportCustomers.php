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

namespace App\PaymentDrivers\Stripe;

use App\Exceptions\StripeConnectFailure;
use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Models\Client;
use App\Models\ClientGatewayToken;
use App\Models\Country;
use App\Models\Currency;
use App\PaymentDrivers\StripePaymentDriver;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Database\QueryException;
use Stripe\Customer;

class ImportCustomers
{
    use MakesHash;
    use GeneratesCounter;

    /** @var StripePaymentDriver */
    public $stripe;

    private bool $completed = true;

    public $update_payment_methods;

    public function __construct(StripePaymentDriver $stripe)
    {
        $this->stripe = $stripe;
    }

    public function run()
    {
        $this->stripe->init();

        $this->update_payment_methods = new UpdatePaymentMethods($this->stripe);

        if (Ninja::isHosted() && strlen($this->stripe->company_gateway->getConfigField('account_id')) < 1) {
            throw new StripeConnectFailure('Stripe Connect has not been configured');
        }

        $starting_after = null;

        do {
            $customers = Customer::all(['limit' => 100, 'starting_after' => $starting_after], $this->stripe->stripe_connect_auth);

            foreach ($customers as $customer) {
                $this->addCustomer($customer);
            }

            $starting_after = isset(end($customers->data)['id']) ? end($customers->data)['id'] : false;

            if (!$starting_after) {
                break;
            }
        } while ($customers->has_more);
    }

    public function addCustomer(Customer $customer)
    {
        $account = $this->stripe->company_gateway->company->account;

        if (Ninja::isHosted() && ! $account->isPaidHostedClient() && Client::query()->where('company_id', $this->stripe->company_gateway->company_id)->count() > config('ninja.quotas.free.clients')) {
            return;
        }

        $existing_customer_token = $this->stripe
                                  ->company_gateway
                                  ->client_gateway_tokens()
                                  ->where('gateway_customer_reference', $customer->id)
                                  ->first();

        if ($existing_customer_token) {
            nlog("Skipping - Customer exists: {$customer->email} just updating payment methods");
            $this->update_payment_methods->updateMethods($customer, $existing_customer_token->client);
        }

        if ($customer->email && $this->stripe->company_gateway->company->client_contacts()->where('email', $customer->email)->exists()) {
            nlog("Customer exists: {$customer->email} just updating payment methods");

            $this->stripe->company_gateway->company->client_contacts()->where('email', $customer->email)->each(function ($contact) use ($customer) {
                $this->update_payment_methods->updateMethods($customer, $contact->client);
            });

            return;
        }

        $client = ClientFactory::create($this->stripe->company_gateway->company_id, $this->stripe->company_gateway->user_id);

        if ($customer->address) {
            $client->address1 = $customer->address->line1 ? $customer->address->line1 : '';
            $client->address2 = $customer->address->line2 ? $customer->address->line2 : '';
            $client->city = $customer->address->city ? $customer->address->city : '';
            $client->state = $customer->address->state ? $customer->address->state : '';
            $client->phone = $customer->phone ?? '';

            if ($customer->address->country) {
                $country = Country::query()->where('iso_3166_2', $customer->address->country)->first();

                if ($country) {
                    $client->country_id = $country->id;
                }
            }
        }

        if ($customer->currency) {
            $currency = Currency::query()->where('code', $customer->currency)->first();

            if ($currency) {
                $settings = $client->settings;
                $settings->currency_id = (string) $currency->id;
                $client->settings = $settings;
            }

        } else {

            $settings = $client->settings;
            $settings->currency_id = (string) $this->stripe->company_gateway->company->settings->currency_id;
            $client->settings = $settings;

        }

        $client->name = $customer->name ? $customer->name : $customer->email;

        if (! isset($client->number) || empty($client->number)) {
            $x = 1;

            do {
                try {
                    $client->number = $this->getNextClientNumber($client);
                    $client->saveQuietly();

                    $this->completed = false;
                } catch (QueryException $e) {
                    $x++;

                    if ($x > 10) {
                        $this->completed = false;
                    }
                }
            } while ($this->completed);
        } else {
            $client->save();
        }

        $contact = ClientContactFactory::create($client->company_id, $client->user_id);
        $contact->client_id = $client->id;
        $contact->first_name = $client->name ?: '';
        $contact->phone = $client->phone ?: '';
        $contact->email = $customer->email ?: '';
        $contact->save();

        $this->update_payment_methods->updateMethods($customer, $client);
    }

    public function importCustomer($customer_id)
    {
        $this->stripe->init();

        $this->update_payment_methods = new UpdatePaymentMethods($this->stripe);

        if (strlen($this->stripe->company_gateway->getConfigField('account_id')) < 1) {
            throw new StripeConnectFailure('Stripe Connect has not been configured');
        }

        $customer = Customer::retrieve($customer_id, $this->stripe->stripe_connect_auth);

        if (! $customer) {
            return;
        }

        foreach ($this->stripe->company_gateway->company->clients as $client) {
            if ($client->present()->email() == $customer->email) {
                $this->update_payment_methods->updateMethods($customer, $client);
            }
        }
    }

    public function match()
    {
        $this->stripe->init();

        $this->update_payment_methods = new UpdatePaymentMethods($this->stripe);

        if (strlen($this->stripe->company_gateway->getConfigField('account_id')) < 1) {
            throw new StripeConnectFailure('Stripe Connect has not been configured');
        }

        foreach ($this->stripe->company_gateway->company->clients as $client) {
            $searchResults = \Stripe\Customer::all([
                'email' => $client->present()->email(),
                'limit' => 2,
                'starting_after' => null,
            ], $this->stripe->stripe_connect_auth);

            // nlog(count($searchResults));

            if (count($searchResults) == 1) {
                $cgt = ClientGatewayToken::query()->where('gateway_customer_reference', $searchResults->data[0]->id)->where('company_id', $this->stripe->company_gateway->company->id)->exists();

                if (! $cgt) {
                    nlog('customer '.$searchResults->data[0]->id.' does not exist.');

                    $this->update_payment_methods->updateMethods($searchResults->data[0], $client); //@phpstan-ignore-line
                }
            }
        }
    }
}

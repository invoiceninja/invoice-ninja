<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers;

use App\DataMapper\ClientSettings;
use App\Models\Client;
use App\Models\SystemLog;
use Illuminate\Support\Arr;
use App\Models\GatewayType;
use App\Models\ClientContact;
use App\Utils\Traits\MakesHash;
use App\Jobs\Util\SystemLogger;
use App\PaymentDrivers\BaseDriver;
use App\Models\ClientGatewayToken;
use App\PaymentDrivers\Rotessa\PaymentMethod as Acss;
use App\PaymentDrivers\Rotessa\PaymentMethod as BankTransfer;
use Illuminate\Support\Facades\Http;

class RotessaPaymentDriver extends BaseDriver
{
    use MakesHash;

    public $refundable = false;

    public $token_billing = true;

    public $can_authorise_credit_card = true;

    public $payment_method;

    public static $methods = [
        GatewayType::BANK_TRANSFER => BankTransfer::class,
        GatewayType::ACSS => Acss::class,
    ];

    public function init(): self
    {
        return $this;
    }

    public function gatewayTypes(): array
    {
        $types = [];

        /*
        // TODO: needs to test with US test account
        if ($this->client
         && $this->client->currency()
         && in_array($this->client->currency()->code, ['USD'])
         && isset($this->client->country)
         && in_array($this->client->country->iso_3166_2, ['US'])) {
             $types[] = GatewayType::BANK_TRANSFER;
         }*/

        if ($this->client
            && $this->client->currency()
            && in_array($this->client->currency()->code, ['CAD'])
            && isset($this->client->country)
            && in_array($this->client->country->iso_3166_2, ['CA'])) {
            $types[] = GatewayType::ACSS;
        }

        return $types;
    }


    public function setPaymentMethod($payment_method_id)
    {
        $class = self::$methods[$payment_method_id];
        $this->payment_method = new $class($this);

        return $this;
    }

    public function authorizeView(array $data)
    {
        return $this->payment_method->authorizeView($data);
    }

    public function authorizeResponse($request)
    {
        return $this->payment_method->authorizeResponse($request);
    }

    public function processPaymentView(array $data)
    {
        return $this->payment_method->paymentView($data);
    }

    public function processPaymentResponse($request)
    {
        return $this->payment_method->paymentResponse($request);
    }

    public function importCustomers()
    {

        try {
            $result = $this->gatewayRequest('get', 'customers', []); //Rotessa customers

            if ($result->failed()) {
                $result->throw();
            }

            $customers = collect($result->json())->unique('email'); //Rotessa customer emails

            $client_emails = $customers->pluck('email')->all();

            $company_id = $this->company_gateway->company->id;
            // get existing customers
            $client_contacts = ClientContact::where('company_id', $company_id)
                                            ->whereIn('email', $client_emails)
                                            ->whereHas('client', function ($q) {
                                                $q->where('is_deleted', false);
                                            })
                                            ->whereNull('deleted_at')
                                            ->get();

            $client_contacts = $client_contacts->map(function ($item, $key) use ($customers) {
                return array_merge($customers->firstWhere("email", $item->email), ['custom_identifier' => $item->client->number, 'identifier' => $item->client->number, 'client_id' => $item->client->id ]);
            });

            // create payment methods
            collect($client_contacts)->each(
                function ($contact) {

                    $contact = (object)$contact;

                    $result = $this->gatewayRequest("get", "customers/{$contact->id}");
                    $result = $result->json();

                    $this->client = Client::query()->find($contact->client_id);

                    $customer = array_merge($result, ['id' => $contact->id, 'custom_identifier' => $contact->custom_identifier ]);

                    $this->findOrCreateCustomer($customer);

                }
            );

            // create new clients from rotessa customers
            $client_emails = $client_contacts->pluck('email')->all();

            $client_contacts = $customers->filter(function ($value, $key) use ($client_emails) {
                return !in_array(((object) $value)->email, $client_emails);
            })->each(function ($customer) use ($company_id) {

                $customer = $this->gatewayRequest("get", "customers/{$customer['id']}")->json();

                $settings = ClientSettings::defaults();
                $settings->currency_id = $this->company_gateway->company->getSetting('currency_id');
                $customer = (object)$customer;
                $client = (\App\Factory\ClientFactory::create($this->company_gateway->company_id, $this->company_gateway->user_id))->fill(
                    [
                        'address1' => $customer->address['address_1'] ?? '',
                        'address2' => $customer->address['address_2'] ?? '',
                        'city' => $customer->address['city'] ?? '',
                        'postal_code' => $customer->address['postal_code'] ?? '',
                        'state' => $customer->address['province_code'] ?? '',
                        'country_id' => empty($customer->transit_number) ? 840 : 124,
                        'routing_id' => empty(($r = $customer->routing_number)) ? null : $r,
                        "number" => str_pad($customer->account_number, 3, '0', STR_PAD_LEFT),
                        "settings" => $settings,
                    ]
                );
                $client->saveQuietly();
                $contact = (\App\Factory\ClientContactFactory::create($company_id, $this->company_gateway->user_id))->fill([
                    "first_name" => substr($customer->name, 0, stripos($customer->name, " ")),
                    "last_name" => substr($customer->name, stripos($customer->name, " ")),
                    "email" => $customer->email,
                    "phone" => $customer->phone,
                    "is_primary"  => true,
                    "send_email" => true,
                ]);
                $client->contacts()->saveMany([$contact]);
                $contact = $client->contacts()->first();
                $this->client = $client;

            });
        } catch (\Throwable $th) {
            $data = [
                 'transaction_reference' => null,
                 'transaction_response' => $th->getMessage(),
                 'success' => false,
                 'description' => $th->getMessage(),
                 'code' => (int) $th->getCode()
             ];
            SystemLogger::dispatch(['server_response' => $th->getMessage(), 'data' => $data], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, SystemLog::TYPE_ROTESSA, $this->company_gateway->client, $this->company_gateway->company);

            throw $th;
        }

        return true;
    }

    public function findOrCreateCustomer(array $data)
    {

        $result = null;
        try {

            $existing = ClientGatewayToken::query()
                ->where('company_gateway_id', $this->company_gateway->id)
                ->where('client_id', $this->client->id)
                ->where('is_deleted', 0)
                ->where('gateway_customer_reference', Arr::only($data, 'id'))
                ->exists();

            if ($existing) {
                return true;
            }

            if (!isset($data['id'])) {

                $result = $this->gatewayRequest('post', 'customers', $data);

                if ($result->failed()) {
                    $result->throw();
                }

                $data = $result->json();
                nlog($data);
            }

            $payment_method_id = GatewayType::ACSS;

            $gateway_token = $this->storeGatewayToken([
                'payment_meta' => ['brand' => 'Bank Transfer', 'last4' => substr($data['account_number'], -4), 'type' => GatewayType::ACSS ],
                'token' => join(".", Arr::only($data, ['id','custom_identifier'])),
                'payment_method_id' => $payment_method_id ,
            ], [
                'gateway_customer_reference' => $data['id'],
                'routing_number' => Arr::has($data, 'routing_number') ? $data['routing_number'] : $data['transit_number']
            ]);

            return $data['id'];


        } catch (\Throwable $th) {
            $data = [
                'transaction_reference' => null,
                'transaction_response' => $th->getMessage(),
                'success' => false,
                'description' => $th->getMessage(),
                'code' => 500
            ];

            SystemLogger::dispatch(['server_response' => $data, 'data' => []], SystemLog::CATEGORY_GATEWAY_RESPONSE, SystemLog::EVENT_GATEWAY_FAILURE, 880, $this->client, $this->company_gateway->company);

            try {
                $errors = explode("422:", $th->getMessage())[1];
            } catch (\Exception) {
                $errors = 'Unknown error occured';
            }

            throw new \Exception($errors, $th->getCode());
        }
    }

    public function gatewayRequest($verb, $uri, $payload = [])
    {
        $r = Http::withToken($this->company_gateway->getConfigField('apiKey'))
                ->{$verb}($this->getUrl().$uri, $payload);

        nlog($r->body());

        return $r;
    }

    private function getUrl(): string
    {
        return $this->company_gateway->getConfigField('testMode') ? 'https://sandbox-api.rotessa.com/v1/' : 'https://api.rotessa.com/v1/';
    }

    public function processPaymentViewData(array $data): array
    {
        return $this->payment_method->paymentData($data);
    }

    public function livewirePaymentView(array $data): string
    {
        return $this->payment_method->livewirePaymentView($data);
    }
}

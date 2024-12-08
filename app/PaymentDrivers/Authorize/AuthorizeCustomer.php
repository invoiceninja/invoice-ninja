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

namespace App\PaymentDrivers\Authorize;

use App\Factory\ClientContactFactory;
use App\Factory\ClientFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\PaymentDrivers\AuthorizePaymentDriver;
use Illuminate\Support\Facades\Cache;
use net\authorize\api\contract\v1\GetCustomerProfileIdsRequest;
use net\authorize\api\contract\v1\GetCustomerProfileRequest;
use net\authorize\api\controller\GetCustomerProfileController;
use net\authorize\api\controller\GetCustomerProfileIdsController;

/**
 * Class AuthorizeCustomer.
 */
class AuthorizeCustomer
{
    public $authorize;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    private function getCustomerProfileIds()
    {
        // Get all existing customer profile ID's
        $request = new GetCustomerProfileIdsRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $controller = new GetCustomerProfileIdsController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());
        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            return $response->getIds();
        } else {
            return [];

            nlog("GetCustomerProfileId's ERROR :  Invalid response");
            $errorMessages = $response->getMessages()->getMessage();
            nlog('Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText());
        }
    }

    private function getCustomerProfile($customer_profile_id)
    {
        $request = new GetCustomerProfileRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setCustomerProfileId($customer_profile_id);
        $controller = new GetCustomerProfileController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());
        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            $profileSelected = $response->getProfile();
            $paymentProfilesSelected = $profileSelected->getPaymentProfiles();

            return [
                'email' => $profileSelected->getEmail(),
                'payment_profiles' => $paymentProfilesSelected,
                'error' => '',
            ];
        } else {
            nlog('ERROR :  GetCustomerProfile: Invalid response');
            $errorMessages = $response->getMessages()->getMessage();
            nlog('Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText());

            return [
                'profile' => null,
                'payment_profiles' => null,
                'error' => $errorMessages[0]->getCode().'  '.$errorMessages[0]->getText(),
            ];
        }
    }

    public function importCustomers()
    {
        $auth_customers = $this->getCustomerProfileIds();
        $company = $this->authorize->company_gateway->company;
        $user = $company->owner();

        foreach ($auth_customers as $gateway_customer_reference) {
            $profile = $this->getCustomerProfile($gateway_customer_reference);

            //if the profile ID already exists in ClientGatewayToken we continue else - add.
            if ($client_gateway_token = ClientGatewayToken::where('company_id', $company->id)->where('gateway_customer_reference', $gateway_customer_reference)->first()) {
                // nlog("found client");
                $client = $client_gateway_token->client;
            } elseif ($client_contact = ClientContact::where('company_id', $company->id)->where('email', $profile['email'])->first()) {
                $client = $client_contact->client;
                // nlog("found client through contact");
            } else {
                // nlog("creating client");

                $first_payment_profile = &$profile['payment_profiles'][0];

                if (! $first_payment_profile) {
                    continue;
                }

                $client = ClientFactory::create($company->id, $user->id);
                $billTo = $first_payment_profile->getBillTo();
                $client->address1 = $billTo->getAddress();
                $client->city = $billTo->getCity();
                $client->state = $billTo->getState();
                $client->postal_code = $billTo->getZip();
                $client->country_id = $billTo->getCountry() ? $this->getCountryCode($billTo->getCountry()) : $company->settings->country_id;
                $client->save();

                $client_contact = ClientContactFactory::create($company->id, $user->id);
                $client_contact->client_id = $client->id;
                $client_contact->first_name = $billTo->getFirstName();
                $client_contact->last_name = $billTo->getLastName();
                $client_contact->email = $profile['email'];
                $client_contact->phone = $billTo->getPhoneNumber();
                $client_contact->save();
            }

            if ($client && is_array($profile['payment_profiles'])) {
                $this->authorize->setClient($client);

                foreach ($profile['payment_profiles'] as $payment_profile) {
                    $token_exists = ClientGatewayToken::where('company_id', $company->id)
                                                      ->where('token', $payment_profile->getCustomerPaymentProfileId())
                                                      ->where('gateway_customer_reference', $gateway_customer_reference)
                                                      ->exists();
                    if ($token_exists) {
                        continue;
                    }

                    //                    $expiry = $payment_profile->getPayment()->getCreditCard()->getExpirationDate();

                    $payment_meta = new \stdClass();
                    $payment_meta->exp_month = 'xx';
                    $payment_meta->exp_year = 'xx';
                    $payment_meta->brand = (string) $payment_profile->getPayment()->getCreditCard()->getCardType();
                    $payment_meta->last4 = (string) $payment_profile->getPayment()->getCreditCard()->getCardNumber();
                    $payment_meta->type = GatewayType::CREDIT_CARD;

                    $data['payment_method_id'] = GatewayType::CREDIT_CARD;
                    $data['payment_meta'] = $payment_meta;
                    $data['token'] = $payment_profile->getCustomerPaymentProfileId();
                    $additional['gateway_customer_reference'] = $gateway_customer_reference;

                    $this->authorize->storeGatewayToken($data, $additional);
                }
            }
        }
    }

    private function getCountryCode($country_code)
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        $country = $countries->first(function ($item) use ($country_code) {
            return $item->iso_3166_2 == $country_code || $item->iso_3166_3 == $country_code;
        });

        return (string) $country->id;
    }
}

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

use App\Exceptions\GenericPaymentDriverFailure;
use App\Exceptions\PaymentFailed;
use App\Models\GatewayType;
use App\PaymentDrivers\AuthorizePaymentDriver;
use net\authorize\api\contract\v1\CreateCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\CustomerAddressType;
use net\authorize\api\contract\v1\CustomerPaymentProfileType;
use net\authorize\api\contract\v1\DeleteCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\GetCustomerPaymentProfileRequest;
use net\authorize\api\contract\v1\OpaqueDataType;
use net\authorize\api\contract\v1\PaymentType;
use net\authorize\api\controller\CreateCustomerPaymentProfileController;
use net\authorize\api\controller\DeleteCustomerPaymentProfileController;
use net\authorize\api\controller\GetCustomerPaymentProfileController;
use stdClass;

/**
 * Class AuthorizePaymentMethod.
 */
class AuthorizePaymentMethod
{
    public $authorize;

    public $payment_method;

    private $payment_method_id;

    public function __construct(AuthorizePaymentDriver $authorize)
    {
        $this->authorize = $authorize;
    }

    public function authorizeView()
    {
        if ($this->authorize->payment_method instanceof AuthorizeCreditCard) {
            $this->payment_method_id = GatewayType::CREDIT_CARD;

            return $this->authorizeCreditCard();
        }

        // case GatewayType::BANK_TRANSFER:
        //     return $this->authorizeBankTransfer();
        //     break;
    }

    public function authorizeResponseView($request)
    {
        $data = $request->all();

        $this->payment_method_id = $data['method'];

        switch ($this->payment_method_id) {
            case GatewayType::CREDIT_CARD:
                return $this->authorizeCreditCardResponse($data);
                break;
            case GatewayType::BANK_TRANSFER:
                return $this->authorizeBankTransferResponse($data);
                break;

            default:
                // code...
                break;
        }
    }

    public function authorizeCreditCard()
    {
        $data['gateway'] = $this->authorize;
        $data['public_client_id'] = $this->authorize->init()->getPublicClientKey();
        $data['api_login_id'] = $this->authorize->company_gateway->getConfigField('apiLoginId');

        return render('gateways.authorize.credit_card.authorize', $data);
    }

    public function authorizeBankTransfer()
    {
    }

    public function authorizeCreditCardResponse($data)
    {
        $client_profile_id = null;

        if ($client_gateway_token = $this->authorize->findClientGatewayRecord()) {
            $payment_profile = $this->addPaymentMethodToClient($client_gateway_token->gateway_customer_reference, $data);
            $gateway_customer_reference = $client_gateway_token->gateway_customer_reference;
        } else {
            $gateway_customer_reference = (new AuthorizeCreateCustomer($this->authorize, $this->authorize->client))->create($data);
            $payment_profile = $this->addPaymentMethodToClient($gateway_customer_reference, $data);

        }

        $this->createClientGatewayToken($payment_profile, $gateway_customer_reference);

        return redirect()->route('client.payment_methods.index');
    }

    public function authorizeBankTransferResponse($data)
    {
    }

    public function createClientGatewayToken($payment_profile, $gateway_customer_reference)
    {
        $data = [];
        $additonal = [];

        $data['token'] = $payment_profile->getPaymentProfile()->getCustomerPaymentProfileId();
        $data['payment_method_id'] = $this->payment_method_id;
        $data['payment_meta'] = $this->buildPaymentMethod($payment_profile);
        $data['payment_method_id'] = GatewayType::CREDIT_CARD;

        $additional['gateway_customer_reference'] = $gateway_customer_reference;

        $this->authorize->storeGatewayToken($data, $additional);
    }

    public function buildPaymentMethod($payment_profile)
    {
        $payment_meta = new stdClass();
        $payment_meta->exp_month = 'xx';
        $payment_meta->exp_year = 'xx';
        $payment_meta->brand = (string) $payment_profile->getPaymentProfile()->getPayment()->getCreditCard()->getCardType();
        $payment_meta->last4 = (string) $payment_profile->getPaymentProfile()->getPayment()->getCreditCard()->getCardNumber();
        $payment_meta->type = $this->payment_method;

        return $payment_meta;
    }

    public function addPaymentMethodToClient($gateway_customer_reference, $data)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $this->authorize->init();

        // Set the transaction's refId
        $refId = 'ref'.time();

        // Set the payment data for the payment profile to a token obtained from Accept.js
        $op = new OpaqueDataType();
        $op->setDataDescriptor($data['dataDescriptor']);
        $op->setDataValue($data['dataValue']);
        $paymentOne = new PaymentType();
        $paymentOne->setOpaqueData($op);

        $contact = $this->authorize->client->primary_contact()->first() ?: $this->authorize->client->contacts()->first();

        $billto = false;

        if ($contact) {
            // Create the Bill To info for new payment type
            $billto = new CustomerAddressType();
            $billto->setFirstName(substr($contact->present()->first_name(), 0, 50));
            $billto->setLastName(substr($contact->present()->last_name(), 0, 50));
            $billto->setCompany(substr($this->authorize->client->present()->name(), 0, 50));
            $billto->setAddress(substr($this->authorize->client->address1, 0, 60));
            $billto->setCity(substr($this->authorize->client->city, 0, 40));
            $billto->setState(substr($this->authorize->client->state, 0, 40));
            $billto->setZip(substr($this->authorize->client->postal_code, 0, 20));

            if (isset($contact->email) && str_contains($contact->email, '@')) {
                $billto->setEmail($contact->email);
            }

            if ($this->authorize->client->country_id) {
                $billto->setCountry($this->authorize->client->country->name);
            }

            $billto->setPhoneNumber(substr($this->authorize->client->phone, 0, 20));
        }

        // Create a new Customer Payment Profile object
        $paymentprofile = new CustomerPaymentProfileType();
        $paymentprofile->setCustomerType('individual');

        if ($billto) {
            $paymentprofile->setBillTo($billto);
        }

        $paymentprofile->setPayment($paymentOne);
        $paymentprofile->setDefaultPaymentProfile(true);
        $paymentprofiles[] = $paymentprofile;

        // Assemble the complete transaction request
        $paymentprofilerequest = new CreateCustomerPaymentProfileRequest();
        $paymentprofilerequest->setMerchantAuthentication($this->authorize->merchant_authentication);

        // Add an existing profile id to the request
        $paymentprofilerequest->setCustomerProfileId($gateway_customer_reference);
        $paymentprofilerequest->setPaymentProfile($paymentprofile);
        $paymentprofilerequest->setValidationMode('liveMode');

        // Create the controller and get the response
        $controller = new CreateCustomerPaymentProfileController($paymentprofilerequest);
        $response = $controller->executeWithApiResponse($this->authorize->mode());

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            return $this->getPaymentProfile($gateway_customer_reference, $response->getCustomerPaymentProfileId());
        } else {
            $errorMessages = $response->getMessages()->getMessage();

            $message = 'Unable to add customer to Authorize.net gateway';

            if (is_array($errorMessages)) {
                $message = $errorMessages[0]->getCode().'  '.$errorMessages[0]->getText();
            }

            throw new PaymentFailed($message, 500);
        }
    }

    public function getPaymentProfile($gateway_customer_reference, $payment_profile_id)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $this->authorize->init();

        // Set the transaction's refId
        $refId = 'ref'.time();

        //request requires customerProfileId and customerPaymentProfileId
        $request = new GetCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setRefId($refId);
        $request->setCustomerProfileId($gateway_customer_reference);
        $request->setCustomerPaymentProfileId($payment_profile_id);

        $controller = new GetCustomerPaymentProfileController($request);
        $response = $controller->executeWithApiResponse($this->authorize->mode());

        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            return $response;
        } elseif ($response) {
            $errorMessages = $response->getMessages()->getMessage();

            $message = 'Unable to add payment method to Authorize.net gateway';

            if (is_array($errorMessages)) {
                $message = $errorMessages[0]->getCode().'  '.$errorMessages[0]->getText();
            }

            throw new GenericPaymentDriverFailure($message);
        } else {
            throw new GenericPaymentDriverFailure('Error communicating with Authorize.net');
        }
    }

    public function deletePaymentProfile($gateway_customer_reference, $payment_profile_id)
    {
        error_reporting(E_ALL & ~E_DEPRECATED);

        $this->authorize->init();

        // Set the transaction's refId
        $refId = 'ref'.time();

        // Use an existing payment profile ID for this Merchant name and Transaction key

        $request = new DeleteCustomerPaymentProfileRequest();
        $request->setMerchantAuthentication($this->authorize->merchant_authentication);
        $request->setCustomerProfileId($gateway_customer_reference);
        $request->setCustomerPaymentProfileId($payment_profile_id);
        $controller = new DeleteCustomerPaymentProfileController($request);

        $response = $controller->executeWithApiResponse($this->authorize->mode());
        if (($response != null) && ($response->getMessages()->getResultCode() == 'Ok')) {
            nlog('SUCCESS: Delete Customer Payment Profile  SUCCESS  :');
        } else {
            nlog("ERROR :  Delete Customer Payment Profile: Invalid response\n");
            $errorMessages = $response->getMessages()->getMessage();
            nlog('Response : '.$errorMessages[0]->getCode().'  '.$errorMessages[0]->getText()."\n");
        }

        return $response;
    }
}

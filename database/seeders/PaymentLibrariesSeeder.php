<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Database\Seeders;

use App\Models\Gateway;
use App\Models\GatewayType;
use App\Utils\Ninja;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PaymentLibrariesSeeder extends Seeder
{
    public function run()
    {
        Model::unguard();

        // Generate a random callback secret for Blockonomics users
        $callbackSecret = Str::random(32);

        $gateways = [
            ['id' => 1, 'name' => 'Authorize.Net', 'provider' => 'Authorize', 'sort_order' => 5, 'key' => '3b6621f970ab18887c4f6dca78d3f8bb', 'fields' => '{"apiLoginId":"","transactionKey":"","testMode":false,"developerMode":false,"liveEndpoint":"https:\/\/api2.authorize.net\/xml\/v1\/request.api","developerEndpoint":"https:\/\/apitest.authorize.net\/xml\/v1\/request.api"}
'],
            ['id' => 2, 'name' => 'CardSave', 'provider' => 'CardSave', 'key' => '46c5c1fed2c43acf4f379bae9c8b9f76', 'fields' => '{"merchantId":"","password":""}
'],
            ['id' => 3, 'name' => 'Eway Rapid', 'provider' => 'Eway', 'is_offsite' => true, 'key' => '944c20175bbe6b9972c05bcfe294c2c7', 'fields' => '{"apiKey":"","password":"","publicApiKey":"","testMode":false}'],
            ['id' => 4, 'name' => 'FirstData Connect', 'provider' => 'FirstData_Connect', 'key' => '4e0ed0d34552e6cb433506d1ac03a418', 'fields' => '{"storeId":"","sharedSecret":"","testMode":false}'],
            ['id' => 5, 'name' => 'Migs ThreeParty', 'provider' => 'Migs_ThreeParty', 'key' => '513cdc81444c87c4b07258bc2858d3fa', 'fields' => '{"merchantId":"","merchantAccessCode":"","secureHash":""}'],
            ['id' => 6, 'name' => 'Migs TwoParty', 'provider' => 'Migs_TwoParty', 'key' => '99c2a271b5088951334d1302e038c01a', 'fields' => '{"merchantId":"","merchantAccessCode":"","secureHash":""}'],
            ['id' => 7, 'name' => 'Mollie', 'provider' => 'Mollie', 'is_offsite' => true, 'sort_order' => 8, 'key' => '1bd651fb213ca0c9d66ae3c336dc77e8', 'fields' => '{"apiKey":"","profileId":"","testMode":false}'],
            ['id' => 8, 'name' => 'MultiSafepay', 'provider' => 'MultiSafepay', 'key' => 'c3dec814e14cbd7d86abd92ce6789f8c', 'fields' => '{"accountId":"","siteId":"","siteCode":"","testMode":false}'],
            ['id' => 9, 'name' => 'Netaxept', 'provider' => 'Netaxept', 'key' => '070dffc5ca94f4e66216e44028ebd52d', 'fields' => '{"merchantId":"","password":"","testMode":false}'],
            ['id' => 10, 'name' => 'NetBanx', 'provider' => 'NetBanx', 'key' => '334d419939c06bd99b4dfd8a49243f0f', 'fields' => '{"accountNumber":"","storeId":"","storePassword":"","testMode":false}'],
            ['id' => 11, 'name' => 'PayFast', 'provider' => 'PayFast', 'is_offsite' => true, 'key' => 'd6814fc83f45d2935e7777071e629ef9', 'fields' => '{"merchantId":"","merchantKey":"","pdtKey":"","passphrase":"","testMode":false}'],
            ['id' => 12, 'name' => 'Payflow Pro', 'provider' => 'Payflow_Pro', 'key' => '0d97c97d227f91c5d0cb86d01e4a52c9', 'fields' => '{"username":"","password":"","vendor":"","partner":"","testMode":false}'],
            ['id' => 13, 'name' => 'PaymentExpress PxPay', 'provider' => 'PaymentExpress_PxPay', 'key' => 'a66b7062f4c8212d2c428209a34aa6bf', 'fields' => '{"username":"","password":"","pxPostUsername":"","pxPostPassword":"","testMode":false}', 'default_gateway_type_id' => GatewayType::PAYPAL],
            ['id' => 14, 'name' => 'PaymentExpress PxPost', 'provider' => 'PaymentExpress_PxPost', 'key' => '7e6fc08b89467518a5953a4839f8baba', 'fields' => '{"username":"","password":"","testMode":false}', 'default_gateway_type_id' => GatewayType::PAYPAL],
            ['id' => 15, 'name' => 'PayPal Express', 'provider' => 'PayPal_Express', 'is_offsite' => true, 'sort_order' => 4, 'key' => '38f2c48af60c7dd69e04248cbb24c36e', 'fields' => '{"username":"","password":"","signature":"","testMode":false,"solutionType":["Sole","Mark"],"landingPage":["Billing","Login"],"brandName":"","headerImageUrl":"","logoImageUrl":"","borderColor":""}', 'default_gateway_type_id' => GatewayType::PAYPAL],
            ['id' => 16, 'name' => 'PayPal Pro', 'provider' => 'PayPal_Pro', 'key' => '80af24a6a69f5c0bbec33e930ab40665', 'fields' => '{"username":"","password":"","signature":"","testMode":false}', 'default_gateway_type_id' => GatewayType::PAYPAL],
            ['id' => 17, 'name' => 'Pin', 'provider' => 'Pin', 'key' => '0749cb92a6b36c88bd9ff8aabd2efcab', 'fields' => '{"secretKey":"","testMode":false}'],
            ['id' => 18, 'name' => 'SagePay Direct', 'provider' => 'SagePay_Direct', 'key' => '4c8f4e5d0f353a122045eb9a60cc0f2d', 'fields' => '{"vendor":"","testMode":false,"referrerId":""}'],
            ['id' => 19, 'name' => 'SecurePay DirectPost', 'provider' => 'SecurePay_DirectPost', 'key' => '8036a5aadb2bdaafb23502da8790b6a2', 'fields' => '{"merchantId":"","transactionPassword":"","testMode":false,"enable_ach":"","enable_sofort":"","enable_apple_pay":"","enable_alipay":""}'],
            ['id' => 20, 'name' => 'Stripe', 'provider' => 'Stripe', 'sort_order' => 1, 'key' => 'd14dd26a37cecc30fdd65700bfb55b23', 'fields' => '{"publishableKey":"","apiKey":"","appleDomainVerification":""}'],
            ['id' => 21, 'name' => 'TargetPay Direct eBanking', 'provider' => 'TargetPay_Directebanking', 'key' => 'd14dd26a37cdcc30fdd65700bfb55b23', 'fields' => '{"subAccountId":""}'],
            ['id' => 22, 'name' => 'TargetPay Ideal', 'provider' => 'TargetPay_Ideal', 'key' => 'ea3b328bd72d381387281c3bd83bd97c', 'fields' => '{"subAccountId":""}'],
            ['id' => 23, 'name' => 'TargetPay Mr Cash', 'provider' => 'TargetPay_Mrcash', 'key' => 'a0035fc0d87c4950fb82c73e2fcb825a', 'fields' => '{"subAccountId":""}'],
            ['id' => 24, 'name' => 'TwoCheckout', 'provider' => 'TwoCheckout', 'is_offsite' => true, 'key' => '16dc1d3c8a865425421f64463faaf768', 'fields' => '{"accountNumber":"","secretWord":"","testMode":false}'],
            ['id' => 25, 'name' => 'WorldPay', 'provider' => 'WorldPay', 'key' => '43e639234f660d581ddac725ba7bcd29', 'fields' => '{"installationId":"","accountId":"","secretWord":"","callbackPassword":"","testMode":false,"noLanguageMenu":false,"fixContact":false,"hideContact":false,"hideCurrency":false,"signatureFields":"instId:amount:currency:cartId"}'],
            ['id' => 26, 'name' => 'moolah', 'provider' => 'AuthorizeNet_AIM', 'key' => '2f71dc17b0158ac30a7ae0839799e888', 'fields' => '{"apiLoginId":"","transactionKey":"","testMode":false,"developerMode":false,"liveEndpoint":"https:\/\/api2.authorize.net\/xml\/v1\/request.api","developerEndpoint":"https:\/\/apitest.authorize.net\/xml\/v1\/request.api"}'],
            ['id' => 27, 'name' => 'Alipay', 'provider' => 'Alipay_Express', 'key' => '733998ee4760b10f11fb48652571e02c', 'fields' => '{"partner":"","key":"","signType":"MD5","inputCharset":"utf-8","transport":"http","paymentType":1,"itBPay":"1d"}'],
            ['id' => 28, 'name' => 'Buckaroo', 'provider' => 'Buckaroo_CreditCard', 'key' => '6312879223e49c5cf92e194646bdee8f', 'fields' => '{"websiteKey":"","secretKey":"","testMode":false}'],
            ['id' => 29, 'name' => 'Coinbase', 'provider' => 'Coinbase', 'is_offsite' => true, 'key' => '106ef7e7da9062b0df363903b455711c', 'fields' => '{"apiKey":"","secret":"","accountId":""}'],
            ['id' => 30, 'name' => 'DataCash', 'provider' => 'DataCash', 'key' => 'e9a38f0896b5b82d196be3b7020c8664', 'fields' => '{"merchantId":"","password":"","testMode":false}'],
            ['id' => 31, 'name' => 'Pacnet', 'provider' => 'Pacnet', 'key' => '0da4e18ed44a5bd5c8ec354d0ab7b301', 'fields' => '{"username":"","sharedSecret":"","paymentRoutingNumber":"","testMode":false}'],
            ['id' => 32, 'name' => 'Realex', 'provider' => 'Realex_Remote', 'key' => 'd3979e62eb603fbdf1c78fe3a8ba7009', 'fields' => '{"merchantId":"","account":"","secret":"","3dSecure":0}'],
            ['id' => 33, 'name' => 'Sisow', 'provider' => 'Sisow', 'key' => '557d98977e7ec02dfa53de4b69b335be', 'fields' => '{"shopId":"","merchantId":""}'],
            ['id' => 34, 'name' => 'Skrill', 'provider' => 'Skrill', 'is_offsite' => true, 'key' => '54dc60c869a7322d87efbec5c0c25805', 'fields' => '{"email":"","notifyUrl":"","testMode":false}'],
            ['id' => 35, 'name' => 'BitPay', 'provider' => 'BitPay', 'is_offsite' => true, 'sort_order' => 7, 'key' => 'e4a02f0a4b235eb5e9e294730703bb74', 'fields' => '{"apiKey":"","testMode":false}'],
            ['id' => 36, 'name' => 'AGMS', 'provider' => 'Agms', 'key' => '1b3c6f3ccfea4f5e7eadeae188cccd7f', 'fields' => '{"username":"","password":"","apiKey":"","accountNumber":""}'],
            ['id' => 37, 'name' => 'Barclays', 'provider' => 'BarclaysEpdq\Essential', 'key' => '7cba6ce5c125f9cb47ea8443ae671b68', 'fields' => '{"clientId":"","testMode":false,"language":"en_US","callbackMethod":"POST"}'],
            ['id' => 38, 'name' => 'Cardgate', 'provider' => 'Cardgate', 'key' => 'b98cfa5f750e16cee3524b7b7e78fbf6', 'fields' => '{"merchantId":"","language":"nl","apiKey":"","siteId":"","notifyUrl":"","returnUrl":"","cancelUrl":"","testMode":false}'],
            ['id' => 39, 'name' => 'Checkout.com', 'provider' => 'CheckoutCom', 'key' => '3758e7f7c6f4cecf0f4f348b9a00f456', 'fields' => '{"secretApiKey":"","publicApiKey":"","testMode":false,"threeds":false}'],
            ['id' => 40, 'name' => 'Creditcall', 'provider' => 'Creditcall', 'key' => 'cbc7ef7c99d31ec05492fbcb37208263', 'fields' => '{"terminalId":"","transactionKey":"","testMode":false,"verifyCvv":true,"verifyAddress":false,"verifyZip":false}'],
            ['id' => 41, 'name' => 'Cybersource', 'provider' => 'Cybersource', 'key' => 'e186a98d3b079028a73390bdc11bdb82', 'fields' => '{"profileId":"","secretKey":"","accessKey":"","testMode":false}'],
            ['id' => 42, 'name' => 'ecoPayz', 'provider' => 'Ecopayz', 'key' => '761040aca40f685d1ab55e2084b30670', 'fields' => '{"merchantId":"","merchantPassword":"","merchantAccountNumber":"","testMode":false}'],
            ['id' => 43, 'name' => 'Fasapay', 'provider' => 'Fasapay', 'key' => '1b2cef0e8c800204a29f33953aaf3360', 'fields' => ''],
            ['id' => 44, 'name' => 'Komoju', 'provider' => 'Komoju', 'key' => '7ea2d40ecb1eb69ef8c3d03e5019028a', 'fields' => '{"apiKey":"","accountId":"","paymentMethod":"credit_card","testMode":false,"locale":"en"}'],
            ['id' => 45, 'name' => 'Paysafecard', 'provider' => 'Paysafecard', 'key' => '70ab90cd6c5c1ab13208b3cef51c0894', 'fields' => '{"username":"","password":"","testMode":false}'],
            ['id' => 46, 'name' => 'Paytrace', 'provider' => 'Paytrace', 'key' => 'bbd736b3254b0aabed6ad7fda1298c88', 'fields' => '{"username":"","password":"","integratorId":"","testMode":false,"endpoint":"https:\/\/paytrace.com\/api\/default.pay"}'],
            ['id' => 47, 'name' => 'Secure Trading', 'provider' => 'SecureTrading', 'key' => '231cb401487b9f15babe04b1ac4f7a27', 'fields' => '{"siteReference":"","username":"","password":"","applyThreeDSecure":false,"accountType":"ECOM"}'],
            ['id' => 48, 'name' => 'SecPay', 'provider' => 'SecPay', 'key' => 'bad8699d581d9fa040e59c0bb721a76c', 'fields' => '{"mid":"","vpnPswd":"","remotePswd":"","usageType":"","confirmEmail":"","testStatus":"true","mailCustomer":"true","additionalOptions":""}'],
            ['id' => 49, 'name' => 'WePay', 'provider' => 'WePay', 'is_offsite' => false, 'sort_order' => 3, 'key' => '8fdeed552015b3c7b44ed6c8ebd9e992', 'fields' => '{"accountId":"","accessToken":"","type":"goods","testMode":false,"feePayer":"payee"}'],
            ['id' => 50, 'name' => 'Braintree', 'provider' => 'Braintree', 'sort_order' => 3, 'key' => 'f7ec488676d310683fb51802d076d713', 'fields' => '{"merchantId":"","merchantAccountId":"","publicKey":"","privateKey":"","testMode":false,"threeds":false}'],
            ['id' => 51, 'name' => 'FirstData Payeezy', 'provider' => 'FirstData_Payeezy', 'key' => '30334a52fb698046572c627ca10412e8', 'fields' => '{"gatewayId":"","password":"","keyId":"","hmac":"","testMode":false}'],
            ['id' => 52, 'name' => 'GoCardless', 'provider' => 'GoCardless', 'sort_order' => 9, 'is_offsite' => true, 'key' => 'b9886f9257f0c6ee7c302f1c74475f6c', 'fields' => '{"accessToken":"","webhookSecret":"","testMode":true}'],
            ['id' => 53, 'name' => 'PagSeguro', 'provider' => 'PagSeguro', 'key' => 'ef498756b54db63c143af0ec433da803', 'fields' => '{"email":"","token":"","sandbox":false}'],
            ['id' => 54, 'name' => 'PAYMILL', 'provider' => 'Paymill', 'key' => 'ca52f618a39367a4c944098ebf977e1c', 'fields' => '{"apiKey":""}'],
            ['id' => 55, 'name' => 'Custom', 'provider' => 'Custom', 'is_offsite' => true, 'sort_order' => 21, 'key' => '54faab2ab6e3223dbe848b1686490baa', 'fields' => '{"name":"","text":""}'],
            ['id' => 57, 'name' => 'Square', 'provider' => 'Square', 'is_offsite' => false, 'sort_order' => 21, 'key' => '65faab2ab6e3223dbe848b1686490baz', 'fields' => '{"accessToken":"","applicationId":"","locationId":"","signatureKey":"","testMode":false}'],
            ['id' => 58, 'name' => 'Razorpay', 'provider' => 'Razorpay', 'is_offsite' => false, 'sort_order' => 21, 'key' => 'hxd6gwg3ekb9tb3v9lptgx1mqyg69zu9', 'fields' => '{"apiKey":"","apiSecret":""}'],
            ['id' => 59, 'name' => 'Forte', 'provider' => 'Forte', 'is_offsite' => false, 'sort_order' => 21, 'key' => 'kivcvjexxvdiyqtj3mju5d6yhpeht2xs', 'fields' => '{"testMode":false,"apiLoginId":"","apiAccessId":"","secureKey":"","authOrganizationId":"","organizationId":"","locationId":""}'],
            ['id' => 60, 'name' => 'PayPal REST', 'provider' => 'PayPal_Rest', 'key' => '80af24a6a691230bbec33e930ab40665', 'fields' => '{"clientId":"","secret":"","signature":"","testMode":false}'],
            ['id' => 61, 'name' => 'PayPal Platform', 'provider' => 'PayPal_PPCP', 'key' => '80af24a6a691230bbec33e930ab40666', 'fields' => '{"testMode":false}'],
            ['id' => 62, 'name' => 'BTCPay', 'provider' => 'BTCPay', 'key' => 'vpyfbmdrkqcicpkjqdusgjfluebftuva', 'fields' => '{"btcpayUrl":"", "apiKey":"", "storeId":"", "webhookSecret":""}'],
            ['id' => 63, 'name' => 'Rotessa', 'is_offsite' => false, 'sort_order' => 22, 'provider' => 'Rotessa', 'key' => '91be24c7b792230bced33e930ac61676', 'fields' => '{"apiKey":"", "testMode":false}'],
            ['id' => 64, 'name' => 'CBA PowerBoard', 'is_offsite' => false, 'sort_order' => 26, 'provider' => 'CBAPowerBoard', 'key' => 'b67581d804dbad1743b61c57285142ad', 'fields' => '{"publicKey":"", "secretKey":"", "testMode":false, "gatewayId":"", "amex":false, "ausbc":false, "discover":false, "japcb":false, "laser":false, "mastercard":true, "solo":false, "visa":true, "visa_white":false}'],
            ['id' => 65, 'name' => 'Blockonomics', 'is_offsite' => false, 'sort_order' => 27, 'provider' => 'Blockonomics', 'key' => 'wbhf02us6owgo7p4nfjd0ymssdshks4d', 'fields' => '{"apiKey":"", "callbackSecret":"' . $callbackSecret . '"}'],
        ];

        foreach ($gateways as $gateway) {
            $record = Gateway::whereName($gateway['name'])
                ->whereProvider($gateway['provider'])
                ->first();
            if ($record) {
                $record->fill($gateway);
                $record->save();
            } else {
                Gateway::create($gateway);
            }
        }

        Gateway::query()->update(['visible' => 0]);

        Gateway::whereIn('id', [1, 3, 7, 11, 15, 20, 39, 46, 55, 50, 57, 52, 58, 59, 60, 62, 63, 64, 65])->update(['visible' => 1]);

        if (Ninja::isHosted()) {
            Gateway::whereIn('id', [20, 49])->update(['visible' => 0]);
            Gateway::whereIn('id', [56, 61])->update(['visible' => 1]);
        }

        Gateway::all()->each(function ($gateway) {
            $gateway->site_url = $gateway->getHelp();
            $gateway->save();
        });
    }
}

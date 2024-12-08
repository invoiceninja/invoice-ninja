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

namespace App\PaymentDrivers\Factory;

use App\Models\Company;
use App\Models\GatewayType;

class SquareCustomerFactory
{
    /*
{
      "id": "A537H7KAQWSAF8M8EM1Y23E16M",
      "created_at": "2021-10-28T20:19:07.692Z",
      "updated_at": "2024-01-09T20:14:21Z",
      "cards": [
        {
          "id": "ccof:oG9wEmGAvoAnrBGt3GB",
          "card_brand": "VISA",
          "last_4": "5858",
          "exp_month": 10,
          "exp_year": 2023,
          "cardholder_name": "Amelia Earhart",
          "billing_address": {
            "address_line_1": "500 Electric Ave",
            "locality": "New York",
            "administrative_district_level_1": "NY",
            "postal_code": "94103",
            "country": "US"
          }
        },
        {
          "id": "gftc:06c30c2b9772458a9e87b2880ee2ce1a",
          "card_brand": "SQUARE_GIFT_CARD",
          "last_4": "0895",
          "exp_month": 12,
          "exp_year": 2050,
          "billing_address": {
            "postal_code": "94103"
          }
        }
      ],
      "given_name": "Amelia",
      "family_name": "Earhart",
      "email_address": "Amelia.Earhart@example.com",
      "address": {
        "address_line_1": "123 Main St",
        "locality": "Seattle",
        "administrative_district_level_1": "WA",
        "postal_code": "98121",
        "country": "US"
      },
      "phone_number": "1-212-555-4240",
      "note": "a customer on seller account",
      "reference_id": "YOUR_REFERENCE_ID",
      "company_name": "ACME",
      "preferences": {
        "email_unsubscribed": false
      },
      "creation_source": "THIRD_PARTY",
      "segment_ids": [
        "8QJTJCE6AZSN6.REACHABLE",
        "8QJTJCE6AZSN6.CARDS_ON_FILE",
        "gv2:8H24YRM74H2030XWJWP9F0MAEW",
        "gv2:4TR2NFVP8N63D9K1FZ5E62VD78"
      ],
      "version": 4
    },
    */

    public function convertToNinja($customer, Company $company): array
    {
        $cards = [];


        foreach ($customer->getCards() ?? [] as $card) {

            $meta = new \stdClass();
            $meta->exp_month = $card->getExpMonth();
            $meta->exp_year = $card->getExpYear();
            $meta->last4 = $card->getLast4();
            $meta->brand = $card->getCardBrand();
            $meta->type = GatewayType::CREDIT_CARD;

            $cards[] = [
                'token' => $card->getId(),
                'payment_meta' => $meta,
                'payment_method_id' => GatewayType::CREDIT_CARD,
                'gateway_customer_reference' => $customer->getId(),
            ];
        }

        $address = $customer->getAddress();

        return
            collect([
                'name' => $customer->getCompanyName() ?? ($customer->getGivenName() ?? '' ." " . $customer->getFamilyName() ?? ''),
                'contacts' => [
                    [
                        'first_name' => $customer->getGivenName(),
                        'last_name' => $customer->getFamilyName(),
                        'email' => $customer->getEmailAddress(),
                        'phone' => $customer->getPhoneNumber(),
                    ]
                ],
                'currency_id' => $company->settings->currency_id,
                'address1' => $address->getAddressLine1(),
                'address2' => $address->getAddressLine2(),
                'city' => $address->getLocality(),
                'state' => $address->getAdministrativeDistrictLevel1(),
                'postal_code' => $address->getPostalCode(),
                'country_id' => '840',
                'settings' => [
                    'currency_id' => $company->settings->currency_id,
                ],
                'cards' => $cards,
            ])
            ->toArray();

    }

}

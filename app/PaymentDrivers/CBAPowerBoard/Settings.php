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

namespace App\PaymentDrivers\CBAPowerBoard;

use App\Models\GatewayType;
use App\PaymentDrivers\CBAPowerBoardPaymentDriver;
use App\PaymentDrivers\CBAPowerBoard\Models\Gateway;
use App\PaymentDrivers\CBAPowerBoard\Models\Gateways;

class Settings
{
    protected const GATEWAY_CBA = 'MasterCard';
    protected const GATEWAY_AFTERPAY = 'Afterpay';
    protected const GATEWAY_PAYPAL = 'Paypal';
    protected const GATEWAY_ZIP = 'Zipmoney';

    public function __construct(public CBAPowerBoardPaymentDriver $powerboard)
    {
    }

    /**
     * Returns the API response for the gateways
     *
     * @return mixed
     */
    public function getGateways(): mixed
    {
        $r = $this->powerboard->gatewayRequest('/v1/gateways', (\App\Enum\HttpVerb::GET)->value, [], []);

        if ($r->failed()) {
            $r->throw();
        }

        return (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(Gateway::class."[]", $r->object()->resource->data);

    }

    /** We will need to have a process that updates this at intervals */
    /**
     * updateSettings from the API
     *
     * @return self
     */
    public function updateSettings(): self
    {
        $gateways = $this->getGateways();

        $settings = $this->powerboard->company_gateway->getSettings();
        $settings->gateways = $gateways;
        $this->powerboard->company_gateway->setSettings($settings);

        return $this;
    }

    /**
     * getSettings
     *
     * @return mixed
     */
    public function getSettings(): mixed
    {
        return $this->powerboard->company_gateway->getSettings();
    }

    /**
     * Entry point for getting the payment gateway configuration
     *
     * @param  int $gateway_type_id
     * @return mixed
     */
    public function getPaymentGatewayConfiguration(int $gateway_type_id): mixed
    {
        $type = self::GATEWAY_CBA;

        match($gateway_type_id) {
            \App\Models\GatewayType::CREDIT_CARD => $type = self::GATEWAY_CBA,
            default => $type = self::GATEWAY_CBA,
        };

        return $this->getGatewayByType($type);
    }

    /**
     * Returns the CBA gateway object for a given gateway type
     *
     * @param  string $gateway_type_const
     * @return mixed
     */
    private function getGatewayByType(string $gateway_type_const): mixed
    {
        $settings = $this->getSettings();

        if (!property_exists($settings, 'gateways')) {
            $this->updateSettings();
            $settings = $this->getSettings();
        }

        $gateways = (new \App\PaymentDrivers\CBAPowerBoard\Models\Parse())->encode(Gateway::class."[]", $settings->gateways);

        if ($gateway_type_const == self::GATEWAY_CBA && strlen($this->powerboard->company_gateway->getConfigField('gatewayId') ?? '') > 1) {

            return collect($gateways)->first(function (Gateway $gateway) {
                return $gateway->_id == $this->powerboard->company_gateway->getConfigField('gatewayId');
            });

        }

        return collect($gateways)->first(function (Gateway $gateway) use ($gateway_type_const) {
            return $gateway->type == $gateway_type_const;
        });
    }

    /**
     * Returns the CBA gateway ID for a given gateway type
     *
     * @param  int $gateway_type_id
     * @return string
     */
    public function getGatewayId(int $gateway_type_id): string
    {

        $gateway = $this->getPaymentGatewayConfiguration($gateway_type_id);

        return $gateway->_id;
    }
}

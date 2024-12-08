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

namespace App\Models;

/**
 * App\Models\Gateway
 *
 * @property int $id
 * @property string $name
 * @property string $key
 * @property string $provider
 * @property bool $visible
 * @property int $sort_order
 * @property string|null $site_url
 * @property bool $is_offsite
 * @property bool $is_secure
 * @property object|null|string $fields
 * @property string|int $default_gateway_type_id
 * @property int|null $created_at
 * @property int|null $updated_at
 * @property-read mixed $options
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel company()
 * @method static \Illuminate\Database\Eloquent\Builder|StaticModel exclude($columns)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway query()
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereDefaultGatewayTypeId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereFields($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereIsOffsite($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereIsSecure($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereKey($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereProvider($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereSiteUrl($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereSortOrder($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder|Gateway whereVisible($value)
 * @mixin \Eloquent
 */
class Gateway extends StaticModel
{
    protected $casts = [
        'is_offsite' => 'boolean',
        'is_secure' => 'boolean',
        'recommended' => 'boolean',
        'visible' => 'boolean',
        'sort_order' => 'int',
        'updated_at' => 'timestamp',
        'created_at' => 'timestamp',
        'default_gateway_type_id' => 'string',
        // 'fields' => 'json',
        'fields' => 'object',
        'options' => 'array',
    ];

    protected $appends = [
        'options',
    ];

    protected $dateFormat = 'Y-m-d H:i:s.u';

    public function getOptionsAttribute()
    {
        return $this->getMethods();
    }

    // /**
    //  * Test if gateway is custom.
    //  * @return bool TRUE|FALSE
    //  */
    // public function isCustom(): bool
    // {
    //     return in_array($this->id, [62, 67, 68]); //static table ids of the custom gateways
    // }

    public function getHelp()
    {
        $link = '';

        if ($this->id == 1) {
            $link = 'http://reseller.authorize.net/application/?id=5560364';
        } elseif (in_array($this->id, [15, 60, 61])) {
            $link = 'https://www.paypal.com/us/cgi-bin/webscr?cmd=_login-api-run';
        } elseif ($this->id == 24) {
            $link = 'https://www.2checkout.com/referral?r=2c37ac2298';
        } elseif ($this->id == 35) {
            $link = 'https://bitpay.com/dashboard/signup';
        } elseif ($this->id == 18) {
            $link = 'https://applications.sagepay.com/apply/2C02C252-0F8A-1B84-E10D-CF933EFCAA99';
        } elseif ($this->id == 20 || $this->id == 56) {
            $link = 'https://dashboard.stripe.com/account/apikeys';
        } elseif ($this->id == 59) {
            $link = 'https://www.forte.net/';
        } elseif ($this->id == 62) {
            $link = 'https://docs.btcpayserver.org/InvoiceNinja/';
        } elseif ($this->id == 63) {
            $link = 'https://rotessa.com';
        } elseif ($this->id == 64) {
            $link = 'https://help.blockonomics.co/a/solutions/articles/33000291849';
        }

        return $link;
    }

    /**
     * Returns an array of methods and the gatewaytypes possible
     *
     * @return array
     */
    public function getMethods()
    {
        switch ($this->id) {
            case 1:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true]]; //Authorize.net
            case 3:
                return [GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => true]]; //eWay
            case 11:
                return [GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => true]]; //Payfast
            case 7:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']], // Mollie
                    GatewayType::BANK_TRANSFER => ['refund' => false, 'token_billing' => true, 'webhooks' => [' ']],
                    GatewayType::KBC => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']],
                    GatewayType::BANCONTACT => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']],
                    GatewayType::IDEAL => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']],
                ];
            case 15:
                return [
                    GatewayType::PAYPAL => ['refund' => false, 'token_billing' => false],
                ]; //Paypal
            case 20:
            case 56:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true, 'webhooks' => ['payment_intent.succeeded', 'charge.refunded', 'payment_intent.payment_failed']],
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.refunded','charge.succeeded', 'customer.source.updated', 'payment_intent.processing', 'payment_intent.payment_failed', 'charge.failed']],
                    GatewayType::DIRECT_DEBIT => ['refund' => false, 'token_billing' => false, 'webhooks' => ['payment_intent.processing', 'charge.refunded', 'payment_intent.succeeded', 'payment_intent.partially_funded', 'payment_intent.payment_failed']],
                    GatewayType::ALIPAY => ['refund' => false, 'token_billing' => false],
                    GatewayType::APPLE_PAY => ['refund' => false, 'token_billing' => false],
                    GatewayType::BACS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.processing', 'payment_intent.succeeded', 'mandate.updated', 'payment_intent.payment_failed']],
                    GatewayType::SOFORT => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::KLARNA => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::SEPA => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::PRZELEWY24 => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::GIROPAY => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::EPS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::BANCONTACT => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::BECS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::IDEAL => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::ACSS => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed', 'payment_intent.succeeded', 'payment_intent.payment_failed']],
                    GatewayType::FPX => ['refund' => true, 'token_billing' => true, 'webhooks' => ['source.chargeable', 'charge.succeeded', 'charge.refunded', 'charge.failed',]],
                ];
            case 39:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']]]; //Checkout
            case 46:
                return [GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true]]; //Paytrace
            case 49:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true],
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']],
                ]; //WePay
            case 50:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true], //Braintree
                    GatewayType::PAYPAL => ['refund' => true, 'token_billing' => true],
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']],
                ];
            case 57:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true], //Square
                ];
            case 52:
                return [
                    GatewayType::BANK_TRANSFER => ['refund' => false, 'token_billing' => true, 'webhooks' => ['confirmed', 'paid_out', 'failed', 'fulfilled']], // GoCardless
                    GatewayType::DIRECT_DEBIT => ['refund' => false, 'token_billing' => true, 'webhooks' => ['confirmed', 'paid_out', 'failed', 'fulfilled']],
                    GatewayType::SEPA => ['refund' => false, 'token_billing' => true, 'webhooks' => ['confirmed', 'paid_out', 'failed', 'fulfilled']],
                    GatewayType::INSTANT_BANK_PAY => ['refund' => false, 'token_billing' => true, 'webhooks' => ['confirmed', 'paid_out', 'failed', 'fulfilled']],
                ];
            case 58:
                return [
                    GatewayType::HOSTED_PAGE => ['refund' => false, 'token_billing' => false, 'webhooks' => [' ']], // Razorpay
                ];
            case 59:
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true], // Forte
                    GatewayType::BANK_TRANSFER => ['refund' => true, 'token_billing' => true, 'webhooks' => [' ']],
                ];
            case 60:
                return [
                    GatewayType::PAYPAL => ['refund' => false, 'token_billing' => false],
                    GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => false],
                    GatewayType::VENMO => ['refund' => false, 'token_billing' => false],
                    GatewayType::PAYPAL_ADVANCED_CARDS => ['refund' => false, 'token_billing' => true],
                    // GatewayType::SEPA => ['refund' => false, 'token_billing' => false],
                    // GatewayType::BANCONTACT => ['refund' => false, 'token_billing' => false],
                    // GatewayType::EPS => ['refund' => false, 'token_billing' => false],
                    // GatewayType::MYBANK => ['refund' => false, 'token_billing' => false],
                    // GatewayType::PAYLATER => ['refund' => false, 'token_billing' => false],
                    // GatewayType::PRZELEWY24 => ['refund' => false, 'token_billing' => false],
                    // GatewayType::SOFORT => ['refund' => false, 'token_billing' => false],
                ]; //Paypal
            case 61:
                return [
                    GatewayType::PAYPAL => ['refund' => false, 'token_billing' => false],
                    GatewayType::CREDIT_CARD => ['refund' => false, 'token_billing' => false],
                    GatewayType::VENMO => ['refund' => false, 'token_billing' => false],
                    GatewayType::PAYPAL_ADVANCED_CARDS => ['refund' => false, 'token_billing' => true],
                    // GatewayType::SEPA => ['refund' => false, 'token_billing' => false],
                    // GatewayType::BANCONTACT => ['refund' => false, 'token_billing' => false],
                    // GatewayType::EPS => ['refund' => false, 'token_billing' => false],
                    // GatewayType::MYBANK => ['refund' => false, 'token_billing' => false],
                    GatewayType::PAYLATER => ['refund' => false, 'token_billing' => false],
                    // GatewayType::PRZELEWY24 => ['refund' => false, 'token_billing' => false],
                    // GatewayType::SOFORT => ['refund' => false, 'token_billing' => false],
                ]; //Paypal PPCP
            case 62:
                return [
                    GatewayType::CRYPTO => ['refund' => true, 'token_billing' => false, 'webhooks' => ['confirmed', 'paid_out', 'failed', 'fulfilled']],
                ]; //BTCPay
            case 63:
                return [
                    GatewayType::BANK_TRANSFER => [
                        'refund' => false,
                        'token_billing' => true,
                        'webhooks' => [],
                        ],
                    GatewayType::ACSS => ['refund' => false, 'token_billing' => true, 'webhooks' => []]
                ]; // Rotessa
            case 64: //b67581d804dbad1743b61c57285142ad - powerboard
                return [
                    GatewayType::CREDIT_CARD => ['refund' => true, 'token_billing' => true],
                ];
            case 65:
                return [
                    GatewayType::CRYPTO => ['refund' => false, 'token_billing' => false, 'webhooks' => ['confirmed', 'paid_out', 'failed', 'fulfilled']],
                ]; //Blockonomics
            default:
                return [];
        }
    }
}

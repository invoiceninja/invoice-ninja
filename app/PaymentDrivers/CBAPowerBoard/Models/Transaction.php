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

namespace App\PaymentDrivers\CBAPowerBoard\Models;

class Threeds
{
    public function __construct(public ?string $token)
    {
    }
}

class Transaction
{
    public ?Threeds $_3ds;
    public ?string $gateway_specific_code;
    public ?string $gateway_specific_description;
    public ?string $error_message;
    public ?string $error_code;
    public ?string $status_code;
    public ?string $status_code_description;
    public ?string $type;
    public ?string $status;
    public float $amount;
    public ?string $currency;
    public ?string $_id;
    public ?string $created_at;
    public ?string $updated_at;
    public ?string $processed_at;
    public ?string $external_id;
    public ?string $external_reference;
    public ?string $authorization_code;

    public function __construct(
        ?Threeds $_3ds,
        ?string $gateway_specific_code,
        ?string $gateway_specific_description,
        ?string $error_message,
        ?string $error_code,
        ?string $status_code,
        ?string $status_code_description,
        ?string $type,
        ?string $status,
        float $amount,
        ?string $currency,
        ?string $_id,
        ?string $created_at,
        ?string $updated_at,
        ?string $processed_at,
        ?string $external_id,
        ?string $external_reference,
        ?string $authorization_code
    ) {
        $this->_3ds = $_3ds;
        $this->gateway_specific_code = $gateway_specific_code;
        $this->gateway_specific_description = $gateway_specific_description;
        $this->error_message = $error_message;
        $this->error_code = $error_code;
        $this->status_code = $status_code;
        $this->status_code_description = $status_code_description;
        $this->type = $type;
        $this->status = $status;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->_id = $_id;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->processed_at = $processed_at;
        $this->external_id = $external_id;
        $this->external_reference = $external_reference;
        $this->authorization_code = $authorization_code;
    }
}

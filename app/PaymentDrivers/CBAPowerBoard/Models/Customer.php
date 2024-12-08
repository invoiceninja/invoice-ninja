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

class Customer
{
    /** @var ?string */
    public ?string $_id;
    /** @var ?string */
    public ?string $_source_ip_address;
    /** @var ?string */
    public ?string $first_name;
    /** @var ?string */
    public ?string $last_name;
    /** @var ?string */
    public ?string $email;
    /** @var ?string */
    public ?string $reference;
    /** @var ?string */
    public ?string $default_source;
    /** @var ?string */
    public ?string $status;
    /** @var ?bool */
    public ?bool $archived;
    /** @var ?string */
    public ?string $created_at;
    /** @var ?string */
    public ?string $updated_at;
    /** @var ?bool */
    public ?bool $_check_expire_date;
    /** @var ?PaymentSource[] */
    public ?array $payment_sources;
    /** @var ?PaymentSource */
    public ?PaymentSource $payment_source;
    /** @var ?array */
    public ?array $payment_destinations;
    /** @var ?string */
    public ?string $company_id;

    public function __construct(
        ?string $_id,
        ?string $_source_ip_address,
        ?string $first_name,
        ?string $last_name,
        ?string $email,
        ?string $reference,
        ?string $default_source,
        ?string $status,
        ?bool $archived,
        ?string $created_at,
        ?string $updated_at,
        ?bool $_check_expire_date,
        ?array $payment_sources,
        ?array $payment_destinations,
        ?string $company_id,
        ?PaymentSource $payment_source
    ) {
        $this->_id = $_id;
        $this->_source_ip_address = $_source_ip_address;
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
        $this->reference = $reference;
        $this->default_source = $default_source;
        $this->status = $status;
        $this->archived = $archived;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->_check_expire_date = $_check_expire_date;
        $this->payment_sources = $payment_sources;
        $this->payment_destinations = $payment_destinations;
        $this->company_id = $company_id;
        $this->payment_source = $payment_source;
    }
}

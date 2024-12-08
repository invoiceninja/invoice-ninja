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

class Charge
{
    /** @var ?string */
    public ?string $external_id;
    /** @var ?string */
    public ?string $_id;
    /** @var ?string */
    public ?string $created_at;
    /** @var ?string */
    public ?string $updated_at;
    /** @var ?string */
    public ?string $remittance_date;
    /** @var ?string */
    public ?string $company_id;
    /** @var float */
    public float $amount;
    /** @var ?string */
    public ?string $currency;
    /** @var ?int */
    public ?int $__v;
    /** @var Transaction[] */
    public array $transactions;
    /** @var ?bool */
    public ?bool $one_off;
    /** @var ?bool */
    public ?bool $archived;
    /** @var Customer */
    public Customer $customer;
    /** @var ?bool */
    public ?bool $capture;
    /** @var ?string */
    public ?string $status;
    /** @var ?array */
    public ?array $items;

    public function __construct(
        ?string $external_id,
        ?string $_id,
        ?string $created_at,
        ?string $updated_at,
        ?string $remittance_date,
        ?string $company_id,
        float $amount,
        ?string $currency,
        ?int $__v,
        array $transactions,
        ?bool $one_off,
        ?bool $archived,
        Customer $customer,
        ?bool $capture,
        ?string $status,
        ?array $items,
    ) {
        $this->external_id = $external_id;
        $this->_id = $_id;
        $this->created_at = $created_at;
        $this->updated_at = $updated_at;
        $this->remittance_date = $remittance_date;
        $this->company_id = $company_id;
        $this->amount = $amount;
        $this->currency = $currency;
        $this->__v = $__v;
        $this->transactions = $transactions;
        $this->one_off = $one_off;
        $this->archived = $archived;
        $this->customer = $customer;
        $this->capture = $capture;
        $this->status = $status;
        $this->items = $items;
    }
}

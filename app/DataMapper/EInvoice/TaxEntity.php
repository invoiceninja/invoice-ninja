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

namespace App\DataMapper\EInvoice;

class TaxEntity
{
    /** @var string $version */
    public string $version = 'alpha';

    /** @var ?int $legal_entity_id */
    public ?int $legal_entity_id = null;

    /** @var string $company_key */
    public string $company_key = '';

    /** @var array<string> */
    public array $received_documents = [];

    /** @var bool $acts_as_sender */
    public bool $acts_as_sender = true;

    /** @var bool $acts_as_receiver */
    public bool $acts_as_receiver = true;
    /**
     * __construct
     *
     * @param mixed $entity
     */
    public function __construct(mixed $entity = null)
    {
        if (!$entity) {
            $this->init();
            return;
        }

        $entityArray = is_object($entity) ? get_object_vars($entity) : $entity;

        foreach ($entityArray as $key => $value) {
            $this->{$key} = $value;
        }

        $this->migrate();
    }

    public function init(): self
    {
        return $this;
    }

    private function migrate(): self
    {
        return $this;
    }
}

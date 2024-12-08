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

namespace App\Services\EDocument\Gateway\Qvalia;

use App\Services\EDocument\Gateway\MutatorUtil;
use App\Services\EDocument\Gateway\MutatorInterface;

class Mutator implements MutatorInterface
{
    private \InvoiceNinja\EInvoice\Models\Peppol\Invoice $p_invoice;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_client_settings;

    private ?\InvoiceNinja\EInvoice\Models\Peppol\Invoice $_company_settings;

    private $invoice;

    private MutatorUtil $mutator_util;

    public function __construct(public Qvalia $qvalia)
    {
        $this->mutator_util = new MutatorUtil($this);
    }

    public function setInvoice($invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function setPeppol($p_invoice): self
    {
        $this->p_invoice = $p_invoice;
        return $this;
    }

    public function getPeppol(): mixed
    {
        return $this->p_invoice;
    }

    public function getClientSettings(): mixed
    {
        return $this->_client_settings;
    }

    public function getCompanySettings(): mixed
    {
        return $this->_company_settings;
    }

    public function setClientSettings($client_settings): self
    {
        $this->_client_settings = $client_settings;
        return $this;
    }

    public function setCompanySettings($company_settings): self
    {
        $this->_company_settings = $company_settings;
        return $this;
    }

    public function getInvoice(): mixed
    {
        return $this->invoice;
    }

    public function getSetting(string $property_path): mixed
    {
        return $this->mutator_util->getSetting($property_path);
    }

    /**
     * senderSpecificLevelMutators
     *
     * Runs sender level specific requirements for the e-invoice,
     *
     * ie, mutations that are required by the senders country.
     *
     * @return self
     */
    public function senderSpecificLevelMutators(): self
    {

        if (method_exists($this, $this->invoice->company->country()->iso_3166_2)) {
            $this->{$this->invoice->company->country()->iso_3166_2}();
        }

        return $this;
    }

    /**
     * receiverSpecificLevelMutators
     *
     * Runs receiver level specific requirements for the e-invoice
     *
     * ie mutations that are required by the receiving country
     * @return self
     */
    public function receiverSpecificLevelMutators(): self
    {

        if (method_exists($this, "client_{$this->invoice->company->country()->iso_3166_2}")) {
            $this->{"client_{$this->invoice->company->country()->iso_3166_2}"}();
        }

        return $this;
    }

    // Country-specific methods
    public function DE(): self
    {
        return $this;
    }
    public function CH(): self
    {
        return $this;
    }
    public function AT(): self
    {
        return $this;
    }
    public function AU(): self
    {
        return $this;
    }
    public function ES(): self
    {
        return $this;
    }
    public function FI(): self
    {
        return $this;
    }
    public function FR(): self
    {
        return $this;
    }
    public function IT(): self
    {
        return $this;
    }
    public function client_IT(): self
    {
        return $this;
    }
    public function MY(): self
    {
        return $this;
    }
    public function NL(): self
    {
        return $this;
    }
    public function NZ(): self
    {
        return $this;
    }
    public function PL(): self
    {
        return $this;
    }
    public function RO(): self
    {
        return $this;
    }
    public function SG(): self
    {
        return $this;
    }
    public function SE(): self
    {
        return $this;
    }

}

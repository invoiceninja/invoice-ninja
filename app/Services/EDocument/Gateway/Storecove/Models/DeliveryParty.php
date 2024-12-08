<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class DeliveryParty
{
    public ?Party $party;
    /** @var PublicIdentifiers[] */
    public ?array $public_identifiers;

    /**
     * @param PublicIdentifiers[] $public_identifiers
     */
    public function __construct(?Party $party, ?array $public_identifiers)
    {
        $this->party = $party;
        $this->public_identifiers = $public_identifiers;
    }

    public function getParty(): ?Party
    {
        return $this->party;
    }

    /**
     * @return PublicIdentifiers[]
     */
    public function getPublicIdentifiers(): ?array
    {
        return $this->public_identifiers;
    }

    public function setParty(?Party $party): self
    {
        $this->party = $party;
        return $this;
    }

    /**
     * @param PublicIdentifiers[] $public_identifiers
     */
    public function setPublicIdentifiers(?array $public_identifiers): self
    {
        $this->public_identifiers = $public_identifiers;
        return $this;
    }
}

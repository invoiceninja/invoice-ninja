<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class BuyerCustomerParty
{
    public ?Party $party;

    public function __construct(?Party $party)
    {
        $this->party = $party;
    }

    public function getParty(): ?Party
    {
        return $this->party;
    }

    public function setParty(?Party $party): self
    {
        $this->party = $party;
        return $this;
    }
}

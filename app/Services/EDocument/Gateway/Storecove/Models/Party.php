<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class Party
{
    #[SerializedPath('[cac:PartyName][0][cbc:Name]')]
    public $company_name;

    #[SerializedPath('[cac:PartyLegalEntity][0][cbc:RegistrationName]')]
    public ?string $registration_name;

    public ?string $classification_code;

    #[SerializedPath('[cac:PostalAddress]')]
    public ?Address $address;

    #[SerializedPath('[cac:Contact]')]
    public ?Contact $contact;

    public function __construct(
        ?string $company_name,
        ?string $registration_name,
        ?string $classification_code,
        ?Address $address,
        ?Contact $contact
    ) {
        $this->company_name = $company_name;
        $this->registration_name = $registration_name;
        $this->classification_code = $classification_code;
        $this->address = $address;
        $this->contact = $contact;
    }

    public function getCompanyName(): ?string
    {
        return $this->company_name;
    }

    public function getRegistrationName(): ?string
    {
        return $this->registration_name;
    }

    public function getClassificationCode(): ?string
    {
        return $this->classification_code;
    }

    public function getAddress(): ?Address
    {
        return $this->address;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setCompanyName(?string $company_name): self
    {
        $this->company_name = $company_name;
        return $this;
    }

    public function setRegistrationName(?string $registration_name): self
    {
        $this->registration_name = $registration_name;
        return $this;
    }

    public function setClassificationCode(?string $classification_code): self
    {
        $this->classification_code = $classification_code;
        return $this;
    }

    public function setAddress(?Address $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;
        return $this;
    }
}

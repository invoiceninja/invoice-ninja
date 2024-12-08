<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class TaxSubtotals
{
    #[SerializedPath('[cbc:TaxAmount][#]')]
    public ?float $tax_amount;

    public ?string $country;

    #[SerializedPath('[cbc:TaxableAmount][#]')]
    public ?float $taxable_amount;

    #[SerializedPath('[cac:TaxCategory][cbc:Percent]')]
    public ?float $percentage;

    #[SerializedPath('[cac:TaxCategory][cbc:ID][#]')]
    public ?string $category;

    public ?string $type;

    public function __construct(
        ?float $tax_amount,
        ?string $country,
        ?float $taxable_amount,
        ?float $percentage,
        ?string $category,
        ?string $type
    ) {
        $this->tax_amount = $tax_amount;
        $this->country = $country;
        $this->taxable_amount = $taxable_amount;
        $this->percentage = $percentage;
        $this->category = $category;
        $this->type = $type;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getTaxAmount(): ?float
    {
        return $this->tax_amount;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function getTaxableAmount(): ?float
    {
        return $this->taxable_amount;
    }

    public function getPercentage(): ?float
    {
        return $this->percentage;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setTaxAmount(?float $tax_amount): self
    {
        $this->tax_amount = $tax_amount;
        return $this;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function setTaxableAmount(?float $taxable_amount): self
    {
        $this->taxable_amount = $taxable_amount;
        return $this;
    }

    public function setPercentage(?float $percentage): self
    {
        $this->percentage = $percentage;
        return $this;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function toArray(): array
    {
        return (array)$this;
    }
}

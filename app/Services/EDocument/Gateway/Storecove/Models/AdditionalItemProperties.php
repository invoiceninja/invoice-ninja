<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class AdditionalItemProperties
{
    public ?string $name;
    public ?string $value;

    public function __construct(?string $name, ?string $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setValue(?string $value): self
    {
        $this->value = $value;
        return $this;
    }
}

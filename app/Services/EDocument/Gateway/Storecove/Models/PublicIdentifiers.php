<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class PublicIdentifiers
{
    public ?string $scheme;
    public ?string $id;

    public function __construct(?string $scheme, ?string $id)
    {
        $this->scheme = $scheme;
        $this->id = $id;
    }

    public function getScheme(): ?string
    {
        return $this->scheme;
    }

    public function getId(): ?string
    {
        return $this->id;
    }

    public function setScheme(?string $scheme): self
    {
        $this->scheme = $scheme;
        return $this;
    }

    public function setId(?string $id): self
    {
        $this->id = $id;
        return $this;
    }
}

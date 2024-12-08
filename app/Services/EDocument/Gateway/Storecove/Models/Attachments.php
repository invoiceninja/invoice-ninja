<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class Attachments
{
    public ?string $document;
    public ?string $mime_type;
    public ?string $filename;
    public ?string $description;
    public ?string $document_id;
    public ?bool $primary_image;

    public function __construct(
        ?string $document,
        ?string $mime_type,
        ?string $filename,
        ?string $description,
        ?string $document_id,
        ?bool $primary_image
    ) {
        $this->document = $document;
        $this->mime_type = $mime_type;
        $this->filename = $filename;
        $this->description = $description;
        $this->document_id = $document_id;
        $this->primary_image = $primary_image;
    }

    public function getDocument(): ?string
    {
        return $this->document;
    }

    public function getMimeType(): ?string
    {
        return $this->mime_type;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getDocumentId(): ?string
    {
        return $this->document_id;
    }

    public function getPrimaryImage(): ?bool
    {
        return $this->primary_image;
    }

    public function setDocument(?string $document): self
    {
        $this->document = $document;
        return $this;
    }

    public function setMimeType(?string $mime_type): self
    {
        $this->mime_type = $mime_type;
        return $this;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setDocumentId(?string $document_id): self
    {
        $this->document_id = $document_id;
        return $this;
    }

    public function setPrimaryImage(?bool $primary_image): self
    {
        $this->primary_image = $primary_image;
        return $this;
    }
}

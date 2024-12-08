<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class Document
{
    public ?string $document_type;
    public ?string $source;
    public ?Invoice $invoice;
    public ?Order $order;

    public function __construct(
        ?string $document_type,
        ?string $source,
        ?Invoice $invoice,
        ?Order $order
    ) {
        $this->document_type = $document_type;
        $this->source = $source;
        $this->invoice = $invoice;
        $this->order = $order;
    }

    public function getDocumentType(): ?string
    {
        return $this->document_type;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getInvoice(): ?Invoice
    {
        return $this->invoice;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setDocumentType(?string $document_type): self
    {
        $this->document_type = $document_type;
        return $this;
    }

    public function setSource(?string $source): self
    {
        $this->source = $source;
        return $this;
    }

    public function setInvoice(?Invoice $invoice): self
    {
        $this->invoice = $invoice;
        return $this;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        return $this;
    }
}

<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class DeliveryTerms
{
	public ?string $incoterms;
	public ?string $special_terms;
	public ?string $delivery_location_id;

	public function __construct(
		?string $incoterms,
		?string $special_terms,
		?string $delivery_location_id
	) {
		$this->incoterms = $incoterms;
		$this->special_terms = $special_terms;
		$this->delivery_location_id = $delivery_location_id;
	}

	public function getIncoterms(): ?string
	{
		return $this->incoterms;
	}

	public function getSpecialTerms(): ?string
	{
		return $this->special_terms;
	}

	public function getDeliveryLocationId(): ?string
	{
		return $this->delivery_location_id;
	}

	public function setIncoterms(?string $incoterms): self
	{
		$this->incoterms = $incoterms;
		return $this;
	}

	public function setSpecialTerms(?string $special_terms): self
	{
		$this->special_terms = $special_terms;
		return $this;
	}

	public function setDeliveryLocationId(?string $delivery_location_id): self
	{
		$this->delivery_location_id = $delivery_location_id;
		return $this;
	}
}

<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class DeliveryLocation
{
	public ?string $id;
	public ?string $scheme_id;
	public ?string $scheme_agency_id;
	public ?string $location_name;
	public ?Address $address;

	public function __construct(
		?string $id,
		?string $scheme_id,
		?string $scheme_agency_id,
		?string $location_name,
		?Address $address
	) {
		$this->id = $id;
		$this->scheme_id = $scheme_id;
		$this->scheme_agency_id = $scheme_agency_id;
		$this->location_name = $location_name;
		$this->address = $address;
	}

	public function getId(): ?string
	{
		return $this->id;
	}

	public function getSchemeId(): ?string
	{
		return $this->scheme_id;
	}

	public function getSchemeAgencyId(): ?string
	{
		return $this->scheme_agency_id;
	}

	public function getLocationName(): ?string
	{
		return $this->location_name;
	}

	public function getAddress(): ?Address
	{
		return $this->address;
	}

	public function setId(?string $id): self
	{
		$this->id = $id;
		return $this;
	}

	public function setSchemeId(?string $scheme_id): self
	{
		$this->scheme_id = $scheme_id;
		return $this;
	}

	public function setSchemeAgencyId(?string $scheme_agency_id): self
	{
		$this->scheme_agency_id = $scheme_agency_id;
		return $this;
	}

	public function setLocationName(?string $location_name): self
	{
		$this->location_name = $location_name;
		return $this;
	}

	public function setAddress(?Address $address): self
	{
		$this->address = $address;
		return $this;
	}
}

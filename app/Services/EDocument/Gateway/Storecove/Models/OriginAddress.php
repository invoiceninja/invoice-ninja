<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class OriginAddress
{
	public ?string $country;
	public ?string $street1;
	public ?string $street2;
	public ?string $city;
	public ?string $zip;
	public ?string $county;

	public function __construct(
		?string $country,
		?string $street1,
		?string $street2,
		?string $city,
		?string $zip,
		?string $county
	) {
		$this->country = $country;
		$this->street1 = $street1;
		$this->street2 = $street2;
		$this->city = $city;
		$this->zip = $zip;
		$this->county = $county;
	}

	public function getCountry(): ?string
	{
		return $this->country;
	}

	public function getStreet1(): ?string
	{
		return $this->street1;
	}

	public function getStreet2(): ?string
	{
		return $this->street2;
	}

	public function getCity(): ?string
	{
		return $this->city;
	}

	public function getZip(): ?string
	{
		return $this->zip;
	}

	public function getCounty(): ?string
	{
		return $this->county;
	}

	public function setCountry(?string $country): self
	{
		$this->country = $country;
		return $this;
	}

	public function setStreet1(?string $street1): self
	{
		$this->street1 = $street1;
		return $this;
	}

	public function setStreet2(?string $street2): self
	{
		$this->street2 = $street2;
		return $this;
	}

	public function setCity(?string $city): self
	{
		$this->city = $city;
		return $this;
	}

	public function setZip(?string $zip): self
	{
		$this->zip = $zip;
		return $this;
	}

	public function setCounty(?string $county): self
	{
		$this->county = $county;
		return $this;
	}
}

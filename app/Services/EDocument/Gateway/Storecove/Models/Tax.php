<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class Tax
{
	public ?string $country;
	public ?float $amount;
	public ?float $percentage;
	public ?string $category;
	public ?string $type;

	public function __construct(
		?string $country,
		?float $amount,
		?float $percentage,
		?string $category,
		?string $type
	) {
		$this->country = $country;
		$this->amount = $amount;
		$this->percentage = $percentage;
		$this->category = $category;
		$this->type = $type;
	}

	public function getCountry(): ?string
	{
		return $this->country;
	}

	public function getAmount(): ?float
	{
		return $this->amount;
	}

	public function getPercentage(): ?float
	{
		return $this->percentage;
	}

	public function getCategory(): ?string
	{
		return $this->category;
	}

	public function getType(): ?string
	{
		return $this->type;
	}

	public function setCountry(?string $country): self
	{
		$this->country = $country;
		return $this;
	}

	public function setAmount(?float $amount): self
	{
		$this->amount = $amount;
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

	public function setType(?string $type): self
	{
		$this->type = $type;
		return $this;
	}
}

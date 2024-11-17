<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;

class PaymentTerms
{
	#[SerializedName('cbc:Note')]
	public ?string $note;

	public function __construct(?string $note)
	{
		$this->note = $note;
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function setNote(?string $note): self
	{
		$this->note = $note;
		return $this;
	}
}

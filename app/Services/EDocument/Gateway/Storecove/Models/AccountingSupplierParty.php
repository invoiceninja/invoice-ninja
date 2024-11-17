<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class AccountingSupplierParty
{
	
	/** @var Party */
	#[SerializedName('cac:Party')]
	public $party;

	/** @var PublicIdentifiers[] */
	public ?array $public_identifiers;

	/**
	 * @param PublicIdentifiers[] $public_identifiers
	 */
	public function __construct(?Party $party, ?array $public_identifiers)
	{
		$this->party = $party;
		$this->public_identifiers = $public_identifiers;
	}

	public function getParty(): ?Party
	{
		return $this->party;
	}

	/**
	 * @return PublicIdentifiers[]
	 */
	public function getPublicIdentifiers(): ?array
	{
		return $this->public_identifiers;
	}

	public function setParty(?Party $party): self
	{
		$this->party = $party;
		return $this;
	}

	/**
	 * @param PublicIdentifiers[] $public_identifiers
	 */
	public function setPublicIdentifiers(?array $public_identifiers): self
	{
		$this->public_identifiers = $public_identifiers;
		return $this;
	}

	public function addPublicIdentifiers($public_identifier): self
	{
		$this->public_identifiers[] = $public_identifier;
		return $this;
	}
}

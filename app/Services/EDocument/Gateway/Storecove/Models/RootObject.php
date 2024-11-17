<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class RootObject
{
	public ?string $legal_entity_id;
	public ?string $direction;
	public ?string $guid;
	public ?string $original;
	public ?Document $document;

	public function __construct(
		?string $legal_entity_id,
		?string $direction,
		?string $guid,
		?string $original,
		?Document $document
	) {
		$this->legal_entity_id = $legal_entity_id;
		$this->direction = $direction;
		$this->guid = $guid;
		$this->original = $original;
		$this->document = $document;
	}

	public function getLegalEntityId(): ?string
	{
		return $this->legal_entity_id;
	}

	public function getDirection(): ?string
	{
		return $this->direction;
	}

	public function getGuid(): ?string
	{
		return $this->guid;
	}

	public function getOriginal(): ?string
	{
		return $this->original;
	}

	public function getDocument(): ?Document
	{
		return $this->document;
	}

	public function setLegalEntityId(?string $legal_entity_id): self
	{
		$this->legal_entity_id = $legal_entity_id;
		return $this;
	}

	public function setDirection(?string $direction): self
	{
		$this->direction = $direction;
		return $this;
	}

	public function setGuid(?string $guid): self
	{
		$this->guid = $guid;
		return $this;
	}

	public function setOriginal(?string $original): self
	{
		$this->original = $original;
		return $this;
	}

	public function setDocument(?Document $document): self
	{
		$this->document = $document;
		return $this;
	}
}

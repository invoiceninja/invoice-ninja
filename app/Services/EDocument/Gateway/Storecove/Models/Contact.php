<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class Contact
{

	#[SerializedPath('[cbc:Name]')]
	public ?string $first_name;
	public ?string $last_name;

	#[SerializedPath('[cbc:ElectronicMail]')]
	public ?string $email;

	#[SerializedPath('[cbc:Telephone]')]
	public ?string $phone;
	public ?string $id;

	public function __construct(
		?string $first_name,
		?string $last_name,
		?string $email,
		?string $phone,
		?string $id
	) {
		$this->first_name = $first_name;
		$this->last_name = $last_name;
		$this->email = $email;
		$this->phone = $phone;
		$this->id = $id;
	}

	public function getFirstName(): ?string
	{
		return $this->first_name;
	}

	public function getLastName(): ?string
	{
		return $this->last_name;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function getId(): ?string
	{
		return $this->id;
	}

	public function setFirstName(?string $first_name): self
	{
		$this->first_name = $first_name;
		return $this;
	}

	public function setLastName(?string $last_name): self
	{
		$this->last_name = $last_name;
		return $this;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;
		return $this;
	}

	public function setPhone(?string $phone): self
	{
		$this->phone = $phone;
		return $this;
	}

	public function setId(?string $id): self
	{
		$this->id = $id;
		return $this;
	}
}

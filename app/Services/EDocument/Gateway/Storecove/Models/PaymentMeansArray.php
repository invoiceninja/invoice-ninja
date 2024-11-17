<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;

class PaymentMeansArray
{
	#[SerializedPath('[cbc:PaymentMeansCode][#]')]
    public ?string $code;

    #[SerializedPath('[cac:PayeeFinancialAccount][cbc:ID][#]')]
    public ?string $account;

    #[SerializedPath('[cac:PayeeFinancialAccount][cac:FinancialInstitutionBranch][cbc:ID][#]')]
    public ?string $branche_code;

    #[SerializedPath('[cac:PayeeFinancialAccount][cbc:Name]')]
    public ?string $holder;

    #[SerializedPath('[cac:PaymentMandate][cac:PayerFinancialAccount][cbc:ID][@schemeID]')]
    public ?string $network;

    #[SerializedPath('[cac:PaymentMandate][cbc:ID][#]')]
    public ?string $mandate;

    #[SerializedPath('[cbc:PaymentID][#]')]
    public ?string $payment_id;

    #[SerializedPath('[cbc:PaymentAmount][#]')]
    public ?string $amount;

	public function __construct(
		?string $code,
		?string $account,
		?string $branche_code,
		?string $holder,
		?string $network,
		?string $mandate,
		?string $payment_id,
		?string $amount
	) {
		$this->code = $code;
		$this->account = $account;
		$this->branche_code = $branche_code;
		$this->holder = $holder;
		$this->network = $network;
		$this->mandate = $mandate;
		$this->payment_id = $payment_id;
		$this->amount = $amount;
	}

	public function getCode(): ?string
	{
		return $this->code;
	}

	public function getAccount(): ?string
	{
		return $this->account;
	}

	public function getBrancheCode(): ?string
	{
		return $this->branche_code;
	}

	public function getHolder(): ?string
	{
		return $this->holder;
	}

	public function getNetwork(): ?string
	{
		return $this->network;
	}

	public function getMandate(): ?string
	{
		return $this->mandate;
	}

	public function getPaymentId(): ?string
	{
		return $this->payment_id;
	}

	public function getAmount(): ?string
	{
		return $this->amount;
	}

	public function setCode(?string $code): self
	{
		$this->code = $code;
		return $this;
	}

	public function setAccount(?string $account): self
	{
		$this->account = $account;
		return $this;
	}

	public function setBrancheCode(?string $branche_code): self
	{
		$this->branche_code = $branche_code;
		return $this;
	}

	public function setHolder(?string $holder): self
	{
		$this->holder = $holder;
		return $this;
	}

	public function setNetwork(?string $network): self
	{
		$this->network = $network;
		return $this;
	}

	public function setMandate(?string $mandate): self
	{
		$this->mandate = $mandate;
		return $this;
	}

	public function setPaymentId(?string $payment_id): self
	{
		$this->payment_id = $payment_id;
		return $this;
	}

	public function setAmount(?string $amount): self
	{
		$this->amount = $amount;
		return $this;
	}
}

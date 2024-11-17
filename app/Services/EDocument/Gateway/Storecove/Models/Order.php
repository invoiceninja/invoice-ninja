<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class Order
{
	public ?string $document_number;
	public ?string $issue_date;
	public ?SellerSupplierParty $seller_supplier_party;
	/** @var OrderLines[] */
	public ?array $order_lines;
	public ?string $amount_including_tax;
	public ?string $tax_system;
	/** @var References[] */
	public ?array $references;
	public ?string $issue_time;
	public ?string $time_zone;
	public ?string $order_type;
	public ?string $note;
	public ?string $document_currency_code;
	public ?string $accounting_cost;
	public ?string $validity_period;
	public ?BuyerCustomerParty $buyer_customer_party;
	public ?Delivery $delivery;
	public ?DeliveryTerms $delivery_terms;
	public ?PaymentTerms $payment_terms;
	/** @var AllowanceCharges[] */
	public ?array $allowance_charges;
	/** @var Attachments[] */
	public ?array $attachments;

	/**
	 * @param OrderLines[] $order_lines
	 * @param References[] $references
	 * @param AllowanceCharges[] $allowance_charges
	 * @param Attachments[] $attachments
	 */
	public function __construct(
		?string $document_number,
		?string $issue_date,
		?SellerSupplierParty $seller_supplier_party,
		?array $order_lines,
		?string $amount_including_tax,
		?string $tax_system,
		?array $references,
		?string $issue_time,
		?string $time_zone,
		?string $order_type,
		?string $note,
		?string $document_currency_code,
		?string $accounting_cost,
		?string $validity_period,
		?BuyerCustomerParty $buyer_customer_party,
		?Delivery $delivery,
		?DeliveryTerms $delivery_terms,
		?PaymentTerms $payment_terms,
		?array $allowance_charges,
		?array $attachments
	) {
		$this->document_number = $document_number;
		$this->issue_date = $issue_date;
		$this->seller_supplier_party = $seller_supplier_party;
		$this->order_lines = $order_lines;
		$this->amount_including_tax = $amount_including_tax;
		$this->tax_system = $tax_system;
		$this->references = $references;
		$this->issue_time = $issue_time;
		$this->time_zone = $time_zone;
		$this->order_type = $order_type;
		$this->note = $note;
		$this->document_currency_code = $document_currency_code;
		$this->accounting_cost = $accounting_cost;
		$this->validity_period = $validity_period;
		$this->buyer_customer_party = $buyer_customer_party;
		$this->delivery = $delivery;
		$this->delivery_terms = $delivery_terms;
		$this->payment_terms = $payment_terms;
		$this->allowance_charges = $allowance_charges;
		$this->attachments = $attachments;
	}

	public function getDocumentNumber(): ?string
	{
		return $this->document_number;
	}

	public function getIssueDate(): ?string
	{
		return $this->issue_date;
	}

	public function getSellerSupplierParty(): ?SellerSupplierParty
	{
		return $this->seller_supplier_party;
	}

	/**
	 * @return OrderLines[]
	 */
	public function getOrderLines(): ?array
	{
		return $this->order_lines;
	}

	public function getAmountIncludingTax(): ?string
	{
		return $this->amount_including_tax;
	}

	public function getTaxSystem(): ?string
	{
		return $this->tax_system;
	}

	/**
	 * @return References[]
	 */
	public function getReferences(): ?array
	{
		return $this->references;
	}

	public function getIssueTime(): ?string
	{
		return $this->issue_time;
	}

	public function getTimeZone(): ?string
	{
		return $this->time_zone;
	}

	public function getOrderType(): ?string
	{
		return $this->order_type;
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function getDocumentCurrencyCode(): ?string
	{
		return $this->document_currency_code;
	}

	public function getAccountingCost(): ?string
	{
		return $this->accounting_cost;
	}

	public function getValidityPeriod(): ?string
	{
		return $this->validity_period;
	}

	public function getBuyerCustomerParty(): ?BuyerCustomerParty
	{
		return $this->buyer_customer_party;
	}

	public function getDelivery(): ?Delivery
	{
		return $this->delivery;
	}

	public function getDeliveryTerms(): ?DeliveryTerms
	{
		return $this->delivery_terms;
	}

	public function getPaymentTerms(): ?PaymentTerms
	{
		return $this->payment_terms;
	}

	/**
	 * @return AllowanceCharges[]
	 */
	public function getAllowanceCharges(): ?array
	{
		return $this->allowance_charges;
	}

	/**
	 * @return Attachments[]
	 */
	public function getAttachments(): ?array
	{
		return $this->attachments;
	}

	public function setDocumentNumber(?string $document_number): self
	{
		$this->document_number = $document_number;
		return $this;
	}

	public function setIssueDate(?string $issue_date): self
	{
		$this->issue_date = $issue_date;
		return $this;
	}

	public function setSellerSupplierParty(?SellerSupplierParty $seller_supplier_party): self
	{
		$this->seller_supplier_party = $seller_supplier_party;
		return $this;
	}

	/**
	 * @param OrderLines[] $order_lines
	 */
	public function setOrderLines(?array $order_lines): self
	{
		$this->order_lines = $order_lines;
		return $this;
	}

	public function setAmountIncludingTax(?string $amount_including_tax): self
	{
		$this->amount_including_tax = $amount_including_tax;
		return $this;
	}

	public function setTaxSystem(?string $tax_system): self
	{
		$this->tax_system = $tax_system;
		return $this;
	}

	/**
	 * @param References[] $references
	 */
	public function setReferences(?array $references): self
	{
		$this->references = $references;
		return $this;
	}

	public function setIssueTime(?string $issue_time): self
	{
		$this->issue_time = $issue_time;
		return $this;
	}

	public function setTimeZone(?string $time_zone): self
	{
		$this->time_zone = $time_zone;
		return $this;
	}

	public function setOrderType(?string $order_type): self
	{
		$this->order_type = $order_type;
		return $this;
	}

	public function setNote(?string $note): self
	{
		$this->note = $note;
		return $this;
	}

	public function setDocumentCurrencyCode(?string $document_currency_code): self
	{
		$this->document_currency_code = $document_currency_code;
		return $this;
	}

	public function setAccountingCost(?string $accounting_cost): self
	{
		$this->accounting_cost = $accounting_cost;
		return $this;
	}

	public function setValidityPeriod(?string $validity_period): self
	{
		$this->validity_period = $validity_period;
		return $this;
	}

	public function setBuyerCustomerParty(?BuyerCustomerParty $buyer_customer_party): self
	{
		$this->buyer_customer_party = $buyer_customer_party;
		return $this;
	}

	public function setDelivery(?Delivery $delivery): self
	{
		$this->delivery = $delivery;
		return $this;
	}

	public function setDeliveryTerms(?DeliveryTerms $delivery_terms): self
	{
		$this->delivery_terms = $delivery_terms;
		return $this;
	}

	public function setPaymentTerms(?PaymentTerms $payment_terms): self
	{
		$this->payment_terms = $payment_terms;
		return $this;
	}

	/**
	 * @param AllowanceCharges[] $allowance_charges
	 */
	public function setAllowanceCharges(?array $allowance_charges): self
	{
		$this->allowance_charges = $allowance_charges;
		return $this;
	}

	/**
	 * @param Attachments[] $attachments
	 */
	public function setAttachments(?array $attachments): self
	{
		$this->attachments = $attachments;
		return $this;
	}
}

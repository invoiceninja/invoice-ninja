<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class Delivery
{
	public ?DeliveryLocation $delivery_location;
	public ?string $requested_delivery_period;
	public ?string $actual_delivery_date;
	public ?float $quantity;
	public ?string $delivery_party_name;
	public ?DeliveryParty $delivery_party;
	public ?Shipment $shipment;
	public ?string $shipping_marks;

	public function __construct(
		?DeliveryLocation $delivery_location,
		?string $requested_delivery_period,
		?string $actual_delivery_date,
		?float $quantity,
		?string $delivery_party_name,
		?DeliveryParty $delivery_party,
		?Shipment $shipment,
		?string $shipping_marks
	) {
		$this->delivery_location = $delivery_location;
		$this->requested_delivery_period = $requested_delivery_period;
		$this->actual_delivery_date = $actual_delivery_date;
		$this->quantity = $quantity;
		$this->delivery_party_name = $delivery_party_name;
		$this->delivery_party = $delivery_party;
		$this->shipment = $shipment;
		$this->shipping_marks = $shipping_marks;
	}

	public function getDeliveryLocation(): ?DeliveryLocation
	{
		return $this->delivery_location;
	}

	public function getRequestedDeliveryPeriod(): ?string
	{
		return $this->requested_delivery_period;
	}

	public function getActualDeliveryDate(): ?string
	{
		return $this->actual_delivery_date;
	}

	public function getQuantity(): ?float
	{
		return $this->quantity;
	}

	public function getDeliveryPartyName(): ?string
	{
		return $this->delivery_party_name;
	}

	public function getDeliveryParty(): ?DeliveryParty
	{
		return $this->delivery_party;
	}

	public function getShipment(): ?Shipment
	{
		return $this->shipment;
	}

	public function getShippingMarks(): ?string
	{
		return $this->shipping_marks;
	}

	public function setDeliveryLocation(?DeliveryLocation $delivery_location): self
	{
		$this->delivery_location = $delivery_location;
		return $this;
	}

	public function setRequestedDeliveryPeriod(?string $requested_delivery_period): self
	{
		$this->requested_delivery_period = $requested_delivery_period;
		return $this;
	}

	public function setActualDeliveryDate(?string $actual_delivery_date): self
	{
		$this->actual_delivery_date = $actual_delivery_date;
		return $this;
	}

	public function setQuantity(?float $quantity): self
	{
		$this->quantity = $quantity;
		return $this;
	}

	public function setDeliveryPartyName(?string $delivery_party_name): self
	{
		$this->delivery_party_name = $delivery_party_name;
		return $this;
	}

	public function setDeliveryParty(?DeliveryParty $delivery_party): self
	{
		$this->delivery_party = $delivery_party;
		return $this;
	}

	public function setShipment(?Shipment $shipment): self
	{
		$this->shipment = $shipment;
		return $this;
	}

	public function setShippingMarks(?string $shipping_marks): self
	{
		$this->shipping_marks = $shipping_marks;
		return $this;
	}
}

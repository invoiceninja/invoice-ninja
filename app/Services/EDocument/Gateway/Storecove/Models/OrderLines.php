<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

class OrderLines
{
    public ?string $line_id;
    public ?float $quantity;
    public ?string $quantity_unit_code;
    public ?string $amount_excluding_tax;
    public ?float $item_price;
    public ?float $base_quantity;
    /** @var AllowanceCharges[] */
    public ?array $allowance_charges;
    public ?bool $allow_partial_delivery;
    public ?string $accounting_cost;
    public ?Delivery $delivery;
    public ?string $description;
    public ?string $name;
    /** @var References[] */
    public ?array $references;
    /** @var TaxesDutiesFees[] */
    public ?array $taxes_duties_fees;
    /** @var AdditionalItemProperties[] */
    public ?array $additional_item_properties;
    /** @var string[] */
    public ?array $lot_number_ids;
    public ?string $note;

    /**
     * @param AllowanceCharges[] $allowance_charges
     * @param References[] $references
     * @param TaxesDutiesFees[] $taxes_duties_fees
     * @param AdditionalItemProperties[] $additional_item_properties
     * @param string[] $lot_number_ids
     */
    public function __construct(
        ?string $line_id,
        ?float $quantity,
        ?string $quantity_unit_code,
        ?string $amount_excluding_tax,
        ?float $item_price,
        ?float $base_quantity,
        ?array $allowance_charges,
        ?bool $allow_partial_delivery,
        ?string $accounting_cost,
        ?Delivery $delivery,
        ?string $description,
        ?string $name,
        ?array $references,
        ?array $taxes_duties_fees,
        ?array $additional_item_properties,
        ?array $lot_number_ids,
        ?string $note
    ) {
        $this->line_id = $line_id;
        $this->quantity = $quantity;
        $this->quantity_unit_code = $quantity_unit_code;
        $this->amount_excluding_tax = $amount_excluding_tax;
        $this->item_price = $item_price;
        $this->base_quantity = $base_quantity;
        $this->allowance_charges = $allowance_charges;
        $this->allow_partial_delivery = $allow_partial_delivery;
        $this->accounting_cost = $accounting_cost;
        $this->delivery = $delivery;
        $this->description = $description;
        $this->name = $name;
        $this->references = $references;
        $this->taxes_duties_fees = $taxes_duties_fees;
        $this->additional_item_properties = $additional_item_properties;
        $this->lot_number_ids = $lot_number_ids;
        $this->note = $note;
    }

    public function getLineId(): ?string
    {
        return $this->line_id;
    }

    public function getQuantity(): ?float
    {
        return $this->quantity;
    }

    public function getQuantityUnitCode(): ?string
    {
        return $this->quantity_unit_code;
    }

    public function getAmountExcludingTax(): ?string
    {
        return $this->amount_excluding_tax;
    }

    public function getItemPrice(): ?float
    {
        return $this->item_price;
    }

    public function getBaseQuantity(): ?float
    {
        return $this->base_quantity;
    }

    /**
     * @return AllowanceCharges[]
     */
    public function getAllowanceCharges(): ?array
    {
        return $this->allowance_charges;
    }

    public function getAllowPartialDelivery(): ?bool
    {
        return $this->allow_partial_delivery;
    }

    public function getAccountingCost(): ?string
    {
        return $this->accounting_cost;
    }

    public function getDelivery(): ?Delivery
    {
        return $this->delivery;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return References[]
     */
    public function getReferences(): ?array
    {
        return $this->references;
    }

    /**
     * @return TaxesDutiesFees[]
     */
    public function getTaxesDutiesFees(): ?array
    {
        return $this->taxes_duties_fees;
    }

    /**
     * @return AdditionalItemProperties[]
     */
    public function getAdditionalItemProperties(): ?array
    {
        return $this->additional_item_properties;
    }

    /**
     * @return string[]
     */
    public function getLotNumberIds(): ?array
    {
        return $this->lot_number_ids;
    }

    public function getNote(): ?string
    {
        return $this->note;
    }

    public function setLineId(?string $line_id): self
    {
        $this->line_id = $line_id;
        return $this;
    }

    public function setQuantity(?float $quantity): self
    {
        $this->quantity = $quantity;
        return $this;
    }

    public function setQuantityUnitCode(?string $quantity_unit_code): self
    {
        $this->quantity_unit_code = $quantity_unit_code;
        return $this;
    }

    public function setAmountExcludingTax(?string $amount_excluding_tax): self
    {
        $this->amount_excluding_tax = $amount_excluding_tax;
        return $this;
    }

    public function setItemPrice(?float $item_price): self
    {
        $this->item_price = $item_price;
        return $this;
    }

    public function setBaseQuantity(?float $base_quantity): self
    {
        $this->base_quantity = $base_quantity;
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

    public function setAllowPartialDelivery(?bool $allow_partial_delivery): self
    {
        $this->allow_partial_delivery = $allow_partial_delivery;
        return $this;
    }

    public function setAccountingCost(?string $accounting_cost): self
    {
        $this->accounting_cost = $accounting_cost;
        return $this;
    }

    public function setDelivery(?Delivery $delivery): self
    {
        $this->delivery = $delivery;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
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

    /**
     * @param TaxesDutiesFees[] $taxes_duties_fees
     */
    public function setTaxesDutiesFees(?array $taxes_duties_fees): self
    {
        $this->taxes_duties_fees = $taxes_duties_fees;
        return $this;
    }

    /**
     * @param AdditionalItemProperties[] $additional_item_properties
     */
    public function setAdditionalItemProperties(?array $additional_item_properties): self
    {
        $this->additional_item_properties = $additional_item_properties;
        return $this;
    }

    /**
     * @param string[] $lot_number_ids
     */
    public function setLotNumberIds(?array $lot_number_ids): self
    {
        $this->lot_number_ids = $lot_number_ids;
        return $this;
    }

    public function setNote(?string $note): self
    {
        $this->note = $note;
        return $this;
    }
}

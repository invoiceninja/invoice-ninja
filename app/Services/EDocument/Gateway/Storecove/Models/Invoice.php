<?php

namespace App\Services\EDocument\Gateway\Storecove\Models;

use DateTime;
use Symfony\Component\Serializer\Attribute\SerializedName;
use Symfony\Component\Serializer\Attribute\SerializedPath;
use Symfony\Component\Serializer\Normalizer\DateTimeNormalizer;
use Symfony\Component\Serializer\Attribute\Context;

class Invoice
{
    #[SerializedPath('[cbc:ID][#]')]
	public $invoice_number;

    // /** @var ?\DateTime */
    #[SerializedPath('[cbc:IssueDate]')]
    // #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
	public $issue_date;

    #[SerializedPath('[cac:AccountingCustomerParty]')]
    /** @var ?AccountingCustomerParty */
	public $accounting_customer_party;

    #[SerializedName('cac:InvoiceLine')]
	/** @var InvoiceLines[] */
	public array $invoice_lines;

    #[SerializedPath('[cbc:AccountingCost]')]
	public $accounting_cost;

	public ?string $accounting_currency_exchange_rate;
	public ?string $accounting_currency_taxable_amount;
	public ?string $accounting_currency_tax_amount;
	public ?string $accounting_currency_tax_amount_currency;
    
    #[SerializedPath('[cac:AccountingSupplierParty]')]
    /** @var ?AccountingSupplierParty */
	public $accounting_supplier_party;

    #[SerializedPath('[cac:AllowanceCharge]')]
	/** @var AllowanceCharges[] */ 
	public ?array $allowance_charges = [];

    //this is an experimental prop
    // #[SerializedPath('[cac:LegalMonetaryTotal][cbc:TaxInclusiveAmount][#]')]
    // #[Context(['path_type' => 'tax'])]
	public $amount_including_tax;
	
    #[SerializedPath('[cac:LegalMonetaryTotal][cbc:TaxInclusiveAmount][#]')]
	public $amount_including_vat;
	
    /** @var Attachments[] */
	public ?array $attachments;
	
    public ?bool $consumer_tax_mode; //toggle this to TRUE if we are using a secondary identifier ie. when German company is taxing French company and therefore using the additional Vat identifier
	
	#[SerializedName('cac:Delivery')]
    public ?Delivery $delivery;

	//no mapping
	public ?DeliveryTerms $delivery_terms;
	
    #[SerializedPath('[cbc:DocumentCurrencyCode][#]')]
    public $document_currency_code;
	
    // /** @var ?\DateTime */
    #[SerializedPath('[cbc:DueDate]')]
    // #[Context([DateTimeNormalizer::FORMAT_KEY => 'Y-m-d'])]
    public ?string $due_date;

	//may need something custom for this
    public ?string $invoice_period;

    //no mapping
	public ?array $issue_reasons;
	
    //no mapping
    public ?string $issue_time;

    #[SerializedPath('[cbc:Note]')]
	public $note;

    //no mapping
	public ?string $payable_rounding_amount;

    #[SerializedPath('[cac:PaymentMeans]')]
	/** @var PaymentMeansArray[] */
	public array $payment_means_array;

	#[SerializedPath('[cac:PaymentTerms][0]')]
	public ?PaymentTerms $payment_terms;

    // no mapping
	public ?string $preferred_invoice_type;
    
    #[SerializedPath('[cac:LegalMonetaryTotal][cbc:PrepaidAmount]')]
	public ?string $prepaid_amount;

	//no mapping
	// 'price_mode_gross' can only be used for sender countries ES, IT and PT
	public ?string $price_mode;

	/** @var References[] */
	public ?array $references;

	//AU/NZ and JP. - only available intra country ie AU - AU
	public ?bool $self_billing_mode;

	//readonly prop for received documents
	public ?string $sub_type;

	//proxy of issue date
	public ?string $tax_point_date;

	#[SerializedPath('[cac:TaxTotal][0][cac:TaxSubtotal]')]
	/** @var TaxSubtotals[] */
	public $tax_subtotals;

	//storecove - no mappings - tax_line_percentages
	public ?string $tax_system;

	//italy only - invoice level
	/** @var TaxesDutiesFees[] */
	public ?array $taxes_duties_fees;

	//no mapping
	public ?string $time_zone;

	// india only enum (b2b, sezwp, sezwop, expwp, expwop, dexp)
	public ?string $transaction_type;

	//no mapping
	public ?array $ubl_extensions;

	//no mapping 
	public ?string $x2y;

	//not found in schema
	public ?bool $vat_reverse_charge;
	public ?string $tax_exempt_reason;
	public ?string $invoice_type;
	public ?string $payment_means_iban;
	public ?string $payment_means_bic;
	public ?string $payment_means_code;
	public ?string $payment_means_payment_id;
	//not found in schema

    #[SerializedPath('[cbc:BuyerReference]')]
	public ?string $buyer_reference;

    #[SerializedPath('[cac:OrderReference][cbc:ID][#]')]
	public ?string $order_reference;

    #[SerializedPath('[cac:OrderReference][cbc:SalesOrderID][#]')]
	public ?string $sales_order_id;

    #[SerializedPath('[cac:BillingReference][cac:InvoiceDocumentReference][cbc:ID][#]')]
	public ?string $billing_reference;

    #[SerializedPath('[cac:ContractDocumentReference][cbc:ID][#]')]
	public ?string $contract_document_reference;

    #[SerializedPath('[cac:ProjectReference][cbc:ID][#]')]
	public ?string $project_reference;

	public function __construct(
		?string $invoice_number,
		?string $issue_date,
		?AccountingCustomerParty $accounting_customer_party,
		?array $invoice_lines,
		?string $accounting_cost,
		?string $accounting_currency_exchange_rate,
		?string $accounting_currency_taxable_amount,
		?string $accounting_currency_tax_amount,
		?string $accounting_currency_tax_amount_currency,
		?AccountingSupplierParty $accounting_supplier_party,
		?array $allowance_charges,
		?float $amount_including_tax,
		?float $amount_including_vat,
		?array $attachments,
		?bool $consumer_tax_mode,
		?Delivery $delivery,
		?DeliveryTerms $delivery_terms,
		?string $document_currency_code,
		?string $due_date,
		?string $invoice_period,
		?array $issue_reasons,
		?string $issue_time,
		?string $note,
		?string $payable_rounding_amount,
		?array $payment_means_array,
		?PaymentTerms $payment_terms,
		?string $preferred_invoice_type,
		?string $prepaid_amount,
		?string $price_mode,
		?array $references,
		?bool $self_billing_mode,
		?string $sub_type,
		?string $tax_point_date,
		?array $tax_subtotals,
		?string $tax_system,
		?array $taxes_duties_fees,
		?string $time_zone,
		?string $transaction_type,
		?array $ubl_extensions,
		?string $x2y,
		?bool $vat_reverse_charge,
		?string $tax_exempt_reason,
		?string $invoice_type,
		?string $buyer_reference,
		?string $order_reference,
		?string $sales_order_id,
		?string $billing_reference,
		?string $contract_document_reference,
		?string $project_reference,
		?string $payment_means_iban,
		?string $payment_means_bic,
		?string $payment_means_code,
		?string $payment_means_payment_id
	) {
		$this->invoice_number = $invoice_number;
		$this->issue_date = $issue_date;
		$this->accounting_customer_party = $accounting_customer_party;
		$this->invoice_lines = $invoice_lines;
		$this->accounting_cost = $accounting_cost;
		$this->accounting_currency_exchange_rate = $accounting_currency_exchange_rate;
		$this->accounting_currency_taxable_amount = $accounting_currency_taxable_amount;
		$this->accounting_currency_tax_amount = $accounting_currency_tax_amount;
		$this->accounting_currency_tax_amount_currency = $accounting_currency_tax_amount_currency;
		$this->accounting_supplier_party = $accounting_supplier_party;
		$this->allowance_charges = $allowance_charges;
		$this->amount_including_tax = $amount_including_tax;
		$this->amount_including_vat = $amount_including_vat;
		$this->attachments = $attachments;
		$this->consumer_tax_mode = $consumer_tax_mode;
		$this->delivery = $delivery;
		$this->delivery_terms = $delivery_terms;
		$this->document_currency_code = $document_currency_code;
		$this->due_date = $due_date;
		$this->invoice_period = $invoice_period;
		$this->issue_reasons = $issue_reasons;
		$this->issue_time = $issue_time;
		$this->note = $note;
		$this->payable_rounding_amount = $payable_rounding_amount;
		$this->payment_means_array = $payment_means_array;
		$this->payment_terms = $payment_terms;
		$this->preferred_invoice_type = $preferred_invoice_type;
		$this->prepaid_amount = $prepaid_amount;
		$this->price_mode = $price_mode;
		$this->references = $references;
		$this->self_billing_mode = $self_billing_mode;
		$this->sub_type = $sub_type;
		$this->tax_point_date = $tax_point_date;
		$this->tax_subtotals = $tax_subtotals;
		$this->tax_system = $tax_system;
		$this->taxes_duties_fees = $taxes_duties_fees;
		$this->time_zone = $time_zone;
		$this->transaction_type = $transaction_type;
		$this->ubl_extensions = $ubl_extensions;
		$this->x2y = $x2y;
		$this->vat_reverse_charge = $vat_reverse_charge;
		$this->tax_exempt_reason = $tax_exempt_reason;
		$this->invoice_type = $invoice_type;
		$this->buyer_reference = $buyer_reference;
		$this->order_reference = $order_reference;
		$this->sales_order_id = $sales_order_id;
		$this->billing_reference = $billing_reference;
		$this->contract_document_reference = $contract_document_reference;
		$this->project_reference = $project_reference;
		$this->payment_means_iban = $payment_means_iban;
		$this->payment_means_bic = $payment_means_bic;
		$this->payment_means_code = $payment_means_code;
		$this->payment_means_payment_id = $payment_means_payment_id;
	}

	public function getInvoiceNumber(): ?string
	{
		return $this->invoice_number;
	}

	public function getIssueDate(): ?string
	{
		return $this->issue_date;
	}

	public function getAccountingCustomerParty(): ?AccountingCustomerParty
	{
		return $this->accounting_customer_party;
	}

	/**
	 * @return InvoiceLines[]
	 */
	public function getInvoiceLines(): ?array
	{
		return $this->invoice_lines;
	}

	public function getAccountingCost(): ?string
	{
		return $this->accounting_cost;
	}

	public function getAccountingCurrencyExchangeRate(): ?string
	{
		return $this->accounting_currency_exchange_rate;
	}

	public function getAccountingCurrencyTaxableAmount(): ?string
	{
		return $this->accounting_currency_taxable_amount;
	}

	public function getAccountingCurrencyTaxAmount(): ?string
	{
		return $this->accounting_currency_tax_amount;
	}

	public function getAccountingCurrencyTaxAmountCurrency(): ?string
	{
		return $this->accounting_currency_tax_amount_currency;
	}

	public function getAccountingSupplierParty(): ?AccountingSupplierParty
	{
		return $this->accounting_supplier_party;
	}

	/**
	 * @return AllowanceCharges[]
	 */
	public function getAllowanceCharges(): ?array
	{
		return $this->allowance_charges;
	}

	public function getAmountIncludingTax(): ?float
	{
		return $this->amount_including_tax;
	}

	public function getAmountIncludingVat(): ?float
	{
		return $this->amount_including_vat;
	}

	/**
	 * @return Attachments[]
	 */
	public function getAttachments(): ?array
	{
		return $this->attachments;
	}

	public function getConsumerTaxMode(): ?bool
	{
		return $this->consumer_tax_mode;
	}

	public function getDelivery(): ?Delivery
	{
		return $this->delivery;
	}

	public function getDeliveryTerms(): ?DeliveryTerms
	{
		return $this->delivery_terms;
	}

	public function getDocumentCurrencyCode(): ?string
	{
		return $this->document_currency_code;
	}

	public function getDueDate()
	{
		return $this->due_date;
	}

	public function getInvoicePeriod(): ?string
	{
		return $this->invoice_period;
	}

	/**
	 * @return string[]
	 */
	public function getIssueReasons(): ?array
	{
		return $this->issue_reasons;
	}

	public function getIssueTime(): ?string
	{
		return $this->issue_time;
	}

	public function getNote(): ?string
	{
		return $this->note;
	}

	public function getPayableRoundingAmount(): ?string
	{
		return $this->payable_rounding_amount;
	}

	/**
	 * @return PaymentMeansArray[]
	 */
	public function getPaymentMeansArray(): ?array
	{
		return $this->payment_means_array;
	}

	public function getPaymentTerms(): ?PaymentTerms
	{
		return $this->payment_terms;
	}

	public function getPreferredInvoiceType(): ?string
	{
		return $this->preferred_invoice_type;
	}

	public function getPrepaidAmount(): ?string
	{
		return $this->prepaid_amount;
	}

	public function getPriceMode(): ?string
	{
		return $this->price_mode;
	}

	/**
	 * @return References[]
	 */
	public function getReferences(): ?array
	{
		return $this->references;
	}

	public function getSelfBillingMode(): ?bool
	{
		return $this->self_billing_mode;
	}

	public function getSubType(): ?string
	{
		return $this->sub_type;
	}

	public function getTaxPointDate(): ?string
	{
		return $this->tax_point_date;
	}

	/**
	 * @return TaxSubtotals[]
	 */
	public function getTaxSubtotals(): ?array
	{
		return $this->tax_subtotals;
	}

	public function getTaxSystem(): ?string
	{
		return $this->tax_system;
	}

	/**
	 * @return TaxesDutiesFees[]
	 */
	public function getTaxesDutiesFees(): ?array
	{
		return $this->taxes_duties_fees;
	}

	public function getTimeZone(): ?string
	{
		return $this->time_zone;
	}

	public function getTransactionType(): ?string
	{
		return $this->transaction_type;
	}

	/**
	 * @return string[]
	 */
	public function getUblExtensions(): ?array
	{
		return $this->ubl_extensions;
	}

	public function getX2y(): ?string
	{
		return $this->x2y;
	}

	public function getVatReverseCharge(): ?bool
	{
		return $this->vat_reverse_charge;
	}

	public function getTaxExemptReason(): ?string
	{
		return $this->tax_exempt_reason;
	}

	public function getInvoiceType(): ?string
	{
		return $this->invoice_type;
	}

	public function getBuyerReference(): ?string
	{
		return $this->buyer_reference;
	}

	public function getOrderReference(): ?string
	{
		return $this->order_reference;
	}

	public function getSalesOrderId(): ?string
	{
		return $this->sales_order_id;
	}

	public function getBillingReference(): ?string
	{
		return $this->billing_reference;
	}

	public function getContractDocumentReference(): ?string
	{
		return $this->contract_document_reference;
	}

	public function getProjectReference(): ?string
	{
		return $this->project_reference;
	}

	public function getPaymentMeansIban(): ?string
	{
		return $this->payment_means_iban;
	}

	public function getPaymentMeansBic(): ?string
	{
		return $this->payment_means_bic;
	}

	public function getPaymentMeansCode(): ?string
	{
		return $this->payment_means_code;
	}

	public function getPaymentMeansPaymentId(): ?string
	{
		return $this->payment_means_payment_id;
	}

	public function setInvoiceNumber(?string $invoice_number): self
	{
		$this->invoice_number = $invoice_number;
		return $this;
	}

	public function setIssueDate($issue_date): self
	{
		$this->issue_date = $issue_date;
		return $this;
	}

	public function setAccountingCustomerParty(?AccountingCustomerParty $accounting_customer_party): self
	{
		$this->accounting_customer_party = $accounting_customer_party;
		return $this;
	}

	/**
	 * @param InvoiceLines[] $invoice_lines
	 */
	public function setInvoiceLines(?array $invoice_lines): self
	{
		$this->invoice_lines = $invoice_lines;
		return $this;
	}

	public function setAccountingCost(?string $accounting_cost): self
	{
		$this->accounting_cost = $accounting_cost;
		return $this;
	}

	public function setAccountingCurrencyExchangeRate(?string $accounting_currency_exchange_rate): self
	{
		$this->accounting_currency_exchange_rate = $accounting_currency_exchange_rate;
		return $this;
	}

	public function setAccountingCurrencyTaxableAmount(?string $accounting_currency_taxable_amount): self
	{
		$this->accounting_currency_taxable_amount = $accounting_currency_taxable_amount;
		return $this;
	}

	public function setAccountingCurrencyTaxAmount(?string $accounting_currency_tax_amount): self
	{
		$this->accounting_currency_tax_amount = $accounting_currency_tax_amount;
		return $this;
	}

	public function setAccountingCurrencyTaxAmountCurrency(?string $accounting_currency_tax_amount_currency): self
	{
		$this->accounting_currency_tax_amount_currency = $accounting_currency_tax_amount_currency;
		return $this;
	}

	public function setAccountingSupplierParty(?AccountingSupplierParty $accounting_supplier_party): self
	{
		$this->accounting_supplier_party = $accounting_supplier_party;
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

	public function setAmountIncludingTax(?float $amount_including_tax): self
	{
		$this->amount_including_tax = $amount_including_tax;
		return $this;
	}

	public function setAmountIncludingVat(?float $amount_including_vat): self
	{
		$this->amount_including_vat = $amount_including_vat;
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

	public function setConsumerTaxMode(?bool $consumer_tax_mode): self
	{
		$this->consumer_tax_mode = $consumer_tax_mode;
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

	public function setDocumentCurrencyCode(?string $document_currency_code): self
	{
		$this->document_currency_code = $document_currency_code;
		return $this;
	}

	public function setDueDate(?string $due_date): self
	{
		$this->due_date = $due_date;
		return $this;
	}

	public function setInvoicePeriod(?string $invoice_period): self
	{
		$this->invoice_period = $invoice_period;
		return $this;
	}

	/**
	 * @param string[] $issue_reasons
	 */
	public function setIssueReasons(?array $issue_reasons): self
	{
		$this->issue_reasons = $issue_reasons;
		return $this;
	}

	public function setIssueTime(?string $issue_time): self
	{
		$this->issue_time = $issue_time;
		return $this;
	}

	public function setNote(?string $note): self
	{
		$this->note = $note;
		return $this;
	}

	public function setPayableRoundingAmount(?string $payable_rounding_amount): self
	{
		$this->payable_rounding_amount = $payable_rounding_amount;
		return $this;
	}

	/**
	 * @param PaymentMeansArray[] $payment_means_array
	 */
	public function setPaymentMeansArray(?array $payment_means_array): self
	{
		$this->payment_means_array = $payment_means_array;
		return $this;
	}

	public function setPaymentTerms(?PaymentTerms $payment_terms): self
	{
		$this->payment_terms = $payment_terms;
		return $this;
	}

	public function setPreferredInvoiceType(?string $preferred_invoice_type): self
	{
		$this->preferred_invoice_type = $preferred_invoice_type;
		return $this;
	}

	public function setPrepaidAmount(?string $prepaid_amount): self
	{
		$this->prepaid_amount = $prepaid_amount;
		return $this;
	}

	public function setPriceMode(?string $price_mode): self
	{
		$this->price_mode = $price_mode;
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

	public function setSelfBillingMode(?bool $self_billing_mode): self
	{
		$this->self_billing_mode = $self_billing_mode;
		return $this;
	}

	public function setSubType(?string $sub_type): self
	{
		$this->sub_type = $sub_type;
		return $this;
	}

	public function setTaxPointDate(?string $tax_point_date): self
	{
		$this->tax_point_date = $tax_point_date;
		return $this;
	}

	/**
	 * @param TaxSubtotals[] $tax_subtotals
	 */
	public function setTaxSubtotals(?array $tax_subtotals): self
	{
		$this->tax_subtotals = $tax_subtotals;
		return $this;
	}

	public function setTaxSystem(?string $tax_system): self
	{
		$this->tax_system = $tax_system;
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

	public function setTimeZone(?string $time_zone): self
	{
		$this->time_zone = $time_zone;
		return $this;
	}

	public function setTransactionType(?string $transaction_type): self
	{
		$this->transaction_type = $transaction_type;
		return $this;
	}

	/**
	 * @param string[] $ubl_extensions
	 */
	public function setUblExtensions(?array $ubl_extensions): self
	{
		$this->ubl_extensions = $ubl_extensions;
		return $this;
	}

	public function setX2y(?string $x2y): self
	{
		$this->x2y = $x2y;
		return $this;
	}

	public function setVatReverseCharge(?bool $vat_reverse_charge): self
	{
		$this->vat_reverse_charge = $vat_reverse_charge;
		return $this;
	}

	public function setTaxExemptReason(?string $tax_exempt_reason): self
	{
		$this->tax_exempt_reason = $tax_exempt_reason;
		return $this;
	}

	public function setInvoiceType(?string $invoice_type): self
	{
		$this->invoice_type = $invoice_type;
		return $this;
	}

	public function setBuyerReference(?string $buyer_reference): self
	{
		$this->buyer_reference = $buyer_reference;
		return $this;
	}

	public function setOrderReference(?string $order_reference): self
	{
		$this->order_reference = $order_reference;
		return $this;
	}

	public function setSalesOrderId(?string $sales_order_id): self
	{
		$this->sales_order_id = $sales_order_id;
		return $this;
	}

	public function setBillingReference(?string $billing_reference): self
	{
		$this->billing_reference = $billing_reference;
		return $this;
	}

	public function setContractDocumentReference(?string $contract_document_reference): self
	{
		$this->contract_document_reference = $contract_document_reference;
		return $this;
	}

	public function setProjectReference(?string $project_reference): self
	{
		$this->project_reference = $project_reference;
		return $this;
	}

	public function setPaymentMeansIban(?string $payment_means_iban): self
	{
		$this->payment_means_iban = $payment_means_iban;
		return $this;
	}

	public function setPaymentMeansBic(?string $payment_means_bic): self
	{
		$this->payment_means_bic = $payment_means_bic;
		return $this;
	}

	public function setPaymentMeansCode(?string $payment_means_code): self
	{
		$this->payment_means_code = $payment_means_code;
		return $this;
	}

	public function setPaymentMeansPaymentId(?string $payment_means_payment_id): self
	{
		$this->payment_means_payment_id = $payment_means_payment_id;
		return $this;
	}
}

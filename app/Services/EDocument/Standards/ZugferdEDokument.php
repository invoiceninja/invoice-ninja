<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\EDocument\Standards;

use App\DataMapper\InvoiceItem;
use App\Models\Company;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\Quote;
use App\Services\AbstractService;
use horstoeko\zugferd\codelists\ZugferdDutyTaxFeeCategories;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use horstoeko\zugferd\ZugferdProfiles;

class ZugferdEDokument extends AbstractService
{
    public ZugferdDocumentBuilder $xdocument;


    /**
     * __construct
     *
     * \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document
     * @param  bool $returnObject
     * @param  array $tax_map
     * @return void
     */
    public function __construct(public \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document, private readonly bool $returnObject = false, private array $tax_map = [])
    {
    }

    public function run(): self
    {

        /** @var \App\Models\Company $company */
        $company = $this->document->company;

        /** @var \App\Models\Client $client */
        $client = $this->document->client;

        $profile = $client->getSetting('e_invoice_type');

        $profile = match ($profile) {
            "XInvoice_3_0" => ZugferdProfiles::PROFILE_XRECHNUNG_3,
            "XInvoice_2_3" => ZugferdProfiles::PROFILE_XRECHNUNG_2_3,
            "XInvoice_2_2" => ZugferdProfiles::PROFILE_XRECHNUNG_2_2,
            "XInvoice_2_1" => ZugferdProfiles::PROFILE_XRECHNUNG_2_1,
            "XInvoice_2_0" => ZugferdProfiles::PROFILE_XRECHNUNG_2,
            "XInvoice_1_0" => ZugferdProfiles::PROFILE_XRECHNUNG,
            "XInvoice-Extended" => ZugferdProfiles::PROFILE_EXTENDED,
            "XInvoice-BasicWL" => ZugferdProfiles::PROFILE_BASICWL,
            "XInvoice-Basic" => ZugferdProfiles::PROFILE_BASIC,
            default => ZugferdProfiles::PROFILE_EN16931,
        };

        $this->xdocument = ZugferdDocumentBuilder::CreateNew($profile);

        $user_or_company_phone = strlen($this->document->user->present()->phone()) > 3 ? $this->document->user->present()->phone() : $company->present()->phone;

        $this->xdocument
            ->setDocumentSupplyChainEvent(date_create($this->document->date ?? now()->format('Y-m-d')))
            ->setDocumentSeller($company->getSetting('name'))
            ->setDocumentSellerAddress($company->getSetting("address1"), $company->getSetting("address2"), "", $company->getSetting("postal_code"), $company->getSetting("city"), $company->country()->iso_3166_2, $company->getSetting("state"))
            ->setDocumentSellerContact($this->document->user->present()->getFullName(), "", $user_or_company_phone, "", $this->document->user->email)
            ->setDocumentSellerCommunication("EM", $this->document->user->email)
            ->setDocumentBuyer($client->present()->name(), $client->number)
            ->setDocumentBuyerAddress($client->address1, "", "", $client->postal_code, $client->city, $client->country->iso_3166_2, $client->state)
            ->setDocumentBuyerContact($client->present()->primary_contact_name(), "", $client->present()->phone(), "", $client->present()->email())
            ->setDocumentBuyerCommunication("EM", $client->present()->email())
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($this->document->date ?? now()->format('Y-m-d'))->diff(date_create($this->document->due_date ?? now()->format('Y-m-d')))->format("%d"), 'paydate' => $this->document->due_date]));

        if (!empty($this->document->public_notes)) {
            $this->xdocument->addDocumentNote($this->document->public_notes ?? '');
        }
        // Document type
        $document_class = get_class($this->document);
        switch ($document_class) {
            case Quote::class:
                // Probably wrong file code https://github.com/horstoeko/zugferd/blob/master/src/codelists/ZugferdInvoiceType.php
                if (empty($this->document->number)) {
                    $this->xdocument->setDocumentInformation("DRAFT", "84", date_create($this->document->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
                    $this->xdocument->setIsTestDocument();
                } else {
                    $this->xdocument->setDocumentInformation($this->document->number, "84", date_create($this->document->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
                }
                break;
            case Invoice::class:
                if (empty($this->document->number)) {
                    $this->xdocument->setDocumentInformation("DRAFT", "380", date_create($this->document->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
                    $this->xdocument->setIsTestDocument();
                } else {
                    $this->xdocument->setDocumentInformation($this->document->number, "380", date_create($this->document->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
                }
                break;
            case Credit::class:
                if (empty($this->document->number)) {
                    $this->xdocument->setDocumentInformation("DRAFT", "389", date_create($this->document->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
                    $this->xdocument->setIsTestDocument();
                } else {
                    $this->xdocument->setDocumentInformation($this->document->number, "389", date_create($this->document->date ?? now()->format('Y-m-d')), $client->getCurrencyCode());
                }
        }
        if (isset($this->document->po_number)) {
            $this->xdocument->setDocumentBuyerOrderReferencedDocument($this->document->po_number);
        }
        if (empty($client->routing_id)) {
            $this->xdocument->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        } else {
            $this->xdocument->setDocumentBuyerReference($client->routing_id)
                 ->setDocumentBuyerCommunication("0204", $client->routing_id);
        }
        if (isset($client->shipping_address1) && $client->shipping_country) {
            $this->xdocument->setDocumentShipToAddress($client->shipping_address1, $client->shipping_address2, "", $client->shipping_postal_code, $client->shipping_city, $client->shipping_country->iso_3166_2, $client->shipping_state);
        }
       
        $this->injectPaymentMeans($company);

        if (str_contains($company->getSetting('vat_number'), "/")) {
            $this->xdocument->addDocumentSellerTaxRegistration("FC", $company->getSetting('vat_number'));
        } else {
            $this->xdocument->addDocumentSellerTaxRegistration("VA", $company->getSetting('vat_number'));

        }
        if (!empty($client->vat_number)) {
            $this->xdocument->addDocumentBuyerTaxRegistration("VA", $client->vat_number);
        }

        $invoicing_data = $this->document->calc();
        //Create line items and calculate taxes
        foreach ($this->document->line_items as $index => $item) {
            /** @var InvoiceItem $item **/
            $this->xdocument->addNewPosition($index)
                ->setDocumentPositionNetPrice($item->cost);
            if (!empty($item->product_key)) {
                if (!empty($item->notes)) {
                    $this->xdocument->setDocumentPositionProductDetails($item->product_key, $item->notes);
                } else {
                    $this->xdocument->setDocumentPositionProductDetails($item->product_key);
                }
            } else {
                if (!empty($item->notes)) {
                    $this->xdocument->setDocumentPositionProductDetails($item->notes);
                } else {
                    $this->xdocument->setDocumentPositionProductDetails("no product name defined");
                }
            }
            if ($item->type_id == 2) {
                $this->xdocument->setDocumentPositionQuantity($item->quantity, "HUR");
            } else {
                $this->xdocument->setDocumentPositionQuantity($item->quantity, "H87");
            }
            $line_discount = 0.0;
            if ($item->discount > 0) {
                if ($this->document->is_amount_discount) {
                    $line_discount -= $item->discount;
                } else {
                    $line_discount -= $item->line_total * ($item->discount / 100);
                }
                $this->xdocument->addDocumentPositionGrossPriceAllowanceCharge(abs($line_discount), false);
            }

            $this->xdocument->setDocumentPositionLineSummation($item->line_total);
            if (isset($company->tax_data->regions->EU->has_sales_above_threshold) && !$company->tax_data->regions->EU->has_sales_above_threshold) {
                // According to european law, each line item can only have one tax rate
                if (!(empty($item->tax_name1) && empty($item->tax_name2) && empty($item->tax_name3))) {
                    $taxtype = $this->getTaxType($item->tax_id ?? 2);
                    if (!empty($item->tax_name1)) {
                        if ($taxtype == ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES) {
                            $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate1, exemptionReason: ctrans('texts.intracommunity_tax_info'));
                        } else {
                            $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate1);
                        }
                        $this->addtoTaxMap($taxtype, $item->line_total, $item->tax_rate1);
                    } elseif (!empty($item->tax_name2)) {
                        if ($taxtype == ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES) {
                            $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate2, exemptionReason: ctrans('texts.intracommunity_tax_info'));
                        } else {
                            $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate2);
                        }
                        $this->addtoTaxMap($taxtype, $item->line_total, $item->tax_rate2);
                    } elseif (!empty($item->tax_name3)) {
                        if ($taxtype == ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES) {
                            $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate3, exemptionReason: ctrans('texts.intracommunity_tax_info'));
                        } else {
                            $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate3);
                        }
                        $this->addtoTaxMap($taxtype, $item->line_total, $item->tax_rate3);
                    } else {
                        nlog("Can't add correct tax position");
                    }
                } else {
                    if (!empty($this->document->tax_name1)) {
                        $taxtype = $this->getTaxType($this->document->tax_name1);
                        $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $this->document->tax_rate1);
                        $this->addtoTaxMap($taxtype, $item->line_total, $this->document->tax_rate1);
                    } elseif (!empty($this->document->tax_name2)) {
                        $taxtype = $this->getTaxType($this->document->tax_name2);
                        $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $this->document->tax_rate2);
                        $this->addtoTaxMap($taxtype, $item->line_total, $this->document->tax_rate2);
                    } elseif (!empty($this->document->tax_name3)) {
                        $taxtype = $this->getTaxType($this->document->tax_name3);
                        $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', $this->document->tax_rate3);
                        $this->addtoTaxMap($taxtype, $item->line_total, $this->document->tax_rate3);
                    } else {
                        $taxtype = ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS;
                        $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', 0);
                        $this->addtoTaxMap($taxtype, $item->line_total, 0);
                        // nlog("Can't add correct tax position");
                    }
                }
            } else {
                $taxtype = ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
                $this->xdocument->addDocumentPositionTax($taxtype, 'VAT', 0, 0, ctrans('texts.vat_not_registered'), "VATNOTREG");
                $this->addtoTaxMap($taxtype, $item->line_total, 0);
            }
        }
        if ($this->document->is_amount_discount) {
            $document_discount = abs($this->document->discount);
        } else {
            $document_discount = $this->document->amount * $this->document->discount / 100;
        }

        if (isset($company->tax_data->regions->EU->has_sales_above_threshold) && !$company->tax_data->regions->EU->has_sales_above_threshold) {
            $this->xdocument->setDocumentSummation($this->document->amount, $this->document->balance, $invoicing_data->getSubTotal(), $invoicing_data->getTotalSurcharges(), $document_discount, $invoicing_data->getSubTotal() - $document_discount, $invoicing_data->getItemTotalTaxes(), 0.0, $this->document->amount - $this->document->balance);
            foreach ($this->tax_map as $item) {
                if ($document_discount > 0) {
                    if ($item["net_amount"] >= $document_discount) {
                        $item["net_amount"] -= $document_discount;
                        $this->xdocument->addDocumentAllowanceCharge($document_discount, false, $item["tax_type"], "VAT", $item["tax_rate"] * 100);
                    } else {
                        $document_discount -= $item["net_amount"];
                        $this->xdocument->addDocumentAllowanceCharge($item["net_amount"], false, $item["tax_type"], "VAT", $item["tax_rate"] * 100);
                        $item["net_amount"] = 0;

                    }
                }
                if ($item["tax_type"] == ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES) {
                    $this->xdocument->addDocumentTax($item["tax_type"], "VAT", $item["net_amount"], $item["tax_rate"] * $item["net_amount"], $item["tax_rate"] * 100, ctrans('texts.intracommunity_tax_info'));
                } else {
                    $this->xdocument->addDocumentTax($item["tax_type"], "VAT", $item["net_amount"], $item["tax_rate"] * $item["net_amount"], $item["tax_rate"] * 100);
                }

            }
        } else {
            $this->xdocument->addDocumentTax(ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX, "VAT", 0, 0, 0, ctrans('texts.vat_not_registered'), "VATNOTREG");
        }

        // The validity can be checked using https://portal3.gefeg.com/invoice/validation or https://e-rechnung.bayern.de/app/#/upload
        return $this;

    }
    
    /**
     * 
     * Expanded functionality to allow injecting UBL Payment Means
     * into the document
     * 
     * @return self
     */
    private function injectPaymentMeans(Company $company): self
    {

        /**Check if the e_invoice object is populated */
        if(isset($company->e_invoice->Invoice->PaymentMeans) && ($pm = $company->e_invoice->Invoice->PaymentMeans[0] ?? false)){

            switch ($pm->PaymentMeansCode->value ?? false) {
                case '30':
                case '58':
                    $iban = $pm->PayeeFinancialAccount->ID->value;
                    $name = $pm->PayeeFinancialAccount->Name ?? '';
                    $bic = $pm->PayeeFinancialAccount->FinancialInstitutionBranch->FinancialInstitution->ID->value ?? '';
                    $typecode = $pm->PaymentMeansCode->value;

                    $this->xdocument->addDocumentPaymentMean(typeCode: $typecode, payeeIban: $iban, payeeAccountName: $name, payeeBic: $bic);

                    return $this;
                
                default:
                    # code...
                    break;
            }

        }

        $custom_value1 = $company->settings->custom_value1;
        //BR-DE-23 - If „Payment means type code“ (BT-81) contains a code for credit transfer (30, 58), „CREDIT TRANSFER“ (BG-17) shall be provided.
        //Payment Means - Switcher
        if (isset($custom_value1) && !empty($custom_value1) && ($custom_value1 == '30' || $custom_value1 == '58')) {
            $this->xdocument->addDocumentPaymentMean(typeCode: $company->settings->custom_value1, payeeIban: $company->settings->custom_value2, payeeAccountName: $company->settings->custom_value4, payeeBic: $company->settings->custom_value3);
        } else {
            $this->xdocument->addDocumentPaymentMean('68', ctrans("texts.xinvoice_online_payment"));
        }

        return $this;
    }
    /**
     * Returns the XML document
     * in string format
     *
     * @return string
     */
    public function getXml(): string
    {
        return $this->xdocument->getContent();
    }

    private function getTaxType($name): string
    {
        $tax_type = null;
        switch ($name) {
            case Product::PRODUCT_TYPE_SERVICE:
            case Product::PRODUCT_TYPE_DIGITAL:
            case Product::PRODUCT_TYPE_PHYSICAL:
            case Product::PRODUCT_TYPE_SHIPPING:
            case Product::PRODUCT_TYPE_REDUCED_TAX:
                $tax_type = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type = ZugferdDutyTaxFeeCategories::EXEMPT_FROM_TAX;
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = ZugferdDutyTaxFeeCategories::ZERO_RATED_GOODS;
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = ZugferdDutyTaxFeeCategories::VAT_REVERSE_CHARGE;
                break;
        }
        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE", "IS", "LI", "NO", "CH"];
        if (empty($tax_type)) {
            if ((in_array($this->document->company->country()->iso_3166_2, $eu_states) && in_array($this->document->client->country->iso_3166_2, $eu_states)) && $this->document->company->country()->iso_3166_2 != $this->document->client->country->iso_3166_2) {
                $tax_type = ZugferdDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            } elseif (!in_array($this->document->client->country->iso_3166_2, $eu_states)) {
                $tax_type = ZugferdDutyTaxFeeCategories::SERVICE_OUTSIDE_SCOPE_OF_TAX;
            } elseif ($this->document->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = ZugferdDutyTaxFeeCategories::CANARY_ISLANDS_GENERAL_INDIRECT_TAX;
            } elseif (in_array($this->document->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = ZugferdDutyTaxFeeCategories::TAX_FOR_PRODUCTION_SERVICES_AND_IMPORTATION_IN_CEUTA_AND_MELILLA;
            } else {
                nlog("Unkown tax case for xinvoice");
                $tax_type = ZugferdDutyTaxFeeCategories::STANDARD_RATE;
            }
        }
        return $tax_type;
    }
    private function addtoTaxMap(string $tax_type, float $net_amount, float $tax_rate): void
    {
        $hash = hash("md5", $tax_type."-".$tax_rate);
        if (array_key_exists($hash, $this->tax_map)) {
            $this->tax_map[$hash]["net_amount"] += $net_amount;
        } else {
            $this->tax_map[$hash] = [
                "tax_type" => $tax_type,
                "net_amount" => $net_amount,
                "tax_rate" => $tax_rate / 100
            ];
        }
    }

}

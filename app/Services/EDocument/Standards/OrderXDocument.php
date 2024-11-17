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

use App\Models\Credit;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Services\AbstractService;
use horstoeko\orderx\codelists\OrderDocumentTypes;
use horstoeko\orderx\codelists\OrderDutyTaxFeeCategories;
use horstoeko\orderx\OrderDocumentBuilder;
use horstoeko\orderx\OrderProfiles;

class OrderXDocument extends AbstractService
{
    public OrderDocumentBuilder $orderxdocument;

    /**
     * __construct
     *
     * @param  \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit $document
     * @param  bool $returnObject
     * @param  array $tax_map
     * @return void
     */
    public function __construct(public \App\Models\Invoice | \App\Models\Quote | \App\Models\PurchaseOrder | \App\Models\Credit  $document, private readonly bool $returnObject = false, private array $tax_map = [])
    {
    }

    public function run(): self
    {

        $company = $this->document->company;
        $settings_entity = ($this->document instanceof PurchaseOrder) ? $this->document->vendor : $this->document->client;
        $profile = $settings_entity->getSetting('e_quote_type') ? $settings_entity->getSetting('e_quote_type') : "OrderX_Extended";

        $profile = match ($profile) {
            "OrderX_Basic" => OrderProfiles::PROFILE_BASIC,
            "OrderX_Comfort" => OrderProfiles::PROFILE_COMFORT,
            "OrderX_Extended" => OrderProfiles::PROFILE_EXTENDED,
            default => OrderProfiles::PROFILE_EXTENDED,
        };

        $this->orderxdocument = OrderDocumentBuilder::CreateNew($profile);

        $this->orderxdocument
            ->setDocumentSeller($company->getSetting('name'))
            ->setDocumentSellerAddress($company->getSetting("address1"), $company->getSetting("address2"), "", $company->getSetting("postal_code"), $company->getSetting("city"), $company->country()->iso_3166_2, $company->getSetting("state"))
            ->setDocumentSellerContact($this->document->user->present()->getFullName(), "", $this->document->user->present()->phone(), "", $this->document->user->email)
            ->setDocumentBuyer($settings_entity->present()->name(), $settings_entity->number)
            ->setDocumentBuyerAddress($settings_entity->address1, "", "", $settings_entity->postal_code, $settings_entity->city, $settings_entity->country->iso_3166_2 ?? $company->country()->iso_3166_2, $settings_entity->state)
            ->setDocumentBuyerContact($settings_entity->present()->primary_contact_name(), "", $settings_entity->present()->phone(), "", $settings_entity->present()->email())
            ->addDocumentPaymentTerm(ctrans("texts.xinvoice_payable", ['payeddue' => date_create($this->document->date ?? now()->format('Y-m-d'))->diff(date_create($this->document->due_date ?? now()->format('Y-m-d')))->format("%d"), 'paydate' => $this->document->due_date]));

        if (!empty($this->document->public_notes)) {
            $this->orderxdocument->addDocumentNote($this->document->public_notes ?? '');
        }
        // Document type
        $document_class = get_class($this->document);
        switch ($document_class) {
            case Quote::class:
                // Probably wrong file code https://github.com/horstoeko/zugferd/blob/master/src/codelists/ZugferdInvoiceType.php
                if (empty($this->document->number)) {
                    $this->orderxdocument->setDocumentInformation("DRAFT", OrderDocumentTypes::ORDER, date_create($this->document->date ?? now()->format('Y-m-d')), $settings_entity->getCurrencyCode());
                    $this->orderxdocument->setIsTestDocument(true);
                } else {
                    $this->orderxdocument->setDocumentInformation($this->document->number, OrderDocumentTypes::ORDER, date_create($this->document->date ?? now()->format('Y-m-d')), $settings_entity->getCurrencyCode());
                };
                break;
            case PurchaseOrder::class:
                if (empty($this->document->number)) {
                    $this->orderxdocument->setDocumentInformation("DRAFT", OrderDocumentTypes::ORDER_RESPONSE, date_create($this->document->date ?? now()->format('Y-m-d')), $settings_entity->getCurrencyCode());
                    $this->orderxdocument->setIsTestDocument(true);
                } else {
                    $this->orderxdocument->setDocumentInformation($this->document->number, OrderDocumentTypes::ORDER_RESPONSE, date_create($this->document->date ?? now()->format('Y-m-d')), $settings_entity->getCurrencyCode());
                }
                break;
        }
        if (isset($this->document->po_number)) {
            $this->orderxdocument->setDocumentBuyerOrderReferencedDocument($this->document->po_number);
        }

        if (empty($settings_entity->routing_id)) {
            $this->orderxdocument->setDocumentBuyerReference(ctrans("texts.xinvoice_no_buyers_reference"));
        } else {
            $this->orderxdocument->setDocumentBuyerReference($settings_entity->routing_id);
        }
        if (isset($settings_entity->shipping_address1) && $settings_entity->shipping_country) {
            $this->orderxdocument->setDocumentShipToAddress($settings_entity->shipping_address1, $settings_entity->shipping_address2, "", $settings_entity->shipping_postal_code, $settings_entity->shipping_city, $settings_entity->shipping_country->iso_3166_2, $settings_entity->shipping_state);
        }

        $this->orderxdocument->addDocumentPaymentMean('68', ctrans("texts.xinvoice_online_payment"));

        if (str_contains($company->getSetting('vat_number'), "/")) {
            $this->orderxdocument->addDocumentSellerTaxRegistration("FC", $company->getSetting('vat_number'));
        } else {
            $this->orderxdocument->addDocumentSellerTaxRegistration("VA", $company->getSetting('vat_number'));
        }

        $invoicing_data = $this->document->calc();

        //Create line items and calculate taxes
        foreach ($this->document->line_items as $index => $item) {
            /** @var \App\DataMapper\InvoiceItem $item **/
            $this->orderxdocument->addNewPosition($index)
                ->setDocumentPositionGrossPrice($item->gross_line_total)
                ->setDocumentPositionNetPrice($item->cost);
            if (!empty($item->product_key)) {
                if (!empty($item->notes)) {
                    $this->orderxdocument->setDocumentPositionProductDetails($item->product_key, $item->notes);
                } else {
                    $this->orderxdocument->setDocumentPositionProductDetails($item->product_key);
                }
            } else {
                if (!empty($item->notes)) {
                    $this->orderxdocument->setDocumentPositionProductDetails($item->notes);
                } else {
                    $this->orderxdocument->setDocumentPositionProductDetails("no product name defined");
                }
            }
            // TODO: add item classification (kg, m^3, ...)
            //            if (isset($item->task_id)) {
            //                $this->orderxdocument->setDocumentPositionQuantity($item->quantity, "HUR");
            //            } else {
            //                $this->orderxdocument->setDocumentPositionQuantity($item->quantity, "H87");
            //            }
            $linenetamount = $item->line_total;
            if ($item->discount > 0) {
                if ($this->document->is_amount_discount) {
                    $linenetamount -= $item->discount;
                } else {
                    $linenetamount -= $linenetamount * ($item->discount / 100);
                }
            }
            $this->orderxdocument->setDocumentPositionLineSummation($linenetamount);
            // According to european law, each line item can only have one tax rate
            if (!(empty($item->tax_name1) && empty($item->tax_name2) && empty($item->tax_name3))) {
                $taxtype = $this->getTaxType($item->tax_id);
                if (!empty($item->tax_name1)) {
                    $this->orderxdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate1);
                    $this->addtoTaxMap($taxtype, $linenetamount, $item->tax_rate1);
                } elseif (!empty($item->tax_name2)) {
                    $this->orderxdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate2);
                    $this->addtoTaxMap($taxtype, $linenetamount, $item->tax_rate2);
                } elseif (!empty($item->tax_name3)) {
                    $this->orderxdocument->addDocumentPositionTax($taxtype, 'VAT', $item->tax_rate3);
                    $this->addtoTaxMap($taxtype, $linenetamount, $item->tax_rate3);
                } else {
                    nlog("Can't add correct tax position");
                }
            } else {
                if (!empty($this->document->tax_name1)) {
                    $taxtype = $this->getTaxType($this->document->tax_name1);
                    $this->orderxdocument->addDocumentPositionTax($taxtype, 'VAT', $this->document->tax_rate1);
                    $this->addtoTaxMap($taxtype, $linenetamount, $this->document->tax_rate1);
                } elseif (!empty($this->document->tax_name2)) {
                    $taxtype = $this->getTaxType($this->document->tax_name2);
                    $this->orderxdocument->addDocumentPositionTax($taxtype, 'VAT', $this->document->tax_rate2);
                    $this->addtoTaxMap($taxtype, $linenetamount, $this->document->tax_rate2);
                } elseif (!empty($this->document->tax_name3)) {
                    $taxtype = $this->getTaxType($this->document->tax_name3);
                    $this->orderxdocument->addDocumentPositionTax($taxtype, 'VAT', $this->document->tax_rate3);
                    $this->addtoTaxMap($taxtype, $linenetamount, $this->document->tax_rate3);
                } else {
                    $taxtype = OrderDutyTaxFeeCategories::ZERO_RATED_GOODS;
                    $this->orderxdocument->addDocumentPositionTax($taxtype, 'VAT', 0);
                    $this->addtoTaxMap($taxtype, $linenetamount, 0);
                    // nlog("Can't add correct tax position");
                }
            }
        }

        $this->orderxdocument->setDocumentSummation(
            $this->document->amount,
            $this->document->balance,
            $invoicing_data->getSubTotal(),
            $invoicing_data->getTotalSurcharges(),
            // $invoicing_data->getTotalDiscount(),
            $invoicing_data->getSubTotal(),
            $invoicing_data->getItemTotalTaxes(),
            // 0.0,
            // ($this->document->amount - $this->document->balance)
        );

        foreach ($this->tax_map as $item) {
            $this->orderxdocument->addDocumentTax($item["tax_type"], "VAT", $item["net_amount"], $item["tax_rate"] * $item["net_amount"], $item["tax_rate"] * 100);
        }

        // The validity can be checked using https://portal3.gefeg.com/invoice/validation or https://e-rechnung.bayern.de/app/#/upload
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
        return $this->orderxdocument->getContent();
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
                $tax_type = OrderDutyTaxFeeCategories::STANDARD_RATE;
                break;
            case Product::PRODUCT_TYPE_EXEMPT:
                $tax_type =  OrderDutyTaxFeeCategories::EXEMPT_FROM_TAX;
                break;
            case Product::PRODUCT_TYPE_ZERO_RATED:
                $tax_type = OrderDutyTaxFeeCategories::ZERO_RATED_GOODS;
                break;
            case Product::PRODUCT_TYPE_REVERSE_TAX:
                $tax_type = OrderDutyTaxFeeCategories::VAT_REVERSE_CHARGE;
                break;
        }
        $eu_states = ["AT", "BE", "BG", "HR", "CY", "CZ", "DK", "EE", "FI", "FR", "DE", "EL", "GR", "HU", "IE", "IT", "LV", "LT", "LU", "MT", "NL", "PL", "PT", "RO", "SK", "SI", "ES", "SE", "IS", "LI", "NO", "CH"];
        if (empty($tax_type)) {
            if ((in_array($this->document->company->country()->iso_3166_2, $eu_states) && in_array($this->document->client->country->iso_3166_2, $eu_states)) && $this->document->company->country()->iso_3166_2 != $this->document->client->country->iso_3166_2) {
                $tax_type = OrderDutyTaxFeeCategories::VAT_EXEMPT_FOR_EEA_INTRACOMMUNITY_SUPPLY_OF_GOODS_AND_SERVICES;
            } elseif (!in_array($this->document->client->country->iso_3166_2, $eu_states)) {
                $tax_type = OrderDutyTaxFeeCategories::SERVICE_OUTSIDE_SCOPE_OF_TAX;
            } elseif ($this->document->client->country->iso_3166_2 == "ES-CN") {
                $tax_type = OrderDutyTaxFeeCategories::CANARY_ISLANDS_GENERAL_INDIRECT_TAX;
            } elseif (in_array($this->document->client->country->iso_3166_2, ["ES-CE", "ES-ML"])) {
                $tax_type = OrderDutyTaxFeeCategories::TAX_FOR_PRODUCTION_SERVICES_AND_IMPORTATION_IN_CEUTA_AND_MELILLA;
            } else {
                nlog("Unkown tax case for xinvoice");
                $tax_type = OrderDutyTaxFeeCategories::STANDARD_RATE;
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

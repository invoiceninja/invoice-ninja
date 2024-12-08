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

use App\Models\Invoice;
use App\Models\PaymentType;
use App\Services\AbstractService;
use Illuminate\Support\Facades\Storage;
use josemmo\Facturae\Facturae;
use josemmo\Facturae\FacturaeCentre;
use josemmo\Facturae\FacturaeItem;
use josemmo\Facturae\FacturaeParty;
use josemmo\Facturae\FacturaePayment;

class FacturaEInvoice extends AbstractService
{
    private Facturae $fac;

    private $calc;

    private $centre_codes = [
        'CONTABLE' => FacturaeCentre::ROLE_CONTABLE,
        'FISCAL' => FacturaeCentre::ROLE_FISCAL,
        'GESTOR' => FacturaeCentre::ROLE_GESTOR,
        'RECEPTOR' => FacturaeCentre::ROLE_RECEPTOR,
        'TRAMITADOR' => FacturaeCentre::ROLE_TRAMITADOR,
        'PAGADOR' => FacturaeCentre::ROLE_PAGADOR,
        'PROPONENTE' => FacturaeCentre::ROLE_PAGADOR,
        'B2B_FISCAL' => FacturaeCentre::ROLE_B2B_FISCAL,
        'B2B_PAYER' => FacturaeCentre::ROLE_B2B_PAYER,
        'B2B_BUYER' => FacturaeCentre::ROLE_B2B_BUYER,
        'B2B_COLLECTOR' => FacturaeCentre::ROLE_B2B_COLLECTOR,
        'B2B_SELLER' => FacturaeCentre::ROLE_B2B_SELLER,
        'B2B_PAYMENT_RECEIVER' => FacturaeCentre::ROLE_B2B_PAYMENT_RECEIVER ,
        'B2B_COLLECTION_RECEIVER' => FacturaeCentre::ROLE_B2B_COLLECTION_RECEIVER ,
        'B2B_ISSUER' => FacturaeCentre::ROLE_B2B_ISSUER,
    ];

    // Facturae::SCHEMA_3_2	Invoice Format 3.2
    // Facturae::SCHEMA_3_2_1	Invoice Format 3.2.1
    // Facturae::SCHEMA_3_2_2	Invoice Format 3.2.2

    // Facturae::ISSUER_SELLER	Provider (issuer)
    // Facturae::ISSUER_BUYER	Recipient (receiver)
    // Facturae::ISSUER_THIRD_PARTY	Third

    // Facturae::PRECISION_LINE	Line level accuracy
    // Facturae::PRECISION_INVOICE	Invoice level accuracy

    // CorrectiveDetails::METHOD_FULL	Full rectification
    // CorrectiveDetails::METHOD_DIFFERENCES	Rectification for differences
    // CorrectiveDetails::METHOD_VOLUME_DISCOUNT	Rectification for discount by volume of operations during a period
    // CorrectiveDetails::METHOD_AEAT_AUTHORIZED	Authorized by the Tax Agency


    // FacturaePayment::TYPE_CASH	Cash
    // FacturaePayment::TYPE_DEBIT	Domiciled receipt
    // FacturaePayment::TYPE_RECEIPT	Receipt
    // FacturaePayment::TYPE_TRANSFER	Transfer
    // FacturaePayment::TYPE_ACCEPTED_BILL_OF_EXCHANGE	Letter Accepted
    // FacturaePayment::TYPE_DOCUMENTARY_CREDIT	Letter of credit
    // FacturaePayment::TYPE_CONTRACT_AWARD	contract award
    // FacturaePayment::TYPE_BILL_OF_EXCHANGE	Bill of exchange
    // FacturaePayment::TYPE_TRANSFERABLE_IOU	I will pay to order
    // FacturaePayment::TYPE_IOU	I Will Pay Not To Order
    // FacturaePayment::TYPE_CHEQUE	Check
    // FacturaePayment::TYPE_REIMBURSEMENT	Replacement
    // FacturaePayment::TYPE_SPECIAL	specials
    // FacturaePayment::TYPE_SETOFF	Compensation
    // FacturaePayment::TYPE_POSTGIRO	Money order
    // FacturaePayment::TYPE_CERTIFIED_CHEQUE	conformed check
    // FacturaePayment::TYPE_BANKERS_DRAFT	Bank check
    // FacturaePayment::TYPE_CASH_ON_DELIVERY	Cash on delivery
    // FacturaePayment::TYPE_CARD	Payment by card

    // Facturae::TAX_IVA	Value Added Tax
    // Facturae::TAX_IPSI	Tax on production, services and imports
    // Facturae::TAX_IGIC	General indirect tax of the Canary Islands
    // Facturae::TAX_IRPF	Personal Income Tax
    // Facturae::TAX_OTHER	Other
    // Facturae::TAX_ITPAJD	Transfer tax and stamp duty
    // Facturae::TAX_IE	Special taxes
    // Facturae::TAX_RA	Customs rent
    // Facturae::TAX_IGTECM	General tax on business traffic that is applied in Ceuta and Melilla
    // Facturae::TAX_IECDPCAC	Special tax on fuels derived from petroleum in the Autonomous Community of the Canary Islands
    // Facturae::TAX_IIIMAB	Tax on facilities that affect the environment in the Balearic Islands
    // Facturae::TAX_ICIO	Tax on constructions, facilities and works
    // Facturae::TAX_IMVDN	Municipal tax on unoccupied homes in Navarra
    // Facturae::TAX_IMSN	Municipal tax on plots in Navarra
    // Facturae::TAX_IMGSN	Municipal tax on luxury expenses in Navarra
    // Facturae::TAX_IMPN	Municipal tax on advertising in Navarra
    // Facturae::TAX_REIVA	Special VAT regime for travel agencies
    // Facturae::TAX_REIGIC	Special IGIC regime: for travel agencies
    // Facturae::TAX_REIPSI	Special IPSI regime for travel agencies
    // Facturae::TAX_IPS	Taxes on insurance premiums
    // Facturae::TAX_RLEA	Surcharge intended to finance the liquidation functions of insurance entities
    // Facturae::TAX_IVPEE	Tax on the value of electricity production
    // Facturae::TAX_IPCNG	Tax on the production of spent nuclear fuel and radioactive waste resulting from the generation of nuclear power
    // Facturae::TAX_IACNG	Tax on the storage of spent nuclear fuel and radioactive waste in centralized facilities
    // Facturae::TAX_IDEC	Tax on Deposits in Credit Institutions
    // Facturae::TAX_ILTCAC	Tax on tobacco products in the Autonomous Community of the Canary Islands
    // Facturae::TAX_IGFEI	Tax on Fluorinated Greenhouse Gases
    // Facturae::TAX_IRNR	Non-Resident Income Tax
    // Facturae::TAX_ISS	Corporate tax

    // FacturaeItem::SPECIAL_TAXABLE_EVENT_EXEMPT	Subject and exempt operation
    // FacturaeItem::SPECIAL_TAXABLE_EVENT_NON_SUBJECT	Operation not subject

    // FacturaeCentre::ROLE_CONTABLE
    // eitherFacturaeCentre::ROLE_FISCAL	Accounting Office
    // FacturaeCentre::ROLE_GESTOR
    // eitherFacturaeCentre::ROLE_RECEPTOR	managing body
    // FacturaeCentre::ROLE_TRAMITADOR
    // eitherFacturaeCentre::ROLE_PAGADOR	processing unit
    // FacturaeCentre::ROLE_PROPONENTE	proposing body
    // FacturaeCentre::ROLE_B2B_FISCAL	Fiscal receiver in FACeB2B
    // FacturaeCentre::ROLE_B2B_PAYER	Payer in FACeB2B
    // FacturaeCentre::ROLE_B2B_BUYER	Buyer at FACeB2B
    // FacturaeCentre::ROLE_B2B_COLLECTOR	Collector at FACeB2B
    // FacturaeCentre::ROLE_B2B_SELLER	Seller at FACeB2B
    // FacturaeCentre::ROLE_B2B_PAYMENT_RECEIVER	Payment recipient in FACeB2B
    // FacturaeCentre::ROLE_B2B_COLLECTION_RECEIVER	Collection receiver in FACeB2B
    // FacturaeCentre::ROLE_B2B_ISSUER	Issuer in FACeB2B

    /*
    const ROLE_CONTABLE = "01";
    const ROLE_FISCAL = "01";
    const ROLE_GESTOR = "02";
    const ROLE_RECEPTOR = "02";
    const ROLE_TRAMITADOR = "03";
    const ROLE_PAGADOR = "03";
    const ROLE_PROPONENTE = "04";

    const ROLE_B2B_FISCAL = "Fiscal";
    const ROLE_B2B_PAYER = "Payer";
    const ROLE_B2B_BUYER = "Buyer";
    const ROLE_B2B_COLLECTOR = "Collector";
    const ROLE_B2B_SELLER = "Seller";
    const ROLE_B2B_PAYMENT_RECEIVER = "Payment receiver";
    const ROLE_B2B_COLLECTION_RECEIVER = "Collection receiver";
    const ROLE_B2B_ISSUER = "Issuer";
    */


    public function __construct(public Invoice $invoice, private mixed $profile)
    {
    }

    public function run()
    {

        $this->calc = $this->invoice->calc();

        $this->fac = new Facturae($this->profile);
        $this->fac->setNumber('', $this->invoice->number);
        $this->fac->setIssueDate($this->invoice->date);
        $this->fac->setPrecision(Facturae::PRECISION_LINE);

        $this->buildBuyer()
             ->buildSeller()
             ->buildItems()
             ->setDiscount()
             ->setPoNumber()
             ->setLegalTerms()
             ->setPayments()
             ->setBillingPeriod()
             ->signDocument();

        return $this->fac->export();

    }

    /** Check if this is a public administration body */
    private function setFace(): array
    {
        $facturae_centres = [];

        if ($this->invoice->client->custom_value1 == 'yes') {

            foreach ($this->invoice->client->contacts()->whereNotNull('custom_value1')->whereNull('deleted_at')->cursor() as $contact) {

                if (in_array($contact->custom_value1, array_keys($this->centre_codes))) {
                    $facturae_centres[] = new FacturaeCentre([
                        'role' => $this->centre_codes[$contact->custom_value1],
                        'code' => $contact->custom_value2,
                        'name' => $contact->custom_value3,
                    ]);
                }

            }

        }

        return $facturae_centres;
    }

    private function setPoNumber(): self
    {
        $po = $this->invoice->po_number ?? '';
        $transaction_reference = (isset($this->invoice->custom_value1) && strlen($this->invoice->custom_value1) > 2) ? substr($this->invoice->custom_value1, 0, 20) : null;
        $contract_reference = (isset($this->invoice->custom_value2) && strlen($this->invoice->custom_value2) > 2) ? $this->invoice->custom_value2 : null;

        $this->fac->setReferences($po, $transaction_reference, $contract_reference);

        return $this;
    }

    private function setDiscount(): self
    {
        if ($this->invoice->discount > 0) {
            $this->fac->addDiscount(ctrans('texts.discount'), $this->calc->getTotalDiscount());
        }

        return $this;
    }

    private function setLegalTerms(): self
    {
        $this->fac->addLegalLiteral(substr($this->invoice->public_notes, 0, 250));

        return $this;
    }

    private function setBillingPeriod(): self
    {
        if (!$this->invoice->custom_value3) {
            return $this;
        }

        try {
            if (\Carbon\Carbon::createFromFormat('Y-m-d', $this->invoice->custom_value3)->format('Y-m-d') === $this->invoice->custom_value3 &&
            \Carbon\Carbon::createFromFormat('Y-m-d', $this->invoice->custom_value4)->format('Y-m-d') === $this->invoice->custom_value4
            ) {
                $this->fac->setBillingPeriod(\Carbon\Carbon::parse($this->invoice->custom_value3)->format('Y-m-d'), \Carbon\Carbon::parse($this->invoice->custom_value4)->format('Y-m-d'));
            }
        } catch (\Exception $e) {
            nlog($e->getMessage());
        }

        return $this;
    }

    private function setPayments(): self
    {
        $this->invoice->payments()->each(function ($payment) {

            $payment_data = [
                "dueDate" => \Carbon\Carbon::parse($payment->date)->format('Y-m-d'),
                "amount"  => $payment->pivot->amount,
            ];

            $data = array_merge($this->resolvePaymentMethod($payment), $payment_data);

            $this->fac->addPayment(new FacturaePayment($data));

        });

        return $this;
    }

    /**
     *
     * FacturaePayment::TYPE_CASH	Cash
     * FacturaePayment::TYPE_DEBIT	Domiciled receipt
     * FacturaePayment::TYPE_RECEIPT	Receipt
     * FacturaePayment::TYPE_TRANSFER	Transfer
     * FacturaePayment::TYPE_ACCEPTED_BILL_OF_EXCHANGE	Letter Accepted
     * FacturaePayment::TYPE_DOCUMENTARY_CREDIT	Letter of credit
     * FacturaePayment::TYPE_CONTRACT_AWARD	contract award
     * FacturaePayment::TYPE_BILL_OF_EXCHANGE	Bill of exchange
     * FacturaePayment::TYPE_TRANSFERABLE_IOU	I will pay to order
     * FacturaePayment::TYPE_IOU	I Will Pay Not To Order
     * FacturaePayment::TYPE_CHEQUE	Check
     * FacturaePayment::TYPE_REIMBURSEMENT	Replacement
     * FacturaePayment::TYPE_SPECIAL	specials
     * FacturaePayment::TYPE_SETOFF	Compensation
     * FacturaePayment::TYPE_POSTGIRO	Money order
     * FacturaePayment::TYPE_CERTIFIED_CHEQUE	conformed check
     * FacturaePayment::TYPE_BANKERS_DRAFT	Bank check
     * FacturaePayment::TYPE_CASH_ON_DELIVERY	Cash on delivery
     * FacturaePayment::TYPE_CARD	Payment by card
     *
     * @param \App\Models\Payment $payment
     * @return array
     */
    private function resolvePaymentMethod(\App\Models\Payment $payment): array
    {
        $data = [];
        $method = FacturaePayment::TYPE_CARD;

        match($payment->type_id) {
            PaymentType::BANK_TRANSFER => $method = FacturaePayment::TYPE_TRANSFER	,
            PaymentType::CASH => $method = FacturaePayment::TYPE_CASH	,
            PaymentType::ACH => $method = FacturaePayment::TYPE_TRANSFER	,
            PaymentType::VISA => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::MASTERCARD => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::AMERICAN_EXPRESS => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::DISCOVER => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::DINERS => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::EUROCARD => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::NOVA => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::CREDIT_CARD_OTHER => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::PAYPAL => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::CHECK => $method = FacturaePayment::TYPE_CHEQUE	,
            PaymentType::CARTE_BLANCHE => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::UNIONPAY => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::JCB => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::LASER => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::MAESTRO => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::SOLO => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::SWITCH => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::VENMO => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::ALIPAY => $method = FacturaePayment::TYPE_CARD	,
            PaymentType::SOFORT => $method =  FacturaePayment::TYPE_TRANSFER,
            PaymentType::SEPA => $method = FacturaePayment::TYPE_TRANSFER,
            PaymentType::GOCARDLESS => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::CRYPTO => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::CREDIT => $method = FacturaePayment::TYPE_DOCUMENTARY_CREDIT	,
            PaymentType::ZELLE => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::MOLLIE_BANK_TRANSFER => $method = FacturaePayment::TYPE_TRANSFER	,
            PaymentType::KBC => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::BANCONTACT => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::IDEAL => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::HOSTED_PAGE => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::GIROPAY => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::PRZELEWY24 => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::EPS => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::DIRECT_DEBIT => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::BECS => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::ACSS => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::INSTANT_BANK_PAY => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::FPX => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::KLARNA => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::Interac_E_Transfer => $method = FacturaePayment::TYPE_TRANSFER	,
            PaymentType::BACS => $method = FacturaePayment::TYPE_SPECIAL	,
            PaymentType::STRIPE_BANK_TRANSFER => $method = FacturaePayment::TYPE_TRANSFER	,
            PaymentType::CASH_APP => $method = FacturaePayment::TYPE_SPECIAL	,
            default => $method = FacturaePayment::TYPE_CARD	,
        };

        $data['method'] = $method;

        if ($method == FacturaePayment::TYPE_TRANSFER) {
            $data['iban'] = $payment->custom_value1;
            $data['bic'] = $payment->custom_value2;
        }

        return $data;


    }

    private function buildItems(): self
    {

        foreach ($this->invoice->line_items as $item) {
            $this->fac->addItem(new FacturaeItem([
                'name' => $item->product_key,
                'description' => $item->notes,
                'quantity' => $item->quantity,
                'unitPriceWithoutTax' => $item->cost,
                'discountsAndRebates' => $item->discount,
                'charges' => [],
                'discounts' => [],
                'taxes' => $this->buildRatesForItem($item),
                // 'specialTaxableEvent' => FacturaeItem::SPECIAL_TAXABLE_EVENT_NON_SUBJECT,
                // 'specialTaxableEventReason' => '',
                // 'specialTaxableEventReasonDescription' => '',
            ]));

        }

        return $this;

    }

    private function buildRatesForItem(\stdClass $item): array
    {
        $data = [];

        if (strlen($item->tax_name1) > 1) {

            $data[$this->resolveTaxCode($item->tax_name1)] = $item->tax_rate1;

        }

        if (strlen($item->tax_name2) > 1) {


            $data[$this->resolveTaxCode($item->tax_name2)] = $item->tax_rate2;

        }

        if (strlen($item->tax_name3) > 1) {


            $data[$this->resolveTaxCode($item->tax_name3)] = $item->tax_rate3;

        }

        if (count($data) == 0) {
            $data[Facturae::TAX_IVA] = 0;
        }

        return $data;
    }

    private function resolveTaxCode(string $tax_name)
    {
        return match (strtoupper($tax_name)) {
            'IVA' => Facturae::TAX_IVA,
            'IPSI' => Facturae::TAX_IPSI,
            'IGIC' => Facturae::TAX_IGIC,
            'IRPF' => Facturae::TAX_IRPF,
            'IRNR' => Facturae::TAX_IRNR,
            'ISS' => Facturae::TAX_ISS,
            'REIVA' => Facturae::TAX_REIVA,
            'REIGIC' => Facturae::TAX_REIGIC,
            'REIPSI' => Facturae::TAX_REIPSI,
            'IPS' => Facturae::TAX_IPS,
            'RLEA' => Facturae::TAX_RLEA,
            'IVPEE' => Facturae::TAX_IVPEE,
            'IPCNG' => Facturae::TAX_IPCNG,
            'IACNG' => Facturae::TAX_IACNG,
            'IDEC' => Facturae::TAX_IDEC,
            'ILTCAC' => Facturae::TAX_ILTCAC,
            'IGFEI' => Facturae::TAX_IGFEI,
            'ISS' => Facturae::TAX_ISS,
            'IMGSN' => Facturae::TAX_IMGSN,
            'IMSN' => Facturae::TAX_IMSN,
            'IMPN' => Facturae::TAX_IMPN,
            'IIIMAB' => Facturae::TAX_IIIMAB,
            'ICIO' => Facturae::TAX_ICIO,
            'IECDPCAC' => Facturae::TAX_IECDPCAC,
            'IGTECM' => Facturae::TAX_IGTECM,
            'IE' => Facturae::TAX_IE,
            'RA' => Facturae::TAX_RA,
            'ITPAJD' => Facturae::TAX_ITPAJD,
            'OTHER' => Facturae::TAX_OTHER,
            'IMVDN' => Facturae::TAX_IMVDN,
            default => Facturae::TAX_IVA,

        };
    }

    private function buildSeller(): self
    {
        $company = $this->invoice->company;

        if ($company->getSetting('classification') == 'individual') {
            return $this->setIndividualSeller();
        }

        $seller = new FacturaeParty([
            "isLegalEntity" => true,
            "taxNumber" => $company->settings->vat_number,
            "name" => substr($company->present()->name(), 0, 40),
            "address" => substr($company->settings->address1, 0, 80),
            "postCode" => substr($this->invoice->client->postal_code, 0, 5),
            "town" => substr($company->settings->city, 0, 50),
            "province" => substr($company->settings->state, 0, 20),
            "countryCode" => $company->country()->iso_3166_3,  // Se asume España si se omite
            "book" => "0",  // Libro
            "merchantRegister" => "RG", // Registro Mercantil
            "sheet" => "1",  // Hoja
            "folio" => "2",  // Folio
            "section" => "3",  // Sección
            "volume" => "4",  // Tomo
            "email" => substr($company->settings->email, 0, 60),
            "phone" => substr($company->settings->phone, 0, 15),
            "fax" => "",
            "website" => substr($company->settings->website, 0, 50),
            "contactPeople" => substr($company->owner()->present()->name(), 0, 40),
            "firstSurname" => $company->owner()->present()->firstName(),
            "lastSurname" => $company->owner()->present()->lastName(),
            // 'centres' => $this->setFace(),
            // "cnoCnae" => "04647", // Clasif. Nacional de Act. Económicas
            // "ineTownCode" => "280796" // Cód. de municipio del INE
        ]);

        $this->fac->setSeller($seller);

        return $this;
    }


    private function setIndividualSeller(): self
    {

        $company = $this->invoice->company;

        $seller = new FacturaeParty([
            "isLegalEntity" => false,
            "taxNumber" => $company->settings->vat_number,
            // "name" => $company->getSetting('classification') === 'individual' ? substr($company->owner()->present()->name(), 0, 40) : substr($company->present()->name(), 0, 40),
            "address" => substr($company->settings->address1, 0, 80),
            "postCode" => substr($this->invoice->client->postal_code, 0, 5),
            "town" => substr($company->settings->city, 0, 50),
            "province" => substr($company->settings->state, 0, 20),
            "countryCode" => $company->country()->iso_3166_3,  // Se asume España si se omite
            // "book" => "0",  // Libro
            // "merchantRegister" => "RG", // Registro Mercantil
            // "sheet" => "1",  // Hoja
            // "folio" => "2",  // Folio
            // "section" => "3",  // Sección
            // "volume" => "4",  // Tomo
            "email" => substr($company->settings->email, 0, 60),
            "phone" => substr($company->settings->phone, 0, 15),
            "fax" => "",
            "website" => substr($company->settings->website, 0, 50),
            // "contactPeople" => substr($company->owner()->present()->name(), 0, 40),
            "name" => $company->owner()->present()->firstName(),
            "firstSurname" => $company->owner()->present()->lastName(),
            // "lastSurname" => $company->owner()->present()->lastName(),
        ]);

        $this->fac->setSeller($seller);

        return $this;


    }


    private function buildBuyer(): self
    {
        $buyer_array = [
            "isLegalEntity" => $this->invoice->client->classification === 'individual' ? false : true,
            "taxNumber"     => $this->invoice->client->vat_number,
            "name"          => substr($this->invoice->client->present()->name(), 0, 40),
            // "firstSurname"  => substr($this->invoice->client->present()->last_name(), 0, 40),
            // "lastSurname"   => substr($this->invoice->client->present()->last_name(), 0, 40),
            "address"       => substr($this->invoice->client->address1, 0, 80),
            "postCode"      => substr($this->invoice->client->postal_code, 0, 5),
            "town"          => substr($this->invoice->client->city, 0, 50),
            "province"      => substr($this->invoice->client->state, 0, 20),
            "countryCode"   => $this->invoice->client->country->iso_3166_3,  // Se asume España si se omite
            "email"   => substr($this->invoice->client->present()->email(), 0, 60),
            "phone"   => substr($this->invoice->client->present()->phone(), 0, 15),
            "fax"     => "",
            "website" => substr($this->invoice->client->present()->website(), 0, 60),
            "contactPeople" => substr($this->invoice->client->present()->first_name()." ".$this->invoice->client->present()->last_name(), 0, 40),
            'centres' => $this->setFace(),
            // "cnoCnae" => "04791", // Clasif. Nacional de Act. Económicas
            // "ineTownCode" => "280796" // Cód. de municipio del INE
        ];

        if ($this->invoice->client->classification === 'individual') {
            $buyer_array['name'] = $this->invoice->client->present()->first_name();
            $buyer_array['firstSurname'] = $this->invoice->client->present()->last_name();
        }

        $buyer = new FacturaeParty($buyer_array);


        $this->fac->setBuyer($buyer);

        return $this;
    }

    private function signDocument(): self
    {

        $ssl_cert = $this->invoice->company->getInvoiceCert();
        $ssl_passphrase = $this->invoice->company->getSslPassPhrase();

        if ($ssl_cert) {
            $this->fac->sign($ssl_cert, null, $ssl_passphrase);
        }

        return $this;
    }


}

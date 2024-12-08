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
use App\Services\AbstractService;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronica;
use InvoiceNinja\EInvoice\Models\FatturaPA\IndirizzoType\Sede;
use InvoiceNinja\EInvoice\Models\FatturaPA\AnagraficaType\Anagrafica;
use InvoiceNinja\EInvoice\Models\FatturaPA\IdFiscaleType\IdFiscaleIVA;
use InvoiceNinja\EInvoice\Models\FatturaPA\IdFiscaleType\IdTrasmittente;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiGeneraliType\DatiGenerali;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiPagamentoType\DatiPagamento;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiRiepilogoType\DatiRiepilogo;
use InvoiceNinja\EInvoice\Models\FatturaPA\DettaglioLineeType\DettaglioLinee;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiBeniServiziType\DatiBeniServizi;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiTrasmissioneType\DatiTrasmissione;
use InvoiceNinja\EInvoice\Models\FatturaPA\CedentePrestatoreType\CedentePrestatore;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiAnagraficiCedenteType\DatiAnagrafici;
use InvoiceNinja\EInvoice\Models\FatturaPA\DettaglioPagamentoType\DettaglioPagamento;
use InvoiceNinja\EInvoice\Models\FatturaPA\DatiGeneraliDocumentoType\DatiGeneraliDocumento;
use InvoiceNinja\EInvoice\Models\FatturaPA\CessionarioCommittenteType\CessionarioCommittente;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronicaBodyType\FatturaElettronicaBody;
use InvoiceNinja\EInvoice\Models\FatturaPA\FatturaElettronicaHeaderType\FatturaElettronicaHeader;

class FatturaPANew extends AbstractService
{
    private FatturaElettronica $FatturaElettronica;
    private FatturaElettronicaBody $FatturaElettronicaBody;
    private FatturaElettronicaHeader $FatturaElettronicaHeader;
    private DatiTrasmissione $DatiTrasmissione;
    private IdTrasmittente $IdTrasmittente;
    private CedentePrestatore $CedentePrestatore;
    private DatiAnagrafici $DatiAnagrafici;
    private IdFiscaleIVA $IdFiscaleIVA;
    private Anagrafica $Anagrafica;
    private DatiGeneraliDocumento $DatiGeneraliDocumento;
    private DatiGenerali $DatiGenerali;
    private DettaglioPagamento $DettaglioPagamento;
    /**
     * @param Invoice $invoice
     */
    public function __construct(public Invoice $invoice)
    {
    }

    public function run()
    {
        $this->init()
             ->setIdTrasmittente() //order of execution matters.
             ->setDatiTrasmissione()
             ->setIdFiscaleIVA()
             ->setAnagrafica()
             ->setDatiAnagrafici()
             ->setCedentePrestatore()
             ->setClientDetails()
             ->setDatiGeneraliDocumento()
             ->setDatiGenerali()
             ->setLineItems()
             ->setDettaglioPagamento()
             ->setFatturaElettronica();
    }

    public function getFatturaElettronica(): FatturaElettronica
    {
        return $this->FatturaElettronica;
    }

    private function setDatiTrasmissione(): self
    {

        $this->DatiTrasmissione->FormatoTrasmissione = "FPR12";
        $this->DatiTrasmissione->CodiceDestinatario = $this->invoice->client->routing_id;
        $this->DatiTrasmissione->ProgressivoInvio = $this->invoice->number;

        $this->DatiTrasmissione->IdTrasmittente = $this->IdTrasmittente;

        $this->FatturaElettronicaHeader->DatiTrasmissione = $this->DatiTrasmissione;

        return $this;
    }


    private function setIdTrasmittente(): self
    {
        $this->IdTrasmittente->IdPaese = $this->invoice->company->country()->iso_3166_2;
        $this->IdTrasmittente->IdCodice = $this->invoice->company->settings->vat_number;

        return $this;
    }

    private function setCedentePrestatore(): self
    {
        $this->CedentePrestatore->DatiAnagrafici = $this->DatiAnagrafici;

        $sede = new Sede();
        $sede->Indirizzo = $this->invoice->company->settings->address1;
        $sede->CAP = (int)$this->invoice->company->settings->postal_code;
        $sede->Comune = $this->invoice->company->settings->city;
        $sede->Provincia = $this->invoice->company->settings->state;
        $sede->Nazione = $this->invoice->company->country()->iso_3166_2;
        $this->CedentePrestatore->Sede = $sede;

        $this->FatturaElettronicaHeader->CedentePrestatore = $this->CedentePrestatore;

        return $this;
    }

    private function setDatiAnagrafici(): self
    {
        $this->DatiAnagrafici->RegimeFiscale = "RF01";
        $this->DatiAnagrafici->Anagrafica = $this->Anagrafica;
        $this->DatiAnagrafici->IdFiscaleIVA = $this->IdFiscaleIVA;

        return $this;
    }

    private function setClientDetails(): self
    {

        $datiAnagrafici = new DatiAnagrafici();
        $anagrafica = new Anagrafica();
        $anagrafica->Denominazione =  $this->invoice->client->present()->name();
        $datiAnagrafici->Anagrafica = $anagrafica;

        $idFiscale = new IdFiscaleIVA();
        $idFiscale->IdCodice = $this->invoice->client->vat_number;
        $idFiscale->IdPaese = $this->invoice->client->country->iso_3166_2;

        $datiAnagrafici->IdFiscaleIVA = $idFiscale;

        $sede = new Sede();
        $sede->Indirizzo =  $this->invoice->client->address1;
        $sede->CAP =  (int)$this->invoice->client->postal_code;
        $sede->Comune =  $this->invoice->client->city;
        $sede->Provincia =  $this->invoice->client->state;
        $sede->Nazione = $this->invoice->client->country->iso_3166_2;

        $cessionarioCommittente = new CessionarioCommittente();
        $cessionarioCommittente->DatiAnagrafici = $datiAnagrafici;
        $cessionarioCommittente->Sede = $sede;

        $this->FatturaElettronicaHeader->CessionarioCommittente = $cessionarioCommittente;

        return $this;
    }

    private function setIdFiscaleIVA(): self
    {

        $this->IdFiscaleIVA->IdPaese = $this->invoice->company->country()->iso_3166_2;
        $this->IdFiscaleIVA->IdCodice = $this->invoice->company->settings->vat_number;

        return $this;
    }

    //this is a choice, need to switch based on values here.
    private function setAnagrafica(): self
    {
        $this->Anagrafica->Denominazione = $this->invoice->company->present()->name();

        return $this;
    }

    private function setDatiGeneraliDocumento(): self
    {

        $this->DatiGeneraliDocumento->TipoDocumento = "TD01";
        $this->DatiGeneraliDocumento->Divisa = $this->invoice->client->currency()->code;
        $this->DatiGeneraliDocumento->Data = new \DateTime($this->invoice->date);
        $this->DatiGeneraliDocumento->Numero = $this->invoice->number;
        $this->DatiGeneraliDocumento->Causale[] = substr($this->invoice->public_notes ?? ' ', 0, 200); //unsure..

        return $this;
    }

    private function setDatiGenerali(): self
    {
        $this->DatiGenerali->DatiGeneraliDocumento = $this->DatiGeneraliDocumento;

        $this->FatturaElettronicaBody->DatiGenerali = $this->DatiGenerali;

        return $this;
    }

    private function setDettaglioPagamento(): self
    {

        $this->DettaglioPagamento->ModalitaPagamento =  "MP01"; //String
        $this->DettaglioPagamento->DataScadenzaPagamento =  new \DateTime($this->invoice->due_date ?? $this->invoice->date);
        $this->DettaglioPagamento->ImportoPagamento =  (string) sprintf('%0.2f', $this->invoice->balance);

        $DatiPagamento = new DatiPagamento();
        $DatiPagamento->CondizioniPagamento = "TP02";
        $DatiPagamento->DettaglioPagamento[] = $this->DettaglioPagamento;

        $this->FatturaElettronicaBody->DatiPagamento[] = $DatiPagamento;

        return $this;
    }

    private function setLineItems(): self
    {

        $calc = $this->invoice->calc();

        $datiBeniServizi  = new DatiBeniServizi();
        $tax_rate_level = 0;
        //line items
        foreach ($this->invoice->line_items as $key => $item) {

            $numero = $key + 1;
            $dettaglioLinee = new DettaglioLinee();
            $dettaglioLinee->NumeroLinea =  "{$numero}";
            $dettaglioLinee->Descrizione =  $item->notes ?? 'Descrizione';
            $dettaglioLinee->Quantita =  sprintf('%0.2f', $item->quantity);
            $dettaglioLinee->PrezzoUnitario =  sprintf('%0.2f', $item->cost);
            $dettaglioLinee->PrezzoTotale =  sprintf('%0.2f', $item->line_total);
            $dettaglioLinee->AliquotaIVA =  sprintf('%0.2f', $item->tax_rate1);


            $datiBeniServizi->DettaglioLinee[] = $dettaglioLinee;

            if ($item->tax_rate1 > $tax_rate_level) {
                $tax_rate_level = sprintf('%0.2f', $item->tax_rate1);
            }

        }

        //totals
        if ($this->invoice->tax_rate1 > $tax_rate_level) {
            $tax_rate_level = sprintf('%0.2f', $this->invoice->tax_rate1);
        }

        $subtotal = sprintf('%0.2f', $calc->getSubTotal());
        $taxes = sprintf('%0.2f', $calc->getTotalTaxes());

        $datiRiepilogo = new DatiRiepilogo();
        $datiRiepilogo->AliquotaIVA = "{$tax_rate_level}";
        $datiRiepilogo->ImponibileImporto = "{$subtotal}";
        $datiRiepilogo->Imposta = "{$taxes}";
        $datiRiepilogo->EsigibilitaIVA = "I";

        $datiBeniServizi->DatiRiepilogo[] = $datiRiepilogo;

        $this->FatturaElettronicaBody->DatiBeniServizi = $datiBeniServizi;

        return $this;
    }

    private function setFatturaElettronica(): self
    {

        $this->FatturaElettronica->FatturaElettronicaBody[] = $this->FatturaElettronicaBody;
        $this->FatturaElettronica->FatturaElettronicaHeader = $this->FatturaElettronicaHeader;

        return $this;
    }

    private function init(): self
    {

        $this->FatturaElettronica = new FatturaElettronica();
        $this->FatturaElettronicaBody = new FatturaElettronicaBody();
        $this->FatturaElettronicaHeader = new FatturaElettronicaHeader();
        $this->DatiTrasmissione = new DatiTrasmissione();
        $this->IdTrasmittente = new IdTrasmittente();
        $this->CedentePrestatore = new CedentePrestatore();
        $this->DatiAnagrafici = new DatiAnagrafici();
        $this->IdFiscaleIVA = new IdFiscaleIVA();
        $this->Anagrafica = new Anagrafica();
        $this->DatiGeneraliDocumento = new DatiGeneraliDocumento();
        $this->DatiGenerali = new DatiGenerali();
        $this->DettaglioPagamento = new DettaglioPagamento();

        return $this;

    }
}

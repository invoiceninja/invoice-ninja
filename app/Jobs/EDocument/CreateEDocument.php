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

namespace App\Jobs\EDocument;

use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\App;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\Services\EDocument\Standards\Peppol;
use horstoeko\zugferd\ZugferdDocumentBuilder;
use App\Services\EDocument\Standards\FatturaPA;
use App\Services\EDocument\Standards\RoEInvoice;
use App\Services\EDocument\Standards\OrderXDocument;
use App\Services\EDocument\Standards\FacturaEInvoice;
use App\Services\EDocument\Standards\ZugferdEDokument;

class CreateEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(private object $document, private bool $returnObject = false)
    {
    }

    /**
     * Execute the job.
     *
     * @return string|ZugferdDocumentBuilder
     */
    public function handle(): string|ZugferdDocumentBuilder
    {
        /* Forget the singleton*/
        App::forgetInstance('translator');

        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        $settings_entity = ($this->document instanceof PurchaseOrder) ? $this->document->vendor : $this->document->client;
        App::setLocale($settings_entity->locale());

        /* Set customized translations _NOW_ */
        if ($this->document->client ?? false) {
            $t->replace(Ninja::transformTranslations($this->document->client->getMergedSettings()));
        }

        $e_document_type = strlen($settings_entity->getSetting('e_invoice_type')) > 2 ? $settings_entity->getSetting('e_invoice_type') : "XInvoice_3_0";
        $e_quote_type = strlen($settings_entity->getSetting('e_quote_type')) > 2 ? $settings_entity->getSetting('e_quote_type') : "OrderX_Extended";

        if ($this->document instanceof Invoice) {
            switch ($e_document_type) {
                case "PEPPOL":
                    return (new Peppol($this->document))->run()->toXml();
                case "FACT1":
                    return (new RoEInvoice($this->document))->generateXml();
                case "FatturaPA":
                    return (new FatturaPA($this->document))->run();
                case "EN16931":
                case "XInvoice_3_0":
                case "XInvoice_2_3":
                case "XInvoice_2_2":
                case "XInvoice_2_1":
                case "XInvoice_2_0":
                case "XInvoice_1_0":
                case "XInvoice-Extended":
                case "XInvoice-BasicWL":
                case "XInvoice-Basic":
                    $zugferd = (new ZugferdEDokument($this->document))->run();

                    return $this->returnObject ? $zugferd->xdocument : $zugferd->getXml();
                case "Facturae_3.2":
                case "Facturae_3.2.1":
                case "Facturae_3.2.2":
                    return (new FacturaEInvoice($this->document, str_replace("Facturae_", "", $e_document_type)))->run();
                default:

                    $zugferd = (new ZugferdEDokument($this->document))->run();

                    return $this->returnObject ? $zugferd : $zugferd->getXml();

            }
        } elseif ($this->document instanceof Quote) {
            switch ($e_quote_type) {
                case "OrderX_Basic":
                case "OrderX_Comfort":
                case "OrderX_Extended":
                    $orderx = (new OrderXDocument($this->document))->run();
                    return $this->returnObject ? $orderx->orderxdocument : $orderx->getXml();
                default:
                    $orderx = (new OrderXDocument($this->document))->run();
                    return $this->returnObject ? $orderx->orderxdocument : $orderx->getXml();
            }
        } elseif ($this->document instanceof PurchaseOrder) {
            switch ($e_quote_type) {
                case "OrderX_Basic":
                case "OrderX_Comfort":
                case "OrderX_Extended":
                    $orderx = (new OrderXDocument($this->document))->run();
                    return $this->returnObject ? $orderx->orderxdocument : $orderx->getXml();
                default:
                    $orderx = (new OrderXDocument($this->document))->run();
                    return $this->returnObject ? $orderx->orderxdocument : $orderx->getXml();
            }
        } elseif ($this->document instanceof Credit) {
            switch ($e_document_type) {
                case "EN16931":
                case "XInvoice_3_0":
                case "XInvoice_2_3":
                case "XInvoice_2_2":
                case "XInvoice_2_1":
                case "XInvoice_2_0":
                case "XInvoice_1_0":
                case "XInvoice-Extended":
                case "XInvoice-BasicWL":
                case "XInvoice-Basic":
                    $zugferd = (new ZugferdEDokument($this->document))->run();
                    return $this->returnObject ? $zugferd->xdocument : $zugferd->getXml();
                default:
                    $zugferd = (new ZugferdEDokument($this->document))->run();
                    return $this->returnObject ? $zugferd : $zugferd->getXml();
            }
        } else {
            return "";
        }
    }
}

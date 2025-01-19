<?php

namespace App\Jobs\EDocument;

use App\Models\Invoice;
use App\Models\PurchaseOrder;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class MergeEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $deleteWhenMissingModels = true;

    public function __construct(private mixed $document, private string $pdf_file)
    {
    }

    /**
     * Execute the job.
     *
     * @return string
     */
    public function handle(): string
    {
        $settings_entity = ($this->document instanceof PurchaseOrder) ? $this->document->vendor : $this->document->client;

        $e_document_type = strlen($settings_entity->getSetting('e_invoice_type')) > 2 ? $settings_entity->getSetting('e_invoice_type') : "XInvoice_3_0";

        if ($this->document instanceof Invoice) {
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
                    $xml = (new CreateEDocument($this->document, true))->handle();
                    
                    return(new ZugferdDocumentPdfBuilder($xml, $this->pdf_file))->generateDocument()->downloadString();
                default:
                    return $this->pdf_file;

            }
        } else {
            return $this->pdf_file;
        }
    }
}

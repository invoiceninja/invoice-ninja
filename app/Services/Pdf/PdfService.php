<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

use App\Jobs\EDocument\CreateEDocument;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\Pdf\PageNumbering;
use App\Utils\Traits\Pdf\PdfMaker;
use App\Utils\VendorHtmlEngine;
use horstoeko\zugferd\ZugferdDocumentPdfBuilder;

class PdfService
{
    use PdfMaker;
    use PageNumbering;

    public InvoiceInvitation | QuoteInvitation | CreditInvitation | RecurringInvoiceInvitation | PurchaseOrderInvitation $invitation;

    public Company $company;

    public PdfConfiguration $config;

    public PdfBuilder $builder;

    public PdfDesigner $designer;

    public array $html_variables;

    public string $document_type;

    public array $options;

    private float $start_time;

    public float $execution_time;

    public const DELIVERY_NOTE = 'delivery_note';
    public const STATEMENT = 'statement';
    public const PURCHASE_ORDER = 'purchase_order';
    public const PRODUCT = 'product';

    public function __construct($invitation, $document_type = 'product', $options = [])
    {
        $this->invitation = $invitation;

        $this->company = $invitation->company;

        $this->document_type = $document_type;

        $this->options = $options;

        $this->start_time = microtime(true);
    }

    public function boot(): self
    {

        $this->init();

        return $this;
    }

    /**
     * Resolves the PDF generation type and
     * attempts to generate a PDF from the HTML
     * string.
     *
     * @return mixed | Exception
     *
     */
    public function getPdf()
    {
        try {
            $pdf = $this->resolvePdfEngine($this->getHtml());

            $numbered_pdf = $this->pageNumbering($pdf, $this->company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            if ($this->config->entity_string == "invoice" && $this->config->settings->enable_e_invoice) {
                $pdf = $this->checkEInvoice($pdf);
            }

        } catch (\Exception $e) {
            nlog($e->getMessage());
            throw new \Exception($e->getMessage(), $e->getCode());
        }

        $this->execution_time = microtime(true) - $this->start_time;

        return $pdf;
    }

    /**
     * Renders the dom document to HTML
     *
     * @return string
     *
     */
    public function getHtml(): string
    {

        $html = $this->builder->getCompiledHTML();

        if (config('ninja.log_pdf_html')) {
            nlog($html);
        }

        $this->execution_time = microtime(true) - $this->start_time;

        return $html;
    }

    /**
     * Initialize all the services to build the PDF
     *
     * @return self
     */
    public function init(): self
    {
        $this->start_time = microtime(true);

        $this->config = (new PdfConfiguration($this))->init();


        $this->html_variables = $this->config->client ?
                                    (new HtmlEngine($this->invitation))->generateLabelsAndValues() :
                                    (new VendorHtmlEngine($this->invitation))->generateLabelsAndValues();

        $this->designer = (new PdfDesigner($this))->build();

        $this->builder = (new PdfBuilder($this))->build();

        return $this;
    }

    /**
     * resolvePdfEngine
     *
     * @return mixed
     */
    public function resolvePdfEngine(string $html): mixed
    {
        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            $pdf = (new Phantom())->convertHtmlToPdf($html);
        } elseif (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($html);
        } else {
            $pdf = $this->makePdf(null, null, $html);
        }

        return $pdf;
    }

    /**
     * Switch to determine if we need to embed the xml into the PDF itself
     *
     * @param  string $pdf
     * @return string
     */
    private function checkEInvoice(string $pdf): string
    {
        if (!$this->config->entity instanceof Invoice) {
            return $pdf;
        }

        $e_invoice_type = $this->config->settings->e_invoice_type;

        switch ($e_invoice_type) {
            case "EN16931":
            case "XInvoice_2_2":
            case "XInvoice_2_1":
            case "XInvoice_2_0":
            case "XInvoice_1_0":
            case "XInvoice-Extended":
            case "XInvoice-BasicWL":
            case "XInvoice-Basic":
                return $this->embedEInvoiceZuGFerD($pdf) ?? $pdf;
                //case "Facturae_3.2":
                //case "Facturae_3.2.1":
                //case "Facturae_3.2.2":
                //
            default:
                return $pdf;
        }

    }

    /**
     * Embed the .xml file into the PDF
     *
     * @param  string $pdf
     * @return string
     */
    private function embedEInvoiceZuGFerD(string $pdf): string
    {
        try {

            $e_rechnung = (new CreateEDocument($this->config->entity, true))->handle();
            $pdfBuilder = new ZugferdDocumentPdfBuilder($e_rechnung, $pdf);
            $pdfBuilder->generateDocument();

            return $pdfBuilder->downloadString(basename($this->config->entity->getFileName()));

        } catch (\Exception $e) {
            nlog("E_Invoice Merge failed - " . $e->getMessage());
        }

        return $pdf;
    }

}

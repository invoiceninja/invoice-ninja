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

namespace App\Services\Invoice;

use App\Models\ClientContact;
use App\Models\Design;
use App\Models\Invoice;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker as PdfMakerService;
use App\Services\Template\TemplateService;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PdfMaker;
use Illuminate\Support\Facades\Storage;

class GenerateDeliveryNote
{
    use MakesHash;
    use PdfMaker;

    /**
     * @var mixed
     */
    private $disk;

    public function __construct(private Invoice $invoice, private ?ClientContact $contact = null, $disk = null)
    {
        $this->disk = $disk ?? config('filesystems.default');
    }

    public function run()
    {

        $delivery_note_design_id = $this->invoice->client->getSetting('delivery_note_design_id');
        $design = Design::withTrashed()->find($this->decodePrimaryKey($delivery_note_design_id));

        if ($design && $design->is_template) {

            $ts = new TemplateService($design);

            $pdf = $ts->setCompany($this->invoice->company)
            ->build([
                'invoices' => collect([$this->invoice]),
            ])->getPdf();

            return $pdf;

        }

        $design_id = $this->invoice->design_id
            ? $this->invoice->design_id
            : $this->decodePrimaryKey($this->invoice->client->getSetting('invoice_design_id'));

        $invitation = $this->invoice->invitations->first();

        // return (new \App\Services\Pdf\PdfService($invitation, 'delivery_note'))->boot()->getPdf();

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom())->generate($this->invoice->invitations->first());
        }

        $design = Design::withTrashed()->find($design_id);
        $html = new HtmlEngine($invitation);

        if ($design->is_custom) {
            $options = ['custom_partials' => json_decode(json_encode($design->design), true)];
            $template = new PdfMakerDesign(PdfMakerDesign::CUSTOM, $options);
        } else {
            $template = new PdfMakerDesign(strtolower($design->name));
        }

        $variables = $html->generateLabelsAndValues();
        $variables['labels']['$entity_label'] = ctrans('texts.delivery_note');
        $variables['labels']['$invoice.date_label'] = ctrans('texts.date');
        $variables['labels']['$invoice.number_label'] = ctrans('texts.number');

        $state = [
            'template' => $template->elements([
                'client' => $this->invoice->client,
                'entity' => $this->invoice,
                'pdf_variables' => (array) $this->invoice->company->settings->pdf_variables,
                'contact' => $this->contact,
            ], 'delivery_note'),
            'variables' => $variables,
            'options' => [
                'client' => $this->invoice->client,
                'entity' => $this->invoice,
                'contact' => $this->contact,
            ],
            'process_markdown' => $this->invoice->client->company->markdown_enabled,
        ];

        $maker = new PdfMakerService($state);
        $maker
            ->design($template)
            ->build();

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));
        } else {
            $pdf = $this->makePdf(null, null, $maker->getCompiledHTML());
        }

        if (config('ninja.log_pdf_html')) {
            info($maker->getCompiledHTML());
        }

        $maker = null;
        $state = null;

        return $pdf;

    }
}

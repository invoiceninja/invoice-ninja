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

namespace App\Http\Controllers;

use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Invoice;
use App\Utils\HtmlEngine;
use Illuminate\Support\Str;
use Twig\Error\SyntaxError;
use App\Jobs\Util\PreviewPdf;
use App\Models\ClientContact;
use App\Services\Pdf\PdfMock;
use App\Utils\Traits\MakesHash;
use App\Services\Pdf\PdfService;
use App\Utils\PhantomJS\Phantom;
use App\Models\InvoiceInvitation;
use App\Services\PdfMaker\Design;
use App\Utils\HostedPDF\NinjaPdf;
use Illuminate\Support\Facades\DB;
use App\Services\PdfMaker\PdfMaker;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesInvoiceHtml;
use Turbo124\Beacon\Facades\LightLogs;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\Response;
use App\DataMapper\Analytics\LivePreview;
use App\Services\Template\TemplateService;
use App\Http\Requests\Preview\ShowPreviewRequest;
use App\Http\Requests\Preview\DesignPreviewRequest;
use App\Http\Requests\Preview\PreviewInvoiceRequest;
use App\Utils\VendorHtmlEngine;

class PreviewController extends BaseController
{
    use GeneratesCounter;
    use MakesHash;
    use MakesInvoiceHtml;
    use PageNumbering;

    public function __construct()
    {
        parent::__construct();
    }

    public function live(PreviewInvoiceRequest $request): mixed
    {

        $start = microtime(true);

        /** Build models */
        $invitation = $request->resolveInvitation();
        $client = $request->getClient();
        $settings = $client->getMergedSettings();
        $entity_prop = str_replace("recurring_", "", $request->entity);
        $entity_obj = $invitation->{$request->entity};
        $entity_obj->fill($request->all());

        if(!$entity_obj->id) {
            $entity_obj->design_id = intval($this->decodePrimaryKey($settings->{$entity_prop."_design_id"}));
            $entity_obj->footer = empty($entity_obj->footer) ? $settings->{$entity_prop."_footer"} : $entity_obj->footer;
            $entity_obj->terms = empty($entity_obj->terms) ? $settings->{$entity_prop."_terms"} : $entity_obj->terms;
            $entity_obj->public_notes = empty($entity_obj->public_notes) ? $request->getClient()->public_notes : $entity_obj->public_notes;
            
            $entity_obj->custom_surcharge_tax1 = $client->company->custom_surcharge_taxes1;
            $entity_obj->custom_surcharge_tax2 = $client->company->custom_surcharge_taxes2;
            $entity_obj->custom_surcharge_tax3 = $client->company->custom_surcharge_taxes3;
            $entity_obj->custom_surcharge_tax4 = $client->company->custom_surcharge_taxes4;
            
            $invitation->setRelation($request->entity, $entity_obj);
        }

        $ps = new PdfService($invitation, 'product', [
            'client' => $client ?? false,
            "{$entity_prop}s" => [$entity_obj],
        ]);

        $pdf = $ps->boot()->getPdf();

        if (Ninja::isHosted()) {
            LightLogs::create(new LivePreview())
                        ->increment()
                        ->batch();
        }

        /** Return PDF */
        return response()->streamDownload(function () use ($pdf) {
            echo $pdf;
        }, 'preview.pdf', [
            'Content-Disposition' => 'inline',
            'Content-Type' => 'application/pdf',
            'Cache-Control:' => 'no-cache',
            'Server-Timing' => microtime(true) - $start
        ]);

    }

    /**
     * Returns the mocked PDF for the invoice design preview.
     *
     * Only used in Settings > Invoice Design as a general overview
     *
     * @param  DesignPreviewRequest $request
     * @return mixed
     */
    public function design(DesignPreviewRequest $request): mixed
    {
        $start = microtime(true);

        if($request->has('entity_type') && in_array($request->entity_type, ['payment_receipt', 'payment_refund', 'statement', 'delivery_note'])) {
            return $this->liveTemplate($request->all());
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        $pdf = (new PdfMock($request->all(), $company))->build()->getPdf();

        $response = Response::make($pdf, 200);
        $response->header('Content-Type', 'application/pdf');
        $response->header('Server-Timing', (string) (microtime(true) - $start));

        return $response;
    }

    /**
     * Returns a template filled with entity variables.
     *
     * Used in the Custom Designer to preview design changes
     * @return mixed
     */
    public function show(ShowPreviewRequest $request)
    {

        if($request->input('design.is_template')) {
            return $this->template();
        }

        if (request()->has('entity') &&
            request()->has('entity_id') &&
            ! empty(request()->input('entity')) &&
            ! empty(request()->input('entity_id'))) {

            if($request->input('entity') == 'purchase_order')
                return app(\App\Http\Controllers\PreviewPurchaseOrderController::class)->show($request);

            $design_object = json_decode(json_encode(request()->input('design')));

            if (! is_object($design_object)) {
                return response()->json(['message' => ctrans('texts.invalid_design_object')], 400);
            }

            $entity = Str::camel(request()->input('entity'));

            $class = "App\Models\\$entity";

            $entity_obj = $class::whereId($this->decodePrimaryKey(request()->input('entity_id')))->company()->first();

            if (! $entity_obj) {
                return $this->blankEntity();
            }

            $entity_obj->load('client');

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->client->preferredLocale());
            $t->replace(Ninja::transformTranslations($entity_obj->client->getMergedSettings()));

            if($entity_obj->client) {
                $html = new HtmlEngine($entity_obj->invitations()->first());
            } else {
                $html = new VendorHtmlEngine($entity_obj->invitations()->first());
            }

            $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

            $state = [
                'template' => $design->elements([
                    'client' => $entity_obj->client,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    'products' => request()->design['design']['product'],
                ]),
                'variables' => $html->generateLabelsAndValues(),
                'process_markdown' => $entity_obj->client->company->markdown_enabled,
                'options' => [
                    'client' => $entity_obj->client ?? [],
                    'vendor' => $entity_obj->vendor ?? [],
                    request()->input('entity_type', 'invoice')."s" => [$entity_obj],
                ]
            ];

            $maker = new PdfMaker($state);

            $maker
                ->design($design)
                ->build();

            if (request()->query('html') == 'true') {
                return $maker->getCompiledHTML();
            }

            //if phantom js...... inject here..
            if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
                return (new Phantom())->convertHtmlToPdf($maker->getCompiledHTML(true));
            }

            /** @var \App\Models\User $user */
            $user = auth()->user();
            $company = $user->company();

            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {

                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));
                $numbered_pdf = $this->pageNumbering($pdf, $company);
                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }

                return $pdf;

            }

            $pdf = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

            return response()->streamDownload(function () use ($pdf) {
                echo $pdf;
            }, 'preview.pdf', [
                'Content-Disposition' => 'inline',
                'Content-Type' => 'application/pdf',
                'Cache-Control:' => 'no-cache',
            ]);


        }

        return $this->blankEntity();
    }

    private function liveTemplate(array $request_data)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();
        $design = \App\Models\Design::query()
                    ->where('id', $request_data['design_id'])
                    ->where(function ($q) use ($user) {
                        $q->whereNull('company_id')->orWhere('company_id', $user->companyId());
                    })
                    ->first();

        $ts = (new TemplateService($design));

        try {

            if(isset($request_data['settings']) && is_array($request_data['settings'])) {
                $ts->setSettings(json_decode(json_encode($request_data['settings'])));
            }

            $ts->setCompany($company)
                ->compose()
                ->mock();
        } catch(SyntaxError $e) {
            // return response()->json(['message' => 'Twig syntax is invalid.', 'errors' => new \stdClass], 422);
        }

        $response = Response::make($ts->getPdf(), 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;

    }

    private function template()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        $design_object = json_decode(json_encode(request()->input('design')), true);

        $ts = (new TemplateService());

        try {
            
            $ts->setCompany($company)
                ->setTemplate($design_object)
                ->mock();

        } catch(SyntaxError $e) {
        } catch(\Exception $e) {
            return response()->json(['message' => 'invalid data access', 'errors' => ['design.design.body' => $e->getMessage()]], 422);
        }

        if (request()->query('html') == 'true') {
            return $ts->getHtml();
        }

        $response = Response::make($ts->getPdf(), 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;

    }

    private function blankEntity()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($company->settings));

        /** @var \App\Models\InvoiceInvitation $invitation */
        $invitation = InvoiceInvitation::where('company_id', $company->id)->orderBy('id', 'desc')->first();

        /* If we don't have a valid invitation in the system - create a mock using transactions */
        if (! $invitation) {
            return $this->mockEntity();
        }

        $design_object = json_decode(json_encode(request()->input('design')));

        if (! is_object($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $html = new HtmlEngine($invitation);

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => $invitation->invoice->client,
                'entity' => $invitation->invoice,
                'pdf_variables' => (array) $invitation->invoice->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $invitation->invoice->client->company->markdown_enabled,
            'options' => [
                'client' => $invitation->invoice->client,
                'invoices' => [$invitation->invoice],
            ]
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        if (request()->query('html') == 'true') {
            return $maker->getCompiledHTML();
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom())->convertHtmlToPdf($maker->getCompiledHTML(true));
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, $company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

    private function mockEntity()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Company $company */
        $company = $user->company();

        try {
            DB::connection($company->db)->beginTransaction();

            /** @var \App\Models\Client $client */
            $client = Client::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
            ]);

            /** @var \App\Models\ClientContact $contact */
            $contact = ClientContact::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'client_id' => $client->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

            $settings = $company->settings;

            /** @var \App\Models\Invoice $invoice */
            $invoice = Invoice::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'client_id' => $client->id,
                'terms' => $company->settings->invoice_terms,
                'footer' => $company->settings->invoice_footer,
                'public_notes' => 'Sample Public Notes',
            ]);

            if ($settings->invoice_number_pattern) {
                $invoice->number = $this->getFormattedEntityNumber(
                    $invoice,
                    rand(1, 9999),
                    $settings->counter_padding ?: 4,
                    $settings->invoice_number_pattern,
                );
                $invoice->save();
            }

            $invitation = InvoiceInvitation::factory()->create([
                'user_id' => $user->id,
                'company_id' => $company->id,
                'invoice_id' => $invoice->id,
                'client_contact_id' => $contact->id,
            ]);

            $invoice->setRelation('invitations', $invitation);
            $invoice->setRelation('client', $client);
            $invoice->setRelation('company', $company);
            $invoice->load('client.company');

            $design_object = json_decode(json_encode(request()->input('design')));

            if (! is_object($design_object)) {
                return response()->json(['message' => 'Invalid custom design object'], 400);
            }

            $html = new HtmlEngine($invoice->invitations()->first());

            $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

            $state = [
                'template' => $design->elements([
                    'client' => $invoice->client,
                    'entity' => $invoice,
                    'pdf_variables' => (array) $settings->pdf_variables,
                    'products' => request()->design['design']['product'],
                ]),
                'variables' => $html->generateLabelsAndValues(),
                'process_markdown' => $invoice->client->company->markdown_enabled,
                'options' => [
                    'client' => $invoice->client,
                    'invoices' => [$invoice],
                ]
            ];

            $maker = new PdfMaker($state);

            $maker
                ->design($design)
                ->build();

            DB::connection($company->db)->rollBack();
        } catch(\Exception $e) {
            DB::connection($company->db)->rollBack();
            return response()->json(['message' => $e->getMessage()], 400);
        }

        if (request()->query('html') == 'true') {
            return $maker->getCompiledHTML();
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom())->convertHtmlToPdf($maker->getCompiledHTML(true));
        }

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, $company);

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $company))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');
        return $response;
    }
}

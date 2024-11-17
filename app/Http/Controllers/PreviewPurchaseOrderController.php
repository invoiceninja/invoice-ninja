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
use App\Models\Vendor;
use App\Libraries\MultiDB;
use App\Jobs\Util\PreviewPdf;
use App\Models\PurchaseOrder;
use App\Models\VendorContact;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use App\Services\Pdf\PdfService;
use App\Utils\PhantomJS\Phantom;
use App\Services\PdfMaker\Design;
use App\Utils\HostedPDF\NinjaPdf;
use Illuminate\Support\Facades\DB;
use App\Services\PdfMaker\PdfMaker;
use Illuminate\Support\Facades\App;
use App\Factory\PurchaseOrderFactory;
use App\Utils\Traits\MakesInvoiceHtml;
use Turbo124\Beacon\Facades\LightLogs;
use App\Models\PurchaseOrderInvitation;
use App\Utils\Traits\Pdf\PageNumbering;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Response;
use App\DataMapper\Analytics\LivePreview;
use App\Repositories\PurchaseOrderRepository;
use App\Http\Requests\Preview\ShowPreviewRequest;
use App\Services\PdfMaker\Design as PdfDesignModel;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Http\Requests\Preview\PreviewPurchaseOrderRequest;

class PreviewPurchaseOrderController extends BaseController
{
    use MakesHash;
    use MakesInvoiceHtml;
    use PageNumbering;


    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Returns a template filled with entity variables.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse | \Illuminate\Http\Response | \Symfony\Component\HttpFoundation\BinaryFileResponse
     *
     * @OA\Post(
     *      path="/api/v1/preview/purchase_order",
     *      operationId="getPreviewPurchaseOrder",
     *      tags={"preview"},
     *      summary="Returns a pdf preview for purchase order",
     *      description="Returns a pdf preview for purchase order.",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="The pdf response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),

     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowPreviewRequest $request)
    {
        if (request()->has('entity') &&
            request()->has('entity_id') &&
            ! empty(request()->input('entity')) &&
            ! empty(request()->input('entity_id')) &&
            request()->has('body')) {
            $design_object = json_decode(json_encode(request()->input('design')));

            if (! is_object($design_object)) {
                return response()->json(['message' => ctrans('texts.invalid_design_object')], 400);
            }

            $entity_obj = PurchaseOrder::query()->whereId($this->decodePrimaryKey(request()->input('entity_id')))->company()->first();

            if (! $entity_obj) {
                return $this->blankEntity();
            }

            App::forgetInstance('translator');
            $t = app('translator');
            App::setLocale($entity_obj->company->locale());
            $t->replace(Ninja::transformTranslations($entity_obj->company->settings));

            $html = new VendorHtmlEngine($entity_obj->invitations()->first());

            $design_namespace = 'App\Services\PdfMaker\Designs\\'.request()->design['name'];

            $design_class = new $design_namespace();

            $state = [
                'template' => $design_class->elements([
                    'client' => null,
                    'vendor' => $entity_obj->vendor,
                    'entity' => $entity_obj,
                    'pdf_variables' => (array) $entity_obj->company->settings->pdf_variables,
                    'variables' => $html->generateLabelsAndValues(),
                ]),
                'variables' => $html->generateLabelsAndValues(),
                'process_markdown' => $entity_obj->company->markdown_enabled,
                'options' => [
                    'vendor' => $entity_obj->vendor ?? [],
                    request()->input('entity')."s" => [$entity_obj],
                ]
            ];

            $design = new Design(request()->design['name']);
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

            if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
                $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

                $numbered_pdf = $this->pageNumbering($pdf, $user->company());

                if ($numbered_pdf) {
                    $pdf = $numbered_pdf;
                }

                return $pdf;
            }

            //else
            $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $user->company()))->handle();

            return response()->download($file_path, basename($file_path), ['Cache-Control:' => 'no-cache'])->deleteFileAfterSend(true);
        }

        return $this->blankEntity();
    }

    public function live(PreviewPurchaseOrderRequest $request)
    {

        $start = microtime(true);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $invitation = $request->resolveInvitation();
        $vendor = $request->getVendor();
        $settings = $user->company()->settings;
        $entity_obj = $invitation->purchase_order;
        $entity_obj->fill($request->all());

        if(!$entity_obj->id) {
            $entity_obj->design_id = intval($this->decodePrimaryKey($settings->{"purchase_order_design_id"}));
            $entity_obj->footer = empty($entity_obj->footer) ? $settings->{"purchase_order_footer"} : $entity_obj->footer;
            $entity_obj->terms = empty($entity_obj->terms) ? $settings->{"purchase_order_terms"} : $entity_obj->terms;
            $entity_obj->public_notes = empty($entity_obj->public_notes) ? $request->getVendor()->public_notes : $entity_obj->public_notes;
            $invitation->setRelation($request->entity, $entity_obj);

        }

        $ps = new PdfService($invitation, 'purchase_order', [
            'client' => $entity_obj->client ?? false,
            'vendor' => $vendor ?? false,
            "purchase_orders" => [$entity_obj],
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

    private function blankEntity()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($user->company()->settings));

        /** @var \App\Models\PurchaseOrderInvitation $invitation */
        $invitation = PurchaseOrderInvitation::where('company_id', $user->company()->id)->orderBy('id', 'desc')->first();

        /* If we don't have a valid invitation in the system - create a mock using transactions */
        if (!$invitation) {
            return $this->mockEntity();
        }

        $design_object = json_decode(json_encode(request()->input('design')));

        if (! is_object($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $html = new VendorHtmlEngine($invitation);

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => null,
                'vendor' => $invitation->purchase_order->vendor,
                'entity' => $invitation->purchase_order,
                'pdf_variables' => (array) $invitation->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $invitation->company->markdown_enabled,
            'options' => [
                'vendor' => $invitation->purchase_order->vendor,
                'purchase_orders' => [$invitation->purchase_order],
            ],
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

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf =  (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, $user->company());

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $user->company()))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }

    private function mockEntity()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        DB::connection($user->company()->db)->beginTransaction();

        /** @var \App\Models\Vendor $vendor */
        $vendor = Vendor::factory()->create([
                'user_id' => $user->id,
                'company_id' => $user->company()->id,
            ]);

        /** @var \App\Models\VendorContact $contact */
        $contact = VendorContact::factory()->create([
                'user_id' => $user->id,
                'company_id' => $user->company()->id,
                'vendor_id' => $vendor->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

        /** @var \App\Models\PurchaseOrder $purchase_order */
        $purchase_order = PurchaseOrder::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'vendor_id' => $vendor->id,
                    'terms' => 'Sample Terms',
                    'footer' => 'Sample Footer',
                    'public_notes' => 'Sample Public Notes',
                ]);

        /** @var \App\Models\PurchaseOrderInvitation $invitation */
        $invitation = PurchaseOrderInvitation::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'purchase_order_id' => $purchase_order->id,
                    'vendor_contact_id' => $contact->id,
        ]);

        $purchase_order->setRelation('invitations', $invitation);
        $purchase_order->setRelation('vendor', $vendor);
        $purchase_order->setRelation('company', $user->company());
        $purchase_order->load('vendor.company');

        $design_object = json_decode(json_encode(request()->input('design')));

        if (! is_object($design_object)) {
            return response()->json(['message' => 'Invalid custom design object'], 400);
        }

        $html = new VendorHtmlEngine($purchase_order->invitations()->first());

        $design = new Design(Design::CUSTOM, ['custom_partials' => request()->design['design']]);

        $state = [
            'template' => $design->elements([
                'client' => null,
                'vendor' => $purchase_order->vendor,
                'entity' => $purchase_order,
                'pdf_variables' => (array) $purchase_order->company->settings->pdf_variables,
                'products' => request()->design['design']['product'],
            ]),
            'variables' => $html->generateLabelsAndValues(),
            'process_markdown' => $purchase_order->company->markdown_enabled,
             'options' => [
                'vendor' => $invitation->purchase_order->vendor,
                'purchase_orders' => [$invitation->purchase_order],
            ],
        ];

        $maker = new PdfMaker($state);

        $maker
            ->design($design)
            ->build();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        DB::connection($user->company()->db)->rollBack();

        if (request()->query('html') == 'true') {
            return $maker->getCompiledHTML();
        }

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return (new Phantom())->convertHtmlToPdf($maker->getCompiledHTML(true));
        }

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($maker->getCompiledHTML(true));

            $numbered_pdf = $this->pageNumbering($pdf, $user->company());

            if ($numbered_pdf) {
                $pdf = $numbered_pdf;
            }

            return $pdf;
        }

        $file_path = (new PreviewPdf($maker->getCompiledHTML(true), $user->company()))->handle();

        $response = Response::make($file_path, 200);
        $response->header('Content-Type', 'application/pdf');

        return $response;
    }
}

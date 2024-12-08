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

use App\Events\RecurringInvoice\RecurringInvoiceWasCreated;
use App\Events\RecurringInvoice\RecurringInvoiceWasUpdated;
use App\Factory\RecurringInvoiceFactory;
use App\Filters\RecurringInvoiceFilters;
use App\Http\Requests\RecurringInvoice\ActionRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\BulkRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\CreateRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\DestroyRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\EditRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\ShowRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\StoreRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\UpdateRecurringInvoiceRequest;
use App\Http\Requests\RecurringInvoice\UploadRecurringInvoiceRequest;
use App\Jobs\RecurringInvoice\UpdateRecurring;
use App\Models\Account;
use App\Models\RecurringInvoice;
use App\Repositories\RecurringInvoiceRepository;
use App\Transformers\RecurringInvoiceTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Response;

/**
 * Class RecurringInvoiceController.
 */
class RecurringInvoiceController extends BaseController
{
    use MakesHash;
    use SavesDocuments;

    protected $entity_type = RecurringInvoice::class;

    protected $entity_transformer = RecurringInvoiceTransformer::class;

    /**
     * @var RecurringInvoiceRepository
     */
    protected $recurring_invoice_repo;

    protected $base_repo;

    /**
     * RecurringInvoiceController constructor.
     *
     * @param RecurringInvoiceRepository $recurring_invoice_repo  The RecurringInvoice repo
     */
    public function __construct(RecurringInvoiceRepository $recurring_invoice_repo)
    {
        parent::__construct();

        $this->recurring_invoice_repo = $recurring_invoice_repo;
    }

    /**
     * Show the list of recurring_invoices.
     *
     * @param RecurringInvoiceFilters $filters  The filters
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices",
     *      operationId="getRecurringInvoices",
     *      tags={"recurring_invoices"},
     *      summary="Gets a list of recurring_invoices",
     *      description="Lists recurring_invoices, search and filters allow fine grained lists to be generated.

        Query parameters can be added to performed more fine grained filtering of the recurring_invoices, these are handled by the RecurringInvoiceFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of recurring_invoices",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
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
    public function index(RecurringInvoiceFilters $filters)
    {
        $recurring_invoices = RecurringInvoice::filter($filters);

        return $this->listResponse($recurring_invoices);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateRecurringInvoiceRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices/create",
     *      operationId="getRecurringInvoicesCreate",
     *      tags={"recurring_invoices"},
     *      summary="Gets a new blank RecurringInvoice object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function create(CreateRecurringInvoiceRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $recurring_invoice = RecurringInvoiceFactory::create($user->company()->id, $user->id);
        $recurring_invoice->auto_bill = $user->company()->settings->auto_bill;

        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreRecurringInvoiceRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/recurring_invoices",
     *      operationId="storeRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Adds a RecurringInvoice",
     *      description="Adds an RecurringInvoice to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function store(StoreRecurringInvoiceRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $recurring_invoice = $this->recurring_invoice_repo->save($request->all(), RecurringInvoiceFactory::create($user->company()->id, $user->id));

        $recurring_invoice->service()
                          ->triggeredActions($request)
                          ->save();

        event(new RecurringInvoiceWasCreated($recurring_invoice, $recurring_invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($recurring_invoice->fresh());
    }

    /**
     * Display the specified resource.
     *
     * @param ShowRecurringInvoiceRequest $request  The request
     * @param RecurringInvoice $recurring_invoice  The RecurringInvoice
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices/{id}",
     *      operationId="showRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Shows an RecurringInvoice",
     *      description="Displays an RecurringInvoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function show(ShowRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditRecurringInvoiceRequest $request  The request
     * @param RecurringInvoice $recurring_invoice  The RecurringInvoice
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/recurring_invoices/{id}/edit",
     *      operationId="editRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Shows an RecurringInvoice for editting",
     *      description="Displays an RecurringInvoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function edit(EditRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateRecurringInvoiceRequest $request  The request
     * @param RecurringInvoice $recurring_invoice  The RecurringInvoice
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Put(
     *      path="/api/v1/recurring_invoices/{id}",
     *      operationId="updateRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Updates an RecurringInvoice",
     *      description="Handles the updating of an RecurringInvoice by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function update(UpdateRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        if ($request->entityIsDeleted($recurring_invoice)) {
            return $request->disallowUpdate();
        }

        $recurring_invoice = $this->recurring_invoice_repo->save($request->all(), $recurring_invoice);

        $recurring_invoice->service()
                          ->triggeredActions($request)
                          ->save();

        event(new RecurringInvoiceWasUpdated($recurring_invoice, $recurring_invoice->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($recurring_invoice);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyRecurringInvoiceRequest $request
     * @param RecurringInvoice $recurring_invoice
     */
    public function destroy(DestroyRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        $this->recurring_invoice_repo->delete($recurring_invoice);

        return $this->itemResponse($recurring_invoice->fresh());
    }

    /**
     */
    public function bulk(BulkRecurringInvoiceRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $percentage_increase = request()->has('percentage_increase') ? request()->input('percentage_increase') : 0;

        if (in_array($request->action, ['increase_prices', 'update_prices'])) {
            UpdateRecurring::dispatch($request->ids, $user->company(), $user, $request->action, $percentage_increase);

            return response()->json(['message' => 'Update in progress.'], 200);
        }

        $recurring_invoices = RecurringInvoice::withTrashed()->find($request->ids);

        if ($request->action == 'bulk_update' && $user->can('edit', $recurring_invoices->first())) {

            $recurring_invoices = RecurringInvoice::withTrashed()
                    ->company()
                    ->whereIn('id', $request->ids);

            $this->recurring_invoice_repo->bulkUpdate($recurring_invoices, $request->column, $request->new_value);

            return $this->listResponse(RecurringInvoice::query()->withTrashed()->company()->whereIn('id', $request->ids));

        }

        if ($request->action == 'set_payment_link' && $request->has('subscription_id')) {

            $recurring_invoices->each(function ($invoice) use ($user, $request) {
                if ($user->can('edit', $invoice)) {
                    $invoice->service()->setPaymentLink($request->subscription_id)->save();
                }
            });

            return $this->listResponse(RecurringInvoice::query()->withTrashed()->whereIn('id', $request->ids)->company());
        }

        $recurring_invoices->each(function ($recurring_invoice, $key) use ($request, $user) {
            if ($user->can('edit', $recurring_invoice)) {
                $this->performAction($recurring_invoice, $request->action, true);
            }
        });

        return $this->listResponse(RecurringInvoice::query()->withTrashed()->whereIn('id', $request->ids)->company());

    }

    /**
     * Recurring Invoice Actions.
     * @param ActionRecurringInvoiceRequest $request
     * @param RecurringInvoice $recurring_invoice
     * @param $action
     * @return Response| \Illuminate\Http\JsonResponse|mixed
     */
    public function action(ActionRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice, $action)
    {
        return $this->performAction($recurring_invoice, $action);
    }

    private function performAction(RecurringInvoice $recurring_invoice, string $action, $bulk = false)
    {
        switch ($action) {
            case 'archive':
                $this->recurring_invoice_repo->archive($recurring_invoice);

                if (! $bulk) {
                    return $this->itemResponse($recurring_invoice);
                }
                break;
            case 'restore':
                $this->recurring_invoice_repo->restore($recurring_invoice);

                if (! $bulk) {
                    return $this->itemResponse($recurring_invoice);
                }
                break;
            case 'delete':
                $this->recurring_invoice_repo->delete($recurring_invoice);

                if (! $bulk) {
                    return $this->itemResponse($recurring_invoice);
                }
                break;
            case 'email':
                //dispatch email to queue
                break;
            case 'start':
                $recurring_invoice = $recurring_invoice->service()->start()->save();

                if (! $bulk) {
                    $this->itemResponse($recurring_invoice);
                }
                break;
            case 'stop':
                $recurring_invoice = $recurring_invoice->service()->stop()->save();

                if (! $bulk) {
                    $this->itemResponse($recurring_invoice);
                }

                break;

            case 'send_now':
                $recurring_invoice = $recurring_invoice->service()->sendNow();

                if (! $bulk) {
                    $this->itemResponse($recurring_invoice);
                }

                break;
            default:
                // code...
                break;
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadRecurringInvoiceRequest $request
     * @param RecurringInvoice $recurring_invoice
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/recurring_invoices/{id}/upload",
     *      operationId="uploadRecurringInvoice",
     *      tags={"recurring_invoices"},
     *      summary="Uploads a document to a recurring_invoice",
     *      description="Handles the uploading of a document to a recurring_invoice",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The RecurringInvoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the RecurringInvoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/RecurringInvoice"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function upload(UploadRecurringInvoiceRequest $request, RecurringInvoice $recurring_invoice)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $recurring_invoice, $request->input('is_public', true));
        }

        return $this->itemResponse($recurring_invoice->fresh());
    }

    public function downloadPdf(string $invitation_key)
    {
        $invitation = $this->recurring_invoice_repo->getInvitationByKey($invitation_key);

        if (! $invitation) {
            return response()->json(['message' => 'no record found'], 400);
        }

        $invoice = $invitation->recurring_invoice;

        \Illuminate\Support\Facades\App::setLocale($invitation->contact->preferredLocale());

        $file_name = $invoice->numberFormatter().'.pdf';

        $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();

        $headers = ['Content-Type' => 'application/pdf'];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

}

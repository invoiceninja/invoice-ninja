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

use App\Events\PurchaseOrder\PurchaseOrderWasCreated;
use App\Events\PurchaseOrder\PurchaseOrderWasUpdated;
use App\Factory\PurchaseOrderFactory;
use App\Filters\PurchaseOrderFilters;
use App\Http\Requests\PurchaseOrder\ActionPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\BulkPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\CreatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\DestroyPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\EditPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\ShowPurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UpdatePurchaseOrderRequest;
use App\Http\Requests\PurchaseOrder\UploadPurchaseOrderRequest;
use App\Jobs\Entity\CreateRawPdf;
use App\Jobs\PurchaseOrder\PurchaseOrderEmail;
use App\Jobs\PurchaseOrder\ZipPurchaseOrders;
use App\Models\Account;
use App\Models\Client;
use App\Models\PurchaseOrder;
use App\Repositories\PurchaseOrderRepository;
use App\Services\PdfMaker\PdfMerge;
use App\Services\Template\TemplateAction;
use App\Transformers\PurchaseOrderTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderController extends BaseController
{
    use MakesHash;
    use SavesDocuments;

    protected $entity_type = PurchaseOrder::class;
    protected $entity_transformer = PurchaseOrderTransformer::class;
    protected $purchase_order_repository;

    public function __construct(PurchaseOrderRepository $purchase_order_repository)
    {
        parent::__construct();

        $this->purchase_order_repository = $purchase_order_repository;
    }
    /**
     * Show the list of Purchase Orders.
     *
     * @param \App\Filters\PurchaseOrderFilters $filters  The filters
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders",
     *      operationId="getPurchaseOrders",
     *      tags={"purchase_orders"},
     *      summary="Gets a list of purchase orders",
     *      description="Lists purchase orders, search and filters allow fine grained lists to be generated.
     *
     *      Query parameters can be added to performed more fine grained filtering of the purchase orders, these are handled by the PurchaseOrderFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of purchase orders",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function index(PurchaseOrderFilters $filters)
    {
        $purchase_orders = PurchaseOrder::filter($filters);

        return $this->listResponse($purchase_orders);
    }
    /**
     * Show the form for creating a new resource.
     *
     * @param CreatePurchaseOrderRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders/create",
     *      operationId="getPurchaseOrderCreate",
     *      tags={"purchase_orders"},
     *      summary="Gets a new blank purchase order object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function create(CreatePurchaseOrderRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $purchase_order = PurchaseOrderFactory::create($user->company()->id, $user->id);
        $purchase_order->date = now()->addSeconds($user->company()->utc_offset())->format('Y-m-d');

        return $this->itemResponse($purchase_order);
    }
    /**
     * Store a newly created resource in storage.
     *
     * @param StorePurchaseOrderRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/purchase_orders",
     *      operationId="storePurchaseOrder",
     *      tags={"purhcase_orders"},
     *      summary="Adds a purchase order",
     *      description="Adds an purchase order to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function store(StorePurchaseOrderRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $purchase_order = $this->purchase_order_repository->save($request->all(), PurchaseOrderFactory::create($user->company()->id, $user->id));

        $purchase_order = $purchase_order->service()
            ->fillDefaults()
            ->triggeredActions($request)
            ->save();

        event(new PurchaseOrderWasCreated($purchase_order, $purchase_order->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($purchase_order->fresh());
    }
    /**
     * Display the specified resource.
     *
     * @param ShowPurchaseOrderRequest $request  The request
     * @param PurchaseOrder $purchase_order  The purchase order
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders/{id}",
     *      operationId="showPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Shows an purcase orders",
     *      description="Displays an purchase order by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Purchase order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function show(ShowPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        return $this->itemResponse($purchase_order);
    }
    /**
     * Show the form for editing the specified resource.
     *
     * @param EditPurchaseOrderRequest $request The request
     * @param PurchaseOrder $purchase_order The purchase order
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/purchase_orders/{id}/edit",
     *      operationId="editPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Shows an purchase order for editting",
     *      description="Displays an purchase order by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The purchase order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
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
    public function edit(EditPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        return $this->itemResponse($purchase_order);
    }
    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePurchaseOrderRequest $request The request
     * @param PurchaseOrder $purchase_order
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \ReflectionException
     * @OA\Put(
     *      path="/api/v1/purchase_order/{id}",
     *      operationId="updatePurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Updates an purchase order",
     *      description="Handles the updating of an purchase order by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The purchase order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the purchase order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
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
    public function update(UpdatePurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        if ($request->entityIsDeleted($purchase_order)) {
            return $request->disallowUpdate();
        }

        $purchase_order = $this->purchase_order_repository->save($request->all(), $purchase_order);

        $purchase_order = $purchase_order->service()
            ->triggeredActions($request)
            ->save();

        event(new PurchaseOrderWasUpdated($purchase_order, $purchase_order->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->itemResponse($purchase_order);
    }
    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyPurchaseOrderRequest $request
     * @param PurchaseOrder $purchase_order
     *
     * @return     Response
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/purchase_orders/{id}",
     *      operationId="deletePurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Deletes a purchase order",
     *      description="Handles the deletion of an purchase orders by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The purhcase order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns a HTTP status",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
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
    public function destroy(DestroyPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        $this->purchase_order_repository->delete($purchase_order);

        return $this->itemResponse($purchase_order->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse | \Illuminate\Http\Response | \Symfony\Component\HttpFoundation\BinaryFileResponse
     *
     * @OA\Post(
     *      path="/api/v1/purchase_orders/bulk",
     *      operationId="bulkPurchaseOrderss",
     *      tags={"purchase_orders"},
     *      summary="Performs bulk actions on an array of purchase_orders",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Purchase Order IDS",
     *         required=true,
     *         @OA\MediaType(
     *             mediaType="application/json",
     *             @OA\Schema(
     *                 type="array",
     *                 @OA\Items(
     *                     type="integer",
     *                     description="Array of hashed IDs to be bulk 'actioned",
     *                     example="[0,1,2,3]",
     *                 ),
     *             )
     *         )
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="The Bulk Action response",
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
    public function bulk(BulkPurchaseOrderRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = $request->input('action');

        $ids = $request->input('ids');

        if (Ninja::isHosted() && (stripos($action, 'email') !== false) && !$user->company()->account->account_sms_verified) {
            return response(['message' => 'Please verify your account to send emails.'], 400);
        }

        $purchase_orders = PurchaseOrder::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if ($purchase_orders->count() == 0) {
            return response()->json(['message' => 'No Purchase Orders Found']);
        }

        /*
         * Download Purchase Order/s
         */
        if ($action == 'bulk_download' && $purchase_orders->count() >= 1) {
            $purchase_orders->each(function ($purchase_order) use ($user) {
                if ($user->cannot('view', $purchase_order)) {
                    return response()->json(['message' => ctrans('text.access_denied')]);
                }
            });

            ZipPurchaseOrders::dispatch($purchase_orders->pluck("id")->toArray(), $purchase_orders->first()->company, auth()->user());

            return response()->json(['message' => ctrans('texts.sent_message')], 200);
        }

        if ($action == 'bulk_print' && $user->can('view', $purchase_orders->first())) {
            $paths = $purchase_orders->map(function ($purchase_order) {
                return (new CreateRawPdf($purchase_order->invitations->first()))->handle();
            });

            $merge = (new PdfMerge($paths->toArray()))->run();

            return response()->streamDownload(function () use ($merge) {
                echo($merge);
            }, 'print.pdf', ['Content-Type' => 'application/pdf']);
        }

        if ($action == 'template' && $user->can('view', $purchase_orders->first())) {

            $hash_or_response = $request->boolean('send_email') ? 'email sent' : \Illuminate\Support\Str::uuid();

            TemplateAction::dispatch(
                $purchase_orders->pluck('hashed_id')->toArray(),
                $request->template_id,
                PurchaseOrder::class,
                $user->id,
                $user->company(),
                $user->company()->db,
                $hash_or_response,
                $request->boolean('send_email')
            );

            return response()->json(['message' => $hash_or_response], 200);
        }

        /*
         * Send the other actions to the switch
         */
        $purchase_orders->each(function ($purchase_order, $key) use ($action, $user) {
            if ($user->can('edit', $purchase_order)) {
                $this->performAction($purchase_order, $action, true);
            }
        });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(PurchaseOrder::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }

    /**
     * @OA\Get(
     *      path="/api/v1/purchase_orders/{id}/{action}",
     *      operationId="actionPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Performs a custom action on an purchase order",
     *      description="Performs a custom action on an purchase order.
     *
     *        The current range of actions are as follows
     *        - mark_paid
     *        - download
     *        - archive
     *        - delete
     *        - email",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Purchase Order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="action",
     *          in="path",
     *          description="The action string to be performed",
     *          example="clone_to_quote",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the invoice object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Invoice"),
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
     * @param ActionPurchaseOrderRequest $request
     * @param PurchaseOrder $purchase_order
     * @param $action
     * @return \App\Http\Controllers\Response|\Illuminate\Http\JsonResponse|Response|mixed|\Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function action(ActionPurchaseOrderRequest $request, PurchaseOrder $purchase_order, $action)
    {
        return $this->performAction($purchase_order, $action);
    }

    private function performAction(PurchaseOrder $purchase_order, $action, $bulk = false)
    {
        /*If we are using bulk actions, we don't want to return anything */
        switch ($action) {
            case 'mark_sent':
                $purchase_order->service()->markSent()->save();

                if (! $bulk) {
                    return $this->itemResponse($purchase_order);
                }
                break;
            case 'download':

                $file = $purchase_order->service()->getPurchaseOrderPdf();

                return response()->streamDownload(function () use ($file) {
                    echo $file;
                }, $purchase_order->numberFormatter().".pdf", ['Content-Type' => 'application/pdf']);

            case 'restore':
                $this->purchase_order_repository->restore($purchase_order);

                if (! $bulk) {
                    return $this->itemResponse($purchase_order);
                }
                break;
            case 'archive':
                $this->purchase_order_repository->archive($purchase_order);

                if (! $bulk) {
                    return $this->itemResponse($purchase_order);
                }
                break;
            case 'delete':

                $this->purchase_order_repository->delete($purchase_order);

                if (! $bulk) {
                    return $this->itemResponse($purchase_order);
                }
                break;

            case 'email':
                //check query parameter for email_type and set the template else use calculateTemplate
                PurchaseOrderEmail::dispatch($purchase_order, $purchase_order->company);

                if (! $bulk) {
                    return response()->json(['message' => 'email sent'], 200);
                }
                break;

            case 'send_email':
                //check query parameter for email_type and set the template else use calculateTemplate
                PurchaseOrderEmail::dispatch($purchase_order, $purchase_order->company);

                if (! $bulk) {
                    return response()->json(['message' => 'email sent'], 200);
                }
                break;

            case 'add_to_inventory':

                $purchase_order->service()->add_to_inventory();

                return $this->itemResponse($purchase_order);

            case 'expense':

                if ($purchase_order->expense()->exists()) {
                    return response()->json(['message' => ctrans('texts.purchase_order_already_expensed')], 400);
                }

                $expense = $purchase_order->service()->expense();

                return $this->itemResponse($purchase_order);

            case 'cancel':

                if ($purchase_order->status_id <= PurchaseOrder::STATUS_SENT) {
                    $purchase_order->status_id = PurchaseOrder::STATUS_CANCELLED;
                    $purchase_order->save();
                }

                if (! $bulk) {
                    return $this->itemResponse($purchase_order);
                }
                break;

            default:
                return response()->json(['message' => ctrans('texts.action_unavailable', ['action' => $action])], 400);

        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadPurchaseOrderRequest $request
     * @param PurchaseOrder $purchase_order
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/purchase_orders/{id}/upload",
     *      operationId="uploadPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Uploads a document to a purchase_orders",
     *      description="Handles the uploading of a document to a purchase_order",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Purchase Order Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Purchase Order object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Vendor"),
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
    public function upload(UploadPurchaseOrderRequest $request, PurchaseOrder $purchase_order)
    {
        if (!$this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $purchase_order, $request->input('is_public', true));
        }

        return $this->itemResponse($purchase_order->fresh());
    }


    /**
     * @OA\Get(
     *      path="/api/v1/purchase_order/{invitation_key}/download",
     *      operationId="downloadPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Download a specific purchase order by invitation key",
     *      description="Downloads a specific purchase order",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="invitation_key",
     *          in="path",
     *          description="The Purchase Order Invitation Key",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Purchase Order pdf",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
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
     * @param $invitation_key
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse | \Illuminate\Http\Response
     */
    public function downloadPdf($invitation_key)
    {
        $invitation = $this->purchase_order_repository->getInvitationByKey($invitation_key);

        if (! $invitation) {
            return response()->json(['message' => 'no record found'], 400);
        }

        $purchase_order = $invitation->purchase_order;

        $file = $purchase_order->service()->getPurchaseOrderPdf();

        $headers = ['Content-Type' => 'application/pdf'];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $purchase_order->numberFormatter().".pdf", $headers);
    }
    /**
     * @OA\Get(
     *      path="/api/v1/credit/{invitation_key}/download_e_purchase_order",
     *      operationId="downloadEPurchaseOrder",
     *      tags={"purchase_orders"},
     *      summary="Download a specific E-Purchase-Order by invitation key",
     *      description="Downloads a specific E-Purchase-Order",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="invitation_key",
     *          in="path",
     *          description="The E-Purchase-Order Invitation Key",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the E-Purchase-Order pdf",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
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
     * @param $invitation_key
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse | \Illuminate\Http\Response
     */
    public function downloadEPurchaseOrder($invitation_key)
    {
        $invitation = $this->purchase_order_repository->getInvitationByKey($invitation_key);

        if (! $invitation) {
            return response()->json(['message' => 'no record found'], 400);
        }

        $contact = $invitation->contact;
        $purchase_order = $invitation->purchase_order;

        $file = $purchase_order->service()->getEPurchaseOrder($contact);
        $file_name = $purchase_order->getFileName("xml");

        $headers = ['Content-Type' => 'application/xml'];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);
    }
}

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

use App\Events\Payment\PaymentWasUpdated;
use App\Factory\PaymentFactory;
use App\Filters\PaymentFilters;
use App\Http\Requests\Payment\BulkActionPaymentRequest;
use App\Http\Requests\Payment\CreatePaymentRequest;
use App\Http\Requests\Payment\DestroyPaymentRequest;
use App\Http\Requests\Payment\EditPaymentRequest;
use App\Http\Requests\Payment\RefundPaymentRequest;
use App\Http\Requests\Payment\ShowPaymentRequest;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Http\Requests\Payment\UploadPaymentRequest;
use App\Models\Account;
use App\Models\Payment;
use App\Repositories\PaymentRepository;
use App\Services\Template\TemplateAction;
use App\Transformers\PaymentTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Response;

/**
 * Class PaymentController.
 */
class PaymentController extends BaseController
{
    use MakesHash;
    use SavesDocuments;

    protected $entity_type = Payment::class;

    protected $entity_transformer = PaymentTransformer::class;

    /**
     * @var PaymentRepository
     */
    protected $payment_repo;

    /**
     * PaymentController constructor.
     *
     * @param PaymentRepository $payment_repo  The invoice repo
     */
    public function __construct(PaymentRepository $payment_repo)
    {
        parent::__construct();

        $this->payment_repo = $payment_repo;
    }

    /**
     * Show the list of Invoices.
     *
     * @param PaymentFilters $filters  The filters
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/payments",
     *      operationId="getPayments",
     *      tags={"payments"},
     *      summary="Gets a list of payments",
     *      description="Lists payments, search and filters allow fine grained lists to be generated.

        Query parameters can be added to performed more fine grained filtering of the payments, these are handled by the PaymentFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of payments",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function index(PaymentFilters $filters)
    {
        $payments = Payment::filter($filters);

        return $this->listResponse($payments);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreatePaymentRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/payments/create",
     *      operationId="getPaymentsCreate",
     *      tags={"payments"},
     *      summary="Gets a new blank Payment object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function create(CreatePaymentRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $payment = PaymentFactory::create($user->company()->id, $user->id);
        $payment->date = now()->addSeconds($user->company()->utc_offset())->format('Y-m-d');

        return $this->itemResponse($payment);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StorePaymentRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/payments",
     *      operationId="storePayment",
     *      tags={"payments"},
     *      summary="Adds a Payment",
     *      description="Adds an Payment to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="The payment request",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Payment"),
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function store(StorePaymentRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $payment = $this->payment_repo->save($request->all(), PaymentFactory::create($user->company()->id, $user->id));

        event('eloquent.created: App\Models\Payment', $payment);

        return $this->itemResponse($payment);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowPaymentRequest $request The request
     * @param Payment $payment The invoice
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/payments/{id}",
     *      operationId="showPayment",
     *      tags={"payments"},
     *      summary="Shows an Payment",
     *      description="Displays an Payment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function show(ShowPaymentRequest $request, Payment $payment)
    {
        return $this->itemResponse($payment);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditPaymentRequest $request The request
     * @param Payment $payment The invoice
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/payments/{id}/edit",
     *      operationId="editPayment",
     *      tags={"payments"},
     *      summary="Shows an Payment for editting",
     *      description="Displays an Payment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function edit(EditPaymentRequest $request, Payment $payment)
    {
        return $this->itemResponse($payment);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdatePaymentRequest $request The request
     * @param Payment $payment The invoice
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Put(
     *      path="/api/v1/payments/{id}",
     *      operationId="updatePayment",
     *      tags={"payments"},
     *      summary="Updates an Payment",
     *      description="Handles the updating of an Payment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function update(UpdatePaymentRequest $request, Payment $payment)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($request->entityIsDeleted($payment)) {
            return $request->disallowUpdate();
        }

        $payment = $this->payment_repo->save($request->all(), $payment);

        event(new PaymentWasUpdated($payment, $payment->company, Ninja::eventVars($user->id)));

        event('eloquent.updated: App\Models\Payment', $payment);

        return $this->itemResponse($payment);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyPaymentRequest $request
     * @param Payment $payment
     *
     * @return     Response
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/payments/{id}",
     *      operationId="deletePayment",
     *      tags={"payments"},
     *      summary="Deletes a Payment",
     *      description="Handles the deletion of an Payment by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Hashed ID",
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
    public function destroy(DestroyPaymentRequest $request, Payment $payment)
    {
        $this->payment_repo->delete($payment);

        return $this->itemResponse($payment);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/payments/bulk",
     *      operationId="bulkPayments",
     *      tags={"payments"},
     *      summary="Performs bulk actions on an array of payments",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="User credentials",
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
     *          description="The Payment response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function bulk(BulkActionPaymentRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = $request->input('action');

        $ids = $request->input('ids');

        $payments = Payment::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if (!$payments) {
            return response()->json(['message' => ctrans('texts.record_not_found')]);
        }

        if ($action == 'template' && $user->can('view', $payments->first())) {

            $hash_or_response = request()->boolean('send_email') ? 'email sent' : \Illuminate\Support\Str::uuid();

            TemplateAction::dispatch(
                $payments->pluck('hashed_id')->toArray(),
                $request->template_id,
                Payment::class,
                $user->id,
                $user->company(),
                $user->company()->db,
                $hash_or_response,
                $request->boolean('send_email')
            );

            return response()->json(['message' => $hash_or_response], 200);
        }


        $payments->each(function ($payment, $key) use ($action, $user) {
            if ($user->can('edit', $payment)) {
                $this->performAction($payment, $action, true);
            }
        });

        return $this->listResponse(Payment::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    /**
     * Payment Actions.
     *
     *
     * @OA\Get(
     *      path="/api/v1/payments/{id}/{action}",
     *      operationId="actionPayment",
     *      tags={"payments"},
     *      summary="Performs a custom action on an Payment",
     *      description="Performs a custom action on an Payment.

    The current range of actions are as follows
    - clone_to_Payment
    - clone_to_quote
    - history
    - delivery_note
    - mark_paid
    - download
    - archive
    - delete
    - email",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Hashed ID",
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
     *          description="Returns the Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
     * @param Payment $payment
     * @param $action
     */
    public function performAction(Payment $payment, $action, $bulk = false)
    {
        switch ($action) {
            case 'restore':
                $this->payment_repo->restore($payment);

                if (! $bulk) {
                    return $this->itemResponse($payment);
                }

                break;
            case 'archive':
                $this->payment_repo->archive($payment);

                if (! $bulk) {
                    return $this->itemResponse($payment);
                }
                // code...
                break;
            case 'delete':
                $this->payment_repo->delete($payment);

                if (! $bulk) {
                    return $this->itemResponse($payment);
                }
                // code...
                break;
            case 'email':
                $payment->service()->sendEmail();

                if (! $bulk) {
                    return $this->itemResponse($payment);
                }
                break;
            case 'email_receipt':
                $payment->service()->sendEmail();

                if (! $bulk) {
                    return $this->itemResponse($payment);
                }
                break;

            default:
                // code...
                break;
        }
    }

    /**
     * Store a newly created refund.
     *
     * @param RefundPaymentRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/payments/refund",
     *      operationId="storeRefund",
     *      tags={"payments"},
     *      summary="Adds a Refund",
     *      description="Adds an Refund to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\RequestBody(
     *         description="The refund request",
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/Payment"),
     *     ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function refund(RefundPaymentRequest $request)
    {
        $payment = $request->payment();

        $payment = $payment->refund($request->all());

        return $this->itemResponse($payment);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadPaymentRequest $request
     * @param Payment $payment
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/payments/{id}/upload",
     *      operationId="uploadPayment",
     *      tags={"payments"},
     *      summary="Uploads a document to a payment",
     *      description="Handles the uploading of a document to a payment",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Payment Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Payment object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Payment"),
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
    public function upload(UploadPaymentRequest $request, Payment $payment)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $payment, $request->input('is_public', true));
        }

        return $this->itemResponse($payment->fresh());
    }
}

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
use App\Models\Credit;
use App\Models\Account;
use App\Models\Invoice;
use App\Models\Webhook;
use Illuminate\Http\Response;
use App\Factory\CreditFactory;
use App\Filters\CreditFilters;
use App\Jobs\Credit\ZipCredits;
use App\Utils\Traits\MakesHash;
use App\Jobs\Entity\EmailEntity;
use App\Factory\CloneCreditFactory;
use App\Services\PdfMaker\PdfMerge;
use Illuminate\Support\Facades\App;
use App\Utils\Traits\SavesDocuments;
use App\Repositories\CreditRepository;
use App\Events\Credit\CreditWasCreated;
use App\Events\Credit\CreditWasUpdated;
use App\Transformers\CreditTransformer;
use Illuminate\Support\Facades\Storage;
use App\Events\General\EntityWasEmailed;
use App\Services\Template\TemplateAction;
use App\Http\Requests\Credit\BulkCreditRequest;
use App\Http\Requests\Credit\EditCreditRequest;
use App\Http\Requests\Credit\ShowCreditRequest;
use App\Http\Requests\Credit\StoreCreditRequest;
use App\Http\Requests\Credit\ActionCreditRequest;
use App\Http\Requests\Credit\CreateCreditRequest;
use App\Http\Requests\Credit\UpdateCreditRequest;
use App\Http\Requests\Credit\UploadCreditRequest;
use App\Http\Requests\Credit\DestroyCreditRequest;

/**
 * Class CreditController.
 */
class CreditController extends BaseController
{
    use MakesHash;
    use SavesDocuments;

    protected $entity_type = Credit::class;

    protected $entity_transformer = CreditTransformer::class;

    protected $credit_repository;

    public function __construct(CreditRepository $credit_repository)
    {
        parent::__construct();

        $this->credit_repository = $credit_repository;
    }

    /**
     * Show the list of Credits.
     *
     * @param CreditFilters $filters  The filters
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/credits",
     *      operationId="getCredits",
     *      tags={"credits"},
     *      summary="Gets a list of credits",
     *      description="Lists credits, search and filters allow fine grained lists to be generated.
     *
     *      Query parameters can be added to performed more fine grained filtering of the credits, these are handled by the CreditFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of credits",
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
    public function index(CreditFilters $filters)
    {
        $credits = Credit::filter($filters);

        return $this->listResponse($credits);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateCreditRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/credits/create",
     *      operationId="getCreditsCreate",
     *      tags={"credits"},
     *      summary="Gets a new blank credit object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank credit object",
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
    public function create(CreateCreditRequest $request)
    {
        /** @var \App\Models\User $user **/
        $user = auth()->user();

        $credit = CreditFactory::create($user->company()->id, $user->id);
        $credit->date = now()->addSeconds($user->company()->utc_offset())->format('Y-m-d');

        return $this->itemResponse($credit);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCreditRequest $request  The request
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/credits",
     *      operationId="storeCredit",
     *      tags={"credits"},
     *      summary="Adds a credit",
     *      description="Adds an credit to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved credit object",
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
    public function store(StoreCreditRequest $request)
    {

        /** @var \App\Models\User $user **/
        $user = auth()->user();

        $credit = $this->credit_repository->save($request->all(), CreditFactory::create($user->company()->id, $user->id));

        $credit = $credit->service()
                         ->fillDefaults()
                         ->triggeredActions($request)
                         ->save();

        if ($credit->invoice_id) {
            $credit = $credit->service()->markSent()->save();
            $credit->client->service()->updatePaidToDate(-1 * $credit->balance)->save();
        }

        event(new CreditWasCreated($credit, $credit->company, Ninja::eventVars($user->id)));

        return $this->itemResponse($credit);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowCreditRequest $request  The request
     * @param Credit $credit  The credit
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/credits/{id}",
     *      operationId="showCredit",
     *      tags={"credits"},
     *      summary="Shows an credit",
     *      description="Displays an credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Credit Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the credit object",
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
    public function show(ShowCreditRequest $request, Credit $credit)
    {
        return $this->itemResponse($credit);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditCreditRequest $request The request
     * @param Credit $credit The credit
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/credits/{id}/edit",
     *      operationId="editCredit",
     *      tags={"credits"},
     *      summary="Shows an credit for editting",
     *      description="Displays an credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Invoice Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the credit object",
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
    public function edit(EditCreditRequest $request, Credit $credit)
    {
        return $this->itemResponse($credit);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateCreditRequest $request The request
     * @param Credit $credit
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \ReflectionException
     * @OA\Put(
     *      path="/api/v1/credits/{id}",
     *      operationId="updateCredit",
     *      tags={"Credits"},
     *      summary="Updates an Credit",
     *      description="Handles the updating of an Credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Credit Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Credit object",
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
    public function update(UpdateCreditRequest $request, Credit $credit)
    {
        if ($request->entityIsDeleted($credit)) {
            return $request->disallowUpdate();
        }

        $credit = $this->credit_repository->save($request->all(), $credit);

        $credit->service()
               ->triggeredActions($request);
        //    ->deletePdf();

        /** @var \App\Models\User $user**/
        $user = auth()->user();

        event(new CreditWasUpdated($credit, $credit->company, Ninja::eventVars($user->id)));

        return $this->itemResponse($credit);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyCreditRequest $request
     * @param Credit $credit
     *
     * @return     Response
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/credits/{id}",
     *      operationId="deleteCredit",
     *      tags={"credits"},
     *      summary="Deletes a credit",
     *      description="Handles the deletion of an credit by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Credit Hashed ID",
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
    public function destroy(DestroyCreditRequest $request, Credit $credit)
    {
        $this->credit_repository->delete($credit);

        return $this->itemResponse($credit->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse | \Illuminate\Http\Response
     *
     * @OA\Post(
     *      path="/api/v1/credits/bulk",
     *      operationId="bulkCredits",
     *      tags={"credits"},
     *      summary="Performs bulk actions on an array of credits",
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
    public function bulk(BulkCreditRequest $request)
    {

        /** @var \App\Models\User $user **/
        $user = auth()->user();

        $action = $request->input('action');

        if (Ninja::isHosted() && (stripos($action, 'email') !== false) && !$user->company()->account->account_sms_verified) {
            return response(['message' => 'Please verify your account to send emails.'], 400);
        }

        $credits = Credit::withTrashed()
                         ->whereIn('id', $request->ids)
                         ->company()
                         ->get();

        if (! $credits) {
            return response()->json(['message' => ctrans('texts.no_credits_found')]);
        }

        /*
         * Download Invoice/s
         */

        if ($action == 'bulk_download' && $credits->count() > 1) {
            $credits->each(function ($credit) use ($user) {
                if ($user->cannot('view', $credit)) {
                    return response()->json(['message' => ctrans('text.access_denied')]);
                }
            });

            ZipCredits::dispatch($credits->pluck('id')->toArray(), $user->company(), $user);

            return response()->json(['message' => ctrans('texts.sent_message')], 200);
        }

        if ($action == 'bulk_print' && $user->can('view', $credits->first())) {
            $paths = $credits->map(function ($credit) {
                return (new \App\Jobs\Entity\CreateRawPdf($credit->invitations->first()))->handle();
            });

            $merge = (new PdfMerge($paths->toArray()))->run();

            return response()->streamDownload(function () use ($merge) {
                echo($merge);
            }, 'print.pdf', ['Content-Type' => 'application/pdf']);
        }


        if ($action == 'template' && $user->can('view', $credits->first())) {

            $hash_or_response = $request->boolean('send_email') ? 'email sent' : \Illuminate\Support\Str::uuid();

            TemplateAction::dispatch(
                $credits->pluck('hashed_id')->toArray(),
                $request->template_id,
                Credit::class,
                $user->id,
                $user->company(),
                $user->company()->db,
                $hash_or_response,
                $request->boolean('send_email')
            );

            return response()->json(['message' => $hash_or_response], 200);
        }

        $credits->each(function ($credit, $key) use ($action, $user) {
            if ($user->can('edit', $credit)) {
                $this->performAction($credit, $action, true);
            }
        });

        return $this->listResponse(Credit::with(['invitations','documents'])->withTrashed()->company()->whereIn('id', $request->ids));
    }

    public function action(ActionCreditRequest $request, Credit $credit, $action)
    {
        return $this->performAction($credit, $action);
    }

    private function performAction(Credit $credit, $action, $bulk = false)
    {
        /*If we are using bulk actions, we don't want to return anything */
        switch ($action) {
            case 'mark_paid':
                $credit->service()->markPaid()->save();

                return $this->itemResponse($credit);

            case 'clone_to_credit':
                $credit = CloneCreditFactory::create($credit, auth()->user()->id);

                return $this->itemResponse($credit);
            case 'history':
                // code...
                break;
            case 'mark_sent':
                $credit->service()->markSent(true)->save();

                if (! $bulk) {
                    return $this->itemResponse($credit);
                }
                break;
            case 'download':
                $file = $credit->service()->getCreditPdf($credit->invitations->first());

                return response()->streamDownload(function () use ($file) {
                    echo $file;
                }, $credit->numberFormatter() . '.pdf', ['Content-Type' => 'application/pdf']);

            case 'archive':
                $this->credit_repository->archive($credit);

                if (! $bulk) {
                    return $this->itemResponse($credit);
                }
                break;
            case 'restore':
                $this->credit_repository->restore($credit);

                if (! $bulk) {
                    return $this->itemResponse($credit);
                }
                break;
            case 'delete':
                $this->credit_repository->delete($credit);

                if (! $bulk) {
                    return $this->itemResponse($credit);
                }
                break;
            case 'email':
            case 'send_email':

                $credit->service()->sendEmail();

                if (! $bulk) {
                    return response()->json(['message' => 'email sent'], 200);
                }
                break;

            default:
                return response()->json(['message' => ctrans('texts.action_unavailable', ['action' => $action])], 400);
        }
    }

    /**
     * @OA\Get(
     *      path="/api/v1/credit/{invitation_key}/download",
     *      operationId="downloadCredit",
     *      tags={"quotes"},
     *      summary="Download a specific credit by invitation key",
     *      description="Downloads a specific quote",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="invitation_key",
     *          in="path",
     *          description="The Credit Invitation Key",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the credit pdf",
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
        $invitation = $this->credit_repository->getInvitationByKey($invitation_key);

        if (! $invitation) {
            return response()->json(['message' => 'no record found'], 400);
        }

        $credit = $invitation->credit;

        App::setLocale($invitation->contact->preferredLocale());

        $file = $credit->service()->getCreditPdf($invitation);

        $headers = ['Content-Type' => 'application/pdf'];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $credit->numberFormatter() . '.pdf', $headers);

    }
    /**
     * @OA\Get(
     *      path="/api/v1/credit/{invitation_key}/download_e_credit",
     *      operationId="downloadXcredit",
     *      tags={"credit"},
     *      summary="Download a specific x-credit by invitation key",
     *      description="Downloads a specific x-credit",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="invitation_key",
     *          in="path",
     *          description="The credit Invitation Key",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the x-credit pdf",
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
    public function downloadECredit($invitation_key)
    {
        $invitation = $this->credit_repository->getInvitationByKey($invitation_key);

        if (! $invitation) {
            return response()->json(['message' => 'no record found'], 400);
        }

        $contact = $invitation->contact;
        $credit = $invitation->credit;

        $file = $credit->service()->getECredit($contact);
        $file_name = $credit->getFileName("xml");

        $headers = ['Content-Type' => 'application/xml'];

        if (request()->input('inline') == 'true') {
            $headers = array_merge($headers, ['Content-Disposition' => 'inline']);
        }

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);
    }


    /**
     * Update the specified resource in storage.
     *
     * @param UploadCreditRequest $request
     * @param Credit $credit
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/credits/{id}/upload",
     *      operationId="uploadCredits",
     *      tags={"credits"},
     *      summary="Uploads a document to a credit",
     *      description="Handles the uploading of a document to a credit",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Credit Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Credit object",
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
    public function upload(UploadCreditRequest $request, Credit $credit)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $credit, $request->input('is_public', true));
        }

        return $this->itemResponse($credit->fresh());
    }
}

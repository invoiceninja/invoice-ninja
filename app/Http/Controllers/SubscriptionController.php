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

use App\Events\Subscription\SubscriptionWasCreated;
use App\Events\Subscription\SubscriptionWasUpdated;
use App\Factory\SubscriptionFactory;
use App\Filters\SubscriptionFilters;
use App\Http\Requests\Subscription\BulkSubscriptionRequest;
use App\Http\Requests\Subscription\CreateSubscriptionRequest;
use App\Http\Requests\Subscription\DestroySubscriptionRequest;
use App\Http\Requests\Subscription\EditSubscriptionRequest;
use App\Http\Requests\Subscription\ShowSubscriptionRequest;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Subscription\UpdateSubscriptionRequest;
use App\Models\Subscription;
use App\Repositories\SubscriptionRepository;
use App\Transformers\SubscriptionTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;

class SubscriptionController extends BaseController
{
    use MakesHash;

    protected $entity_type = Subscription::class;

    protected $entity_transformer = SubscriptionTransformer::class;

    protected $subscription_repo;

    public function __construct(SubscriptionRepository $subscription_repo)
    {
        parent::__construct();

        $this->subscription_repo = $subscription_repo;
    }

    /**
     * Show the list of Subscriptions.
     *
     * @return \Illuminate\Http\Response
     *
     * @OA\Get(
     *      path="/api/v1/subscriptions",
     *      operationId="getSubscriptions",
     *      tags={"subscriptions"},
     *      summary="Gets a list of subscriptions",
     *      description="Lists subscriptions.",
     *
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of subscriptions",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Subscription"),
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
    public function index(SubscriptionFilters $filters): \Illuminate\Http\Response
    {
        $subscriptions = Subscription::filter($filters);

        return $this->listResponse($subscriptions);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateSubscriptionRequest $request  The request
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/subscriptions/create",
     *      operationId="getSubscriptionsCreate",
     *      tags={"subscriptions"},
     *      summary="Gets a new blank subscriptions object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank subscriptions object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Subscription"),
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
    public function create(CreateSubscriptionRequest $request): \Illuminate\Http\Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $subscription = SubscriptionFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($subscription);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreSubscriptionRequest $request  The request
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Post(
     *      path="/api/v1/subscriptions",
     *      operationId="storeSubscription",
     *      tags={"subscriptions"},
     *      summary="Adds a subscriptions",
     *      description="Adds an subscriptions to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved subscriptions object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Subscription"),
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
    public function store(StoreSubscriptionRequest $request): \Illuminate\Http\Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $subscription = $this->subscription_repo->save($request->all(), SubscriptionFactory::create($user->company()->id, $user->id));

        event(new SubscriptionWasCreated($subscription, $subscription->company, Ninja::eventVars($user->id)));

        return $this->itemResponse($subscription);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowSubscriptionRequest $request  The request
     * @param Subscription $subscription  The invoice
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Get(
     *      path="/api/v1/subscriptions/{id}",
     *      operationId="showSubscription",
     *      tags={"subscriptions"},
     *      summary="Shows an subscriptions",
     *      description="Displays an subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Subscription Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Subscription object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Subscription"),
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
    public function show(ShowSubscriptionRequest $request, Subscription $subscription): \Illuminate\Http\Response
    {
        return $this->itemResponse($subscription);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditSubscriptionRequest $request  The request
     * @param Subscription $subscription  The subscription
     *
     * @return \Illuminate\Http\Response
     *
     * @OA\Get(
     *      path="/api/v1/subscriptions/{id}/edit",
     *      operationId="editSubscription",
     *      tags={"subscriptions"},
     *      summary="Shows an subscriptions for editting",
     *      description="Displays an subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Subscription Hashed ID",
     *          example="D2J234DFA",
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
     *          @OA\JsonContent(ref="#/components/schemas/Subscription"),
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
    public function edit(EditSubscriptionRequest $request, Subscription $subscription): \Illuminate\Http\Response
    {
        return $this->itemResponse($subscription);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateSubscriptionRequest $request  The request
     * @param Subscription $subscription  The subscription
     *
     * @return \Illuminate\Http\Response
     *
     *
     * @OA\Put(
     *      path="/api/v1/subscriptions/{id}",
     *      operationId="updateSubscription",
     *      tags={"subscriptions"},
     *      summary="Updates an subscriptions",
     *      description="Handles the updating of an subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Subscription Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the subscriptions object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Subscription"),
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
    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): \Illuminate\Http\Response
    {
        if ($request->entityIsDeleted($subscription)) {
            return $request->disallowUpdate();
        }

        $subscription = $this->subscription_repo->save($request->all(), $subscription);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        event(new SubscriptionWasUpdated($subscription, $subscription->company, Ninja::eventVars($user->id)));

        return $this->itemResponse($subscription);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroySubscriptionRequest $request
     * @param Subscription $subscription
     *
     * @return \Illuminate\Http\Response
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/subscriptions/{id}",
     *      operationId="deleteSubscription",
     *      tags={"subscriptions"},
     *      summary="Deletes a subscriptions",
     *      description="Handles the deletion of an subscriptions by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Subscription Hashed ID",
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
    public function destroy(DestroySubscriptionRequest $request, Subscription $subscription): \Illuminate\Http\Response
    {
        $this->subscription_repo->delete($subscription);

        return $this->itemResponse($subscription->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/subscriptions/bulk",
     *      operationId="bulkSubscriptions",
     *      tags={"subscriptions"},
     *      summary="Performs bulk actions on an array of subscriptions",
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
     *          description="The Subscription response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Subscription"),
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
    public function bulk(BulkSubscriptionRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $subscriptions = Subscription::withTrashed()->find($request->ids);

        if (in_array($request->action, ['assign_invoice'])) {

            $subscriptions->each(function ($subscription, $key) use ($request, $user) {
                if ($user->can('edit', $subscription)) {
                    $this->subscription_repo->{$request->action}($subscription, $request);
                }
            });

            return $this->listResponse(Subscription::withTrashed()->whereIn('id', $request->ids));

        }

        $subscriptions->each(function ($subscription, $key) use ($request, $user) {
            if ($user->can('edit', $subscription)) {
                $this->subscription_repo->{$request->action}($subscription);
            }
        });

        return $this->listResponse(Subscription::withTrashed()->whereIn('id', $request->ids));
    }
}

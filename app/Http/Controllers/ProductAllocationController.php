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

use App\Factory\ProductAllocationFactory;
use App\Filters\ProductAllocationFilters;
use App\Http\Requests\ProductAllocation\BulkProductAllocationRequest;
use App\Http\Requests\ProductAllocation\CreateProductAllocationRequest;
use App\Http\Requests\ProductAllocation\DestroyProductAllocationRequest;
use App\Http\Requests\ProductAllocation\EditProductAllocationRequest;
use App\Http\Requests\ProductAllocation\ShowProductAllocationRequest;
use App\Http\Requests\ProductAllocation\StoreProductAllocationRequest;
use App\Http\Requests\ProductAllocation\UpdateProductAllocationRequest;
use App\Http\Requests\ProductAllocation\UploadProductAllocationRequest;
use App\Models\Account;
use App\Models\ProductAllocation;
use App\Repositories\ProductAllocationRepository;
use App\Transformers\ProductAllocationTransformer;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use Illuminate\Http\Response;

class ProductAllocationController extends BaseController
{
    use MakesHash;
    use SavesDocuments;

    protected $entity_type = ProductAllocation::class;

    protected $entity_transformer = ProductAllocationTransformer::class;

    protected $product_allocation_repo;

    /**
     * ProductAllocationController constructor.
     * @param ProductAllocationRepository $product_allocation_repo
     */
    public function __construct(ProductAllocationRepository $product_allocation_repo)
    {
        parent::__construct();

        $this->product_allocation_repo = $product_allocation_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/product_allocations",
     *      operationId="getProductAllocations",
     *      tags={"product_allocation"},
     *      summary="Gets a list of product_allocation",
     *      description="Lists product_allocation, search and filters allow fine grained lists to be generated.

     *  Query parameters can be added to performed more fine grained filtering of the product_allocation, these are handled by the ProductAllocationFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of product_allocation",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
     * @param ProductAllocationFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse|mixed
     */
    public function index(ProductAllocationFilters $filters)
    {
        $productAllocation = ProductAllocation::filter($filters);

        return $this->listResponse($productAllocation);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateProductAllocationRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/product_allocations/create",
     *      operationId="getProductAllocationsCreate",
     *      tags={"product_allocation"},
     *      summary="Gets a new blank ProductAllocation object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank ProductAllocation object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
    public function create(CreateProductAllocationRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $data = $request->all();

        $productAllocation = ProductAllocationFactory::create($user->company()->id, $user->id, array_key_exists('product_id', $data) ? $data['product_id'] : 0);

        return $this->itemResponse($productAllocation);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProductAllocationRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/product_allocations",
     *      operationId="storeProductAllocation",
     *      tags={"product_allocation"},
     *      summary="Adds a ProductAllocation",
     *      description="Adds an ProductAllocation to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved ProductAllocation object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
    public function store(StoreProductAllocationRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $productAllocation = $this->product_allocation_repo->create($user->company()->id, $user->id, $request->all());

        return $this->itemResponse($productAllocation);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowProductAllocationRequest $request
     * @param ProductAllocation $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/product_allocations/{id}",
     *      operationId="showProductAllocation",
     *      tags={"product_allocation"},
     *      summary="Shows an ProductAllocation",
     *      description="Displays an ProductAllocation by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductAllocation Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductAllocation object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
    public function show(ShowProductAllocationRequest $request, ProductAllocation $productAllocation)
    {
        return $this->itemResponse($productAllocation);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditProductAllocationRequest $request
     * @param ProductAllocation $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/product_allocations/{id}/edit",
     *      operationId="editProductAllocation",
     *      tags={"product_allocation"},
     *      summary="Shows an ProductAllocation for editting",
     *      description="Displays an ProductAllocation by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductAllocation Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductAllocation object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
    public function edit(EditProductAllocationRequest $request, ProductAllocation $productAllocation)
    {
        return $this->itemResponse($productAllocation);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProductAllocationRequest $request
     * @param ProductAllocation $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Put(
     *      path="/api/v1/product_allocations/{id}",
     *      operationId="updateProductAllocation",
     *      tags={"product_allocation"},
     *      summary="Updates an ProductAllocation",
     *      description="Handles the updating of an ProductAllocation by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductAllocation ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductAllocation object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
    public function update(UpdateProductAllocationRequest $request, ProductAllocation $productAllocation)
    {
        if ($request->entityIsDeleted($productAllocation)) {
            return $request->disallowUpdate();
        }

        $productAllocation = $this->product_allocation_repo->save($request->all(), $productAllocation);

        return $this->itemResponse($productAllocation);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyProductAllocationRequest $request
     * @param ProductAllocation $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/product_allocations/{id}",
     *      operationId="deleteProductAllocation",
     *      tags={"product_allocation"},
     *      summary="Deletes a ProductAllocation",
     *      description="Handles the deletion of an ProductAllocation by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductAllocation Hashed ID",
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
    public function destroy(DestroyProductAllocationRequest $request, ProductAllocation $productAllocation)
    {
        $this->product_allocation_repo->delete($productAllocation);

        return $this->itemResponse($productAllocation->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/product_allocations/bulk",
     *      operationId="bulkProductAllocations",
     *      tags={"product_allocation"},
     *      summary="Performs bulk actions on an array of product_allocation",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Hashed IDs",
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
     *          description="The ProductAllocation response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
    public function bulk(BulkProductAllocationRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = $request->input('action');

        $ids = $request->input('ids');

        $productAllocation = ProductAllocation::withTrashed()->whereIn('id', $ids);

        $productAllocation->cursor()->each(function ($productAllocation, $key) use ($action, $user) {
            if ($user->can('edit', $productAllocation)) {
                $this->product_allocation_repo->{$action}($productAllocation);
            }
        });

        return $this->listResponse(ProductAllocation::withTrashed()->whereIn('id', $ids));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadProductAllocationRequest $request
     * @param ProductAllocation $productAllocation
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/product_allocations/{productAllocation}/upload",
     *      operationId="uploadProductAllocation",
     *      tags={"product_allocation"},
     *      summary="Uploads a document to a product",
     *      description="Handles the uploading of a document to a product",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ProductAllocation Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the ProductAllocation object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ProductAllocation"),
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
    public function upload(UploadProductAllocationRequest $request, ProductAllocation $productAllocation)
    {
        if (!$this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $productAllocation, $request->input('is_public', true));
        }

        return $this->itemResponse($productAllocation->fresh());
    }
}

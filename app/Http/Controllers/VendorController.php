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
use App\Models\Vendor;
use App\Models\Account;
use Illuminate\Http\Response;
use App\Factory\VendorFactory;
use App\Filters\VendorFilters;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Uploadable;
use App\Utils\Traits\BulkOptions;
use App\Utils\Traits\SavesDocuments;
use App\Repositories\VendorRepository;
use App\Events\Vendor\VendorWasCreated;
use App\Events\Vendor\VendorWasUpdated;
use App\Transformers\VendorTransformer;
use App\Http\Requests\Vendor\EditVendorRequest;
use App\Http\Requests\Vendor\ShowVendorRequest;
use App\Http\Requests\Vendor\PurgeVendorRequest;
use App\Http\Requests\Vendor\StoreVendorRequest;
use App\Http\Requests\Vendor\CreateVendorRequest;
use App\Http\Requests\Vendor\UpdateVendorRequest;
use App\Http\Requests\Vendor\UploadVendorRequest;
use App\Http\Requests\Vendor\DestroyVendorRequest;

/**
 * Class VendorController.
 */
class VendorController extends BaseController
{
    use MakesHash;
    use Uploadable;
    use BulkOptions;
    use SavesDocuments;

    protected $entity_type = Vendor::class;

    protected $entity_transformer = VendorTransformer::class;

    /**
     * @var VendorRepository
     */
    protected $vendor_repo;

    /**
     * VendorController constructor.
     * @param VendorRepository $vendor_repo
     */
    public function __construct(VendorRepository $vendor_repo)
    {
        parent::__construct();

        $this->vendor_repo = $vendor_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/vendors",
     *      operationId="getVendors",
     *      tags={"vendors"},
     *      summary="Gets a list of vendors",
     *      description="Lists vendors, search and filters allow fine grained lists to be generated.

    Query parameters can be added to performed more fine grained filtering of the vendors, these are handled by the VendorFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of vendors",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Vendor"),
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
     * @param VendorFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse|mixed
     */
    public function index(VendorFilters $filters)
    {
        $vendors = Vendor::filter($filters);

        return $this->listResponse($vendors);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowVendorRequest $request
     * @param Vendor $vendor
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/vendors/{id}",
     *      operationId="showVendor",
     *      tags={"vendors"},
     *      summary="Shows a client",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Vendor Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the vendor object",
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
    public function show(ShowVendorRequest $request, Vendor $vendor)
    {
        return $this->itemResponse($vendor);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditVendorRequest $request
     * @param Vendor $vendor
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/vendors/{id}/edit",
     *      operationId="editVendor",
     *      tags={"vendors"},
     *      summary="Shows a client for editting",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Vendor Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client object",
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
    public function edit(EditVendorRequest $request, Vendor $vendor)
    {
        return $this->itemResponse($vendor);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateVendorRequest $request
     * @param Vendor $vendor
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/vendors/{id}",
     *      operationId="updateVendor",
     *      tags={"vendors"},
     *      summary="Updates a client",
     *      description="Handles the updating of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Vendor Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the client object",
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
    public function update(UpdateVendorRequest $request, Vendor $vendor)
    {
        if ($request->entityIsDeleted($vendor)) {
            return $request->disallowUpdate();
        }

        $vendor = $this->vendor_repo->save($request->all(), $vendor);

        $this->uploadLogo($request->file('company_logo'), $vendor->company, $vendor);

        event(new VendorWasUpdated($vendor, $vendor->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        event('eloquent.updated: App\Models\Vendor', $vendor);

        return $this->itemResponse($vendor->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateVendorRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/vendors/create",
     *      operationId="getVendorsCreate",
     *      tags={"vendors"},
     *      summary="Gets a new blank client object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank client object",
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
    public function create(CreateVendorRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $vendor = VendorFactory::create($user->company()->id, auth()->user()->id);

        return $this->itemResponse($vendor);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreVendorRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/vendors",
     *      operationId="storeVendor",
     *      tags={"vendors"},
     *      summary="Adds a client",
     *      description="Adds an client to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved client object",
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
    public function store(StoreVendorRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $vendor = $this->vendor_repo->save($request->all(), VendorFactory::create($user->company()->id, auth()->user()->id));

        $vendor->load('contacts', 'primary_contact');

        $this->uploadLogo($request->file('company_logo'), $vendor->company, $vendor);

        event(new VendorWasCreated($vendor, $vendor->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        event('eloquent.created: App\Models\Vendor', $vendor);

        return $this->itemResponse($vendor);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyVendorRequest $request
     * @param Vendor $vendor
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/vendors/{id}",
     *      operationId="deleteVendor",
     *      tags={"vendors"},
     *      summary="Deletes a client",
     *      description="Handles the deletion of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Vendor Hashed ID",
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
    public function destroy(DestroyVendorRequest $request, Vendor $vendor)
    {
        //may not need these destroy routes as we are using actions to 'archive/delete'
        $vendor->delete();

        return $this->itemResponse($vendor->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/vendors/bulk",
     *      operationId="bulkVendors",
     *      tags={"vendors"},
     *      summary="Performs bulk actions on an array of vendors",
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
     *          description="The Vendor User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Vendor"),
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
    public function bulk()
    {
        $action = request()->input('action');

        $ids = request()->input('ids');
        $vendors = Vendor::withTrashed()->find($this->transformKeys($ids));

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $vendors->each(function ($vendor, $key) use ($action, $user) {
            if ($user->can('edit', $vendor)) {
                $this->vendor_repo->{$action}($vendor);
            }
        });

        return $this->listResponse(Vendor::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    /**
     * Returns a client statement.
     *
     * @return void [type] [description]
     */
    public function statement()
    {
        //todo
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadVendorRequest $request
     * @param Vendor $vendor
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/vendors/{id}/upload",
     *      operationId="uploadVendor",
     *      tags={"vendors"},
     *      summary="Uploads a document to a vendor",
     *      description="Handles the uploading of a document to a vendor",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Vendor Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Vendor object",
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
    public function upload(UploadVendorRequest $request, Vendor $vendor)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $vendor, $request->input('is_public', true));
        }

        return $this->itemResponse($vendor->fresh());
    }


    /**
        * Update the specified resource in storage.
        *
        * @param PurgeVendorRequest $request
        * @param Vendor $vendor
        * @param string $mergeable_vendor
        * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
        *
        */

    public function merge(PurgeVendorRequest $request, Vendor $vendor, string $mergeable_vendor)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $m_vendor = Vendor::withTrashed()
                            ->where('id', $this->decodePrimaryKey($mergeable_vendor))
                            ->where('company_id', $user->company()->id)
                            ->first();

        if (!$m_vendor) {
            return response()->json(['message' => "Vendor not found"], 400);
        }

        if ($m_vendor->id == $vendor->id) {
            return response()->json(['message' => "Attempting to merge the same vendor is not possible."], 400);
        }

        $merged_vendor = $vendor->service()->merge($m_vendor)->save();

        return $this->itemResponse($merged_vendor);
    }
}

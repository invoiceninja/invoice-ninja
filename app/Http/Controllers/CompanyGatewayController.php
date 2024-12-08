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

use App\Models\Client;
use App\Libraries\MultiDB;
use Illuminate\Http\Response;
use App\Models\CompanyGateway;
use App\Utils\Traits\MakesHash;
use App\DataMapper\FeesAndLimits;
use App\Jobs\Util\ApplePayDomain;
use Illuminate\Support\Facades\Cache;
use App\Factory\CompanyGatewayFactory;
use App\Filters\CompanyGatewayFilters;
use App\Repositories\CompanyRepository;
use Illuminate\Foundation\Bus\DispatchesJobs;
use App\Transformers\CompanyGatewayTransformer;
use App\PaymentDrivers\Stripe\Jobs\StripeWebhook;
use App\PaymentDrivers\CheckoutCom\CheckoutSetupWebhook;
use App\Http\Requests\CompanyGateway\BulkCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\EditCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\ShowCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\TestCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\StoreCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\CreateCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\UpdateCompanyGatewayRequest;
use App\Http\Requests\CompanyGateway\DestroyCompanyGatewayRequest;

/**
 * Class CompanyGatewayController.
 */
class CompanyGatewayController extends BaseController
{
    use DispatchesJobs;
    use MakesHash;

    protected $entity_type = CompanyGateway::class;

    protected $entity_transformer = CompanyGatewayTransformer::class;

    protected $company_repo;

    public $forced_includes = [];

    private array $stripe_keys = ['d14dd26a47cecc30fdd65700bfb67b34', 'd14dd26a37cecc30fdd65700bfb55b23'];

    private string $checkout_key = '3758e7f7c6f4cecf0f4f348b9a00f456';

    private string $forte_key = 'kivcvjexxvdiyqtj3mju5d6yhpeht2xs';

    private string $cbapowerboard_key = 'b67581d804dbad1743b61c57285142ad';

    /**
     * CompanyGatewayController constructor.
     * @param CompanyRepository $company_repo
     */
    public function __construct(CompanyRepository $company_repo)
    {
        parent::__construct();

        $this->company_repo = $company_repo;
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways",
     *      operationId="getCompanyGateways",
     *      tags={"company_gateways"},
     *      summary="Gets a list of company_gateways",
     *      description="Lists company_gateways, search and filters allow fine grained lists to be generated.

        Query parameters can be added to performed more fine grained filtering of the company_gateways, these are handled by the CompanyGatewayFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of company_gateways",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
    public function index(CompanyGatewayFilters $filters)
    {
        $company_gateways = CompanyGateway::filter($filters);

        return $this->listResponse($company_gateways);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateCompanyGatewayRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways/create",
     *      operationId="getCompanyGatewaysCreate",
     *      tags={"company_gateways"},
     *      summary="Gets a new blank CompanyGateway object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
    public function create(CreateCompanyGatewayRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company_gateway = CompanyGatewayFactory::create($user->company()->id, auth()->user()->id);

        return $this->itemResponse($company_gateway);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCompanyGatewayRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/company_gateways",
     *      operationId="storeCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Adds a CompanyGateway",
     *      description="Adds an CompanyGateway to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
    public function store(StoreCompanyGatewayRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company_gateway = CompanyGatewayFactory::create($user->company()->id, $user->id);
        $company_gateway->fill($request->all());
        $company_gateway->save();

        /*Always ensure at least one fees and limits object is set per gateway*/
        $gateway_types = $company_gateway->driver(new Client())->getAvailableMethods();

        $fees_and_limits = $company_gateway->fees_and_limits;

        foreach ($gateway_types as $key => $gateway_type) {
            if (!property_exists($fees_and_limits, $key)) {
                $fees_and_limits->{$key} = new FeesAndLimits();
            }
        }

        $company_gateway->fees_and_limits = $fees_and_limits;
        $company_gateway->save();

        ApplePayDomain::dispatch($company_gateway, $company_gateway->company->db);

        switch ($company_gateway->gateway_key) {
            case in_array($company_gateway->gateway_key, $this->stripe_keys):
                StripeWebhook::dispatch($company_gateway->company->company_key, $company_gateway->id);
                break;

            case $this->checkout_key:
                CheckoutSetupWebhook::dispatch($company_gateway->company->company_key, $company_gateway->id);
                break;

            case $this->forte_key:
                dispatch(function () use ($company_gateway) {
                    MultiDB::setDb($company_gateway->company->db);
                    $company_gateway->driver()->updateFees();
                })->afterResponse();

                break;

            case $this->cbapowerboard_key:
                dispatch(function () use ($company_gateway) {
                    MultiDB::setDb($company_gateway->company->db);
                    $company_gateway->driver()->init()->settings()->updateSettings();
                })->afterResponse();

                $config = $company_gateway->getConfig();
                $config->visa = true;
                $config->mastercard = true;
                $company_gateway->setConfig($config);
                $company_gateway->save();

                break;

            default:
                # code...
                break;

        }

        return $this->itemResponse($company_gateway);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways/{id}",
     *      operationId="showCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Shows an CompanyGateway",
     *      description="Displays an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
    public function show(ShowCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        return $this->itemResponse($company_gateway);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/company_gateways/{id}/edit",
     *      operationId="editCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Shows an CompanyGateway for editting",
     *      description="Displays an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
    public function edit(EditCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        return $this->itemResponse($company_gateway);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Put(
     *      path="/api/v1/company_gateways/{id}",
     *      operationId="updateCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Updates an CompanyGateway",
     *      description="Handles the updating of an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the CompanyGateway object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
    public function update(UpdateCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        $company_gateway->fill($request->all());

        /*Always ensure at least one fees and limits object is set per gateway*/
        $gateway_types = $company_gateway->driver(new Client())->getAvailableMethods();

        $fees_and_limits = $company_gateway->fees_and_limits;

        foreach ($gateway_types as $key => $gateway_type) {
            if (!property_exists($fees_and_limits, $key)) {
                $fees_and_limits->{$key} = new FeesAndLimits();
            }
        }

        $company_gateway->fees_and_limits = $fees_and_limits;
        $company_gateway->save();

        if ($company_gateway->gateway_key == $this->checkout_key) {
            CheckoutSetupWebhook::dispatch($company_gateway->company->company_key, $company_gateway->fresh()->id);
        } elseif ($company_gateway->gateway_key == $this->forte_key) {

            dispatch(function () use ($company_gateway) {
                MultiDB::setDb($company_gateway->company->db);
                $company_gateway->driver()->updateFees();
            })->afterResponse();

        }

        return $this->itemResponse($company_gateway);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyCompanyGatewayRequest $request
     * @param CompanyGateway $company_gateway
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/company_gateways/{id}",
     *      operationId="deleteCompanyGateway",
     *      tags={"company_gateways"},
     *      summary="Deletes a CompanyGateway",
     *      description="Handles the deletion of an CompanyGateway by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The CompanyGateway Hashed ID",
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
    public function destroy(DestroyCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {
        $company_gateway->driver(new Client())
                         ->disconnect();

        $company_gateway->delete();

        return $this->itemResponse($company_gateway->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/company_gateways/bulk",
     *      operationId="bulkCompanyGateways",
     *      tags={"company_gateways"},
     *      summary="Performs bulk actions on an array of company_gateways",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Array of company gateway IDs",
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
     *          description="The Company Gateways response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/CompanyGateway"),
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
    public function bulk(BulkCompanyGatewayRequest $request)
    {
        $action = $request->input('action');

        $company_gateways = CompanyGateway::withTrashed()
                                          ->whereIn('id', $request->ids)
                                          ->company()
                                          ->cursor()
                                          ->each(function ($company_gateway, $key) use ($action) {
                                              $this->company_repo->{$action}($company_gateway);
                                          });

        return $this->listResponse(CompanyGateway::withTrashed()->company()->whereIn('id', $request->ids));
    }

    public function test(TestCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {

        return response()->json(['message' => $company_gateway->driver()->auth() ? 'true' : 'false'], 200);

    }

    public function importCustomers(TestCompanyGatewayRequest $request, CompanyGateway $company_gateway)
    {

        //Throttle here
        // if (Cache::has("throttle_polling:import_customers:{$company_gateway->company->company_key}:{$company_gateway->hashed_id}")) {
        //     return response()->json(['message' => 'Please wait whilst your previous attempts complete.'], 200);
        // }

        dispatch(function () use ($company_gateway) {
            MultiDB::setDb($company_gateway->company->db);
            $company_gateway->driver()->importCustomers();
        })->afterResponse();

        Cache::put("throttle_polling:import_customers:{$company_gateway->company->company_key}:{$company_gateway->hashed_id}", true, 300);

        return response()->json(['message' => ctrans('texts.import_started')], 200);
    }

}

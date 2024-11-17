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

use App\Factory\ClientGatewayTokenFactory;
use App\Filters\ClientGatewayTokenFilters;
use App\Http\Requests\ClientGatewayToken\CreateClientGatewayTokenRequest;
use App\Http\Requests\ClientGatewayToken\DestroyClientGatewayTokenRequest;
use App\Http\Requests\ClientGatewayToken\EditClientGatewayTokenRequest;
use App\Http\Requests\ClientGatewayToken\ListClientGatewayTokenRequest;
use App\Http\Requests\ClientGatewayToken\ShowClientGatewayTokenRequest;
use App\Http\Requests\ClientGatewayToken\StoreClientGatewayTokenRequest;
use App\Http\Requests\ClientGatewayToken\UpdateClientGatewayTokenRequest;
use App\Jobs\ClientGatewayToken\StoreClientGatewayToken;
use App\Jobs\ClientGatewayToken\UpdateClientGatewayToken;
use App\Models\ClientGatewayToken;
use App\Repositories\ClientGatewayTokenRepository;
use App\Transformers\ClientGatewayTokenTransformer;
use App\Utils\Traits\BulkOptions;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\Uploadable;
use Illuminate\Http\Response;

/**
 * Class ClientGatewayTokenController.
 * @covers App\Http\Controllers\ClientGatewayTokenController
 */
class ClientGatewayTokenController extends BaseController
{
    use MakesHash;
    use Uploadable;
    use BulkOptions;
    use SavesDocuments;

    protected $entity_type = ClientGatewayToken::class;

    protected $entity_transformer = ClientGatewayTokenTransformer::class;

    /**
     * ClientGatewayTokenController constructor.
     * @param ClientGatewayTokenRepository $client_gateway_token_repo
     */
    public function __construct(protected ClientGatewayTokenRepository $client_gateway_token_repo)
    {
        parent::__construct();

        $this->client_gateway_token_repo = $client_gateway_token_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/client_gateway_tokens",
     *      operationId="getClientGatewayTokens",
     *      tags={"client_gateway_tokens"},
     *      summary="Gets a list of client_gateway_tokens",
     *      description="Lists client_gateway_tokens, search and filters allow fine grained lists to be generated.
     * Query parameters can be added to performed more fine grained filtering of the client_gateway_tokens, these are handled by the ClientGatewayTokenFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of client_gateway_tokens",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientGatewayToken"),
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
     * @param ListClientGatewayTokenRequest $request
     * @return Response| \Illuminate\Http\JsonResponse|mixed
     */
    public function index(ListClientGatewayTokenRequest $request)
    {
        $client_gateway_token_gateway_tokens = ClientGatewayToken::query()->company();

        return $this->listResponse($client_gateway_token_gateway_tokens);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowClientGatewayTokenRequest $request
     * @param ClientGatewayToken $client_gateway_token
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/client_gateway_tokens/{id}",
     *      operationId="showClientGatewayToken",
     *      tags={"client_gateway_tokens"},
     *      summary="Shows a client",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientGatewayToken Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the cl.ient object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/ClientGatewayToken"),
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
    public function show(ShowClientGatewayTokenRequest $request, ClientGatewayToken $client_gateway_token)
    {
        return $this->itemResponse($client_gateway_token);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditClientGatewayTokenRequest $request
     * @param ClientGatewayToken $client_gateway_token
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/client_gateway_tokens/{id}/edit",
     *      operationId="editClientGatewayToken",
     *      tags={"client_gateway_tokens"},
     *      summary="Shows a client for editting",
     *      description="Displays a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientGatewayToken Hashed ID",
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
     *          @OA\JsonContent(ref="#/components/schemas/ClientGatewayToken"),
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
    public function edit(EditClientGatewayTokenRequest $request, ClientGatewayToken $client_gateway_token)
    {
        return $this->itemResponse($client_gateway_token);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateClientGatewayTokenRequest $request
     * @param ClientGatewayToken $client_gateway_token
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/client_gateway_tokens/{id}",
     *      operationId="updateClientGatewayToken",
     *      tags={"client_gateway_tokens"},
     *      summary="Updates a client",
     *      description="Handles the updating of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientGatewayToken Hashed ID",
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
     *          @OA\JsonContent(ref="#/components/schemas/ClientGatewayToken"),
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
    public function update(UpdateClientGatewayTokenRequest $request, ClientGatewayToken $client_gateway_token)
    {
        $client_gateway_token = $this->client_gateway_token_repo->save($request->all(), $client_gateway_token);

        return $this->itemResponse($client_gateway_token->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateClientGatewayTokenRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/client_gateway_tokens/create",
     *      operationId="getClientGatewayTokensCreate",
     *      tags={"client_gateway_tokens"},
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
     *          @OA\JsonContent(ref="#/components/schemas/ClientGatewayToken"),
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
    public function create(CreateClientGatewayTokenRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $client_gateway_token = ClientGatewayTokenFactory::create($user->company()->id);

        $client_gateway_token = $this->client_gateway_token_repo->save($request->all(), $client_gateway_token);

        return $this->itemResponse($client_gateway_token);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreClientGatewayTokenRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/client_gateway_tokens",
     *      operationId="storeClientGatewayToken",
     *      tags={"client_gateway_tokens"},
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
     *          @OA\JsonContent(ref="#/components/schemas/ClientGatewayToken"),
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
    public function store(StoreClientGatewayTokenRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $client_gateway_token = ClientGatewayTokenFactory::create($user->company()->id);

        $client_gateway_token = $this->client_gateway_token_repo->save($request->all(), $client_gateway_token);

        return $this->itemResponse($client_gateway_token);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyClientGatewayTokenRequest $request
     * @param ClientGatewayToken $client_gateway_token
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/client_gateway_tokens/{id}",
     *      operationId="deleteClientGatewayToken",
     *      tags={"client_gateway_tokens"},
     *      summary="Deletes a client",
     *      description="Handles the deletion of a client by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The ClientGatewayToken Hashed ID",
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
    public function destroy(DestroyClientGatewayTokenRequest $request, ClientGatewayToken $client_gateway_token)
    {
        $this->client_gateway_token_repo->delete($client_gateway_token);

        return $this->itemResponse($client_gateway_token->fresh());
    }

    public function setAsDefault(UpdateClientGatewayTokenRequest $request, ClientGatewayToken $client_gateway_token)
    {
        $client_gateway_token = $this->client_gateway_token_repo->setDefault($client_gateway_token);

        return $this->itemResponse($client_gateway_token->fresh());
    }
}

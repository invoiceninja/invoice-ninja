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

use App\Factory\TaxRateFactory;
use App\Filters\TaxRateFilters;
use App\Http\Requests\TaxRate\CreateTaxRateRequest;
use App\Http\Requests\TaxRate\DestroyTaxRateRequest;
use App\Http\Requests\TaxRate\EditTaxRateRequest;
use App\Http\Requests\TaxRate\ShowTaxRateRequest;
use App\Http\Requests\TaxRate\StoreTaxRateRequest;
use App\Http\Requests\TaxRate\UpdateTaxRateRequest;
use App\Models\TaxRate;
use App\Repositories\BaseRepository;
use App\Transformers\TaxRateTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

/**
 * Class TaxRateController.
 */
class TaxRateController extends BaseController
{
    use MakesHash;

    protected $entity_type = TaxRate::class;

    protected $entity_transformer = TaxRateTransformer::class;

    protected $base_repo;

    public function __construct(BaseRepository $base_repo)
    {
        parent::__construct();

        $this->base_repo = $base_repo;
    }

    /**
     *      @OA\Get(
     *      path="/api/v1/tax_rates",
     *      operationId="getTaxRates",
     *      tags={"tax_rates"},
     *      summary="Gets a list of tax_rates",
     *      description="Lists tax rates",
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of tax_rates",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaxRate"),
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
     *
     *
     * Display a listing of the resource.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function index(TaxRateFilters $filters)
    {
        $tax_rates = TaxRate::filter($filters);

        return $this->listResponse($tax_rates);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateTaxRateRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/tax_rates/create",
     *      operationId="getTaxRateCreate",
     *      tags={"tax_rates"},
     *      summary="Gets a new blank Tax Rate object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank Tax Rate object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaxRate"),
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
    public function create(CreateTaxRateRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $tax_rate = TaxRateFactory::create($user->company()->id, auth()->user()->id);

        return $this->itemResponse($tax_rate);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreTaxRateRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function store(StoreTaxRateRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $tax_rate = TaxRateFactory::create($user->company()->id, $user->id);
        $tax_rate->fill($request->all());
        $tax_rate->save();

        return $this->itemResponse($tax_rate);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowTaxRateRequest $request
     * @param TaxRate $tax_rate
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/tax_rates/{id}",
     *      operationId="showTaxRate",
     *      tags={"tax_rates"},
     *      summary="Shows a Tax Rate",
     *      description="Displays an TaxRate by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaxRate Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Tax Rate object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaxRate"),
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
    public function show(ShowTaxRateRequest $request, TaxRate $tax_rate)
    {
        return $this->itemResponse($tax_rate);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditTaxRateRequest $request
     * @param TaxRate $tax_rate
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/tax_rates/{id}/edit",
     *      operationId="editTaxRate",
     *      tags={"tax_rates"},
     *      summary="Shows a Tax Rate for editting",
     *      description="Displays a Tax Rate by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaxRate Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Tax Rate object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaxRate"),
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
    public function edit(EditTaxRateRequest $request, TaxRate $tax_rate)
    {
        return $this->itemResponse($tax_rate);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateTaxRateRequest $request
     * @param TaxRate $tax_rate
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/tax_rates/{id}",
     *      operationId="updateTaxRate",
     *      tags={"tax_rates"},
     *      summary="Updates a tax rate",
     *      description="Handles the updating of a tax rate by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaxRate Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the TaxRate object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/TaxRate"),
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
    public function update(UpdateTaxRateRequest $request, TaxRate $tax_rate)
    {
        $tax_rate->fill($request->all());
        $tax_rate->save();

        return $this->itemResponse($tax_rate);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyTaxRateRequest $request
     * @param TaxRate $tax_rate
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/tax_rates/{id}",
     *      operationId="deleteTaxRate",
     *      tags={"tax_rates"},
     *      summary="Deletes a TaxRate",
     *      description="Handles the deletion of an TaxRate by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The TaxRate Hashed ID",
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
    public function destroy(DestroyTaxRateRequest $request, TaxRate $tax_rate)
    {
        $tax_rate->is_deleted = true;
        $tax_rate->save();
        $tax_rate->delete();

        return $this->itemResponse($tax_rate);
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/tax_rates/bulk",
     *      operationId="bulkTaxRates",
     *      tags={"tax_rates"},
     *      summary="Performs bulk actions on an array of TaxRates",
     *      description="",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\RequestBody(
     *         description="Tax Rates",
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
     *          description="The TaxRate List response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Webhook"),
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
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = request()->input('action');
        $ids = request()->input('ids');

        $tax_rates = TaxRate::withTrashed()->find($this->transformKeys($ids));

        $tax_rates->each(function ($tax_rate, $key) use ($action, $user) {
            if ($user->can('edit', $tax_rate)) {

                if (in_array($action, ['archive','delete'])) {
                    $settings = $user->company()->settings;

                    foreach (['tax_name1','tax_name2','tax_name3'] as $tax_name) {

                        if ($settings->{$tax_name} == $tax_rate->name) {
                            $settings->{$tax_name} = '';
                            $settings->{str_replace("name", "rate", $tax_name)} = '';
                        }
                    }

                    $user->company()->saveSettings($settings, $user->company());
                }

                $this->base_repo->{$action}($tax_rate);

            }
        });

        return $this->listResponse(TaxRate::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }
}

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

use App\Factory\DesignFactory;
use App\Filters\DesignFilters;
use App\Http\Requests\Design\CreateDesignRequest;
use App\Http\Requests\Design\DefaultDesignRequest;
use App\Http\Requests\Design\DestroyDesignRequest;
use App\Http\Requests\Design\EditDesignRequest;
use App\Http\Requests\Design\ShowDesignRequest;
use App\Http\Requests\Design\StoreDesignRequest;
use App\Http\Requests\Design\UpdateDesignRequest;
use App\Models\Design;
use App\Repositories\DesignRepository;
use App\Transformers\DesignTransformer;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Class DesignController.
 */
class DesignController extends BaseController
{
    use MakesHash;

    protected $entity_type = Design::class;

    protected $entity_transformer = DesignTransformer::class;

    protected $design_repo;

    /**
     * DesignController constructor.
     * @param DesignRepository $design_repo
     */
    public function __construct(DesignRepository $design_repo)
    {
        parent::__construct();

        $this->design_repo = $design_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/designs",
     *      operationId="getDesigns",
     *      tags={"designs"},
     *      summary="Gets a list of designs",
     *      description="Lists designs",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of designs",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Design"),
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
     * @param DesignFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse|mixed
     */
    public function index(DesignFilters $filters)
    {
        $designs = Design::filter($filters);

        return $this->listResponse($designs);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowDesignRequest $request
     * @param Design $design
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/designs/{id}",
     *      operationId="showDesign",
     *      tags={"designs"},
     *      summary="Shows a design",
     *      description="Displays a design by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Design Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the expense object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Design"),
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
    public function show(ShowDesignRequest $request, Design $design)
    {
        return $this->itemResponse($design);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditDesignRequest $request
     * @param Design $design
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/designs/{id}/edit",
     *      operationId="editDesign",
     *      tags={"designs"},
     *      summary="Shows a design for editting",
     *      description="Displays a design by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Design Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the design object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Design"),
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
    public function edit(EditDesignRequest $request, Design $design)
    {
        return $this->itemResponse($design);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateDesignRequest $request
     * @param Design $design
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/designs/{id}",
     *      operationId="updateDesign",
     *      tags={"designs"},
     *      summary="Updates a design",
     *      description="Handles the updating of a design by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Design Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the design object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Design"),
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
    public function update(UpdateDesignRequest $request, Design $design)
    {
        if ($request->entityIsDeleted($design)) {
            return $request->disallowUpdate();
        }

        $design->fill($request->all());
        $design->save();

        return $this->itemResponse($design->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateDesignRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/designs/create",
     *      operationId="getDesignsCreate",
     *      tags={"designs"},
     *      summary="Gets a new blank design object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank design object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Design"),
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
    public function create(CreateDesignRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $design = DesignFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($design);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreDesignRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/designs",
     *      operationId="storeDesign",
     *      tags={"designs"},
     *      summary="Adds a design",
     *      description="Adds an design to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved design object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Design"),
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
    public function store(StoreDesignRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $design = DesignFactory::create($user->company()->id, $user->id);

        $design->fill($request->all());
        $design->save();

        /*
         This is required as the base template does not know to inject the table elements
        */

        $properties = ['includes', 'header', 'body', 'footer'];

        $d = $design->design;

        $old_header = '<div class="repeating-header" id="header"></div>';
        $new_header = '<table style="min-width: 100%">
   <thead>
      <tr>
         <td>
            <div class="repeating-header-space">&nbsp;</div>
         </td>
      </tr>
   </thead>
   <tbody>
      <tr>
         <td>';

        $old_footer = '<div class="repeating-footer" id="footer">';
        $new_footer = '</td>
      </tr>
   </tbody>
   <tfoot>
      <tr>
         <td>
            <div class="repeating-footer-space">&nbsp;</div>
         </td>
      </tr>
   </tfoot>
</table>

<div class="repeating-header" id="header"></div>

<div class="repeating-footer" id="footer">';

        foreach ($properties as $property) {
            $d->{$property} = str_replace($old_header, $new_header, $d->{$property});
            $d->{$property} = str_replace($old_footer, $new_footer, $d->{$property});
        }

        $design->design = $d;
        // $design->save();

        /*
         This is required as the base template does not know to inject the table elements
        */

        return $this->itemResponse($design->fresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyDesignRequest $request
     * @param Design $design
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/designs/{id}",
     *      operationId="deleteDesign",
     *      tags={"designs"},
     *      summary="Deletes a design",
     *      description="Handles the deletion of a design by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Design Hashed ID",
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
    public function destroy(DestroyDesignRequest $request, Design $design)
    {
        //may not need these destroy routes as we are using actions to 'archive/delete'
        $design->is_deleted = true;
        $design->name = $design->name.'_deleted_'.Str::random(5);
        $design->delete();
        $design->save();

        return $this->itemResponse($design->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/designs/bulk",
     *      operationId="bulkDesigns",
     *      tags={"designs"},
     *      summary="Performs bulk actions on an array of designs",
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
     *          description="The Design User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Design"),
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

        $designs = Design::withTrashed()->company()->whereIn('id', $this->transformKeys($ids));

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $designs->each(function ($design, $key) use ($action, $user) {
            if ($user->can('edit', $design)) {
                $this->design_repo->{$action}($design);
            }
        });

        return $this->listResponse(Design::withTrashed()->company()->whereIn('id', $this->transformKeys($ids)));
    }

    public function default(DefaultDesignRequest $request)
    {
        $design_id = $request->input('design_id');
        $entity = $request->input('entity');
        $settings_level = $request->input('settings_level', 'company');
        $group_settings_id = $request->input('group_settings_id', false);
        $client_id = $request->input('client_id', false);


        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company = $user->getCompany();

        nlog("Design Change {$company->id}");
        nlog($request->all());

        $design = Design::where('company_id', $company->id)
                        ->orWhereNull('company_id')
                        ->where('id', $design_id)
                        ->exists();

        if (! $design) {
            return response()->json(['message' => 'Design does not exist.'], 400);
        }

        switch ($entity) {
            case 'invoice':

                $company->invoices()
                        ->withTrashed()
                        ->when($settings_level == 'company', function ($query) {
                            $query->where(function ($query) {
                                $query->whereDoesntHave('client.group_settings')
                                    ->orWhereHas('client.group_settings', function ($q) {

                                        $q->whereRaw("JSON_EXTRACT(settings, '$.invoice_design_id') IS NULL")
                                        ->orWhereRaw("JSON_EXTRACT(settings, '$.invoice_design_id') = ''");

                                    });
                            });
                        })
                        ->when($settings_level == 'group_settings' && $group_settings_id, function ($query) use ($group_settings_id) {

                            $query->whereHas('client', function ($q) use ($group_settings_id) {
                                $q->where('group_settings_id', $group_settings_id);
                            });

                        })
                        ->when($settings_level == 'client' && $client_id, function ($query) use ($client_id) {

                            $query->where('client_id', $client_id);

                        })->update(['design_id' => $design_id]);

                
                // Recurring Invoice Designs are set using the global company level.
                if ($settings_level == 'company') {
                    $company->recurring_invoices()->withTrashed()->update(['design_id' => $design_id]);
                }

                break;
            case 'quote':

                $company->quotes()
                        ->withTrashed()
                        ->when($settings_level == 'company', function ($query) {
                            $query->where(function ($query) {
                                $query->whereDoesntHave('client.group_settings')
                                    ->orWhereHas('client.group_settings', function ($q) {

                                        $q->whereRaw("JSON_EXTRACT(settings, '$.invoice_design_id') IS NULL")
                                        ->orWhereRaw("JSON_EXTRACT(settings, '$.invoice_design_id') = ''");

                                    });
                            });
                        })
                        ->when($settings_level == 'group_settings' && $group_settings_id, function ($query) use ($group_settings_id) {

                            $query->whereHas('client', function ($q) use ($group_settings_id) {
                                $q->where('group_settings_id', $group_settings_id);
                            });

                        })
                        ->when($settings_level == 'client' && $client_id, function ($query) use ($client_id) {

                            $query->where('client_id', $client_id);

                        })
                        ->update(['design_id' => $design_id]);

                break;
            case 'credit':

                $company->credits()
                        ->withTrashed()
                        ->when($settings_level == 'company', function ($query) {
                            $query->where(function ($query) {
                                $query->whereDoesntHave('client.group_settings')
                                    ->orWhereHas('client.group_settings', function ($q) {

                                        $q->whereRaw("JSON_EXTRACT(settings, '$.invoice_design_id') IS NULL")
                                        ->orWhereRaw("JSON_EXTRACT(settings, '$.invoice_design_id') = ''");

                                    });
                            });
                        })
                        ->when($settings_level == 'group_settings' && $group_settings_id, function ($query) use ($group_settings_id) {

                            $query->whereHas('client', function ($q) use ($group_settings_id) {
                                $q->where('group_settings_id', $group_settings_id);
                            });

                        })
                        ->when($settings_level == 'client' && $client_id, function ($query) use ($client_id) {

                            $query->where('client_id', $client_id);

                        })
                        ->update(['design_id' => $design_id]);

                break;

            case 'purchase_order':
                $company->purchase_orders()->withTrashed()->update(['design_id' => $design_id]);
                break;
            case 'recurring_invoice':
                $company->recurring_invoices()->withTrashed()->update(['design_id' => $design_id]);

                break;
            default:
                // code...
                break;
        }

        return response()->json(['message' => 'success'], 200);
    }
}

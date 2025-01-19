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

use App\Models\Account;
use App\Models\Invoice;
use App\Models\Project;
use Illuminate\Http\Response;
use App\Factory\ProjectFactory;
use App\Filters\ProjectFilters;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\GeneratesCounter;
use App\Repositories\ProjectRepository;
use App\Transformers\ProjectTransformer;
use App\Services\Template\TemplateAction;
use App\Http\Requests\Project\BulkProjectRequest;
use App\Http\Requests\Project\EditProjectRequest;
use App\Http\Requests\Project\ShowProjectRequest;
use App\Http\Requests\Project\StoreProjectRequest;
use App\Http\Requests\Project\CreateProjectRequest;
use App\Http\Requests\Project\UpdateProjectRequest;
use App\Http\Requests\Project\UploadProjectRequest;
use App\Http\Requests\Project\DestroyProjectRequest;
use App\Http\Requests\Project\InvoiceProjectRequest;
use App\Transformers\InvoiceTransformer;

/**
 * Class ProjectController.
 */
class ProjectController extends BaseController
{
    use MakesHash;
    use SavesDocuments;
    use GeneratesCounter;

    protected $entity_type = Project::class;

    protected $entity_transformer = ProjectTransformer::class;

    protected $project_repo;

    /**
     * ProjectController constructor.
     * @param ProjectRepository $project_repo
     */
    public function __construct(ProjectRepository $project_repo)
    {
        parent::__construct();

        $this->project_repo = $project_repo;
    }

    /**
     * @OA\Get(
     *      path="/api/v1/projects",
     *      operationId="getProjects",
     *      tags={"projects"},
     *      summary="Gets a list of projects",
     *      description="Lists projects",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(ref="#/components/parameters/index"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of projects",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
     * @param ProjectFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse|mixed
     */
    public function index(ProjectFilters $filters)
    {
        $projects = Project::filter($filters);

        return $this->listResponse($projects);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowProjectRequest $request
     * @param Project $project
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/projects/{id}",
     *      operationId="showProject",
     *      tags={"projects"},
     *      summary="Shows a project",
     *      description="Displays a project by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Project Hashed ID",
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
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
    public function show(ShowProjectRequest $request, Project $project)
    {
        return $this->itemResponse($project);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditProjectRequest $request
     * @param Project $project
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/projects/{id}/edit",
     *      operationId="editProject",
     *      tags={"projects"},
     *      summary="Shows a project for editting",
     *      description="Displays a project by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Project Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the project object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
    public function edit(EditProjectRequest $request, Project $project)
    {
        return $this->itemResponse($project);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateProjectRequest $request
     * @param Project $project
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/projects/{id}",
     *      operationId="updateProject",
     *      tags={"projects"},
     *      summary="Updates a project",
     *      description="Handles the updating of a project by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Project Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the project object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
    public function update(UpdateProjectRequest $request, Project $project)
    {
        if ($request->entityIsDeleted($project)) {
            return $request->disallowUpdate();
        }

        $project->fill($request->all());
        $project->number = empty($project->number) ? $this->getNextProjectNumber($project) : $project->number;
        $project->saveQuietly();

        if ($request->has('documents')) {
            $this->saveDocuments($request->input('documents'), $project, $request->input('is_public', true));
        }

        event('eloquent.updated: App\Models\Project', $project);

        return $this->itemResponse($project->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateProjectRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/projects/create",
     *      operationId="getProjectsCreate",
     *      tags={"projects"},
     *      summary="Gets a new blank project object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank project object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
    public function create(CreateProjectRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $project = ProjectFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($project);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProjectRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/projects",
     *      operationId="storeProject",
     *      tags={"projects"},
     *      summary="Adds a project",
     *      description="Adds an project to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved project object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
    public function store(StoreProjectRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $project = ProjectFactory::create($user->company()->id, $user->id);
        $project->fill($request->all());
        $project->saveQuietly();

        if (empty($project->number)) {
            $project->number = $this->getNextProjectNumber($project);
            $project->saveQuietly();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->input('documents'), $project, $request->input('is_public', true));
        }

        event('eloquent.created: App\Models\Project', $project);

        return $this->itemResponse($project->fresh());
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyProjectRequest $request
     * @param Project $project
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/projects/{id}",
     *      operationId="deleteProject",
     *      tags={"projects"},
     *      summary="Deletes a project",
     *      description="Handles the deletion of a project by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Project Hashed ID",
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
    public function destroy(DestroyProjectRequest $request, Project $project)
    {
        //may not need these destroy routes as we are using actions to 'archive/delete'
        $project->is_deleted = true;
        $project->delete();
        $project->save();

        return $this->itemResponse($project->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/projects/bulk",
     *      operationId="bulkProjects",
     *      tags={"projects"},
     *      summary="Performs bulk actions on an array of projects",
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
     *          description="The Project User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
    public function bulk(BulkProjectRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = $request->input('action');

        $ids = $request->input('ids');

        $projects = Project::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if($action == 'invoice' && $user->can('edit', $projects->first())) {
           $invoice = $this->project_repo->invoice($projects);
           $this->entity_transformer = InvoiceTransformer::class;
           $this->entity_type = Invoice::class;
           return $this->itemResponse($invoice);
        }

        if ($action == 'template' && $user->can('view', $projects->first())) {

            $hash_or_response = $request->boolean('send_email') ? 'email sent' : \Illuminate\Support\Str::uuid();

            TemplateAction::dispatch(
                $projects->pluck('hashed_id')->toArray(),
                $request->template_id,
                Project::class,
                $user->id,
                $user->company(),
                $user->company()->db,
                $hash_or_response,
                $request->boolean('send_email')
            );

            return response()->json(['message' => $hash_or_response], 200);
        }

        $projects->each(function ($project) use ($action, $user) {
            if ($user->can('edit', $project)) {
                $this->project_repo->{$action}($project);
            }
        });

        return $this->listResponse(Project::withTrashed()->whereIn('id', $this->transformKeys($ids)));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadProjectRequest $request
     * @param Project $project
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Put(
     *      path="/api/v1/projects/{id}/upload",
     *      operationId="uploadProject",
     *      tags={"projects"},
     *      summary="Uploads a document to a project",
     *      description="Handles the uploading of a document to a project",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Project Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the Project object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Project"),
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
    public function upload(UploadProjectRequest $request, Project $project)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $project, $request->input('is_public', true));
        }

        return $this->itemResponse($project->fresh());
    }

    public function invoice(InvoiceProjectRequest $request, Project $project)
    {
        $this->entity_transformer = InvoiceTransformer::class;
        $this->entity_type = Invoice::class;

        $invoice = $this->project_repo->invoice($project);

        return $this->itemResponse($invoice);
    }
}

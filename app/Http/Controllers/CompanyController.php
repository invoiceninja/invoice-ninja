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

use App\DataMapper\Analytics\AccountDeleted;
use App\DataMapper\CompanySettings;
use App\Http\Requests\Company\CreateCompanyRequest;
use App\Http\Requests\Company\DefaultCompanyRequest;
use App\Http\Requests\Company\DestroyCompanyRequest;
use App\Http\Requests\Company\EditCompanyRequest;
use App\Http\Requests\Company\ShowCompanyRequest;
use App\Http\Requests\Company\StoreCompanyRequest;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\Requests\Company\UploadCompanyRequest;
use App\Jobs\Company\CompanyTaxRate;
use App\Jobs\Company\CreateCompany;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Company\CompanyDeleted;
use App\Models\Account;
use App\Models\Company;
use App\Models\CompanyUser;
use App\Repositories\CompanyRepository;
use App\Transformers\CompanyTransformer;
use App\Transformers\CompanyUserTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\Uploadable;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Turbo124\Beacon\Facades\LightLogs;

/**
 * Class CompanyController.
 */
class CompanyController extends BaseController
{
    use DispatchesJobs;
    use MakesHash;
    use Uploadable;
    use SavesDocuments;

    protected $entity_type = Company::class;

    protected $entity_transformer = CompanyTransformer::class;

    protected $company_repo;

    public $forced_includes = [];

    /**
     * CompanyController constructor.
     * @param CompanyRepository $company_repo
     */
    public function __construct(CompanyRepository $company_repo)
    {
        parent::__construct();

        $this->company_repo = $company_repo;

        $this->middleware('password_protected')->only(['destroy']);
    }

    /**
     * Display a listing of the resource.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/companies",
     *      operationId="getCompanies",
     *      tags={"companies"},
     *      summary="Gets a list of companies",
     *      description="Lists companies, search and filters allow fine grained lists to be generated.

     *   Query parameters can be added to performed more fine grained filtering of the companies, these are handled by the CompanyFilters class which defines the methods available",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of companies",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function index()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $companies = Company::where('account_id', $user->company()->account->id);

        return $this->listResponse($companies);
    }

    public function current()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company = Company::find($user->company()->id);

        return $this->itemResponse($company);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateCompanyRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Get(
     *      path="/api/v1/companies/create",
     *      operationId="getCompaniesCreate",
     *      tags={"companies"},
     *      summary="Gets a new blank company object",
     *      description="Returns a blank object with default values",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A blank company object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function create(CreateCompanyRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company_factory = new \App\Factory\CompanyFactory();

        $company = $company_factory->create($user->company()->account->id);

        return $this->itemResponse($company);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreCompanyRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Post(
     *      path="/api/v1/companies",
     *      operationId="storeCompany",
     *      tags={"companies"},
     *      summary="Adds a company",
     *      description="Adds an company to the system",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the saved company object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function store(StoreCompanyRequest $request)
    {
        $this->forced_includes = ['company_user'];

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $company = (new CreateCompany($request->all(), $user->company()->account))->handle();
        (new CreateCompanyPaymentTerms($company, $user))->handle();
        (new CreateCompanyTaskStatuses($company, $user))->handle();

        $company = $this->company_repo->save($request->all(), $company);

        $this->uploadLogo($request->file('company_logo'), $company, $company);

        $user->companies()->attach($company->id, [
            'account_id' => $company->account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'permissions' => '',
            'settings' => null,
            'notifications' => CompanySettings::notificationDefaults(),
        ]);

        if ($company->account->companies()->where('is_large', 1)->exists()) {
            $company->account->companies()->update(['is_large' => true]);
        }

        /*
         * Required dependencies
         */
        $user->setCompany($company);

        /*
         * Create token
         */
        $user_agent = request()->has('token_name') ? request()->input('token_name') : request()->server('HTTP_USER_AGENT');

        $company_token = (new CreateCompanyToken($company, auth()->user(), $user_agent))->handle();
        $this->entity_transformer = CompanyUserTransformer::class;
        $this->entity_type = CompanyUser::class;

        $ct = CompanyUser::whereUserId(auth()->user()->id)->whereCompanyId($company->id);

        return $this->listResponse($ct);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowCompanyRequest $request
     * @param Company $company
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/companies/{id}",
     *      operationId="showCompany",
     *      tags={"companies"},
     *      summary="Shows an company",
     *      description="Displays an company by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the company object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function show(ShowCompanyRequest $request, Company $company)
    {
        return $this->itemResponse($company);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditCompanyRequest $request
     * @param Company $company
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Get(
     *      path="/api/v1/companies/{id}/edit",
     *      operationId="editCompany",
     *      tags={"companies"},
     *      summary="Shows an company for editting",
     *      description="Displays an company by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the company object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function edit(EditCompanyRequest $request, Company $company)
    {
        return $this->itemResponse($company);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateCompanyRequest $request
     * @param Company $company
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @OA\Put(
     *      path="/api/v1/companies/{id}",
     *      operationId="updateCompany",
     *      tags={"companies"},
     *      summary="Updates an company",
     *      description="Handles the updating of an company by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the company object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function update(UpdateCompanyRequest $request, Company $company)
    {
        if ($request->hasFile('company_logo') || (is_array($request->input('settings')) && ! array_key_exists('company_logo', $request->input('settings')))) {
            $this->removeLogo($company);
        }

        $company = $this->company_repo->save($request->all(), $company);

        if ($request->has('documents')) {
            $this->saveDocuments($request->input('documents'), $company, $request->input('is_public', true));
        }

        if ($request->has('e_invoice_certificate') && !is_null($request->file("e_invoice_certificate"))) {

            $company->e_invoice_certificate = base64_encode($request->file("e_invoice_certificate")->get());

            $settings = $company->settings;
            $settings->enable_e_invoice = true;

            $company->save();

        }

        $this->uploadLogo($request->file('company_logo'), $company, $company);

        return $this->itemResponse($company);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyCompanyRequest $request
     * @param Company $company
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     * @throws \Exception
     * @OA\Delete(
     *      path="/api/v1/companies/{id}",
     *      operationId="deleteCompany",
     *      tags={"companies"},
     *      summary="Deletes a company",
     *      description="Handles the deletion of an company by id",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Company Hashed ID",
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
    public function destroy(DestroyCompanyRequest $request, Company $company)
    {
        if (Ninja::isHosted() && config('ninja.ninja_default_company_id') == $company->id) {
            return response()->json(['message' => 'Cannot purge this company'], 400);
        }

        $company_count = $company->account->companies->count();
        $account = $company->account;
        $account_key = $account->key;

        if ($company_count == 1) {
            $company->company_users->each(function ($company_user) {
                $company_user->user->forceDelete();
                $company_user->forceDelete();
            });

            try {
                Storage::disk(config('filesystems.default'))->deleteDirectory($company->company_key);
            } catch (\Exception $e) {
            }

            $account->delete();

            if (Ninja::isHosted()) {
                \Modules\Admin\Jobs\Account\NinjaDeletedAccount::dispatch($account_key, $request->all(), auth()->user()->email);
            }

            LightLogs::create(new AccountDeleted())
                     ->increment()
                     ->batch();
        } else {
            $company_id = $company->id;

            $company->company_users->each(function ($company_user) {
                $company_user->forceDelete();
            });

            $other_company = $company->account->companies->where('id', '!=', $company->id)->first();

            $nmo = new NinjaMailerObject();
            $nmo->mailable = new CompanyDeleted($company->present()->name, auth()->user(), $company->account, $company->settings);
            $nmo->company = $other_company;
            $nmo->settings = $other_company->settings;
            $nmo->to_user = auth()->user();
            (new NinjaMailerJob($nmo, true))->handle();

            try {
                Storage::disk(config('filesystems.default'))->deleteDirectory($company->company_key);
            } catch (\Exception $e) {
            }


            $company->delete();

            //If we are deleting the default companies, we'll need to make a new company the default.
            if ($account->default_company_id == $company_id) {

                /** @var \App\Models\Company $new_default_company **/
                $new_default_company = Company::whereAccountId($account->id)->first();
                $account->default_company_id = $new_default_company->id;
                $account->save();
            }
        }

        return response()->json(['message' => ctrans('texts.success')], 200);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadCompanyRequest $request
     * @param Company $company
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Put(
     *      path="/api/v1/companies/{id}/upload",
     *      operationId="uploadCompanies",
     *      tags={"companies"},
     *      summary="Uploads a document to a company",
     *      description="Handles the uploading of a document to a company",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="id",
     *          in="path",
     *          description="The Company Hashed ID",
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
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function upload(UploadCompanyRequest $request, Company $company)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $company, $request->input('is_public', true));
        }

        return $this->itemResponse($company->fresh());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param DefaultCompanyRequest $request
     * @param Company $company
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     *
     * @OA\Post(
     *      path="/api/v1/companies/{company}/default",
     *      operationId="setDefaultCompany",
     *      tags={"companies"},
     *      summary="Sets the company as the default company.",
     *      description="Sets the company as the default company.",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Parameter(
     *          name="company",
     *          in="path",
     *          description="The Company Hashed ID",
     *          example="D2J234DFA",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Returns the company object",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Company"),
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
    public function default(DefaultCompanyRequest $request, Company $company)
    {
        $account = $company->account;
        $account->default_company_id = $company->id;
        $account->save();

        return $this->itemResponse($company->fresh());
    }

    public function updateOriginTaxData(DefaultCompanyRequest $request, Company $company)
    {

        if ($company->settings->country_id == "840" && !$company->account->isFreeHostedClient()) {
            try {
                (new CompanyTaxRate($company))->handle();
            } catch (\Exception $e) {
                return response()->json(['message' => 'There was a problem updating the tax rates. Please try again.'], 400);
            }
        } else {
            return response()->json(['message' => 'Tax configuration not available due to settings / plan restriction.'], 400);
        }

        return $this->itemResponse($company->fresh());
    }

    /**
     *
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse | \Illuminate\Http\JsonResponse
     */
    public function logo()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();
        $company = $user->company();
        $logo = strlen($company->settings->company_logo) > 5 ? $company->settings->company_logo : 'https://pdf.invoicing.co/favicon-v2.png';
        $headers = ['Content-Disposition' => 'inline'];

        try {
            $response = \Illuminate\Support\Facades\Http::get($logo);

            if ($response->successful()) {
                $logo = $response->body();
            } else {
                $logo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
            }

        } catch (\Exception $e) {

            $logo = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');

        }

        return response()->streamDownload(function () use ($logo) {
            echo $logo;
        }, 'logo.png', $headers);

    }
}

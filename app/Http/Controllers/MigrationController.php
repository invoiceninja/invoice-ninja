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

use App\Console\Commands\ImportMigrations;
use App\DataMapper\CompanySettings;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\StartMigration;
use App\Mail\ExistingMigration;
use App\Mail\Migration\MaxCompanies;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Utils\Ninja;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;

class MigrationController extends BaseController
{
    use DispatchesJobs;

    public bool $silent_migration = false;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Purge Company.
     *
     * @OA\Post(
     *      path="/api/v1/migration/purge/{company}",
     *      operationId="postPurgeCompany",
     *      tags={"migration"},
     *      summary="Attempts to purge a company record and all its child records",
     *      description="Attempts to purge a company record and all its child records",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
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
     *          description="Success",
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
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     * @throws \Exception
     */
    public function purgeCompany(Company $company)
    {
        if (Ninja::isHosted() && config('ninja.ninja_default_company_id') == $company->id) {
            return response()->json(['message' => 'Cannot purge this company'], 400);
        }

        $account = $company->account;
        $company_id = $company->id;

        $company->delete();

        /*Update the new default company if necessary*/
        if ($company_id == $account->default_company_id && $account->companies->count() >= 1) {
            $new_default_company = $account->companies->first();

            if ($new_default_company) {
                $account->default_company_id = $new_default_company->id;
                $account->save();
            }
        }

        return response()->json(['message' => 'Company purged'], 200);
    }

    private function purgeCompanyWithForceFlag(Company $company)
    {
        if (Ninja::isHosted() && config('ninja.ninja_default_company_id') == $company->id) {
            return response()->json(['message' => 'Cannot purge this company'], 400);
        }

        $account = $company->account;
        $company_id = $company->id;

        $company->delete();

        /*Update the new default company if necessary*/
        if ($company_id == $account->default_company_id && $account->companies->count() >= 1) {
            $new_default_company = $account->companies->first();

            if ($new_default_company) {
                $account->default_company_id = $new_default_company->id;
                $account->save();
            }
        }
    }

    /**
     * Purge Company but save settings.
     *
     * @OA\Post(
     *      path="/api/v1/migration/purge_save_settings/{company}",
     *      operationId="postPurgeCompanySaveSettings",
     *      tags={"migration"},
     *      summary="Attempts to purge a companies child records but save the company record and its settings",
     *      description="Attempts to purge a companies child records but save the company record and its settings",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
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
     *          description="Success",
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
     * @param Request $request
     * @param Company $company
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function purgeCompanySaveSettings(Request $request, Company $company)
    {
        $company->clients()->forceDelete();
        $company->products()->forceDelete();
        $company->projects()->forceDelete();
        $company->tasks()->forceDelete();
        $company->vendors()->forceDelete();
        $company->expenses()->forceDelete();
        $company->purchase_orders()->forceDelete();
        $company->bank_transaction_rules()->forceDelete();
        $company->bank_transactions()->forceDelete();
        // $company->bank_integrations()->forceDelete();

        $company->all_activities()->forceDelete();

        $settings = $company->settings;

        /* Reset all counters to 1 after a purge */
        $settings->recurring_invoice_number_counter = 1;
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->client_number_counter = 1;
        $settings->credit_number_counter = 1;
        $settings->task_number_counter = 1;
        $settings->expense_number_counter = 1;
        $settings->recurring_expense_number_counter = 1;
        $settings->recurring_quote_number_counter = 1;
        $settings->vendor_number_counter = 1;
        $settings->ticket_number_counter = 1;
        $settings->payment_number_counter = 1;
        $settings->project_number_counter = 1;
        $settings->purchase_order_number_counter = 1;

        $company->settings = $settings;

        $company->save();

        return response()->json(['message' => 'Settings preserved'], 200);
    }

    /**
     * Start the migration from V1.
     *
     * @OA\Post(
     *      path="/api/v1/migration/start",
     *      operationId="postStartMigration",
     *      tags={"migration"},
     *      summary="Starts the migration from previous version of Invoice Ninja",
     *      description="Starts the migration from previous version of Invoice Ninja",
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/X-API-PASSWORD"),
     *      @OA\Parameter(
     *          name="migration",
     *          in="query",
     *          description="The migraton file",
     *          example="migration.zip",
     *          required=true,
     *          @OA\Schema(
     *              type="object",
     *              format="file",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success",
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
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response|void
     */
    public function startMigration(Request $request)
    {
        nlog('Starting Migration');

        if ($request->has('silent_migration')) {
            $this->silent_migration = true;
        }

        if ($request->companies) {
            //handle Laravel 5.5 UniHTTP
            $companies = json_decode($request->companies, true);
        } else {
            //handle Laravel 6 Guzzle
            $companies = [];

            foreach ($request->all() as $input) {
                if ($input instanceof UploadedFile) {
                } else {
                    $companies[] = json_decode($input, true);
                }
            }
        }

        foreach ($companies as $company) {
            if (! is_array($company)) {
                continue;
            }

            $company = (array) $company;

            $user = auth()->user();

            $company_count = $user->account->companies()->count();
            $fresh_company = false;

            // Look for possible existing company (based on company keys).
            $existing_company = Company::query()->whereRaw('BINARY `company_key` = ?', [$company['company_key']])->first();

            App::forgetInstance('translator');
            $t = app('translator');
            $t->replace(Ninja::transformTranslations($user->account->companies()->first()->settings));
            App::setLocale($user->account->companies()->first()->getLocale());

            if (! $existing_company && $company_count >= 10) {
                $nmo = new NinjaMailerObject();
                $nmo->mailable = new MaxCompanies($user->account->companies()->first());
                $nmo->company = $user->account->companies()->first();
                $nmo->settings = $user->account->companies()->first()->settings;
                $nmo->to_user = $user;

                if (!$this->silent_migration) {
                    NinjaMailerJob::dispatch($nmo, true);
                }

                return;
            } elseif ($existing_company && $company_count > 10) {
                $nmo = new NinjaMailerObject();
                $nmo->mailable = new MaxCompanies($user->account->companies()->first());
                $nmo->company = $user->account->companies()->first();
                $nmo->settings = $user->account->companies()->first()->settings;
                $nmo->to_user = $user;

                if (!$this->silent_migration) {
                    NinjaMailerJob::dispatch($nmo, true);
                }

                return;
            }

            $checks = [
                'existing_company' => $existing_company ? (bool) 1 : false,
                'force' => array_key_exists('force', $company) ? (bool) $company['force'] : false,
            ];

            // If there's existing company and ** no ** force is provided - skip migration.
            if ($checks['existing_company'] == true && $checks['force'] == false) {
                nlog('Migrating: Existing company without force. (CASE_01)');

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new ExistingMigration($existing_company);
                $nmo->company = $user->account->companies()->first();
                $nmo->settings = $user->account->companies()->first();
                $nmo->to_user = $user;

                if (!$this->silent_migration) {
                    NinjaMailerJob::dispatch($nmo, true);
                }

                return response()->json([
                    '_id' => Str::uuid(),
                    'method' => config('queue.default'),
                    'started_at' => now(),
                ], 200);
            }

            // If there's existing company and force ** is provided ** - purge the company and migrate again.
            if ($checks['existing_company'] == true && $checks['force'] == true) {
                nlog('purging the existing company here');
                $this->purgeCompanyWithForceFlag($existing_company);

                $account = auth()->user()->account;
                $fresh_company = (new ImportMigrations())->getCompany($account);
                $fresh_company->is_disabled = true;
                $fresh_company->save();

                $account->default_company_id = $fresh_company->id;
                $account->save();

                $fresh_company_token = new CompanyToken();
                $fresh_company_token->user_id = $user->id;
                $fresh_company_token->company_id = $fresh_company->id;
                $fresh_company_token->account_id = $account->id;
                $fresh_company_token->name = $request->token_name ?? Str::random(12);
                $fresh_company_token->token = $request->token ?? Str::random(64);
                $fresh_company_token->is_system = true;
                $fresh_company_token->save();

                /** @var \App\Models\User $user */
                $user->companies()->attach($fresh_company->id, [
                    'account_id' => $account->id,
                    'is_owner' => 1,
                    'is_admin' => 1,
                    'is_locked' => 0,
                    'notifications' => CompanySettings::notificationDefaults(),
                    'permissions' => '',
                    'settings' => null,
                ]);
            }

            // If there's no existing company migrate just normally.
            if ($checks['existing_company'] == false) {
                nlog('creating fresh company');

                $account = auth()->user()->account;
                $fresh_company = (new ImportMigrations())->getCompany($account);

                $fresh_company->is_disabled = true;
                $fresh_company->save();

                $fresh_company_token = new CompanyToken();
                $fresh_company_token->user_id = $user->id;
                $fresh_company_token->company_id = $fresh_company->id;
                $fresh_company_token->account_id = $account->id;
                $fresh_company_token->name = $request->token_name ?? Str::random(12);
                $fresh_company_token->token = $request->token ?? Str::random(64);
                $fresh_company_token->is_system = true;

                $fresh_company_token->save();

                /** @var \App\Models\User $user */
                $user->companies()->attach($fresh_company->id, [
                    'account_id' => $account->id,
                    'is_owner' => 1,
                    'is_admin' => 1,
                    'is_locked' => 0,
                    'notifications' => CompanySettings::notificationDefaults(),
                    'permissions' => '',
                    'settings' => null,
                ]);
            }

            $migration_file = $request->file($company['company_index'])
            ->storeAs(
                'migrations',
                $request->file($company['company_index'])->getClientOriginalName(),
                'public'
            );

            if (app()->environment() == 'testing') {
                nlog('environment is testing = bailing out now');

                return;
            }

            nlog('starting migration job');
            nlog($migration_file);

            if (Ninja::isHosted()) {
                StartMigration::dispatch($migration_file, $user, $fresh_company, $this->silent_migration)->onQueue('migration');
            } else {
                StartMigration::dispatch($migration_file, $user, $fresh_company, $this->silent_migration);
            }
        }

        return response()->json([
            '_id' => Str::uuid(),
            'method' => config('queue.default'),
            'started_at' => now(),
        ], 200);

    }
}

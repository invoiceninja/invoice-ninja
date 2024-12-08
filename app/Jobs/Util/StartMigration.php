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

namespace App\Jobs\Util;

use App\Exceptions\ClientHostedMigrationException;
use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\NonExistingMigrationFile;
use App\Exceptions\ProcessingMigrationArchiveFailed;
use App\Exceptions\ResourceDependencyMissing;
use App\Exceptions\ResourceNotAvailableForMigration;
use App\Libraries\MultiDB;
use App\Mail\MigrationFailed;
use App\Models\Company;
use App\Models\User;
use App\Utils\Ninja;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class StartMigration implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private $filepath;

    /**
     * @var User
     */
    private $user;

    /**
     * @var Company
     */
    private $company;

    private $silent_migration;

    /**
     * Create a new job instance.
     *
     * @param $filepath
     * @param User $user
     * @param Company $company
     */
    public $tries = 1;

    public $timeout = 0;

    public function __construct($filepath, User $user, Company $company, $silent_migration = false)
    {
        $this->filepath = $filepath;
        $this->user = $user;
        $this->company = $company;
        $this->silent_migration = $silent_migration;
    }

    /**
     * Execute the job.
     *
     * @return bool
     */
    public function handle()
    {
        nlog('Inside Migration Job');

        Cache::put("migration-{$this->company->company_key}", "started", 86400);

        set_time_limit(0);

        MultiDB::setDb($this->company->db);

        auth()->login($this->user, false);

        auth()->user()->setCompany($this->company);

        $this->company->is_disabled = true;
        $this->company->save();

        $zip = new ZipArchive();
        $archive = $zip->open(public_path("storage/{$this->filepath}"));
        $filename = pathinfo($this->filepath, PATHINFO_FILENAME);

        $update_product_flag = $this->company->update_products;

        $this->company->update_products = false;
        $this->company->save();

        try {
            if (! $archive) {
                throw new ProcessingMigrationArchiveFailed('Processing migration archive failed. Migration file is possibly corrupted.');
            }

            $zip->extractTo(public_path("storage/migrations/{$filename}"));
            $zip->close();

            if (app()->environment() == 'testing') {
                return true;
            }

            $file = public_path("storage/migrations/$filename/migration.json");

            if (! file_exists($file)) {
                throw new NonExistingMigrationFile('Migration file does not exist, or it is corrupted.');
            }

            (new Import($file, $this->company, $this->user, [], $this->silent_migration))->handle();

            Storage::deleteDirectory(public_path("storage/migrations/{$filename}"));

            $this->company->update_products = $update_product_flag;
            $this->company->save();

            Cache::put("migration-{$this->company->company_key}", "completed", 86400);

            App::forgetInstance('translator');
            $t = app('translator');
            $t->replace(Ninja::transformTranslations($this->company->settings));
        } catch (ClientHostedMigrationException | NonExistingMigrationFile | ProcessingMigrationArchiveFailed | ResourceNotAvailableForMigration | MigrationValidatorFailed | ResourceDependencyMissing | \Exception $e) {
            $this->company->update_products = $update_product_flag;
            $this->company->save();

            Cache::put("migration-{$this->company->company_key}", "failed", 86400);

            if (Ninja::isHosted()) {
                app('sentry')->captureException($e);
            }

            if (!$this->silent_migration) {
                Mail::to($this->user->email, $this->user->name())->send(new MigrationFailed($e, $this->company, $e->getMessage()));
            }

            if (Ninja::isHosted()) {
                $migration_failed = new MigrationFailed($e, $this->company, $e->getMessage());
                $migration_failed->is_system = true;

                Mail::to('contact@invoiceninja.com', 'Failed Migration')->send($migration_failed);
            }

            if (app()->environment() !== 'production') {
                info($e->getMessage());
            }

            Storage::deleteDirectory(public_path("storage/migrations/{$filename}"));

        }

        return true;
    }

    public function failed($exception = null)
    {
        nlog($exception->getMessage());
    }
}

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

namespace App\Console\Commands;

use App\Libraries\MultiDB;
use App\Models\Backup;
use App\Models\Client;
use App\Models\Company;
use App\Models\Document;
use App\Models\GroupSetting;
use App\Utils\Ninja;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class BackupUpdate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:backup-files {--disk=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Shift files between object storage locations';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        //always return state to first DB

        if (Ninja::isSelfHost()) {
            return;
        }

        $current_db = config('database.default');

        if (! config('ninja.db.multi_db_enabled')) {
            $this->handleOnDb();
        } else {
            //multiDB environment, need to
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                $this->handleOnDb();
            }

            MultiDB::setDB($current_db);
        }
    }

    private function handleOnDb()
    {
        set_time_limit(0);

        //logos
        Company::cursor()
               ->each(function ($company) {
                   $company_logo_path = $company->settings->company_logo;

                   if ($company_logo_path == config('ninja.app_logo') || $company_logo_path == '') {
                       return;
                   }

                   $logo = @file_get_contents($company_logo_path);
                   $extension = @pathinfo($company->settings->company_logo, PATHINFO_EXTENSION);

                   if ($logo && $extension) {
                       $path = "{$company->company_key}/{$company->company_key}.{$extension}";

                       Storage::disk($this->option('disk'))->put($path, $logo);

                       $url = Storage::disk($this->option('disk'))->url($path);

                       nlog("Company - Moving {$company_logo_path} logo to {$this->option('disk')} final URL = {$url}}");

                       $settings = $company->settings;
                       $settings->company_logo = $url;
                       $company->settings = $settings;
                       ;
                       $company->save();
                   }
               });

        Client::withTrashed()
              ->whereNotNull('settings->company_logo')
              ->cursor()
              ->each(function ($client) {
                  $company_logo_path = $client->settings->company_logo;

                  $logo = @file_get_contents($company_logo_path);
                  $extension = @pathinfo($company_logo_path, PATHINFO_EXTENSION);

                  if ($logo && $extension) {
                      $path = "{$client->company->company_key}/{$client->client_hash}.{$extension}";

                      Storage::disk($this->option('disk'))->put($path, $logo);

                      $url = Storage::disk($this->option('disk'))->url($path);

                      nlog("Client - Moving {$company_logo_path} logo to {$this->option('disk')} final URL = {$url}}");

                      $settings = $client->settings;
                      $settings->company_logo = $url;
                      $client->settings = $settings;
                      ;
                      $client->saveQuietly();
                  }
              });

        GroupSetting::withTrashed()
              ->whereNotNull('settings->company_logo')
              ->orWhere('settings->company_logo', '!=', '')
              ->cursor()
              ->each(function ($group) {
                  $company_logo_path = $group->settings->company_logo;

                  if (!$company_logo_path) {
                      return;
                  }

                  $logo = @file_get_contents($company_logo_path);
                  $extension = @pathinfo($company_logo_path, PATHINFO_EXTENSION);

                  if ($logo && $extension) {
                      $path = "{$group->company->company_key}/{$group->hashed_id}.{$extension}";

                      Storage::disk($this->option('disk'))->put($path, $logo);

                      $url = Storage::disk($this->option('disk'))->url($path);

                      nlog("Group - Moving {$company_logo_path} logo to {$this->option('disk')} final URL = {$url}}");

                      $settings = $group->settings;
                      $settings->company_logo = $url;
                      $group->settings = $settings;
                      ;
                      $group->saveQuietly();
                  }
              });



        //documents
        Document::cursor()
                ->each(function (Document $document) {
                    $doc_bin = false;

                    try {
                        $doc_bin = $document->getFile();
                    } catch (\Exception $e) {
                        nlog("Exception:: BackupUpdate::" . $e->getMessage());
                    }

                    if ($doc_bin) {
                        Storage::disk($this->option('disk'))->put($document->url, $doc_bin);

                        $document->disk = $this->option('disk');
                        $document->saveQuietly();
                    }
                });


        //backups
        Backup::whereNotNull('filename')
                ->where('filename', '!=', '')
                ->cursor()
                ->each(function ($backup) {
                    $backup_bin = Storage::disk('s3')->get($backup->filename);

                    if ($backup_bin) {
                        Storage::disk($this->option('disk'))->put($backup->filename, $backup_bin);
                    }
                });
    }
}

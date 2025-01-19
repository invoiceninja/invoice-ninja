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

use App\Exceptions\FilePermissionsFailure;
use App\Models\Company;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\ClientGroupSettingsSaver;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

class SelfUpdateController extends BaseController
{
    use DispatchesJobs;
    use ClientGroupSettingsSaver;
    use AppSetup;

    private string $filename = 'invoiceninja.tar';

    private string $version = '';

    private array $purge_file_list = [
        'bootstrap/cache/compiled.php',
        'bootstrap/cache/config.php',
        'bootstrap/cache/packages.php',
        'bootstrap/cache/services.php',
        'bootstrap/cache/routes-v7.php',
        'bootstrap/cache/livewire-components.php',
        'public/index.html',
    ];

    public function __construct()
    {
    }

    public function update()
    {
        set_time_limit(0);
        define('STDIN', fopen('php://stdin', 'r'));

        if (Ninja::isHosted()) {
            return response()->json(['message' => ctrans('texts.self_update_not_available')], 403);
        }

        nlog('Test filesystem is writable');

        $this->testWritable();

        nlog('Clear cache directory');

        $this->clearCacheDir();

        nlog('copying release file');

        $file_headers = @get_headers($this->getDownloadUrl());

        nlog("Download URL");
        nlog($this->getDownloadUrl());

        if (strlen($this->version) == 1) {
            nlog("version server down, trying github");
            $this->version = trim(file_get_contents('https://raw.githubusercontent.com/invoiceninja/invoiceninja/refs/heads/v5-develop/VERSION.txt'));
        }

        if (!is_array($file_headers)) {
            nlog($file_headers);
            return response()->json(['message' => 'There was a problem reaching the update server, please try again in a little while.'], 410);
        }

        if (stripos($file_headers[0], "404 Not Found") > 0  || (stripos($file_headers[0], "302 Found") > 0 && stripos($file_headers[7], "404 Not Found") > 0)) {
            nlog($file_headers);
            return response()->json(['message' => 'Download not yet available. Please try again shortly.'], 410);
        }

        try {
            if (copy($this->getDownloadUrl(), storage_path("app/{$this->filename}"))) {
                nlog('Copied file from URL');
            }
        } catch (\Exception $e) {
            nlog($e->getMessage());
            return response()->json(['message' => 'File exists on the server, however there was a problem downloading and copying to the local filesystem'], 500);
        }

        nlog('Finished copying');

        $file = Storage::disk('local')->path($this->filename);

        nlog('Extracting tar');

        $phar = new \PharData($file);
        $phar->extractTo(base_path(), null, true);

        nlog('Finished extracting files');

        unlink($file);

        nlog('Deleted release zip file');

        foreach ($this->purge_file_list as $purge_file_path) {
            $purge_file = base_path($purge_file_path);
            if (file_exists($purge_file)) {
                unlink($purge_file);
            }
        }

        if (Storage::disk('base')->directoryExists('resources/lang')) {
            Storage::disk('base')->deleteDirectory('resources/lang');
        }

        nlog('Removing cache files');

        Artisan::call('clear-compiled');
        Artisan::call('route:clear');
        Artisan::call('view:clear');
        Artisan::call('migrate', ['--force' => true]);
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('ninja:design');

        // $this->runModelChecks();

        nlog('Called Artisan commands');

        return response()->json(['message' => 'Update completed'], 200);
    }

    // private function runModelChecks()
    // {
    //     Company::query()
    //            ->cursor()
    //            ->each(function ($company) {

    //                $settings = $company->settings;

    //                if(property_exists($settings->pdf_variables, 'purchase_order_details')) {
    //                    return;
    //                }

    //                $pdf_variables = $settings->pdf_variables;
    //                $pdf_variables->purchase_order_details = [];
    //                $settings->pdf_variables = $pdf_variables;
    //                $company->settings = $settings;
    //                $company->save();

    //            });
    // }

    private function clearCacheDir()
    {
        $directoryIterator = new \RecursiveDirectoryIterator(base_path('bootstrap/cache'), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            unlink(base_path('bootstrap/cache/').$file->getFileName());
            $file = null;
        }

        $directoryIterator = null;
    }

    private function testWritable()
    {
        $directoryIterator = new \RecursiveDirectoryIterator(base_path(), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            if (strpos($file->getPathname(), '.git') !== false || strpos($file->getPathname(), 'vendor/') !== false) {
                continue;
            }

            if ($file->isFile() && ! $file->isWritable()) {

                nlog("Cannot update system because {$file->getFileName()} is not writable");
                throw new FilePermissionsFailure("Cannot update system because {$file->getFileName()} is not writable");

            }

            $file = null;
        }

        $directoryIterator = null;

        return true;
    }

    public function checkVersion()
    {
        if (Ninja::isHosted()) {
            return '5.10.SaaS';
        }

        return trim(file_get_contents(config('ninja.version_url')));
    }

    private function getDownloadUrl()
    {

        $this->version = $this->checkVersion();

        return "https://github.com/invoiceninja/invoiceninja/releases/download/v{$this->version}/invoiceninja.tar";

    }
}

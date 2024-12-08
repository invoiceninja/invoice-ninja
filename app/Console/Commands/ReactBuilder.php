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

use Illuminate\Console\Command;

class ReactBuilder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:react {--type=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Builds blade component for react includes';

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
     * @return void
     */
    public function handle()
    {
        if ($this->option('type') == 'local') {



            $includes = '';

            $directoryIterator = false;

            try {
                $directoryIterator = new \RecursiveDirectoryIterator(public_path('react/v'.config('ninja.app_version').'/'), \RecursiveDirectoryIterator::SKIP_DOTS);
            } catch (\Exception $e) {
                $this->error('React files not found');
                return;
            }

            foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
                if ($file->getExtension() == 'js') {
                    if (str_contains($file->getFileName(), 'index-')) {
                        $includes .= '<script type="module" crossorigin src="/react/v'.config('ninja.app_version').'/'.$file->getFileName().'"></script>'."\n";
                    } else {
                        $includes .= '<link rel="modulepreload" href="/react/v'.config('ninja.app_version').'/'.$file->getFileName().'">'."\n";
                    }
                }

                if (str_contains($file->getFileName(), '.css')) {
                    $includes .= '<link rel="stylesheet" href="/react/v'.config('ninja.app_version').'/'.$file->getFileName().'">'."\n";
                }
            }

            file_put_contents(resource_path('views/react/head.blade.php'), $includes);

        }


    }
}

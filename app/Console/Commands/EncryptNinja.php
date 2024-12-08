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
use Illuminate\Support\Facades\Storage;

class EncryptNinja extends Command
{
    protected $files = [
        'resources/views/email/template/admin_premium.blade.php',
        'resources/views/email/template/client_premium.blade.php',
    ];

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:crypt {--encrypt} {--decrypt}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Encrypt Protected files';

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
        if ($this->option('encrypt')) {
            return $this->encryptFiles();
        }

        if ($this->option('decrypt')) {
            return $this->decryptFiles();
        }

    }

    private function encryptFiles()
    {
        foreach ($this->files as $file) {
            $contents = Storage::disk('base')->get($file);
            $encrypted = encrypt($contents);
            Storage::disk('base')->put($file.".enc", $encrypted);
        }
    }

    private function decryptFiles()
    {
        foreach ($this->files as $file) {
            $encrypted_file = "{$file}.enc";
            $contents = Storage::disk('base')->get($encrypted_file);
            $decrypted = decrypt($contents);
            Storage::disk('base')->put($file, $decrypted);
        }
    }
}

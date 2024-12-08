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

namespace App\Utils;

use LimitIterator;
use SplFileObject;
use App\Libraries\MultiDB;
use App\Mail\TestMailServer;
use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

/**
 * Class SystemHealth.
 */
class SystemHealth
{
    private static $extensions = [
        'gd',
        'curl',
        'zip',
        'openssl',
        'mbstring',
        'xml',
        'bcmath',
        'iconv',
    ];

    private static $php_version = 8.1;

    /**
     * Check loaded extensions / PHP version / DB Connections.
     *
     * @param bool $check_database
     * @return     array  Result set of checks
     */
    public static function check($check_database = true, $check_file_system = true): array
    {
        $system_health = true;

        if (in_array(false, Arr::dot(self::extensions()))) {
            $system_health = false;
        }

        if (phpversion() < self::$php_version) {
            $system_health = false;
        }

        if (! self::simpleDbCheck() && $check_database) {
            info('db fails');
            $system_health = false;
        }

        return [
            'system_health' => $system_health,
            'extensions' => self::extensions(),
            'php_version' => [
                'minimum_php_version' => (string) self::$php_version,
                'current_php_version' => phpversion(),
                'current_php_cli_version' => (string) self::checkPhpCli(),
                'is_okay' => version_compare(phpversion(), self::$php_version, '>='),
                'memory_limit' => ini_get('memory_limit'),
            ],
            'env_writable' => self::checkEnvWritable(),
            'simple_db_check' => self::simpleDbCheck(),
            'cache_enabled' => self::checkConfigCache(),
            'phantom_enabled' => (bool) config('ninja.phantomjs_pdf_generation'),
            'exec' => (bool) self::checkExecWorks(),
            'open_basedir' => (bool) self::checkOpenBaseDir(),
            'mail_mailer' => (string) self::checkMailMailer(),
            'flutter_renderer' => (string) config('ninja.flutter_canvas_kit'),
            'pdf_engine' => (string) self::getPdfEngine(),
            'queue' => (string) config('queue.default'),
            'queue_data' => self::checkQueueData(),
            'jobs_pending' => 0, // TODO for backwards compatibility, remove once Flutter AP is updated
            'trailing_slash' => (bool) self::checkUrlState(),
            'file_permissions' => (string) ($check_file_system ? self::checkFileSystem() : ''),
            'exchange_rate_api_not_configured' => (bool)self::checkCurrencySanity(),
            'api_version' => (string) config('ninja.app_version'),
            'is_docker' => (bool) config('ninja.is_docker'),
            'pending_migrations' => (bool) ($check_file_system ? self::checkPendingMigrations() : false),
        ];
    }

    private static function checkCurrencySanity()
    {
        if (!self::simpleDbCheck()) {
            return true;
        }

        if (strlen(config('ninja.currency_converter_api_key')) == 0) {
            try {
                $cs = DB::table('clients')
                      ->select('settings->currency_id as id')
                                ->get();
            } catch (\Exception $e) {
                return true; //fresh installs, there may be no DB connection, nor migrations could have run yet.
            }

            $currency_count = $cs->unique('id')->filter(function ($value) {
                return !is_null($value->id);
            })->count();


            if ($currency_count > 1) {
                return true;
            }
        }

        return false;
    }

    private static function checkQueueData()
    {
        $pending = 0;
        $failed = 0;
        $last_error = '';

        try {
            $pending = DB::table('jobs')->count();
            $failed = DB::table('failed_jobs')->count();

            if ($failed > 0) {
                $failed_job = DB::table('failed_jobs')->latest('failed_at')->first();
                $last_error = $failed_job->exception;
            }
        } catch (\Exception $e) {
        }

        return [
            'failed' => $failed,
            'pending' => $pending,
            'last_error' => $last_error,
        ];
    }

    public static function checkFileSystem()
    {
        $directoryIterator = new \RecursiveDirectoryIterator(base_path(), \RecursiveDirectoryIterator::SKIP_DOTS);

        foreach (new \RecursiveIteratorIterator($directoryIterator) as $file) {
            if (strpos($file->getPathname(), '.git') !== false) {
                continue;
            }

            //nlog($file->getPathname());

            if ($file->isFile() && ! $file->isWritable()) {
                return "{$file->getFileName()} is not writable";
            }
        }

        return 'Ok';
    }

    public static function checkUrlState()
    {
        if (env('APP_URL') && substr(env('APP_URL'), -1) == '/') {
            return true;
        }

        return false;
    }

    public static function checkPendingMigrations()
    {
        $run_count = DB::table('migrations')->count();

        $directory = base_path('database/migrations');
        $iterator = new \FilesystemIterator($directory);
        $total_count = iterator_count($iterator) - 1;

        return $run_count != $total_count;
    }

    public static function getPdfEngine()
    {
        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            return 'Invoice Ninja Hosted PDF Generator';
        } elseif (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            return 'Phantom JS Web Generator';
        } else {
            return 'SnapPDF PDF Generator';
        }
    }

    public static function checkMailMailer()
    {
        return config('mail.default');
    }

    public static function checkOpenBaseDir()
    {
        if (strlen(ini_get('open_basedir')) == 0) {
            return true;
        }

        return false;
    }

    public static function checkExecWorks()
    {
        if (function_exists('exec')) {
            return true;
        }

        return false;
    }

    public static function checkConfigCache()
    {
        if (env('APP_URL')) {
            return false;
        }

        return true;
    }

    private static function simpleDbCheck(): bool
    {
        $result = false;

        try {
            $result = DB::connection()->getPdo();
            $result = true;
            $result = DB::connection()->getDatabaseName() && strlen(DB::connection()->getDatabaseName()) > 1;
        } catch (Exception $e) {
            $result = false;
        }

        return $result;
    }

    private static function checkPhpCli()
    {
        if (!function_exists('exec')) {
            return "Unable to check CLI version";
        }

        try {
            exec('php -v', $foo, $exitCode);

            if ($exitCode === 0) {
                return empty($foo[0]) ? 'Found php cli, but no version information' : $foo[0];
            }
        } catch (Exception $e) {
            return false;
        }
    }

    private static function extensions(): array
    {
        $loaded_extensions = null;

        $loaded_extensions = [];

        foreach (self::$extensions as $extension) {
            $loaded_extensions[] = [$extension => extension_loaded($extension)];
        }

        return $loaded_extensions;
    }

    public static function dbCheck($request = null): array
    {
        $result = ['success' => false];

        if ($request && ! config('ninja.preconfigured_install')) {
            config(['database.connections.mysql.host' => $request->input('db_host')]);
            config(['database.connections.mysql.port' => $request->input('db_port')]);
            config(['database.connections.mysql.database' => $request->input('db_database')]);
            config(['database.connections.mysql.username' => $request->input('db_username')]);
            config(['database.connections.mysql.password' => $request->input('db_password')]);
            config(['database.default' => 'mysql']);

            DB::purge('mysql');
        }

        if (! config('ninja.db.multi_db_enabled')) {
            try {
                $pdo = DB::connection()->getPdo();
                $x = DB::connection()->getDatabaseName();
                // nlog($pdo);
                // nlog($x);
                $result['success'] = true;
            } catch (Exception $e) {
                // $x = [config('database.connections.'.config('database.default').'.database') => false];
                $result['success'] = false;
                $result['message'] = $e->getMessage();
            }
        } else {
            foreach (MultiDB::$dbs as $db) {
                MultiDB::setDB($db);

                try {
                    $pdo = DB::connection()->getPdo();
                    $x = DB::connection()->getDatabaseName();
                    $result['success'] = true;
                } catch (Exception $e) {
                    // $x = [config('database.connections.'.config('database.default').'.database') => false];
                    $result['success'] = false;
                    $result['message'] = $e->getMessage();
                }
            }
        }

        return $result;
    }

    public static function testMailServer($request = null)
    {
        if ($request->driver == 'log') {
            return [];
        }

        if ($request) {
            config(['mail.driver' => $request->input('mail_driver')]);
            config(['mail.host' => $request->input('mail_host')]);
            config(['mail.port' => $request->input('mail_port')]);
            config(['mail.from.address' => $request->input('mail_address')]);
            config(['mail.from.name' => $request->input('mail_name')]);
            config(['mail.encryption' => $request->input('encryption')]);
            config(['mail.username' => $request->input('mail_username')]);
            config(['mail.password' => $request->input('mail_password')]);
            (new \Illuminate\Mail\MailServiceProvider(app()))->register();
        }

        try {
            Mail::to(config('mail.from.address'))->send(new TestMailServer('Email Server Works!', config('mail.from.address')));
        } catch (Exception $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }

        return ['success' => true];
    }

    private static function checkEnvWritable()
    {
        return is_writable(base_path().'/.env');
    }

    public static function lastError()
    {
        $log_file = new SplFileObject(sprintf('%s/laravel.log', base_path('storage/logs')));
        $log_file->seek(PHP_INT_MAX);
        $last_line = $log_file->key();

        $lines = new LimitIterator($log_file, max(0, $last_line - 500), $last_line);
        $log_lines = iterator_to_array($lines);
        $last_error = '';

        foreach ($lines as $line) {
            // Match the main error, ie. [2024-07-10 12:23:07] production.ERROR: ...
            if (substr($line, 0, 2) === '[2') {
                $last_error = $line;
            }
        }

        return $last_error;
    }
}

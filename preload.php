<?php

require_once __DIR__ . '/vendor/autoload.php';

class Preloader
{
    private array $ignores = [];

    private static int $count = 0;

    private array $paths;

    private array $fileMap;

    private array $exclude_paths;

    public function __construct(string ...$paths)
    {
        $this->paths = $paths;

        // We'll use composer's classmap
        // to easily find which classes to autoload,
        // based on their filename
        $classMap = require __DIR__ . '/vendor/composer/autoload_classmap.php';

        $this->fileMap = array_flip($classMap);
    }

    public function paths(string ...$paths): Preloader
    {
        $this->paths = array_merge(
            $this->paths,
            $paths
        );

        return $this;
    }

    public function ignore(string ...$names): Preloader
    {
        $this->ignores = array_merge(
            $this->ignores,
            $names
        );

        return $this;
    }

    public function load(): void
    {
        // We'll loop over all registered paths
        // and load them one by one
        foreach ($this->paths as $path) {
            $this->loadPath(rtrim($path, '/'));
        }

        $count = self::$count;

        // echo "[Preloader] Preloaded {$count} classes" . PHP_EOL;
    }

    private function loadPath(string $path): void
    {
        // If the current path is a directory,
        // we'll load all files in it
        if (is_dir($path)) {
            if (in_array($path, $this->exclude_paths)) {
                return;
            }

            $this->loadDir($path);

            return;
        }

        // Otherwise we'll just load this one file
        $this->loadFile($path);
    }

    private function loadDir(string $path): void
    {
        $handle = opendir($path);

        // We'll loop over all files and directories
        // in the current path,
        // and load them one by one
        while ($file = readdir($handle)) {
            if (in_array($file, ['.', '..'])) {
                continue;
            }

            $this->loadPath("{$path}/{$file}");
        }

        closedir($handle);
    }

    private function loadFile(string $path): void
    {
        // We resolve the classname from composer's autoload mapping
        $class = $this->fileMap[$path] ?? null;

        // And use it to make sure the class shouldn't be ignored
        if ($this->shouldIgnore($class)) {
            return;
        }

        // Finally we require the path,
        // causing all its dependencies to be loaded as well
        require_once($path);

        self::$count++;

        // echo "[Preloader] Preloaded `{$class}`" . PHP_EOL;
    }

    public function excludePaths(array $exclude_paths): Preloader
    {
        $this->exclude_paths = $exclude_paths;

        return $this;
    }

    private function shouldIgnore(?string $name): bool
    {
        if ($name === null) {
            return true;
        }

        foreach ($this->ignores as $ignore) {
            if (strpos($name, $ignore) === 0) {
                return true;
            }
        }

        return false;
    }
}

(new Preloader())
    ->paths(__DIR__ . '/vendor/laravel')
    ->excludePaths([
        __DIR__ . '/vendor/laravel/framework/src/Illuminate/Support/Testing',
        __DIR__ . '/vendor/laravel/framework/src/Illuminate/Http/Testing',
        __DIR__ . '/vendor/laravel/framework/src/Illuminate/Testing',
        __DIR__ . '/vendor/laravel/framework/src/Illuminate/Foundation/Testing',
        __DIR__ . '/vendor/laravel/octane/src/Testing',
    ])
    ->ignore(
        \Illuminate\Filesystem\Cache::class,
        \Illuminate\Http\UploadedFile::class,
        \Illuminate\Log\LogManager::class,
        \Illuminate\Support\Carbon::class,
        \Illuminate\Console\View\Components\Choice::class,
        \Laravel\Octane\Tables\OpenSwooleTable::class,
        \Laravel\Octane\Tables\SwooleTable::class,
        \Laravel\Octane\WorkerExceptionInspector::class,
    )
    ->load();

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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Facades\Storage;

class TranslationsExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ninja:translations {--type=} {--path=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Transform translations to json';

    protected $log = '';

    private array $langs = [
        'ar',
        'bg',
        'ca',
        'cs',
        'da',
        'de',
        'el',
        'en',
        'en_GB',
        'es',
        'es_ES',
        'et',
        'fa',
        'fi',
        'fr',
        'fr_CA',
        'fr_CH',
        'he',
        'hr',
        'hu',
        'it',
        'ja',
        'km_KH',
        'lo_LA',
        'lt',
        'lv_LV',
        'mk_MK',
        'nb_NO',
        'nl',
        'pl',
        'pt_BR',
        'pt_PT',
        'ro',
        'ru_RU',
        'sl',
        'sk',
        'sq',
        'sr',
        'sv',
        'th',
        'tr_TR',
        'vi',
        'zh_TW',
    ];

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
        $type = $this->option('type') ?? 'export';

        if ($type == 'import') {
            $this->import();
        }

        if ($type == 'export') {
            $this->export();
        }
    }

    private function import()
    {
        //loop and

        foreach ($this->langs as $lang) {
            $import_file = "textsphp_{$lang}.php";
            $dir = $this->option('path') ?? storage_path('lang_import/');
            $path = $dir.$import_file;

            if (file_exists($path)) {
                $this->logMessage($path);

                $trans = file_get_contents($path);

                file_put_contents(lang_path("/{$lang}/texts.php"), $trans);
            } else {
                $this->logMessage("Could not open file");
                $this->logMessage($path);
            }
        }
    }


    private function export()
    {
        Storage::disk('local')->makeDirectory('lang');

        foreach ($this->langs as $lang) {
            Storage::disk('local')->makeDirectory("lang/{$lang}");

            $translations = Lang::getLoader()->load($lang, 'texts');
            foreach ($translations as $key => $value) {
                $translations[$key] = html_entity_decode($value);
            }

            Storage::disk('local')->put("lang/{$lang}/{$lang}.json", json_encode(Arr::dot($translations), JSON_UNESCAPED_UNICODE));
        }
    }

    private function logMessage($str)
    {
        $str = date('Y-m-d h:i:s').' '.$str;
        $this->info($str);
        $this->log .= $str."\n";
    }
}

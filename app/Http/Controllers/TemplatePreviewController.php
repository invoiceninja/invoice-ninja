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

use App\Http\Requests\Report\ReportPreviewRequest;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class TemplatePreviewController extends BaseController
{
    use MakesHash;

    private string $path_prefix = 'templates/';

    private string $path_suffix = '.pdf';

    public function __construct()
    {
        parent::__construct();
    }

    public function __invoke(ReportPreviewRequest $request, ?string $hash)
    {

        $report = Storage::disk(config('filesystems.default'))->exists($this->path_prefix.$hash.$this->path_suffix);

        if (!$report) {
            return response()->json(['message' => 'Still working.....'], 409);
        }

        Cache::forget($hash);

        return response()->streamDownload(function () use ($hash) {

            echo Storage::get($this->path_prefix.$hash.$this->path_suffix);
            Storage::delete($this->path_prefix.$hash.$this->path_suffix);

        }, 'template.pdf', ['Content-Type' => 'application/pdf']);

    }
}

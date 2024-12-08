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

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\BaseController;
use App\Http\Requests\Report\ReportPreviewRequest;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Cache;

class ReportExportController extends BaseController
{
    use MakesHash;

    private string $filename = 'report.csv';

    public function __construct()
    {
        parent::__construct();
    }

    public function __invoke(ReportPreviewRequest $request, ?string $hash)
    {

        $report = Cache::get($hash);

        if (!$report) {
            return response()->json(['message' => 'Still working.....'], 409);
        }

        Cache::forget($hash);

        $headers = [
            'Content-Disposition' => 'attachment',
            'Content-Type' => 'text/csv',
        ];

        return response()->streamDownload(function () use ($report) {
            echo $report;
        }, $this->filename, $headers);

    }
}

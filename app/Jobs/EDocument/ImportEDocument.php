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

namespace App\Jobs\EDocument;

use Exception;
use App\Models\Company;
use App\Models\Expense;
use App\Utils\TempFile;
use Illuminate\Bus\Queueable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use App\Services\EDocument\Imports\ParseEDocument;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use App\Services\EDocument\Imports\ZugferdEDocument;

class ImportEDocument implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(private readonly string $file_content, private string $file_name, private string $file_mime_type, private Company $company)
    {

    }

    /**
     * Execute the job.
     *
     * @return Expense
     * @throws \Exception
     */
    public function handle(): Expense
    {
        $file = TempFile::UploadedFileFromRaw($this->file_content, $this->file_name, $this->file_mime_type);

        return (new ParseEDocument($file, $this->company))->run();

    }

    public function middleware()
    {
        return [new WithoutOverlapping($this->company->company_key."_expense_import_".$this->file_name)];
    }

    public function failed($exception = null)
    {
        if ($exception) {
            nlog("EXCEPTION:: ImportEDocument:: " . $exception->getMessage());
        }

        $this->fail($exception); //manually fail - prevents future jobs with the same name from being discarded
        config(['queue.failed.driver' => null]);
    }
}

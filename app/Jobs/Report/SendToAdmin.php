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

namespace App\Jobs\Report;

use App\Models\User;
use App\Models\Company;
use App\Libraries\MultiDB;
use App\Mail\DownloadReport;
use Illuminate\Bus\Queueable;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Services\Report\ARDetailReport;
use App\Services\Report\ARSummaryReport;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\Middleware\WithoutOverlapping;

class SendToAdmin implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    protected Company $company;

    protected array $request;

    protected string $report_class;

    protected string $file_name;

    /**
     * Create a new job instance.
     */
    public function __construct(Company $company, array $request, $report_class, $file_name)
    {
        $this->company = $company;
        $this->request = $request;
        $this->report_class = $report_class;
        $this->file_name = $file_name;
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);
        $export = new $this->report_class($this->company, $this->request);
        $csv = base64_encode($export->run());

        $files = [];
        $files[] = ['file' => $csv, 'file_name' => "{$this->file_name}", 'mime' => 'text/csv'];

        if(in_array(get_class($export), [ARDetailReport::class, ARSummaryReport::class])) {
            $pdf = base64_encode($export->getPdf());
            $files[] = ['file' => $pdf, 'file_name' => str_replace(".csv", ".pdf", $this->file_name), 'mime' => 'application/pdf'];
        }

        $user = $this->company->owner();

        if(isset($this->request['user_id'])) {
            $user = User::find($this->request['user_id']) ?? $this->company->owner();
        }

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new DownloadReport($this->company, $files);
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $user;
    
        try {
            (new NinjaMailerJob($nmo))->handle();
        } catch (\Throwable $th) {
            nlog("EXCEPTION:: SendToAdmin:: could not email report for" . $th->getMessage());
        }

    }

    public function middleware()
    {
        return [new WithoutOverlapping("report-{$this->company->company_key}")];
    }
}

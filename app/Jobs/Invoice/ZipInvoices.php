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

namespace App\Jobs\Invoice;

use App\Models\User;
use App\Models\Company;
use App\Models\Invoice;
use App\Libraries\MultiDB;
use App\Jobs\Util\UnlinkFile;
use Illuminate\Bus\Queueable;
use App\Mail\DownloadInvoices;
use App\Jobs\Mail\NinjaMailerJob;
use Illuminate\Support\Facades\App;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Events\Socket\DownloadAvailable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ZipInvoices implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $timeout = 3600;

    /**
     * @param $invoices
     * @param Company $company
     * @param $email
     * Create a new job instance.
     */
    public function __construct(public mixed $invoices, public Company $company, public User $user)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        MultiDB::setDb($this->company->db);
        App::setLocale($this->company->locale());
        
        $settings = $this->company->settings;

        $this->invoices = Invoice::withTrashed()
                                ->where('company_id', $this->company->id)
                                ->whereIn('id', $this->invoices)
                                ->get();


        // create new zip object
        $zipFile = new \PhpZip\ZipFile();
        $file_name = date('Y-m-d').'_'.str_replace(' ', '_', trans('texts.invoices')).'.zip';
        $invitation = $this->invoices->first()->invitations->first();

        if (!$invitation) {
            nlog("no Invoice Invitations");
            return;
        }

        $path = $this->invoices->first()->client->invoice_filepath($invitation);

        try {

            foreach ($this->invoices as $invoice) {

                if ($invoice->client->getSetting('enable_e_invoice')) {
                    try {
                        $xml = $invoice->service()->getEDocument();
                        $zipFile->addFromString($invoice->getFileName("xml"), $xml);
                    } catch (\Exception $e) {
                        nlog("could not create e invoice for {$invoice->id}");
                        nlog($e->getMessage());
                    }
                }

                $file = $invoice->service()->getRawInvoicePdf();
                $zip_file_name = $invoice->getFileName();
                $zipFile->addFromString($zip_file_name, $file);

            }

            Storage::put($path.$file_name, $zipFile->outputAsString());
            $storage_url = Storage::url($path.$file_name);

            $nmo = new NinjaMailerObject();
            $nmo->mailable = new DownloadInvoices($storage_url, $this->company);
            $nmo->to_user = $this->user;
            $nmo->settings = $settings;
            $nmo->company = $this->company;

            NinjaMailerJob::dispatch($nmo);

            UnlinkFile::dispatch(config('filesystems.default'), $path.$file_name)->delay(now()->addHours(1));

            $message = count($this->invoices). " ". ctrans('texts.invoices');
            $message = ctrans('texts.download_ready', ['message' => $message]);

            broadcast(new DownloadAvailable($storage_url, $message, $this->user));

        } catch (\PhpZip\Exception\ZipException $e) {
            nlog('could not make zip => '.$e->getMessage());
        } finally {
            $zipFile->close();
        }
    }

    public function failed($exception)
    {
        nlog("ZipInvoices:: Exception:: => ".$exception->getMessage());
        config(['queue.failed.driver' => null]);
    }
}

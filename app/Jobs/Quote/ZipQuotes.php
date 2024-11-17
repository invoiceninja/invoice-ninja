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

namespace App\Jobs\Quote;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Mail\DownloadQuotes;
use App\Models\Company;
use App\Models\QuoteInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ZipQuotes implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $settings;

    public $tries = 1;

    public $timeout = 3600;
    
    public function __construct(protected mixed $quote_ids, protected Company $company, protected User $user)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $this->settings = $this->company->settings;

        // create new zip object
        $zipFile = new \PhpZip\ZipFile();
        $file_name = now()->addSeconds($this->company->timezone_offset())->format('Y-m-d-h-m-s').'_'.str_replace(' ', '_', trans('texts.quotes')).'.zip';

        $invitations = QuoteInvitation::query()->with('quote')->whereIn('quote_id', $this->quote_ids)->get();

        $invitation = $invitations->first();
        $path = $invitation->contact->client->quote_filepath($invitation);

        try {

            foreach ($invitations as $invitation) {
                if ($invitation->quote->client->getSetting('enable_e_invoice')) {
                    $xml = $invitation->quote->service()->getEDocument();
                    $zipFile->addFromString($invitation->quote->getFileName("xml"), $xml);
                }
                $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();
                $zipFile->addFromString($invitation->quote->numberFormatter() . '.pdf', $file);
            }

            Storage::put($path.$file_name, $zipFile->outputAsString());

            $nmo = new NinjaMailerObject();
            $nmo->mailable = new DownloadQuotes(Storage::url($path.$file_name), $this->company);
            $nmo->to_user = $this->user;
            $nmo->settings = $this->settings;
            $nmo->company = $this->company;

            NinjaMailerJob::dispatch($nmo);

            UnlinkFile::dispatch(config('filesystems.default'), $path.$file_name)->delay(now()->addHours(1));

        } catch (\PhpZip\Exception\ZipException $e) {
            nlog("zip build failed: ".$e->getMessage());
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

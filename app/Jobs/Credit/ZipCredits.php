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

namespace App\Jobs\Credit;

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Mail\DownloadCredits;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;

class ZipCredits implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $timeout = 3600;

    public function __construct(protected mixed $credit_ids, protected Company $company, protected User $user)
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

        $settings = $this->company->settings;
        $zipFile = new \PhpZip\ZipFile();
        $file_name = now()->addSeconds($this->company->timezone_offset())->format('Y-m-d-h-m-s').'_'.str_replace(' ', '_', trans('texts.credits')).'.zip';

        nlog($this->credit_ids);

        $invitations = CreditInvitation::query()->with('credit')->whereIn('credit_id', $this->credit_ids)->get();

        if ($invitations->count() == 0) {
            nlog("no Credit Invitations");
            return;
        }

        $invitation = $invitations->first();

        $path = $invitation->contact->client->credit_filepath($invitation);

        try {
            foreach ($invitations as $invitation) {
                $file = (new \App\Jobs\Entity\CreateRawPdf($invitation))->handle();
                $zipFile->addFromString($invitation->credit->numberFormatter() . '.pdf', $file);
            }

            Storage::put($path.$file_name, $zipFile->outputAsString());

            $nmo = new NinjaMailerObject();
            $nmo->mailable = new DownloadCredits(Storage::url($path.$file_name), $this->company);
            $nmo->to_user = $this->user;
            $nmo->settings = $settings;
            $nmo->company = $this->company;

            NinjaMailerJob::dispatch($nmo);

            UnlinkFile::dispatch(config('filesystems.default'), $path.$file_name)->delay(now()->addHours(1));
        } catch (\PhpZip\Exception\ZipException $e) {
            // handle exception
        } finally {
            $zipFile->close();
        }
    }

    public function failed($exception)
    {
        nlog("ZipCredits:: Exception:: => ".$exception->getMessage());
        config(['queue.failed.driver' => null]);
    }
}

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

namespace App\Mail;

use App\Models\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

class DownloadReport extends Mailable
{
    use Queueable;
    use SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct(protected Company $company, protected array $files)
    {
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        App::setLocale($this->company->getLocale());

        $mailable = $this->from(config('mail.from.address'), config('mail.from.name'))
            ->subject(ctrans('texts.download_files'))
            ->text('email.admin.download_report_text')
            ->view('email.admin.download_report', [
                'logo' => $this->company->present()->logo,
                'whitelabel' => $this->company->account->isPaid() ? true : false,
                'settings' => $this->company->settings,
                'greeting' => $this->company->present()->name(),
            ]);

            foreach($this->files as $file)
            {
                $mailable->attachData(base64_decode($file['file']), $file['file_name'], ['mime' => $file['mime']]);
            }

            return $mailable;
            
    }
}

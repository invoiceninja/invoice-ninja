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

namespace App\Jobs\Mail;

use App\Libraries\MultiDB;
use App\Mail\Admin\PaymentFailureObject;
use App\Models\User;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/*Multi Mailer implemented*/

class PaymentFailureMailer implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use UserNotifies;

    public $client;

    public $error;

    public $company;

    public $amount;

    public $settings;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $message
     * @param $company
     * @param $amount
     */
    public function __construct($client, $error, $company, $amount)
    {
        $this->company = $company;

        $this->error = $error;

        $this->client = $client;

        $this->amount = $amount;

        $this->company = $company;

        $this->settings = $client->getMergedSettings();
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        //Set DB
        MultiDB::setDb($this->company->db);

        //iterate through company_users
        $this->company->company_users->each(function ($company_user) {
            //determine if this user has the right permissions
            $methods = $this->findCompanyUserNotificationType($company_user, ['payment_failure_all', 'payment_failure', 'payment_failure_user', 'all_notifications']);

            if (! is_string($this->error)) {
                $this->error = 'Undefined error. Please contact the administrator for further information.';
            }

            //if mail is a method type -fire mail!!
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $use_react_link = false;

                if (isset($company_user->react_settings->react_notification_link) && $company_user->react_settings->react_notification_link) {
                    $use_react_link = true;
                }

                $mail_obj = (new PaymentFailureObject($this->client, $this->error, $this->company, $this->amount, null, $use_react_link))->build();

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer($mail_obj);
                $nmo->company = $this->company;
                $nmo->to_user = $company_user->user;
                $nmo->settings = $this->settings;

                NinjaMailerJob::dispatch($nmo);
            }
        });

        //add client payment failures here.
    }
}

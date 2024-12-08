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

namespace App\Listeners\Invoice;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntitySentObject;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class InvoiceEmailedNotification implements ShouldQueue
{
    use UserNotifies;

    public $delay = 10;

    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param  object  $event
     * @return void
     */
    public function handle($event)
    {
        nlog($event->template);

        MultiDB::setDb($event->company->db);

        $first_notification_sent = true;

        $invoice = $event->invitation->invoice->fresh();
        $invoice->last_sent_date = now();
        $invoice->saveQuietly();


        /* We loop through each user and determine whether they need to be notified */
        foreach ($event->invitation->company->company_users as $company_user) {
            /* The User */
            $user = $company_user->user;

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'invoice', ['all_notifications', 'invoice_sent', 'invoice_sent_all', 'invoice_sent_user']);

            /* If one of the methods is email then we fire the EntitySentMailer */
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer((new EntitySentObject($event->invitation, 'invoice', $event->template, $company_user->portalType()))->build());
                $nmo->company = $invoice->company;
                $nmo->settings = $invoice->company->settings;
                $nmo->to_user = $user;

                (new NinjaMailerJob($nmo))->handle();

                $nmo = null;
                /* This prevents more than one notification being sent */
                $first_notification_sent = false;
            }

        }
    }
}

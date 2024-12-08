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

use App\Libraries\MultiDB;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use Illuminate\Support\Facades\Cache;
use App\Mail\Admin\EntityFailedSendObject;
use App\Utils\Traits\Notifications\UserNotifies;

class InvoiceFailedEmailNotification
{
    use UserNotifies;

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
        MultiDB::setDb($event->company->db);

        if (Cache::has("invoice_failed_email_notification_{$event->invitation->key}")) {
            return;
        }

        $invoice = $event->invitation->invoice;

        foreach ($event->invitation->company->company_users as $company_user) {
            $user = $company_user->user;

            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'invoice', ['all_notifications', 'invoice_sent', 'invoice_sent_all', 'invoice_sent_user']);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer((new EntityFailedSendObject($event->invitation->withoutRelations(), 'invoice', $event->template, $event->message, $company_user->portalType()))->build());
                $nmo->company = $invoice->company->withoutRelations();
                $nmo->settings = $invoice->company->settings;
                $nmo->to_user = $user;

                (new NinjaMailerJob($nmo))->handle();

                $nmo = null;

            }
        }

        Cache::put("invoice_failed_email_notification_{$event->invitation->key}", true, 60 * 60);
    }
}

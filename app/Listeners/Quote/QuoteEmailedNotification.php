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

namespace App\Listeners\Quote;

use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Admin\EntitySentObject;
use App\Utils\Traits\Notifications\UserNotifies;
use Illuminate\Contracts\Queue\ShouldQueue;

class QuoteEmailedNotification implements ShouldQueue
{
    use UserNotifies;

    public $delay = 5;

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

        $quote = $event->invitation->quote->fresh();
        $quote->last_sent_date = now();
        $quote->saveQuietly();

        foreach ($event->invitation->company->company_users as $company_user) {
            $user = $company_user->user;

            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, 'quote', ['all_notifications', 'quote_sent', 'quote_sent_all', 'quote_sent_user']);

            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new NinjaMailer((new EntitySentObject($event->invitation, 'quote', $event->template, $company_user->portalType()))->build());
                $nmo->company = $quote->company;
                $nmo->settings = $quote->company->settings;
                $nmo->to_user = $user;

                (new NinjaMailerJob($nmo))->handle();

                $nmo = null;
            }
        }
    }
}

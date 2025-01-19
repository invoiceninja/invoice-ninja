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

namespace App\Listeners\General;

use App\Libraries\MultiDB;
use App\Jobs\Mail\NinjaMailer;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Jobs\Mail\NinjaMailerJob;
use App\Models\InvoiceInvitation;
use App\Jobs\Mail\NinjaMailerObject;
use App\Mail\Admin\EntitySentObject;
use App\Models\PurchaseOrderInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use App\Utils\Traits\Notifications\UserNotifies;

class EntityEmailedNotification implements ShouldQueue
{
    use UserNotifies;

    public $delay = 10;

    private $entity_string;

    public function __construct()
    {
    }

    private function resolveEntityString($invitation): self
    {
        $this->entity_string = match(get_class($invitation)) {
            InvoiceInvitation::class => 'invoice',
            CreditInvitation::class => 'credit',
            QuoteInvitation::class => 'quote',
            PurchaseOrderInvitation::class => 'purchase_order',
        };

        return $this;

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

        $first_notification_sent = true;

        $this->resolveEntityString($event->invitation);

        $entity = $event->invitation->{$this->entity_string}->fresh();
        $entity->last_sent_date = now();
        $entity->saveQuietly();

        /* We loop through each user and determine whether they need to be notified */
        foreach ($event->invitation->company->company_users as $company_user) {
            /* The User */
            $user = $company_user->user;

            /* Returns an array of notification methods */
            $methods = $this->findUserNotificationTypes($event->invitation, $company_user, $this->entity_string, ['all_notifications', "{$this->entity_string}_sent", "{$this->entity_string}_sent_all", "{$this->entity_string}_sent_user"]);

            /* If one of the methods is email then we fire the EntitySentMailer */
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo = new NinjaMailerObject();
                $sent_object = (new EntitySentObject($event->invitation, $this->entity_string, $event->template, $company_user->portalType()))->build();
                $nmo->mailable = new NinjaMailer($sent_object);
                $nmo->company = $event->invitation->company;
                $nmo->settings = $event->invitation->company->settings;
                $nmo->to_user = $user;

                (new NinjaMailerJob($nmo))->handle();

                $nmo = null;
                /* This prevents more than one notification being sent */
                $first_notification_sent = false;
            }

        }
    }
}

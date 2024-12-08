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

use App\Libraries\MultiDB;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class QuoteReminderEmailActivity implements ShouldQueue
{
    public $delay = 5;

    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activity_repo
     */
    public function __construct(protected ActivityRepository $activity_repo)
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

        $fields = new stdClass();

        $user_id = isset($event->event_vars['user_id']) ? $event->event_vars['user_id'] : $event->invitation->quote->user_id;

        $reminder = match($event->template) {
            'quote_reminder1' => 142,
            default => 142,
        };

        $fields->user_id = $user_id;
        $fields->quote_id = $event->invitation->quote_id;
        $fields->company_id = $event->invitation->company_id;
        $fields->account_id = $event->invitation->company->account_id;
        $fields->client_contact_id = $event->invitation->client_contact_id;
        $fields->client_id = $event->invitation->quote->client_id;
        $fields->activity_type_id = $reminder;

        $this->activity_repo->save($fields, $event->invitation, $event->event_vars);
    }
}

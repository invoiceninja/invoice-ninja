<?php

/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Listeners\Credit;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class CreditEmailActivity implements ShouldQueue
{
    protected $activity_repo;

    public $delay = 5;

    /**
     * Create the event listener.
     *
     * @param ActivityRepository $activity_repo
     */
    public function __construct(ActivityRepository $activity_repo)
    {
        $this->activity_repo = $activity_repo;
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

        $user_id = isset($event->event_vars['user_id']) ? $event->event_vars['user_id'] : $event->invitation->credit->user_id;

        $fields->user_id = $user_id;
        $fields->credit_id = $event->invitation->credit->id;
        $fields->company_id = $event->invitation->credit->company_id;
        $fields->client_contact_id = $event->invitation->client_contact_id;
        $fields->client_id = $event->invitation->credit->client_id;
        $fields->activity_type_id = Activity::EMAIL_CREDIT;

        $this->activity_repo->save($fields, $event->invitation, $event->event_vars);
    }
}

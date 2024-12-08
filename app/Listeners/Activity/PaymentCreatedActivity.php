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

namespace App\Listeners\Activity;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Repositories\ActivityRepository;
use Illuminate\Contracts\Queue\ShouldQueue;
use stdClass;

class PaymentCreatedActivity implements ShouldQueue
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

        $payment = $event->payment;
        $invoice_id = null;

        if ($payment->invoices()->exists()) {
            $invoice_id = $payment->invoices()->first()->id;
        }

        $user_id = isset($event->event_vars['user_id']) ? $event->event_vars['user_id'] : $event->payment->user_id;

        $fields = new stdClass();

        $fields->payment_id = $payment->id;
        $fields->invoice_id = $invoice_id;
        $fields->client_id = $payment->client_id;
        $fields->user_id = $user_id;
        $fields->company_id = $payment->company_id;
        $fields->activity_type_id = Activity::CREATE_PAYMENT;
        $fields->client_contact_id = $payment->client_contact_id ?? null;

        $this->activity_repo->save($fields, $payment, $event->event_vars);

    }
}

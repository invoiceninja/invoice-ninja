<?php
/**
 * Credit Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Credit Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Credit;

use App\Utils\Ninja;
use App\Models\Credit;
use App\Models\Webhook;
use Illuminate\Http\Request;
use App\Jobs\Entity\EmailEntity;
use App\Services\AbstractService;
use App\Utils\Traits\GeneratesCounter;
use App\Events\Credit\CreditWasEmailed;

class TriggeredActions extends AbstractService
{
    use GeneratesCounter;

    private $request;

    private $credit;

    public function __construct(Credit $credit, Request $request)
    {
        $this->request = $request;

        $this->credit = $credit;
    }

    public function run()
    {
        if ($this->request->has('send_email') && $this->request->input('send_email') == 'true') {
            $this->credit = $this->credit->service()->markSent()->save();
            $this->sendEmail();
        }

        if ($this->request->has('mark_sent') && $this->request->input('mark_sent') == 'true') {
            $this->credit = $this->credit->service()->markSent(true)->save();
        }

        if ($this->request->has('save_default_footer') && $this->request->input('save_default_footer') == 'true') {
            $company = $this->credit->company;
            $settings = $company->settings;
            $settings->credit_footer = $this->credit->footer;
            $company->settings = $settings;
            $company->save();
        }

        if ($this->request->has('save_default_terms') && $this->request->input('save_default_terms') == 'true') {
            $company = $this->credit->company;
            $settings = $company->settings;
            $settings->credit_terms = $this->credit->terms;
            $company->settings = $settings;
            $company->save();
        }

        if ($this->request->has('mark_paid') && $this->request->input('mark_paid') == 'true') {
            $this->credit->service()->markPaid()->save();
        }

        return $this->credit;
    }

    private function sendEmail()
    {
        $reminder_template = $this->credit->calculateTemplate('credit');

        $this->credit->invitations->load('contact.client.country', 'credit.client.country', 'credit.company')->each(function ($invitation) use ($reminder_template) {
            EmailEntity::dispatch($invitation->withoutRelations(), $this->credit->company->db, $reminder_template);
        });

        if ($this->credit->invitations->count() > 0) {
            event(new CreditWasEmailed($this->credit->invitations->first(), $this->credit->company, Ninja::eventVars(), 'credit'));
            $this->credit->sendEvent(Webhook::EVENT_SENT_CREDIT, "client");
        }
    }
}

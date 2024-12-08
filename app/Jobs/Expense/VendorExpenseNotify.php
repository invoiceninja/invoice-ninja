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

namespace App\Jobs\Expense;

use App\Libraries\MultiDB;
use App\Models\Activity;
use App\Models\Expense;
use App\Models\VendorContact;
use App\Repositories\ActivityRepository;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VendorExpenseNotify implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesDates;

    public $tries = 1;

    public function __construct(private Expense $expense, private string $db)
    {
    }

    public function handle()
    {
        MultiDB::setDB($this->db);

        if (!$this->expense->vendor) {
            return;
        }

        $this->expense->vendor->contacts->filter(function (VendorContact $contact) {
            return $contact->send_email && $contact->email;
        })->each(function (VendorContact $contact) {
            $this->notify($contact);
        });
    }

    private function notify(VendorContact $contact)
    {

        $mo = new EmailObject();
        $mo->contact = $contact;
        $mo->vendor_contact_id = $contact->id;
        $mo->user_id = $this->expense->user_id;
        $mo->company_key = $this->expense->company->company_key;
        $mo->subject = ctrans('texts.vendor_notification_subject', [
            'amount' => Number::formatMoney($this->expense->amount, $contact->vendor),
            'vendor' => $contact->vendor->present()->name(),
        ]);
        $mo->body = ctrans('texts.vendor_notification_body', [
            'vendor' => $this->expense->vendor->present()->name(),
            'amount' => Number::formatMoney($this->expense->amount, $contact->vendor),
            'contact' => $contact->present()->name(),
            'payment_date' => $this->translateDate($this->expense->payment_date, $this->expense->company->date_format(), $this->expense->vendor->locale()),
            'transaction_reference' => $this->expense->transaction_reference ?? '',
            'number' => $this->expense->number,
        ]);
        $mo->template = '';
        $mo->email_template_body = 'vendor_notification_subject';
        $mo->email_template_subject = 'vendor_notification_body';
        $mo->vendor_id = $contact->vendor_id ?? null;
        $mo->variables = [
            'amount' => Number::formatMoney($this->expense->amount, $contact->vendor),
            'contact' => $contact->present()->name(),
            'vendor' => $contact->vendor->present()->name(),
            'payment_date' => $this->translateDate($this->expense->payment_date, $this->expense->company->date_format(), $this->expense->vendor->locale()),
            'transaction_reference' => $this->expense->transaction_reference ?? '',
            'number' => $this->expense->number,
        ];

        Email::dispatch($mo, $this->expense->company);

        $fields = new \stdClass();
        $fields->expense_id = $this->expense->id;
        $fields->vendor_id = $contact->vendor_id;
        $fields->vendor_contact_id = $contact->id;
        $fields->user_id = $this->expense->user_id;
        $fields->company_id = $contact->company_id;
        $fields->activity_type_id = Activity::VENDOR_NOTIFICATION_EMAIL;
        $fields->account_id = $this->expense->company->account_id;

        $activity_repo = new ActivityRepository();
        $activity_repo->save($fields, $this->expense, Ninja::eventVars());

    }
}

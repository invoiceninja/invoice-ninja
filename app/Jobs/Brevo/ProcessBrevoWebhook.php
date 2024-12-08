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

namespace App\Jobs\Brevo;

use App\DataMapper\Analytics\Mail\EmailBounce;
use App\DataMapper\Analytics\Mail\EmailSpam;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Models\SystemLog;
use App\Notifications\Ninja\EmailBounceNotification;
use App\Notifications\Ninja\EmailSpamNotification;
use Brevo\Client\Configuration;
use Brevo\Client\Model\GetTransacEmailContentEvents;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Brevo\Client\Api\TransactionalEmailsApi;
use Turbo124\Beacon\Facades\LightLogs;

class ProcessBrevoWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $invitation;

    private $entity;

    private array $default_response = [
        'recipients' => '',
        'subject' => 'Message not found.',
        'entity' => '',
        'entity_id' => '',
        'events' => [],
    ];


    /** @var ?\App\Models\Company $company*/
    private ?Company $company = null;

    /**
     * Create a new job instance.
     *
     */
    public function __construct(private array $request)
    {
    }

    private function getSystemLog(string $message_id): ?SystemLog
    {
        return SystemLog::query()
            ->where('company_id', $this->invitation->company_id)
            ->where('type_id', SystemLog::TYPE_WEBHOOK_RESPONSE)
            ->whereJsonContains('log', ['message-id' => $message_id])
            ->orderBy('id', 'desc')
            ->first();

    }

    private function updateSystemLog(SystemLog $system_log, array $data): void
    {
        $system_log->log = $data;
        $system_log->save();
    }

    /**
     * Execute the job.
     *
     *
     */
    public function handle()
    {
        MultiDB::findAndSetDbByCompanyKey($this->request['tags'][0]);

        $this->company = Company::query()->where('company_key', $this->request['tags'][0])->first();

        $this->invitation = $this->discoverInvitation($this->request['message-id']);

        if ($this->company && $this->request['event'] == 'spam' && config('ninja.notification.slack')) {
            $this->company->notification(new EmailSpamNotification($this->company))->ninja();
        }

        if (!$this->invitation) {
            return;
        }

        if (array_key_exists('reason', $this->request)) {
            $this->invitation->email_error = $this->request['reason'];
        }

        switch ($this->request['event']) {
            case 'delivered':
                return $this->processDelivery();
            case 'soft_bounce':
            case 'hard_bounce':
            case 'invalid_email':
            case 'blocked':

                if ($this->request['subject'] == ctrans('texts.confirmation_subject')) {
                    $this->company->notification(new EmailBounceNotification($this->request['email']))->ninja();
                }

                return $this->processBounce();
            case 'spam':
                return $this->processSpamComplaint();
            case 'unique_opened':
            case 'opened':
            case 'click':
                return $this->processOpen();
            default:
                # code...
                break;
        }
    }

    // {
    //   "id": 948562,
    //   "email": "test@example.com",
    //   "message-id": "<202312211546.94160606300@smtp-relay.mailin.fr>",
    //   "date": "2023-12-21 18:34:42",
    //   "tags": [
    //     "gMtwiTIJtJxklXCj1OUFANgY6YYynQxV"
    //   ],
    //   "tag": "[\"gMtwiTIJtJxklXCj1OUFANgY6YYynQxV\"]",
    //   "event": "unique_opened",
    //   "subject": "Reminder: Invoice 0002 from Untitled Company",
    //   "sending_ip": "74.125.208.8",
    //   "ts": 1703180082,
    //   "ts_epoch": 1703180082286,
    //   "ts_event": 1703180082,
    //   "link": "",
    //   "sender_email": "user@example.com"
    // }
    // {
    //   "id": 948562,
    //   "email": "test@example.com",
    //   "message-id": "<202312211555.14720890391@smtp-relay.mailin.fr>",
    //   "date": "2023-12-21 18:34:53",
    //   "tags": [
    //     "gMtwiTIJtJxklXCj1OUFANgY6YYynQxV"
    //   ],
    //   "tag": "[\"gMtwiTIJtJxklXCj1OUFANgY6YYynQxV\"]",
    //   "event": "opened",
    //   "subject": "Reminder: Invoice 0002 from Untitled Company",
    //   "sending_ip": "74.125.208.8",
    //   "ts": 1703180093,
    //   "ts_epoch": 1703180093075,
    //   "ts_event": 1703180093,
    //   "link": "",
    //   "sender_email": "user@example.com"
    // }
    // {
    //   "id": 948562,
    //   "email": "paul@wer-ner.de",
    //   "message-id": "<202312280812.10968711117@smtp-relay.mailin.fr>",
    //   "date": "2023-12-28 09:20:18",
    //   "tags": [
    //     "gMtwiTIJtJxklXCj1OUFANgY6YYynQxV"
    //   ],
    //   "tag": "[\"gMtwiTIJtJxklXCj1OUFANgY6YYynQxV\"]",
    //   "event": "click",
    //   "subject": "Reminder: Invoice 0002 from Untitled Company",
    //   "sending_ip": "79.235.133.157",
    //   "ts": 1703751618,
    //   "ts_epoch": 1703751618831,
    //   "ts_event": 1703751618,
    //   "link": "http://localhost/client/invoice/CssCvqOcKsenMCgYJ7EUNRZwxSDGUkau",
    //   "sender_email": "user@example.com"
    // }

    private function processOpen()
    {
        $this->invitation->opened_date = now();
        $this->invitation->save();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (
            new SystemLogger(
                $data,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_OPENED,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->invitation->contact->client,
                $this->invitation->company
            )
        )->handle();
    }

    // {
    //   "id": 948562,
    //   "email": "test@example",
    //   "message-id": "<202312211742.12697514322@smtp-relay.mailin.fr>",
    //   "date": "2023-12-21 18:42:31",
    //   "tags": [
    //     "gMtwiTIJtJxklXCj1OUFANgY6YYynQxV"
    //   ],
    //   "tag": "[\"gMtwiTIJtJxklXCj1OUFANgY6YYynQxV\"]",
    //   "event": "delivered",
    //   "subject": "Reminder: Invoice 0002 from Untitled Company",
    //   "sending_ip": "77.32.148.26",
    //   "ts_event": 1703180551,
    //   "ts": 1703180551,
    //   "reason": "sent",
    //   "ts_epoch": 1703180551324,
    //   "sender_email": "user@example.com"
    // }
    private function processDelivery()
    {
        $this->invitation->email_status = 'delivered';
        $this->invitation->save();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (
            new SystemLogger(
                $data,
                SystemLog::CATEGORY_MAIL,
                SystemLog::EVENT_MAIL_DELIVERY,
                SystemLog::TYPE_WEBHOOK_RESPONSE,
                $this->invitation->contact->client,
                $this->invitation->company
            )
        )->handle();
    }

    // {
    //   "id": 948562,
    //   "email": "ryder36@example.net",
    //   "message-id": "<202312211744.55168080257@smtp-relay.mailin.fr>",
    //   "date": "2023-12-21 18:44:52",
    //   "tags": [
    //     "gMtwiTIJtJxklXCj1OUFANgY6YYynQxV"
    //   ],
    //   "tag": "[\"gMtwiTIJtJxklXCj1OUFANgY6YYynQxV\"]",
    //   "event": "soft_bounce",
    //   "subject": "Reminder: Invoice 0001 from Untitled Company",
    //   "sending_ip": "77.32.148.26",
    //   "ts_event": 1703180692,
    //   "ts": 1703180692,
    //   "reason": "Unable to find MX of domain example.net",
    //   "ts_epoch": 1703180692382,
    //   "sender_email": "user@example.com"
    // }
    // {
    //   "id": 948562,
    //   "email": "gloria46@example.com",
    //   "message-id": "<202312211744.57456703957@smtp-relay.mailin.fr>",
    //   "date": "2023-12-21 18:44:54",
    //   "tags": [
    //     "gMtwiTIJtJxklXCj1OUFANgY6YYynQxV"
    //   ],
    //   "tag": "[\"gMtwiTIJtJxklXCj1OUFANgY6YYynQxV\"]",
    //   "event": "hard_bounce",
    //   "subject": "Reminder: Invoice 0001 from Untitled Company",
    //   "sending_ip": "77.32.148.25",
    //   "ts_event": 1703180694,
    //   "ts": 1703180694,
    //   "reason": "blocked by Admin",
    //   "ts_epoch": 1703180694175,
    //   "sender_email": "user@example.com"
    // }
    // {
    //   "event" :  "invalid_email",
    //   "email" :  "example@example.com",
    //   "id" :  1,
    //   "date" :  "yyyy-mm-dd hh:i:s",
    //   "message-id" :  "<xxx@msgid.domain>",
    //   "subject" :  "Test subject",
    //   "tag" :  "<defined-tag>",//json of array
    //   "tags": [
    //        "company_key"
    //    ],
    //   "sending_ip" :  "xxx.xx.xxx.xx",
    //   "ts_epoch" :  1534486682000,
    //   "template_id" :  1,
    //   "sender_email": "user@example.com",
    // }
    // {
    //   "id": 948562,
    //   "email": "neoma.langosh@example.com",
    //   "message-id": "<202312211745.65538701430@smtp-relay.mailin.fr>",
    //   "date": "2023-12-21 18:45:48",
    //   "tags": [
    //       "gMtwiTIJtJxklXCj1OUFANgY6YYynQxV"
    //   ],
    //   "tag": "[\"gMtwiTIJtJxklXCj1OUFANgY6YYynQxV\"]",
    //   "event": "blocked",
    //   "subject": "Reminder: Invoice 0001 from Untitled Company",
    //   "ts_event": 1703180748,
    //   "ts": 1703180748,
    //   "reason": "blocked : due to blacklist user",
    //   "ts_epoch": 1703180748987,
    //   "sender_email": "user@example.com"
    // }

    private function processBounce()
    {
        $this->invitation->email_status = 'bounced';
        $this->invitation->save();

        $bounce = new EmailBounce(
            $this->request['tags'][0],
            $this->request['sender_email'], // TODO: @turbo124 is this the recipent?
            $this->request['message-id']
        );

        LightLogs::create($bounce)->send();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (new SystemLogger($data, SystemLog::CATEGORY_MAIL, SystemLog::EVENT_MAIL_BOUNCED, SystemLog::TYPE_WEBHOOK_RESPONSE, $this->invitation->contact->client, $this->invitation->company))->handle();

        // if(config('ninja.notification.slack'))
        // $this->invitation->company->notification(new EmailBounceNotification($this->invitation->company->account))->ninja();
    }

    // {
    //   "event" :  "spam",
    //   "email" :  "example@example.com",
    //   "id" :  1,
    //   "date" :  "yyyy-mm-dd hh:i:s",
    //   "message-id" :  "<xxx@msgid.domain>",
    //   "tag" :  "<defined-tag>",//json of array
    //   "tags": [
    //        "company_key"
    //    ],
    //   "sending_ip" :  "xxx.xx.xxx.xx",
    //   "sender_email": "user@example.com",
    //  }
    private function processSpamComplaint()
    {
        $this->invitation->email_status = 'spam';
        $this->invitation->save();

        $spam = new EmailSpam(
            $this->request['tags'][0],
            $this->request['sender_email'],
            $this->request['message-id']
        );

        LightLogs::create($spam)->send();

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        $sl = $this->getSystemLog($this->request['message-id']);

        if ($sl) {
            $this->updateSystemLog($sl, $data);
            return;
        }

        (new SystemLogger($data, SystemLog::CATEGORY_MAIL, SystemLog::EVENT_MAIL_SPAM_COMPLAINT, SystemLog::TYPE_WEBHOOK_RESPONSE, $this->invitation->contact->client, $this->invitation->company))->handle();

        if (config('ninja.notification.slack')) {
            $this->invitation->company->notification(new EmailSpamNotification($this->invitation->company->account))->ninja();
        }
    }

    private function discoverInvitation(string $message_id)
    {
        $invitation = false;

        if ($invitation = InvoiceInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'invoice';
            return $invitation;
        } elseif ($invitation = QuoteInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'quote';
            return $invitation;
        } elseif ($invitation = RecurringInvoiceInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'recurring_invoice';
            return $invitation;
        } elseif ($invitation = CreditInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'credit';
            return $invitation;
        } elseif ($invitation = PurchaseOrderInvitation::where('message_id', $message_id)->first()) {
            $this->entity = 'purchase_order';
            return $invitation;
        } else {
            return $invitation;
        }
    }

    public function getRawMessage(string $message_id)
    {

        $brevo_secret = !empty($this->company->settings->brevo_secret) ? $this->company->settings->brevo_secret : config('services.brevo.secret');

        $brevo = new TransactionalEmailsApi(null, Configuration::getDefaultConfiguration()->setApiKey('api-key', $brevo_secret));
        $messageDetail = $brevo->getTransacEmailContent($message_id);
        return $messageDetail;

    }


    public function getBounceId(string $message_id): ?int
    {

        $messageDetail = $this->getRawMessage($message_id);

        $event = collect($messageDetail->getEvents())->first(function ($event) {

            return $event?->Details?->BounceID ?? false;

        });

        return $event?->Details?->BounceID ?? null;

    }

    // TODO
    private function fetchMessage(): array
    {
        if (strlen($this->request['message-id']) < 1) {
            return $this->default_response;
        }

        try {

            $messageDetail = $this->getRawMessage($this->request['message-id']);

            $recipient = array_key_exists("email", $this->request) ? $this->request["email"] : '';
            $server_ip = array_key_exists("sending_ip", $this->request) ? $this->request["sending_ip"] : '';
            $delivery_message = array_key_exists("reason", $this->request) ? $this->request["reason"] : '';
            $subject = $messageDetail->getSubject() ?? '';

            $events = collect($messageDetail->getEvents())->map(function (GetTransacEmailContentEvents $event) use ($recipient, $server_ip, $delivery_message) { // @turbo124 event does only contain name & time property, how to handle transformation?!

                return [
                    'bounce_id' => '',
                    'recipient' => $recipient,
                    'status' => $event->name ?? '',
                    'delivery_message' => $delivery_message, // TODO: @turbo124 this results in all cases for the history in the string, which may be incorrect
                    'server' => '',
                    'server_ip' => $server_ip,
                    'date' => \Carbon\Carbon::parse($event->getTime())->format('Y-m-d H:i:s') ?? '',
                ];

            })->toArray();

            return [
                'recipients' => $recipient,
                'subject' => $subject,
                'entity' => $this->entity ?? '',
                'entity_id' => $this->invitation->{$this->entity}->hashed_id ?? '',
                'events' => $events,
            ];

        } catch (\Exception $e) {

            return $this->default_response;

        }
    }
}

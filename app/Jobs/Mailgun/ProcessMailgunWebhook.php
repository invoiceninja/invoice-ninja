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

namespace App\Jobs\Mailgun;

use App\Utils\Ninja;
use App\Models\Company;
use App\Models\SystemLog;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Jobs\Util\SystemLogger;
use App\Models\QuoteInvitation;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use Illuminate\Queue\SerializesModels;
use Turbo124\Beacon\Facades\LightLogs;
use App\Models\PurchaseOrderInvitation;
use Illuminate\Queue\InteractsWithQueue;
use App\Models\RecurringInvoiceInvitation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use App\DataMapper\Analytics\Mail\EmailSpam;
use App\DataMapper\Analytics\Mail\EmailBounce;
use App\Notifications\Ninja\EmailSpamNotification;
use App\Notifications\Ninja\EmailBounceNotification;

class ProcessMailgunWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $invitation;

    private $entity;

    private string $message_id = '';

    private array $default_response =  [
        'recipients' => '',
        'subject' => 'Message not found.',
        'entity' => '',
        'entity_id' => '',
        'events' => [],
    ];

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
                ->where('category_id', SystemLog::CATEGORY_MAIL)
                ->where('log->MessageID', $message_id)
                // ->whereJsonContains('log', ['MessageID' => $this->message_id])
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
     */
    public function handle()
    {
        nlog($this->request);

        if (!$this->request['event-data']['tags'][0] ?? false) { //@phpstan-ignore-line
            return;
        }

        MultiDB::findAndSetDbByCompanyKey($this->request['event-data']['tags'][0]);

        /** @var \App\Models\Company $company */
        $company = Company::where('company_key', $this->request['event-data']['tags'][0])->first();

        if ($company && $this->request['event-data']['event'] == 'complained' && config('ninja.notification.slack')) {
            $company->notification(new EmailSpamNotification($company))->ninja();
        }

        $this->message_id = $this->request['event-data']['message']['headers']['message-id'];

        $this->request['MessageID'] = $this->message_id;

        $this->invitation = $this->discoverInvitation($this->message_id);

        if (!$this->invitation) {
            return;
        }

        if (isset($this->request['event-details']['delivery-status']['message'])) {
            $this->invitation->email_error = $this->request['event-details']['delivery-status']['message'];
        }

        switch ($this->request['event-data']['event']) {
            case 'delivered':
                return $this->processDelivery();
            case 'failed':
                return $this->processBounce();
            case 'complained':
                return $this->processSpamComplaint();
            case 'opened':
                return $this->processOpen();
            default:
                # code...
                break;
        }
    }

    /*
    {
      "signature": {
        "token": "7f388cf8096aa0bca1477aee9d91e156c61f8fa8282c7f1c0c",
        "timestamp": "1705376308",
        "signature": "a22b7c3dd4861e27a1664cef3611a1954c0665cfcaca9b8f35ee216243a4ce3f"
      },
      "event-data": {
        "id": "Ase7i2zsRYeDXztHGENqRA",
        "timestamp": 1521243339.873676,
        "log-level": "info",
        "event": "opened",
        "message": {
          "headers": {
            "message-id": "20130503182626.18666.16540@mail.invoicing.co"
          }
        },
        "recipient": "alice@example.com",
        "recipient-domain": "example.com",
        "ip": "50.56.129.169",
        "geolocation": {
          "country": "US",
          "region": "CA",
          "city": "San Francisco"
        },
        "client-info": {
          "client-os": "Linux",
          "device-type": "desktop",
          "client-name": "Chrome",
          "client-type": "browser",
          "user-agent": "Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.31 (KHTML, like Gecko) Chrome/26.0.1410.43 Safari/537.31"
        },
        "campaigns": [],
        "tags": [
          "my_tag_1",
          "my_tag_2"
        ],
        "user-variables": {
          "my_var_1": "Mailgun Variable #1",
          "my-var-2": "awesome"
        }
      }
    }
    */
    private function processOpen()
    {
        $this->invitation->opened_date = now();
        $this->invitation->saveQuietly();

        $sl = $this->getSystemLog($this->request['MessageID']);

        /** Prevents Gmail tracking from firing inappropriately */
        if (!$sl || $this->request['signature']['timestamp'] < $sl->log['signature']['timestamp'] + 3) {
            return;
        }

        $event = [
            'bounce_id' => '',
            'recipient' => $this->request['event-data']['recipient'] ?? '',
            'status' => $this->request['event-data']['event'] ?? '',
            'delivery_message' => collect($this->request['event-data']['client-info'])->implode(" ") ?? '',
            'server' => collect($this->request['event-data']['geolocation'])->implode(" - ") ??  '',
            'server_ip' => $this->request['event-data']['ip'] ?? '',
            'date' => \Carbon\Carbon::parse($this->request['event-data']['timestamp'])->format('Y-m-d H:i:s') ?? '',
        ];

        if ($sl instanceof SystemLog) {
            $data = $sl->log;
            $data['history']['events'][] = $event;
            $this->updateSystemLog($sl, $data);
        }

    }

    /*
    {
      "signature": {
        "token": "70b91a64ed0f1bdf90fb9c6ea7e3c31d5792a3d0945ffc20fe",
        "timestamp": "1705376276",
        "signature": "ba96f841fc236e1bf5840b02fad512d0bd15b0731b5e6b154764c7a05f7ee999"
      },
      "event-data": {
        "id": "CPgfbmQMTCKtHW6uIWtuVe",
        "timestamp": 1521472262.908181,
        "log-level": "info",
        "event": "delivered",
        "delivery-status": {
          "tls": true,
          "mx-host": "smtp-in.example.com",
          "code": 250,
          "description": "",
          "session-seconds": 0.4331989288330078,
          "utf8": true,
          "attempt-no": 1,
          "message": "OK",
          "certificate-verified": true
        },
        "flags": {
          "is-routed": false,
          "is-authenticated": true,
          "is-system-test": false,
          "is-test-mode": false
        },
        "envelope": {
          "transport": "smtp",
          "sender": "bob@mail.invoicing.co",
          "sending-ip": "209.61.154.250",
          "targets": "alice@example.com"
        },
        "message": {
          "headers": {
            "to": "Alice <alice@example.com>",
            "message-id": "20130503182626.18666.16540@mail.invoicing.co",
            "from": "Bob <bob@mail.invoicing.co>",
            "subject": "Test delivered webhook"
          },
          "attachments": [],
          "size": 111
        },
        "recipient": "alice@example.com",
        "recipient-domain": "example.com",
        "storage": {
          "url": "https://se.api.mailgun.net/v3/domains/mail.invoicing.co/messages/message_key",
          "key": "message_key"
        },
        "campaigns": [],
        "tags": [
          "my_tag_1",
          "my_tag_2"
        ],
        "user-variables": {
          "my_var_1": "Mailgun Variable #1",
          "my-var-2": "awesome"
        }
      }
    }
    */
    private function processDelivery()
    {
        $this->invitation->email_status = 'delivered';
        $this->invitation->saveQuietly();

        $sl = $this->getSystemLog($this->request['MessageID']);

        if ($sl) {
            $data = $sl->log;
            $data['history']['events'][] = $this->getEvent();
            $this->updateSystemLog($sl, $data);
            return;
        }

        $data = array_merge($this->request, ['history' => $this->fetchMessage()]);

        SystemLogger::dispatch(
            $data,
            SystemLog::CATEGORY_MAIL,
            SystemLog::EVENT_MAIL_DELIVERY,
            SystemLog::TYPE_WEBHOOK_RESPONSE,
            $this->invitation->contact->client,
            $this->invitation->company
        );
    }

    /*
    {
  "signature": {
    "token": "7494a9089874cda8c478ba7608d15158229d5b8de41ddfdae8",
    "timestamp": "1705376357",
    "signature": "a8ba107ac919626526b76e46e43ba40e629833fafab8728d402f28476bad0c7b"
  },
  "event-data": {
    "id": "G9Bn5sl1TC6nu79C8C0bwg",
    "timestamp": 1521233195.375624,
    "log-level": "error",
    "event": "failed",
    "severity": "permanent",
    "reason": "suppress-bounce",
    "delivery-status": {
      "attempt-no": 1,
      "message": "",
      "code": 605,
      "enhanced-code": "",
      "description": "Not delivering to previously bounced address",
      "session-seconds": 0
    },
    "flags": {
      "is-routed": false,
      "is-authenticated": true,
      "is-system-test": false,
      "is-test-mode": false
    },
    "envelope": {
      "sender": "bob@mail.invoicing.co",
      "transport": "smtp",
      "targets": "alice@example.com"
    },
    "message": {
      "headers": {
        "to": "Alice <alice@example.com>",
        "message-id": "20130503192659.13651.20287@mail.invoicing.co",
        "from": "Bob <bob@mail.invoicing.co>",
        "subject": "Test permanent_fail webhook"
      },
      "attachments": [],
      "size": 111
    },
    "recipient": "alice@example.com",
    "recipient-domain": "example.com",
    "storage": {
      "url": "https://se.api.mailgun.net/v3/domains/mail.invoicing.co/messages/message_key",
      "key": "message_key"
    },
    "campaigns": [],
    "tags": [
      "my_tag_1",
      "my_tag_2"
    ],
    "user-variables": {
      "my_var_1": "Mailgun Variable #1",
      "my-var-2": "awesome"
    }
  }
}
*/
    private function processBounce()
    {
        $this->invitation->email_status = 'bounced';
        $this->invitation->saveQuietly();

        $bounce = new EmailBounce(
            $this->request['event-data']['tags'][0],
            $this->request['event-data']['message']['headers']['from'] ?? $this->request['event-data']['message']['headers']['to'],
            $this->message_id
        );

        LightLogs::create($bounce)->queue();

        $sl = $this->getSystemLog($this->message_id);

        $event = [
            'bounce_id' => $this->request['event-data']['id'],
            'recipient' => $this->request['event-data']['message']['headers']['to'] ?? '',
            'status' => $this->request['event-data']['event'] ?? '',
            'delivery_message' => $this->request['event-data']['delivery-status']['message'] ?? $this->request['event-data']['delivery-status']['bounce-code'] ?? '',
            'server' => $this->request['event-data']['delivery-status']['message'] ??  '',
            'server_ip' => '',
            'date' => \Carbon\Carbon::parse($this->request['event-data']['timestamp'])->format('Y-m-d H:i:s') ?? '',
        ];

        if ($sl) {
            $data = $sl->log;
            $data['history']['events'][] = $event;
            $this->updateSystemLog($sl, $data);
        }

    }

    /*
    {
      "signature": {
        "token": "d7be371deef49c8b187119df295e3eb17fd1974d513a4be2cb",
        "timestamp": "1705376380",
        "signature": "52f31c75b492d67be906423279e0effe563e28790ee65ba23a1b30006df649df"
      },
      "event-data": {
        "id": "-Agny091SquKnsrW2NEKUA",
        "timestamp": 1521233123.501324,
        "log-level": "warn",
        "event": "complained",
        "envelope": {
          "sending-ip": "173.193.210.33"
        },
        "flags": {
          "is-test-mode": false
        },
        "message": {
          "headers": {
            "to": "Alice <alice@example.com>",
            "message-id": "20110215055645.25246.63817@mail.invoicing.co",
            "from": "Bob <bob@mail.invoicing.co>",
            "subject": "Test complained webhook"
          },
          "attachments": [],
          "size": 111
        },
        "recipient": "alice@example.com",
        "campaigns": [],
        "tags": [
          "my_tag_1",
          "my_tag_2"
        ],
        "user-variables": {
          "my_var_1": "Mailgun Variable #1",
          "my-var-2": "awesome"
        }
      }
    }
    */
    private function processSpamComplaint()
    {
        $this->invitation->email_status = 'spam';
        $this->invitation->saveQuietly();

        $spam = new EmailSpam(
            $this->request['event-data']['tags'][0],
            $this->request['event-data']['message']['headers']['from'],
            $this->message_id
        );

        LightLogs::create($spam)->queue();

        $sl = $this->getSystemLog($this->message_id);

        $event = [
            'bounce_id' => '',
            'recipient' => $this->request['event-data']['recipient'] ?? '',
            'status' => $this->request['event-data']['event'] ?? '',
            'delivery_message' => 'Spam Complaint',
            'server' => '',
            'server_ip' => $this->request['event-data']['envelope']['sending-ip'] ?? '',
            'date' => \Carbon\Carbon::parse($this->request['event-data']['timestamp'])->format('Y-m-d H:i:s') ?? '',
        ];

        if ($sl) {
            $data = $sl->log;
            $data['history']['events'][] = $event;
            $this->updateSystemLog($sl, $data);
        }

    }

    private function discoverInvitation($message_id)
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

    private function fetchMessage(): array
    {
        if (strlen($this->message_id) < 2) {
            return $this->default_response;
        }

        try {

            $recipients = $this->request['event-data']['recipient'] ?? '';
            $subject = $this->request['event-data']['message']['headers']['subject'] ?? '';

            return [
                'recipients' => $recipients,
                'subject' => $subject,
                'entity' => $this->entity ?? '',
                'entity_id' => $this->invitation->{$this->entity}->hashed_id ?? '',
                'events' => [$this->getEvent()],
            ];

        } catch (\Exception $e) {

            return $this->default_response;

        }
    }

    private function getEvent(): array
    {
        $recipients = $this->request['event-data']['recipient'] ?? '';

        return [
            'bounce_id' => '',
            'recipient' => $recipients,
            'status' => $this->request['event-data']['event'] ?? '',
            'delivery_message' => $this->request['event-details']['delivery-status']['description'] ?? $this->request['event-details']['delivery-status']['message'] ?? '',
            'server' => $this->request['event-data']['recipient-domain'] ??  '',
            'server_ip' => $this->request['event-data']['envelope']['sending-ip'] ?? '',
            'date' => \Carbon\Carbon::parse($this->request['event-data']['timestamp'])->format('Y-m-d H:i:s') ?? '',
        ];

    }

}

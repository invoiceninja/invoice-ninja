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

namespace App\Jobs\Invoice;

use App\Models\Invoice;
use App\Models\Webhook;
use App\Services\Email\Email;
use Illuminate\Bus\Queueable;
use App\Jobs\Entity\EmailEntity;
use App\Libraries\MultiDB;
use App\Services\Email\EmailObject;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class BulkInvoiceJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    public $timeout = 3600;

    private bool $contact_has_email = false;

    private array $templates = [
        'email_template_invoice',
        'email_template_quote',
        'email_template_credit',
        'email_template_payment',
        'email_template_payment_partial',
        'email_template_statement',
        'email_template_reminder1',
        'email_template_reminder2',
        'email_template_reminder3',
        'email_template_reminder_endless',
        'email_template_custom1',
        'email_template_custom2',
        'email_template_custom3',
        'email_template_purchase_order',
    ];

    public function __construct(public array $invoice_ids, public string $db, public string $reminder_template)
    {
    }

    /**
     * Execute the job.
     *
     *
     * @return void
     */
    public function handle()
    {
        MultiDB::setDb($this->db);

        Invoice::with([
                'invitations',
                'invitations.contact.client.country',
                'invitations.invoice.client.country',
                'invitations.invoice.company'
                ])
                ->withTrashed()
                ->whereIn('id', $this->invoice_ids)
                ->cursor()
                ->each(function ($invoice) {

                    $invoice->service()->markSent()->save();

                    $invoice->invitations->each(function ($invitation) {

                        $template = $this->resolveTemplateString($this->reminder_template);

                        if ($invitation->contact->email) {
                            $this->contact_has_email = true;

                            $mo = new EmailObject();
                            $mo->entity_id = $invitation->invoice_id;
                            $mo->template = $template; //full template name in use
                            $mo->email_template_body = $template;
                            $mo->email_template_subject = str_replace("template", "subject", $template);

                            $mo->entity_class = get_class($invitation->invoice);
                            $mo->invitation_id = $invitation->id;
                            $mo->client_id = $invitation->contact->client_id ?? null;
                            $mo->vendor_id = $invitation->contact->vendor_id ?? null;

                            Email::dispatch($mo, $invitation->company->withoutRelations());

                            sleep(1); // this is needed to slow down the amount of data that is pushed into cache
                        }
                    });

                    if ($invoice->invitations->count() >= 1 && $this->contact_has_email) {
                        $invoice->entityEmailEvent($invoice->invitations->first(), 'invoice', $this->reminder_template);
                        $invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");
                    }

                    sleep(1); // this is needed to slow down the amount of data that is pushed into cache
                    $this->contact_has_email = false;
                });
    }

    private function resolveTemplateString(string $template): string
    {

        return match ($template) {
            'reminder1' => 'email_template_reminder1',
            'reminder2' => 'email_template_reminder2',
            'reminder3' => 'email_template_reminder3',
            'endless_reminder' => 'email_template_reminder_endless',
            'custom1' => 'email_template_custom1',
            'custom2' => 'email_template_custom2',
            'custom3' => 'email_template_custom3',
            default => "email_template_{$template}",
        };

    }
}

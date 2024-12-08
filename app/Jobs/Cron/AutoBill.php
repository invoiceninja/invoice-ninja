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

namespace App\Jobs\Cron;

use App\Models\Invoice;
use App\Models\Webhook;
use App\Libraries\MultiDB;
use Illuminate\Bus\Queueable;
use App\Jobs\Entity\EmailEntity;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class AutoBill implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public $tries = 1;

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(public int $invoice_id, public ?string $db, public bool $send_email_on_failure = false)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        set_time_limit(0);

        if ($this->db) {
            MultiDB::setDb($this->db);
        }
        $invoice = false;

        
        try {
            
            nlog("autobill {$this->invoice_id}");
            
            $invoice = Invoice::withTrashed()->find($this->invoice_id);
            
            if($invoice)
                $invoice->service()->autoBill();

        } catch (\Exception $e) {
            nlog("Failed to capture payment for {$this->invoice_id} ->".$e->getMessage());

            if ($this->send_email_on_failure && $invoice) {

                $invoice->invitations->each(function ($invitation) use ($invoice) {

                    if ($invitation->contact && !$invitation->contact->trashed() && strlen($invitation->contact->email) >= 1 && $invoice->client->getSetting('auto_email_invoice')) {
                        try {
                            EmailEntity::dispatch($invitation->withoutRelations(), $invoice->company->db)->delay(rand(1, 2));

                            $invoice->entityEmailEvent($invitation, 'invoice', 'email_template_invoice');

                        } catch (\Exception $e) {
                            nlog($e->getMessage());
                        }

                        nlog("Firing email for invoice {$invoice->number} which failed to capture payment");
                    }
                });

                $invoice->sendEvent(Webhook::EVENT_SENT_INVOICE, "client");

            }

        }
    }
}

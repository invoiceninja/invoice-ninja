<?php

namespace App\Jobs\Invoice;

use App\Models\PurchaseOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class InjectSignature implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder $entity
     * @param int $contact_id
     * @param string $signature
     * @param string $ip
     */
    public function __construct(public \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder $entity, private int $contact_id, private string $signature, private ?string $ip)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $invitation = false;

        if ($this->entity instanceof PurchaseOrder) {
            $invitation = $this->entity->invitations()->where('vendor_contact_id', $this->contact_id)->first();

            if (!$invitation) {
                $invitation = $this->entity->invitations->first();
            }

        } else {

            $invitation = $this->entity->invitations()->where('client_contact_id', $this->contact_id)->first();

            if (!$invitation) {
                $invitation = $this->entity->invitations->first();
            }
        }

        if (! $invitation) {
            return;
        }

        $invitation->signature_base64 = $this->signature;
        $invitation->signature_date = now();
        $invitation->signature_ip = $this->ip;
        $invitation->save();

    }
}

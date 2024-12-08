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

namespace App\Services\Invoice;

use App\Factory\ClientContactFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Services\AbstractService;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

class CreateInvitations extends AbstractService
{
    use MakesHash;

    public function __construct(private Invoice $invoice)
    {
    }

    public function run()
    {
        $contacts = $this->invoice->client->contacts()->where('send_email', true)->get();

        if ($contacts->count() == 0) {
            $this->createBlankContact();
        }

        $this->invoice->client->contacts()->each(function ($contact) {
            $invitation = InvoiceInvitation::query()->where('company_id', $this->invoice->company_id)
                                        ->where('client_contact_id', $contact->id)
                                        ->where('invoice_id', $this->invoice->id)
                                        ->withTrashed()
                                        ->first();

            if (! $invitation && $contact->send_email) {
                $ii = InvoiceInvitationFactory::create($this->invoice->company_id, $this->invoice->user_id);
                $ii->key = $this->createDbHash($this->invoice->company->db);
                $ii->invoice_id = $this->invoice->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_email) {
                $invitation->delete();
            }
        });

        if ($this->invoice->invitations()->count() == 0) {
            if ($contacts->count() == 0) {
                $contact = $this->createBlankContact();
            } else {
                $contact = $contacts->first();

                $invitation = InvoiceInvitation::query()->where('company_id', $this->invoice->company_id)
                                        ->where('client_contact_id', $contact->id)
                                        ->where('invoice_id', $this->invoice->id)
                                        ->withTrashed()
                                        ->first();

                if ($invitation) {
                    $invitation->restore();

                    return $this->invoice;
                }
            }

            $ii = InvoiceInvitationFactory::create($this->invoice->company_id, $this->invoice->user_id);
            $ii->key = $this->createDbHash($this->invoice->company->db);
            $ii->invoice_id = $this->invoice->id;
            $ii->client_contact_id = $contact->id;
            $ii->save();
        }

        return $this->invoice;
    }

    private function createBlankContact()
    {
        $new_contact = ClientContactFactory::create($this->invoice->company_id, $this->invoice->user_id);
        $new_contact->client_id = $this->invoice->client_id;
        $new_contact->contact_key = Str::random(40);
        $new_contact->is_primary = true;
        $new_contact->save();

        return $new_contact;
    }
}

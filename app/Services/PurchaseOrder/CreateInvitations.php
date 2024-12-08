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

namespace App\Services\PurchaseOrder;

use App\Factory\PurchaseOrderInvitationFactory;
use App\Factory\VendorContactFactory;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Services\AbstractService;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Str;

class CreateInvitations extends AbstractService
{
    use MakesHash;

    public PurchaseOrder $purchase_order;

    public function __construct(PurchaseOrder $purchase_order)
    {
        $this->purchase_order = $purchase_order;
    }

    private function createBlankContact()
    {
        $new_contact = VendorContactFactory::create($this->purchase_order->company_id, $this->purchase_order->user_id);
        $new_contact->vendor_id = $this->purchase_order->vendor_id;
        $new_contact->contact_key = Str::random(40);
        $new_contact->is_primary = true;
        $new_contact->save();
    }

    public function run()
    {

        if (!$this->purchase_order->vendor) {
            return $this->purchase_order;
        }

        $contacts = $this->purchase_order->vendor->contacts()->get();

        if ($contacts->count() == 0) {
            $this->createBlankContact();

            $this->purchase_order->refresh();
            $contacts = $this->purchase_order->vendor->contacts;
        }

        $contacts->each(function ($contact) {
            $invitation = PurchaseOrderInvitation::query()->where('company_id', $this->purchase_order->company_id)
                ->where('vendor_contact_id', $contact->id)
                ->where('purchase_order_id', $this->purchase_order->id)
                ->withTrashed()
                ->first();

            if (! $invitation) {
                try {
                    $ii = PurchaseOrderInvitationFactory::create($this->purchase_order->company_id, $this->purchase_order->user_id);
                    $ii->key = $this->createDbHash($this->purchase_order->company->db);
                    $ii->purchase_order_id = $this->purchase_order->id;
                    $ii->vendor_contact_id = $contact->id;
                    $ii->save();
                } catch (\Exception $e) {
                    nlog($e->getMessage());
                }
            } elseif (! $contact->send_email) {
                $invitation->delete();
            }
        });

        if ($this->purchase_order->invitations()->count() == 0) {
            if ($contacts->count() == 0) {
                $contact = $this->createBlankContact();
            } else {
                $contact = $contacts->first();

                $invitation = PurchaseOrderInvitation::query()->where('company_id', $this->purchase_order->company_id)
                    ->where('vendor_contact_id', $contact->id)
                    ->where('purchase_order_id', $this->purchase_order->id)
                    ->withTrashed()
                    ->first();

                if ($invitation) {
                    $invitation->restore();

                    return $this->purchase_order;
                }
            }

            $ii = PurchaseOrderInvitationFactory::create($this->purchase_order->company_id, $this->purchase_order->user_id);
            $ii->key = $this->createDbHash($this->purchase_order->company->db);
            $ii->purchase_order_id = $this->purchase_order->id;
            $ii->vendor_contact_id = $contact->id;
            $ii->save();
        }

        return $this->purchase_order;
    }
}

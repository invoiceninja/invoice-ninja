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

namespace App\Services\Vendor;

use App\Factory\CompanyLedgerFactory;
use App\Models\Activity;
use App\Models\Vendor;
use App\Models\CompanyLedger;
use App\Services\AbstractService;

class Merge extends AbstractService
{
    public $vendor;

    public $mergable_vendor;

    public function __construct(Vendor $vendor, Vendor $mergable_vendor)
    {
        $this->vendor = $vendor;
        $this->mergable_vendor = $mergable_vendor;
    }

    public function run()
    {

        $this->mergable_vendor->activities()->update(['vendor_id' => $this->vendor->id]);
        $this->mergable_vendor->contacts()->update(['vendor_id' => $this->vendor->id]);
        $this->mergable_vendor->credits()->update(['vendor_id' => $this->vendor->id]);
        $this->mergable_vendor->expenses()->update(['vendor_id' => $this->vendor->id]);
        $this->mergable_vendor->invoices()->update(['vendor_id' => $this->vendor->id]);
        $this->mergable_vendor->payments()->update(['vendor_id' => $this->vendor->id]);
        $this->mergable_vendor->quotes()->update(['vendor_id' => $this->vendor->id]);
        $this->mergable_vendor->documents()->update(['documentable_id' => $this->vendor->id]);

        /* Loop through contacts an only merge distinct contacts by email */
        $this->mergable_vendor->contacts->each(function ($contact) {
            $exist = $this->vendor->contacts->contains(function ($vendor_contact) use ($contact) {
                return $vendor_contact->email == $contact->email || empty($contact->email) || $contact->email == ' ';
            });

            if ($exist) {
                $contact->delete();
                $contact->save();
            }
        });

        $this->mergable_vendor->forceDelete();

        return $this->vendor;
    }


}

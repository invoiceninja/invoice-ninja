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

use App\Models\Vendor;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Database\QueryException;

class VendorService
{
    use GeneratesCounter;

    private bool $completed = true;

    public function __construct(public Vendor $vendor)
    {
    }

    public function applyNumber(): self
    {
        $x = 1;

        if (isset($this->vendor->number)) {
            return $this;
        }

        do {
            try {
                $this->vendor->number = $this->getNextVendorNumber($this->vendor);
                $this->vendor->saveQuietly();

                $this->completed = false;
            } catch (QueryException $e) {
                $x++;

                if ($x > 50) {
                    $this->completed = false;
                }
            }
        } while ($this->completed);

        return $this;
    }

    public function merge(Vendor $mergable_vendor)
    {
        $this->vendor = (new Merge($this->vendor, $mergable_vendor))->run();

        return $this;
    }

    /**
     * Saves the vendor instance
     *
     * @return Vendor The Vendor Model
     */
    public function save(): Vendor
    {
        $this->vendor->saveQuietly();

        return $this->vendor->fresh();
    }
}

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

namespace App\Repositories;

use App\Factory\VendorFactory;
use App\Models\Vendor;
use App\Utils\Traits\GeneratesCounter;

/**
 * VendorRepository.
 */
class VendorRepository extends BaseRepository
{
    use GeneratesCounter;

    protected $contact_repo;

    /**
     * VendorContactRepository constructor.
     */
    public function __construct(VendorContactRepository $contact_repo)
    {
        $this->contact_repo = $contact_repo;
    }

    /**
     * Saves the vendor and its contacts.
     *
     * @param array $data The data
     * @param \App\Models\Vendor $vendor The vendor
     *
     * @return     vendor|\App\Models\Vendor|null  Vendor Object
     * @throws \Laracasts\Presenter\Exceptions\PresenterException
     */
    public function save(array $data, Vendor $vendor): ?Vendor
    {
        $saveable_vendor = $data;

        if (array_key_exists('contacts', $data)) {
            unset($saveable_vendor['contacts']);
        }

        $vendor->fill($saveable_vendor);

        if (!$vendor->country_id) {
            $vendor->country_id = auth()->user()->company()->country()->id ?? 840;
        }

        $vendor->saveQuietly();

        $vendor->service()->applyNumber();

        if (isset($data['contacts']) || $vendor->contacts()->count() == 0) {
            $this->contact_repo->save($data, $vendor);
        }

        if (array_key_exists('documents', $data) && count($data['documents']) >= 1) {
            $this->saveDocuments($data['documents'], $vendor);
        }

        return $vendor;
    }

    /**
     * Store vendors in bulk.
     *
     * @param array $vendor
     * @return vendor|null
     */
    public function create($vendor): ?Vendor
    {
        return $this->save(
            $vendor,
            VendorFactory::create(auth()->user()->company()->id, auth()->user()->id)
        );
    }
}

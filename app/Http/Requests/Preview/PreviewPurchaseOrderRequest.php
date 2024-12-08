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

namespace App\Http\Requests\Preview;

use App\Http\Requests\Request;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Vendor;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;

class PreviewPurchaseOrderRequest extends Request
{
    use MakesHash;
    use CleanLineItems;

    private ?Vendor $vendor = null;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->hasIntersectPermissionsOrAdmin(['create_purchase_order', 'edit_purchase_order', 'view_purchase_order']);
    }

    public function rules()
    {
        $rules = [];

        $rules['number'] = ['nullable'];

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $input = $this->decodePrimaryKeys($input);

        $input['line_items'] = isset($input['line_items']) ? $this->cleanItems($input['line_items']) : [];
        $input['amount'] = 0;
        $input['balance'] = 0;
        $input['number'] = isset($input['number']) ? $input['number'] : ctrans('texts.live_preview').' #'.rand(0, 1000); //30-06-2023

        if ($input['entity_id'] ?? false) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id'], true);
        }

        $this->replace($input);
    }



    public function resolveInvitation()
    {
        $invitation = false;

        if (! isset($this->entity_id)) {
            return $this->stubInvitation();
        }

        $invitation = PurchaseOrderInvitation::withTrashed()->where('purchase_order_id', $this->entity_id)->first();

        if ($invitation) {
            return $invitation;
        }

        return $this->stubInvitation();


    }

    public function getVendor(): ?Vendor
    {
        if (!$this->vendor) {
            $this->vendor = Vendor::query()->with('contacts', 'company', 'user')->withTrashed()->find($this->vendor_id);
        }

        return $this->vendor;
    }

    public function setVendor(Vendor $vendor): self
    {
        $this->vendor = $vendor;

        return $this;
    }

    public function stubInvitation()
    {
        $vendor = Vendor::query()->with('contacts', 'company', 'user')->withTrashed()->find($this->vendor_id);
        $this->setVendor($vendor);
        $invitation = false;

        $entity = $this->stubEntity($vendor);
        $invitation = PurchaseOrderInvitation::factory()->make();
        $invitation->setRelation('purchase_order', $entity);
        $invitation->setRelation('contact', $vendor->contacts->first()->load('vendor.company'));
        $invitation->setRelation('company', $vendor->company);

        return $invitation;
    }

    private function stubEntity(Vendor $vendor)
    {
        $entity = PurchaseOrder::factory()->make(['vendor_id' => $vendor->id,'user_id' => $vendor->user_id, 'company_id' => $vendor->company_id]);

        $entity->setRelation('vendor', $vendor);
        $entity->setRelation('company', $vendor->company);
        $entity->setRelation('user', $vendor->user);
        $entity->fill($this->all());

        return $entity;
    }


}

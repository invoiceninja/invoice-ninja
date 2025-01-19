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

use App\Jobs\EDocument\CreateEDocument;
use App\Models\PurchaseOrder;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\Storage;

class PurchaseOrderService
{
    use MakesHash;

    public function __construct(public PurchaseOrder $purchase_order)
    {
    }

    public function createInvitations()
    {
        $this->purchase_order = (new CreateInvitations($this->purchase_order))->run();

        return $this;
    }

    public function applyNumber()
    {
        $this->purchase_order = (new ApplyNumber($this->purchase_order->vendor, $this->purchase_order))->run();

        return $this;
    }

    public function fillDefaults()
    {
        $settings = $this->purchase_order->company->settings;

        if (! $this->purchase_order->design_id) {
            $this->purchase_order->design_id = $this->decodePrimaryKey($settings->purchase_order_design_id);
        }

        if (!isset($this->purchase_order->footer) || empty($this->purchase_order->footer)) {
            $this->purchase_order->footer = $settings->purchase_order_footer;
        }

        if (!isset($this->purchase_order->terms)  || empty($this->purchase_order->terms)) {
            $this->purchase_order->terms = $settings->purchase_order_terms;
        }

        if (!isset($this->purchase_order->public_notes)  || empty($this->purchase_order->public_notes)) {
            $this->purchase_order->public_notes = $this->purchase_order->vendor->public_notes;
        }

        if ($settings->counter_number_applied == 'when_saved') {
            $this->applyNumber()->save();
        }

        return $this;
    }

    public function triggeredActions($request)
    {
        $this->purchase_order = (new TriggeredActions($this->purchase_order->load('invitations'), $request))->run();

        return $this;
    }

    public function getPurchaseOrderPdf($contact = null)
    {
        return (new GetPurchaseOrderPdf($this->purchase_order, $contact))->run();
    }

    public function getEPurchaseOrder($contact = null)
    {
        return (new CreateEDocument($this->purchase_order))->handle();
    }
    public function getEDocument($contact = null)
    {
        return $this->getEPurchaseOrder($contact);
    }

    public function deleteEPurchaseOrder()
    {
        $this->purchase_order->load('invitations');

        $this->purchase_order->invitations->each(function ($invitation) {
            try {
                // if (Storage::disk(config('filesystems.default'))->exists($this->invoice->client->e_invoice_filepath($invitation).$this->invoice->getFileName("xml"))) {
                Storage::disk(config('filesystems.default'))->delete($this->purchase_order->vendor->e_document_filepath($invitation).$this->purchase_order->getFileName("xml"));
                // }

                // if (Ninja::isHosted() && Storage::disk('public')->exists($this->invoice->client->e_invoice_filepath($invitation).$this->invoice->getFileName("xml"))) {
                if (Ninja::isHosted()) {
                    Storage::disk('public')->delete($this->purchase_order->vendor->e_document_filepath($invitation).$this->purchase_order->getFileName("xml"));
                }
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        });

        return $this;
    }

    public function setStatus($status)
    {
        $this->purchase_order->status_id = $status;

        return $this;
    }

    public function markSent()
    {
        $this->purchase_order = (new MarkSent($this->purchase_order->vendor, $this->purchase_order))->run();

        return $this;
    }

    public function adjustBalance($adjustment)
    {
        $this->purchase_order->balance += $adjustment;

        return $this;
    }

    public function add_to_inventory()
    {
        if ($this->purchase_order->status_id >= PurchaseOrder::STATUS_RECEIVED) {
            return $this->purchase_order;
        }

        $this->purchase_order = (new PurchaseOrderInventory($this->purchase_order))->run();

        return $this;
    }

    public function expense()
    {
        $this->markSent();

        if ($this->purchase_order->expense()->exists()) {
            return $this;
        }

        $expense = (new PurchaseOrderExpense($this->purchase_order))->run();

        return $expense;
    }

    public function sendEmail($contact = null)
    {
        $send_email = new SendEmail($this->purchase_order, 'purchase_order', $contact);

        return $send_email->run();
    }


    /**
     * Saves the purchase order.
     * @return \App\Models\PurchaseOrder
     */
    public function save(): ?PurchaseOrder
    {
        $this->purchase_order->saveQuietly();

        return $this->purchase_order;
    }
}

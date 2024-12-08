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

use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Utils\Traits\MakesHash;

/**
 * InvoiceRepository.
 */
class InvoiceRepository extends BaseRepository
{
    use MakesHash;

    /**
     * Saves the invoices.
     *
     * @param      array $data       The invoice data
     * @param      Invoice $invoice  The invoice
     *
     * @return     Invoice|null  Returns the invoice object
     */
    public function save($data, Invoice $invoice): ?Invoice
    {
        return $this->alternativeSave($data, $invoice);
    }

    /**
     * Mark the invoice as sent.
     *
     * @param Invoice $invoice  The invoice
     *
     * @return     Invoice|null  Return the invoice object
     */
    public function markSent(Invoice $invoice): ?Invoice
    {
        return $invoice->service()->markSent()->save();
    }

    public function getInvitationByKey($key): ?InvoiceInvitation
    {
        return InvoiceInvitation::query()->where('key', $key)->first();
    }

    /**
     * Method is not protected, assumes that
     * other protections have been implemented prior
     * to hitting this method.
     *
     * ie. invoice can be deleted from a business logic perspective.
     *
     * @param Invoice $invoice
     * @return Invoice $invoice
     */
    public function delete($invoice): Invoice
    {
        $invoice = $invoice->fresh();

        if ($invoice->is_deleted) {
            return $invoice;
        }

        $invoice = $invoice->service()->markDeleted()->save();

        parent::delete($invoice);

        return $invoice;
    }

    /**
     * Handles the restoration on a deleted invoice.
     *
     * @param  Invoice $invoice
     * @return Invoice
     */
    public function restore($invoice): Invoice
    {
        if ($invoice->is_proforma) {
            return $invoice;
        }

        //if we have just archived, only perform a soft restore
        if (! $invoice->is_deleted) {
            parent::restore($invoice);

            return $invoice;
        }

        // reversed delete invoice actions
        $invoice = $invoice->service()->handleRestore()->save();

        /* If the reverse did not succeed due to rules, then do not restore / unarchive */
        if ($invoice->is_deleted) {
            return $invoice;
        }

        parent::restore($invoice);

        return $invoice;
    }

    public function reverse()
    {
    }

    public function cancel()
    {
    }
}

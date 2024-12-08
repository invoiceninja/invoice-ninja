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

namespace App\Services\Quickbooks\Models;

use Carbon\Carbon;
use App\Models\Invoice;
use App\DataMapper\InvoiceSync;
use App\Factory\InvoiceFactory;
use App\Interfaces\SyncInterface;
use App\Repositories\InvoiceRepository;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\InvoiceTransformer;
use App\Services\Quickbooks\Transformers\PaymentTransformer;

class QbInvoice implements SyncInterface
{
    protected InvoiceTransformer $invoice_transformer;

    protected InvoiceRepository $invoice_repository;

    public function __construct(public QuickbooksService $service)
    {
        $this->invoice_transformer = new InvoiceTransformer($this->service->company);
        $this->invoice_repository = new InvoiceRepository();
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Invoice', $id);
    }

    public function syncToNinja(array $records): void
    {

        foreach ($records as $record) {

            $this->syncNinjaInvoice($record);

        }

    }

    public function syncToForeign(array $records): void
    {

    }

    private function qbInvoiceUpdate(array $ninja_invoice_data, Invoice $invoice): void
    {
        $current_ninja_invoice_balance = $invoice->balance;
        $qb_invoice_balance = $ninja_invoice_data['balance'];

        if (floatval($current_ninja_invoice_balance) == floatval($qb_invoice_balance)) {
            nlog('Invoice balance is the same, skipping update of line items');
            unset($ninja_invoice_data['line_items']);
            $invoice->fill($ninja_invoice_data);
            $invoice->saveQuietly();
        } else {
            nlog('Invoice balance is different, updating line items');
            $this->invoice_repository->save($ninja_invoice_data, $invoice);
        }
    }

    private function findInvoice(string $id, ?string $client_id = null): ?Invoice
    {
        $search = Invoice::query()
                            ->withTrashed()
                            ->where('company_id', $this->service->company->id)
                            ->where('sync->qb_id', $id);

        if ($search->count() == 0) {
            $invoice = InvoiceFactory::create($this->service->company->id, $this->service->company->owner()->id);
            $invoice->client_id = (int)$client_id;

            $sync = new InvoiceSync();
            $sync->qb_id = $id;
            $invoice->sync = $sync;

            return $invoice;
        } elseif ($search->count() == 1) {
            return $this->service->syncable('invoice', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
        }

        return null;

    }

    public function sync($id, string $last_updated): void
    {

        $qb_record = $this->find($id);

        nlog($qb_record);

        if ($this->service->syncable('invoice', \App\Enum\SyncDirection::PULL)) {

            $invoice = $this->findInvoice($id);

            nlog("Comparing QB last updated: " . $last_updated);
            nlog("Comparing Ninja last updated: " . $invoice->updated_at);

            if (data_get($qb_record, 'TxnStatus') === 'Voided') {
                $this->delete($id);
                return;
            }

            if (!$invoice->id) {
                $this->syncNinjaInvoice($qb_record);
            } elseif (Carbon::parse($last_updated)->gt(Carbon::parse($invoice->updated_at)) || $qb_record->SyncToken == '0') {
                $ninja_invoice_data = $this->invoice_transformer->qbToNinja($qb_record);
                nlog($ninja_invoice_data);

                $this->invoice_repository->save($ninja_invoice_data, $invoice);

            }

        }
    }

    /**
     * syncNinjaInvoice
     *
     * @param  $record
     * @return void
     */
    public function syncNinjaInvoice($record): void
    {

        $ninja_invoice_data = $this->invoice_transformer->qbToNinja($record);

        nlog($ninja_invoice_data);

        $payment_ids = $ninja_invoice_data['payment_ids'] ?? [];

        $client_id = $ninja_invoice_data['client_id'] ?? null;

        if (is_null($client_id)) {
            return;
        }

        unset($ninja_invoice_data['payment_ids']);

        if ($invoice = $this->findInvoice($ninja_invoice_data['id'], $ninja_invoice_data['client_id'])) {

            if ($invoice->id) {
                $this->qbInvoiceUpdate($ninja_invoice_data, $invoice);
            }

            //new invoice scaffold
            $invoice->fill($ninja_invoice_data);
            $invoice->saveQuietly();

            $invoice = $invoice->calc()->getInvoice()->service()->markSent()->applyNumber()->createInvitations()->save();

            foreach ($payment_ids as $payment_id) {

                $payment = $this->service->sdk->FindById('Payment', $payment_id);

                $payment_transformer = new PaymentTransformer($this->service->company);

                $transformed = $payment_transformer->qbToNinja($payment);

                $ninja_payment = $payment_transformer->buildPayment($payment);
                $ninja_payment->service()->applyNumber()->save();

                $paymentable = new \App\Models\Paymentable();
                $paymentable->payment_id = $ninja_payment->id;
                $paymentable->paymentable_id = $invoice->id;
                $paymentable->paymentable_type = 'invoices';
                $paymentable->amount = $transformed['applied'] + $ninja_payment->credits->sum('amount');
                $paymentable->created_at = $ninja_payment->date; //@phpstan-ignore-line
                $paymentable->save();

                $invoice->service()->applyPayment($ninja_payment, $paymentable->amount);

            }

            if ($record instanceof \QuickBooksOnline\API\Data\IPPSalesReceipt) {
                $invoice->service()->markPaid()->save();
            }

        }

        $ninja_invoice_data = false;


    }

    /**
     * Deletes the invoice from Ninja and sets the sync to null
     *
     * @param string $id
     * @return void
     */
    public function delete($id): void
    {
        $qb_record = $this->find($id);

        if ($this->service->syncable('invoice', \App\Enum\SyncDirection::PULL) && $invoice = $this->findInvoice($id)) {
            $invoice->sync = null;
            $invoice->saveQuietly();
            $this->invoice_repository->delete($invoice);
        }
    }
}

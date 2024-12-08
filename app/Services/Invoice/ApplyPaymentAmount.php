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

use App\Events\Invoice\InvoiceWasPaid;
use App\Events\Payment\PaymentWasCreated;
use App\Factory\PaymentFactory;
use App\Libraries\Currency\Conversion\CurrencyApi;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\AbstractService;
use App\Utils\Ninja;
use App\Utils\Traits\GeneratesCounter;
use Illuminate\Support\Carbon;

class ApplyPaymentAmount extends AbstractService
{
    use GeneratesCounter;

    public function __construct(private Invoice $invoice, private float $amount, private ?string $reference)
    {
    }

    public function run()
    {
        if ($this->invoice->status_id == Invoice::STATUS_DRAFT) {
            $this->invoice = $this->invoice->service()->markSent()->save();
        }

        /*Don't double pay*/
        if ($this->invoice->status_id == Invoice::STATUS_PAID) {
            return $this->invoice;
        }

        if ($this->amount == 0) {
            return $this->invoice;
        }

        /* Create Payment */
        $payment = PaymentFactory::create($this->invoice->company_id, $this->invoice->user_id);

        $payment->amount = $this->amount;
        $payment->applied = min($this->amount, $this->invoice->balance);
        $payment->number = $this->getNextPaymentNumber($this->invoice->client, $payment);
        $payment->status_id = Payment::STATUS_COMPLETED;
        $payment->client_id = $this->invoice->client_id;
        $payment->transaction_reference = $this->reference ?: ctrans('texts.manual_entry');
        $payment->currency_id = $this->invoice->client->getSetting('currency_id');
        $payment->is_manual = true;
        /* Create a payment relationship to the invoice entity */
        $payment->saveQuietly();

        $this->setExchangeRate($payment);

        $payment->invoices()->attach($this->invoice->id, [
            'amount' => $payment->amount,
        ]);


        $has_partial = $this->invoice->hasPartial();

        $invoice_service = $this->invoice->service()
                ->setExchangeRate()
                ->updateBalance($payment->amount * -1)
                ->updatePaidToDate($payment->amount)
                ->setCalculatedStatus()
                ->applyNumber();


        if ($has_partial) {
            $this->invoice->partial = max(0, $this->invoice->partial - $payment->amount);
            $invoice_service->checkReminderStatus();
        }

        if ($this->invoice->balance == 0) {
            $this->invoice->next_send_date = null;
        }

        $this->invoice = $invoice_service->save();

        $this->invoice
            ->client
            ->service()
            ->updateBalanceAndPaidToDate($payment->amount * -1, $payment->amount)
            ->save();


        if ($this->invoice->client->getSetting('client_manual_payment_notification')) {
            $payment->service()->sendEmail();
        }

        /* Update Invoice balance */

        $payment->ledger()
                ->updatePaymentBalance($payment->amount * -1, "ApplyPaymentInvoice-");

        $this->invoice->service()->workFlow()->save();

        event('eloquent.created: App\Models\Payment', $payment);
        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));
        event(new InvoiceWasPaid($this->invoice, $payment, $payment->company, Ninja::eventVars(auth()->user() ? auth()->user()->id : null)));

        return $this->invoice;
    }

    private function setExchangeRate(Payment $payment)
    {
        if ($payment->exchange_rate != 1) {
            return;
        }

        $client_currency = $payment->client->getSetting('currency_id');
        $company_currency = $payment->client->company->settings->currency_id;

        if ($company_currency != $client_currency) {
            $exchange_rate = new CurrencyApi();

            $payment->exchange_rate = $exchange_rate->exchangeRate($client_currency, $company_currency, Carbon::parse($payment->date));

            $payment->exchange_currency_id = $company_currency;

            $payment->saveQuietly();
        }
    }
}

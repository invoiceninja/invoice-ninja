<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\PaymentDrivers\BTCPay;

use App\Models\Payment;
use App\PaymentDrivers\BTCPayPaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\Utils\Traits\MakesHash;
use App\PaymentDrivers\Common\MethodInterface;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use Illuminate\Mail\Mailables\Address;
use App\Services\Email\EmailObject;
use App\Services\Email\Email;
use Illuminate\Support\Facades\App;

class BTCPay implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    public $driver_class;

    public function __construct(BTCPayPaymentDriver $driver_class)
    {
        $this->driver_class = $driver_class;
        $this->driver_class->init();
    }

    public function authorizeView($data)
    {
    }

    public function authorizeRequest($request)
    {
    }
    public function authorizeResponse($request)
    {
    }

    public function paymentView($data)
    {
        $data = $this->paymentData($data);

        return render('gateways.btcpay.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {

        $request->validate([
            'payment_hash' => ['required'],
            'amount' => ['required'],
            'currency' => ['required'],
        ]);

        $drv = $this->driver_class;
        if (
            strlen($drv->btcpay_url) < 1
            || strlen($drv->api_key) < 1
            || strlen($drv->store_id) < 1
            || strlen($drv->webhook_secret) < 1
        ) {
            throw new PaymentFailed('BTCPay is not well configured');
        }
        if (!filter_var($this->driver_class->btcpay_url, FILTER_VALIDATE_URL)) {
            throw new PaymentFailed('Wrong format for BTCPay Url');
        }

        try {
            $_invoice = collect($drv->payment_hash->data->invoices)->first();
            $cli = $drv->client;

            $metaData = [
                'buyerName' => $cli->name,
                'buyerAddress1' => $cli->address1,
                'buyerAddress2' =>  $cli->address2,
                'buyerCity' =>  $cli->city,
                'buyerState' => $cli->state,
                'buyerZip' => $cli->postal_code,
                'buyerCountry' => $cli->country_id,
                'buyerPhone' => $cli->phone,
                'itemDesc' => "From InvoiceNinja",
                'InvoiceNinjaPaymentHash' => $drv->payment_hash->hash
            ];


            $urlRedirect = redirect()->route('client.invoice.show', ['invoice' => $_invoice->invoice_id])->getTargetUrl();
            $checkoutOptions = new \BTCPayServer\Client\InvoiceCheckoutOptions();
            $checkoutOptions->setRedirectURL($urlRedirect);

            $client = new \BTCPayServer\Client\Invoice($drv->btcpay_url, $drv->api_key);
            $rep = $client->createInvoice(
                $drv->store_id,
                $request->currency,
                \BTCPayServer\Util\PreciseNumber::parseString($request->amount),
                $_invoice->invoice_number,
                $cli->present()->email(),
                $metaData,
                $checkoutOptions
            );
            //$payment->transaction_reference = $rep->getId();
            // $payment->save();

            return redirect($rep->getCheckoutLink());
        } catch (\Throwable $e) {
            PaymentFailureMailer::dispatch($drv->client, $drv->payment_hash->data, $drv->client->company, $request->amount);
            throw new PaymentFailed('Error during BTCPay payment : ' . $e->getMessage());
        }
    }

    public function refund(Payment $payment, $amount)
    {
        try {
            if ($amount == $payment->amount) {
                $refundVariant = "Fiat";
                $refundPaymentMethod = "BTC";
                $refundDescription = "Full refund";
                $refundCustomCurrency = null;
                $refundCustomAmount = null;
            } else {
                $refundVariant = "Custom";
                $refundPaymentMethod = "";
                $refundDescription = "Partial refund";
                $refundCustomCurrency = $payment->currency;
                $refundCustomAmount = $amount;
            }

            $client = new \BTCPayServer\Client\Invoice($this->driver_class->btcpay_url, $this->driver_class->api_key);
            $refund = $client->refundInvoice(
                $this->driver_class->store_id,
                $payment->transaction_reference,
                $refundVariant,
                $refundPaymentMethod,
                null,
                $refundDescription,
                0,
                $refundCustomAmount,
                $refundCustomCurrency
            );
            App::setLocale($payment->company->getLocale());

            $mo = new EmailObject();
            $mo->subject = ctrans('texts.btcpay_refund_subject');
            $mo->body = ctrans('texts.btcpay_refund_body') . '<br>' . $refund->getViewLink();
            $mo->text_body = ctrans('texts.btcpay_refund_body') . '\n' . $refund->getViewLink();
            $mo->company_key = $payment->company->company_key;
            $mo->html_template = 'email.template.generic';
            $mo->to = [new Address($payment->client->present()->email(), $payment->client->present()->name())];
            $mo->email_template_body = 'btcpay_refund_subject';
            $mo->email_template_subject = 'btcpay_refund_body';

            Email::dispatch($mo, $payment->company);

            $data = [
                'transaction_reference' => $refund->getId(),
                'transaction_response' => json_encode($refund),
                'success' => true,
                'description' => "Please follow this link to claim your refund: " . $refund->getViewLink(),
                'code' => 202,
            ];

            return $data;
        } catch (\Throwable $e) {
            throw new PaymentFailed('Error during BTCPay refund : ' . $e->getMessage());
        }
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.btcpay.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->driver_class;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['currency'] = $this->driver_class->client->getCurrencyCode();

        return $data;
    }
}

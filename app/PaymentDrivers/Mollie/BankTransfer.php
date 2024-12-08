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

namespace App\PaymentDrivers\Mollie;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\MolliePaymentDriver;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;
use Mollie\Api\Resources\Payment as ResourcesPayment;

class BankTransfer implements MethodInterface, LivewireMethodInterface
{
    protected MolliePaymentDriver $mollie;

    public function __construct(MolliePaymentDriver $mollie)
    {
        $this->mollie = $mollie;

        $this->mollie->init();
    }

    /**
     * Show the authorization page for bank transfer.
     *
     * @param array $data
     * @return \Illuminate\View\View
     */
    public function authorizeView(array $data): View
    {
        return render('gateways.mollie.bank_transfer.authorize', $data);
    }

    /**
     * Handle the authorization for bank transfer.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Show the payment page for bank transfer.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponseor|RedirectResponse
     */
    public function paymentView(array $data)
    {
        $this->mollie->payment_hash
            ->withData('gateway_type_id', GatewayType::BANK_TRANSFER)
            ->withData('client_id', $this->mollie->client->id);

        try {
            $payment = $this->mollie->gateway->payments->create([
                'method' => 'banktransfer',
                'amount' => [
                    'currency' => $this->mollie->client->currency()->code,
                    'value' => $this->mollie->convertToMollieAmount((float) $this->mollie->payment_hash->data->amount_with_fee),
                ],
                'description' => \sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($data['invoices'])->pluck('invoice_number')->toArray())),
                'redirectUrl' => route('client.payments.response', [
                    'company_gateway_id' => $this->mollie->company_gateway->id,
                    'payment_hash' => $this->mollie->payment_hash->hash,
                    'payment_method_id' => GatewayType::BANK_TRANSFER,
                ]),
                'webhookUrl' => $this->mollie->company_gateway->webhookUrl(),
                'metadata' => [
                    'client_id' => $this->mollie->client->hashed_id,
                    'hash' => $this->mollie->payment_hash->hash,
                    'gateway_type_id' => GatewayType::BANK_TRANSFER,
                    'payment_type_id' => PaymentType::MOLLIE_BANK_TRANSFER,
                ],
            ]);

            $this->mollie->payment_hash->withData('payment_id', $payment->id);

            return redirect(
                $payment->getCheckoutUrl()
            );
        } catch (\Mollie\Api\Exceptions\ApiException | \Exception $exception) {
            return $this->processUnsuccessfulPayment($exception);
        }
    }

    /**
     * Handle unsuccessful payment.
     *
     * @param Exception $e
     * @throws PaymentFailed
     * @return void
     */
    public function processUnsuccessfulPayment(Exception $e): void
    {
        $this->mollie->sendFailureMail($e->getMessage());

        SystemLogger::dispatch(
            $e->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        throw new PaymentFailed($e->getMessage(), $e->getCode());
    }

    /**
     * Handle the payments for the bank transfer.
     *
     * @param PaymentResponseRequest $request
     * @return mixed
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        if (! \property_exists($this->mollie->payment_hash->data, 'payment_id')) {
            return $this->processUnsuccessfulPayment(
                new PaymentFailed('Whoops, something went wrong. Missing required [payment_id] parameter. Please contact administrator. Reference hash: '.$this->mollie->payment_hash->hash)
            );
        }

        try {
            $payment = $this->mollie->gateway->payments->get(
                $this->mollie->payment_hash->data->payment_id
            );

            if ($payment->status === 'paid') {
                return $this->processSuccessfulPayment($payment);
            }

            if ($payment->status === 'open') {
                return $this->processOpenPayment($payment);
            }

            return $this->processUnsuccessfulPayment(
                new PaymentFailed(ctrans('texts.status_voided'))
            );
        } catch (\Mollie\Api\Exceptions\ApiException | \Exception $exception) {
            return $this->processUnsuccessfulPayment($exception);
        }
    }

    /**
     * Handle the successful payment for bank transfer.
     *
     * @param ResourcesPayment $payment
     * @param string $status
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSuccessfulPayment(ResourcesPayment $payment, $status = 'paid'): RedirectResponse
    {
        $data = [
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
            'amount' => array_sum(array_column($this->mollie->payment_hash->invoices(), 'amount')) + $this->mollie->payment_hash->fee_total,
            'payment_type' => PaymentType::MOLLIE_BANK_TRANSFER,
            'transaction_reference' => $payment->id,
        ];

        $payment_record = $this->mollie->createPayment(
            $data,
            $status === 'paid' ? Payment::STATUS_COMPLETED : Payment::STATUS_PENDING
        );

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_MOLLIE,
            $this->mollie->client,
            $this->mollie->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->mollie->encodePrimaryKey($payment_record->id)]);
    }

    /**
     * Handle 'open' payment status for bank transfer.
     *
     * @param ResourcesPayment $payment
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processOpenPayment(ResourcesPayment $payment): RedirectResponse
    {
        return $this->processSuccessfulPayment($payment, 'open');
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        // Doesn't support, it's offsite payment method.

        return '';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $this->paymentView($data);

        return $data;
    }
}

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

namespace App\PaymentDrivers\Razorpay;

use App\Exceptions\PaymentFailed;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\RazorpayPaymentDriver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Razorpay\Api\Errors\SignatureVerificationError;

class Hosted implements MethodInterface, LivewireMethodInterface
{
    protected RazorpayPaymentDriver $razorpay;

    public function __construct(RazorpayPaymentDriver $razorpay)
    {
        $this->razorpay = $razorpay;

        $this->razorpay->init();
    }

    /**
     * Show the authorization page for Razorpay.
     *
     * @param array $data
     * @return \Illuminate\View\View
     */
    public function authorizeView(array $data): View
    {
        return render('gateways.razorpay.hosted.authorize', $data);
    }

    /**
     * Handle the authorization page for Razorpay.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->route('client.payment_methods.index');
    }

    /**
     * Payment view for the Razorpay.
     *
     * @param array $data
     * @return \Illuminate\View\View
     */
    public function paymentView(array $data): View
    {
        $data = $this->paymentData($data);

        return render('gateways.razorpay.hosted.pay', $data);
    }

    /**
     * Handle payments page for Razorpay.
     *
     * @param PaymentResponseRequest $request
     * @return void
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $request->validate([
            'payment_hash' => ['required'],
            'razorpay_payment_id' => ['required'],
            'razorpay_signature' => ['required'],
        ]);

        if (! property_exists($this->razorpay->payment_hash->data, 'order_id')) {
            $this->razorpay->sendFailureMail('Missing [order_id] property. ');

            throw new PaymentFailed('Missing [order_id] property. Please contact the administrator. Reference: '.$this->razorpay->payment_hash->hash);
        }

        try {
            $attributes = [
                'razorpay_order_id' => $this->razorpay->payment_hash->data->order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ];

            $this->razorpay->gateway->utility->verifyPaymentSignature($attributes);

            return $this->processSuccessfulPayment($request->razorpay_payment_id);
        } catch (SignatureVerificationError $exception) {
            return $this->processUnsuccessfulPayment($exception);
        }
    }

    /**
     * Handle the successful payment for Razorpay.
     *
     * @param string $payment_id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSuccessfulPayment(string $payment_id): RedirectResponse
    {
        $data = [
            'gateway_type_id' => GatewayType::HOSTED_PAGE,
            'amount' => array_sum(array_column($this->razorpay->payment_hash->invoices(), 'amount')) + $this->razorpay->payment_hash->fee_total,
            'payment_type' => PaymentType::HOSTED_PAGE,
            'transaction_reference' => $payment_id,
        ];

        $payment_record = $this->razorpay->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $payment_id, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_RAZORPAY,
            $this->razorpay->client,
            $this->razorpay->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->razorpay->encodePrimaryKey($payment_record->id)]);
    }

    /**
     * Handle unsuccessful payment for Razorpay.
     *
     * @param Exception $exception
     * @throws PaymentFailed
     * @return void
     */
    public function processUnsuccessfulPayment(\Exception $exception): void
    {
        $this->razorpay->sendFailureMail($exception->getMessage());

        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_RAZORPAY,
            $this->razorpay->client,
            $this->razorpay->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        return 'gateways.razorpay.hosted.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $order = $this->razorpay->gateway->order->create([
            'currency' => $this->razorpay->client->currency()->code,
            'amount' => $this->razorpay->convertToRazorpayAmount((float) $this->razorpay->payment_hash->data->amount_with_fee),
        ]);

        $this->razorpay->payment_hash->withData('order_id', $order->id);
        $this->razorpay->payment_hash->withData('order_amount', $order->amount);

        $data['gateway'] = $this->razorpay;

        $data['options'] = [
            'key' => $this->razorpay->company_gateway->getConfigField('apiKey'),
            'amount' => $this->razorpay->convertToRazorpayAmount((float) $this->razorpay->payment_hash->data->amount_with_fee),
            'currency' => $this->razorpay->client->currency()->code,
            'name' => $this->razorpay->company_gateway->company->present()->name(),
            'order_id' => $order->id,
        ];

        return $data;
    }
}

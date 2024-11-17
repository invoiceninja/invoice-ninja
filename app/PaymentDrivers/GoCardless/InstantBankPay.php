<?php

namespace App\PaymentDrivers\GoCardless;

use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\PaymentFailureMailer;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class InstantBankPay implements MethodInterface, LivewireMethodInterface
{
    protected GoCardlessPaymentDriver $go_cardless;

    public function __construct(GoCardlessPaymentDriver $go_cardless)
    {
        $this->go_cardless = $go_cardless;

        $this->go_cardless->init();
    }

    /**
     * Authorization page for Instant Bank Pay.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     * @throws BindingResolutionException
     */
    public function authorizeView(array $data): RedirectResponse
    {
        return redirect()->back();
    }

    /**
     * Handle authorization for Instant Bank Pay.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     * @throws BindingResolutionException
     */
    public function authorizeResponse(Request $request): RedirectResponse
    {
        return redirect()->back();
    }

    public function paymentView(array $data)
    {
        try {
            $billing_request = $this->go_cardless->gateway->billingRequests()->create([
                'params' => [
                    'payment_request' => [
                        'description' => ctrans('texts.invoices').': '.collect($data['invoices'])->pluck('invoice_number'),
                        'amount' => (string) $data['amount_with_fee'] * 100,
                        'currency' => $this->go_cardless->client->getCurrencyCode(),
                    ],
                    'metadata' => [
                        'payment_hash' => $this->go_cardless->payment_hash->hash,
                    ],
                ],
            ]);

            $billing_request_flow = $this->go_cardless->gateway->billingRequestFlows()->create([
                'params' => [
                    'redirect_uri' => route('gocardless.ibp_redirect', [
                        'company_key' => $this->go_cardless->company_gateway->company->company_key,
                        'company_gateway_id' => $this->go_cardless->company_gateway->hashed_id,
                        'hash' => $this->go_cardless->payment_hash->hash,
                    ]),
                    'links' => [
                        'billing_request' => $billing_request->id,
                    ],
                ],
            ]);

            $this->go_cardless->payment_hash
                ->withData('client_id', $this->go_cardless->client->id)
                ->withData('billing_request', $billing_request->id)
                ->withData('billing_request_flow', $billing_request_flow->id);

            return redirect(
                $billing_request_flow->authorisation_url
            );
        } catch (\Exception $exception) {
            throw $exception;
        }
    }

    public function paymentResponse($request)
    {

        $this->go_cardless->setPaymentHash(
            $request->getPaymentHash()
        );

        $this->go_cardless->init();

        nlog($request->all());

        try {
            $billing_request = $this->go_cardless->gateway->billingRequests()->get(
                $this->go_cardless->payment_hash->data->billing_request
            );

            nlog($billing_request);
            

            $payment = $this->go_cardless->gateway->payments()->get(
                $billing_request->payment_request->links->payment
            );

            if ($billing_request->status === 'fulfilled') {
                return $this->processSuccessfulPayment($payment);
            }

            if (in_array($billing_request->status, ['fulfilling', 'submitted'])) {
                return $this->processPendingPayment($payment);
            }

            $this->processUnsuccessfulPayment($payment);
        } catch (\Exception $exception) {

            throw new PaymentFailed(
                $exception->getMessage(),
                $exception->getCode()
            );
        }
    }

    /**
     * Handle pending payments for Instant Bank Transfer.
     *
     * @param \GoCardlessPro\Resources\Payment $payment
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPendingPayment(\GoCardlessPro\Resources\Payment $payment, array $data = [])
    {
        $data = [
            'payment_method' => $payment->links->mandate, //@phpstan-ignore tag
            'payment_type' => PaymentType::INSTANT_BANK_PAY,
            'amount' => $this->go_cardless->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id, //@phpstan-ignore tag
            'gateway_type_id' => GatewayType::INSTANT_BANK_PAY,
        ];

        $_payment = $this->go_cardless->createPayment($data, Payment::STATUS_PENDING);

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $_payment->hashed_id]);
    }



    /**
     * Handle pending payments for Instant Bank Transfer.
     *
     * @param \GoCardlessPro\Resources\Payment $payment
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processSuccessfulPayment(\GoCardlessPro\Resources\Payment $payment, array $data = [])
    {
        $data = [
            'payment_method' => $payment->links->mandate,
            'payment_type' => PaymentType::INSTANT_BANK_PAY,
            'amount' => $this->go_cardless->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id,
            'gateway_type_id' => GatewayType::INSTANT_BANK_PAY,
        ];

        $_payment = $this->go_cardless->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $payment, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $_payment->hashed_id]);
    }

    /**
     * Process unsuccessful payments for Direct Debit.
     *
     * @param ResourcesPayment $payment
     */
    public function processUnsuccessfulPayment(\GoCardlessPro\Resources\Payment $payment): void
    {
        PaymentFailureMailer::dispatch($this->go_cardless->client, $payment->status, $this->go_cardless->client->company, $this->go_cardless->payment_hash->data->amount_with_fee);

        // PaymentFailureMailer::dispatch(
        //     $this->go_cardless->client,
        //     $payment,
        //     $this->go_cardless->client->company,
        //     $payment->amount
        // );

        $message = [
            'server_response' => $payment,
            'data' => $this->go_cardless->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string 
    {
        // not supported, this is offsite payment method.

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

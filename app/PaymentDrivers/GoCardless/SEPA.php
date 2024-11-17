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

namespace App\PaymentDrivers\GoCardless;

use App\Exceptions\PaymentFailed;
use App\Http\Controllers\ClientPortal\InvoiceController;
use App\Http\Requests\ClientPortal\Invoices\ProcessInvoicesInBulkRequest;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\PaymentDrivers\GoCardlessPaymentDriver;
use App\Utils\Traits\MakesHash;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\View\View;

class SEPA implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    protected GoCardlessPaymentDriver $go_cardless;

    public function __construct(GoCardlessPaymentDriver $go_cardless)
    {
        $this->go_cardless = $go_cardless;

        $this->go_cardless->init();
    }

    /**
     * Handle authorization for SEPA.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponseor|RedirectResponse|void
     */
    public function authorizeView(array $data)
    {
        $session_token = \Illuminate\Support\Str::uuid()->toString();

        try {
            $redirect = $this->go_cardless->gateway->redirectFlows()->create([
                'params' => [
                    'scheme' => 'sepa_core',
                    'session_token' => $session_token,
                    'success_redirect_url' => route('client.payment_methods.confirm', [
                        'method' => GatewayType::SEPA,
                        'session_token' => $session_token,
                        'authorize_then_redirect' => true,
                        'payment_hash' => $this->go_cardless->payment_hash->hash ?? '',
                    ]),
                    'prefilled_customer' => [
                        'given_name' => auth()->guard('contact')->user()->client->present()->first_name(),
                        'family_name' => auth()->guard('contact')->user()->client->present()->last_name(),
                        'email' => auth()->guard('contact')->user()->client->present()->email(),
                        'address_line1' => auth()->guard('contact')->user()->client->address1 ?: '',
                        'city' => auth()->guard('contact')->user()->client->city ?: '',
                        'postal_code' => auth()->guard('contact')->user()->client->postal_code ?: '',
                    ],
                ],
            ]);

            return redirect(
                $redirect->redirect_url
            );
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulAuthorization($exception);
        }
    }

    /**
     * Handle unsuccessful authorization for SEPA.
     *
     * @param Exception $exception
     * @return void
     */
    public function processUnsuccessfulAuthorization(Exception $exception): void
    {
        $this->go_cardless->sendFailureMail($exception->getMessage());

        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_GOCARDLESS,
            $this->go_cardless->client,
            $this->go_cardless->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }

    /**
     * Handle authorization response for SEPA.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function authorizeResponse(Request $request)
    {
        try {
            $redirect_flow = $this->go_cardless->gateway->redirectFlows()->complete(
                $request->redirect_flow_id,
                ['params' => [
                    'session_token' => $request->session_token,
                ]],
            );

            $payment_meta = new \stdClass();
            $payment_meta->brand = ctrans('texts.sepa');
            $payment_meta->type = GatewayType::SEPA;
            $payment_meta->state = 'authorized';

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $redirect_flow->links->mandate,
                'payment_method_id' => GatewayType::SEPA,
            ];

            $payment_method = $this->go_cardless->storeGatewayToken($data, ['gateway_customer_reference' => $redirect_flow->links->customer]);

            if ($request->has('authorize_then_redirect') && $request->payment_hash !== null) {
                $this->go_cardless->payment_hash = PaymentHash::where('hash', $request->payment_hash)->firstOrFail();

                $data = [
                    'invoices' => collect($this->go_cardless->payment_hash->data->invoices)->map(fn ($invoice) => $invoice->invoice_id)->toArray(),
                    'action' => 'payment',
                ];
                
                $request = new ProcessInvoicesInBulkRequest();
                $request->replace($data);
    
                session()->flash('message', ctrans('texts.payment_method_added'));
    
                return app(InvoiceController::class)->bulk($request);
            }

            return redirect()->route('client.payment_methods.show', $payment_method->hashed_id);
        } catch (\Exception $exception) {
            return $this->processUnsuccessfulAuthorization($exception);
        }
    }

    /**
     * Payment view for SEPA.
     *
     * @param array $data
     * @return \Illuminate\View\View
     */
    public function paymentView(array $data): View
    {
        $data = $this->paymentData($data);

        return render('gateways.gocardless.sepa.pay', $data);
    }

    /**
     * Handle the payment page for SEPA.
     *
     * @param PaymentResponseRequest $request
     * @return \Illuminate\Http\RedirectResponse|App\PaymentDrivers\GoCardless\never|void
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {
        $this->go_cardless->ensureMandateIsReady($request->source);

        $invoice = Invoice::query()->whereIn('id', $this->transformKeys(array_column($this->go_cardless->payment_hash->invoices(), 'invoice_id')))
                          ->withTrashed()
                          ->first();

        if ($invoice) {
            $description = "Invoice {$invoice->number} for {$request->amount} for client {$this->go_cardless->client->present()->name()}";
        } else {
            $description = "Amount {$request->amount} from client {$this->go_cardless->client->present()->name()}";
        }

        $amount = $this->go_cardless->convertToGoCardlessAmount($this->go_cardless->payment_hash?->amount_with_fee(), $this->go_cardless->client->currency()->precision);

        try {
            $payment = $this->go_cardless->gateway->payments()->create([
                'params' => [
                    'amount' => $amount,
                    'currency' => $request->currency,
                    'description' => $description,
                    'metadata' => [
                        'payment_hash' => $this->go_cardless->payment_hash->hash,
                    ],
                    'links' => [
                        'mandate' => $request->source,
                    ],
                ],
            ]);

            if ($payment->status === 'pending_submission') {
                return $this->processPendingPayment($payment, ['token' => $request->source]);
            }

            return $this->processUnsuccessfulPayment($payment);
        } catch (\Exception $exception) {
            throw new PaymentFailed($exception->getMessage(), $exception->getCode());
        }
    }

    /**
     * Handle pending payments for Direct Debit.
     *
     * @param ResourcesPayment $payment
     * @param array $data
     * @return \Illuminate\Http\RedirectResponse
     */
    public function processPendingPayment(\GoCardlessPro\Resources\Payment $payment, array $data = [])
    {
        $data = [
            'payment_type' => PaymentType::SEPA,
            'amount' => $this->go_cardless->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id,
            'gateway_type_id' => GatewayType::SEPA,
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
     * Process unsuccessful payments for Direct Debit.
     *
     * @param ResourcesPayment $payment
     * @return never
     */
    public function processUnsuccessfulPayment(\GoCardlessPro\Resources\Payment $payment)
    {
        $this->go_cardless->sendFailureMail(
            $payment->status
        );

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

        throw new PaymentFailed('Failed to process the payment.', 500);
    }
    
    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string 
    {
        return 'gateways.gocardless.sepa.pay_livewire';
    }
    
    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array 
    {
        $data['gateway'] = $this->go_cardless;
        $data['amount'] = $this->go_cardless->convertToGoCardlessAmount($data['total']['amount_with_fee'], $this->go_cardless->client->currency()->precision);
        $data['currency'] = $this->go_cardless->client->getCurrencyCode();

        if (count($data['tokens']) === 0) {
            $data['authorize_then_redirect'] = true;

            $this->authorizeView($data);
        }

        return $data;
    }
}

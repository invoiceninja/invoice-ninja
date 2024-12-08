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
use App\Jobs\Mail\PaymentFailureMailer;
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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DirectDebit implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    protected GoCardlessPaymentDriver $go_cardless;

    public function __construct(GoCardlessPaymentDriver $go_cardless)
    {
        $this->go_cardless = $go_cardless;

        $this->go_cardless->init();
    }

    /**
     * Handle authorization for Direct Debit.
     *
     * @param array $data
     * @return \Illuminate\Http\RedirectResponseor|RedirectResponse|void
     */
    public function authorizeView(array $data)
    {
        return $this->billingRequestFlows($data);
    }

    public function billingRequestFlows(array $data)
    {
        $session_token = \Illuminate\Support\Str::uuid()->toString();
        $exit_uri = route('client.payment_methods.index');

        $response = $this->go_cardless->gateway->billingRequests()->create([
            "params" => [
                "mandate_request" => [
                "currency" => auth()->guard('contact')->user()->client->currency()->code,
                "verify" => "when_available"
                ]
            ]
        ]);

        try {
            $brf = $this->go_cardless->gateway->billingRequestFlows()->create([
                "params" => [
                    "redirect_uri" => route('client.payment_methods.confirm', [
                            'method' => GatewayType::DIRECT_DEBIT,
                            'session_token' => $session_token,
                            'billing_request' => $response->id,
                            'authorize_then_redirect' => true,
                            'payment_hash' => $this->go_cardless->payment_hash->hash ?? '',
                        ]),
                    "exit_uri" => $exit_uri,
                    "links" => [
                    "billing_request" => $response->id
                    ],
                    "show_redirect_buttons" => true,
                    "show_success_redirect_button" => true,
                ]
            ]);

            return redirect($brf->authorisation_url);

        } catch (\Exception $exception) {
            nlog($exception->getMessage());
            return $this->processUnsuccessfulAuthorization($exception);
        }

    }


    /**
     * Handle unsuccessful authorization.
     *
     * @param \Exception $exception
     * @throws PaymentFailed
     * @return void
     */
    public function processUnsuccessfulAuthorization(\Exception $exception): void
    {
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
     * Handle authorization response for Direct Debit.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|void
     */
    public function authorizeResponse(Request $request)
    {

        try {

            $billing_request = $this->go_cardless->gateway->billingRequests()->get($request->billing_request);

            $payment_meta = new \stdClass();
            $payment_meta->brand = $billing_request->resources->customer_bank_account->bank_name;
            $payment_meta->type = $this->resolveScheme($billing_request->mandate_request->scheme);
            $payment_meta->state = 'pending';
            $payment_meta->last4 = $billing_request->resources->customer_bank_account->account_number_ending;

            $data = [
                'payment_meta' => $payment_meta,
                'token' => $billing_request->mandate_request->links->mandate,
                'payment_method_id' => $this->resolveScheme($billing_request->mandate_request->scheme),
            ];

            $payment_method = $this->go_cardless->storeGatewayToken($data, ['gateway_customer_reference' => $billing_request->resources->customer->id]);

            $mandate = $this->go_cardless->gateway->mandates()->get($billing_request->mandate_request->links->mandate);

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

    private function resolveScheme(string $scheme): int
    {
        match ($scheme) {
            'sepa_core' => $type = GatewayType::SEPA,
            'ach' => $type = GatewayType::BANK_TRANSFER,
            default => $type = GatewayType::DIRECT_DEBIT,
        };

        return $type;
    }


    /**
     * Payment view for Direct Debit.
     *
     * @param array $data
     */
    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        if (isset($data['authorize_then_redirect'])) {
            return $this->authorizeView($data);
        }

        return render('gateways.gocardless.direct_debit.pay', $data);
    }

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
                    // 'amount' => $request->amount,
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
            'payment_type' => PaymentType::DIRECT_DEBIT,
            'amount' => $this->go_cardless->payment_hash->data->amount_with_fee,
            'transaction_reference' => $payment->id,
            'gateway_type_id' => GatewayType::DIRECT_DEBIT,
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
        PaymentFailureMailer::dispatch($this->go_cardless->client, $payment->status, $this->go_cardless->client->company, $this->go_cardless->payment_hash->data->amount_with_fee);

        PaymentFailureMailer::dispatch(
            $this->go_cardless->client,
            $payment,
            $this->go_cardless->client->company,
            $payment->amount
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
        return 'gateways.gocardless.direct_debit.pay_livewire';
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
        }

        return $data;
    }
}

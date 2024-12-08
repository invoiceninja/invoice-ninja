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

namespace App\PaymentDrivers\Braintree;

use App\Exceptions\PaymentFailed;
use App\Http\Controllers\ClientPortal\InvoiceController;
use App\Http\Requests\ClientPortal\Invoices\ProcessInvoicesInBulkRequest;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;
use App\Jobs\Util\SystemLogger;
use App\Models\ClientGatewayToken;
use App\Models\GatewayType;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\SystemLog;
use App\PaymentDrivers\BraintreePaymentDriver;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use App\PaymentDrivers\Common\MethodInterface;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;

class ACH implements MethodInterface, LivewireMethodInterface
{
    use MakesHash;

    protected BraintreePaymentDriver $braintree;

    public function __construct(BraintreePaymentDriver $braintree)
    {
        $this->braintree = $braintree;

        $this->braintree->init();
    }

    public function authorizeView(array $data)
    {
        try {
            $data['gateway'] = $this->braintree;
            $data['client_token'] = $this->braintree->gateway->clientToken()->generate();
        } catch (\Exception $e) {

            throw new PaymentFailed("Unable to generate client token, check your Braintree credentials. Error: " . $e->getMessage(), 500);

        }

        return render('gateways.braintree.ach.authorize', $data);
    }

    public function authorizeResponse(Request $request)
    {
        $request->validate([
            'nonce' => ['required'],
            'gateway_type_id' => ['required'],
        ]);

        $customer = $this->braintree->findOrCreateCustomer();

        $result = $this->braintree->gateway->paymentMethod()->create([
            'customerId' => $customer->id,
            'paymentMethodNonce' => $request->nonce,
            'options' => [
                'usBankAccountVerificationMethod' => \Braintree\Result\UsBankAccountVerification::NETWORK_CHECK,
            ],
        ]);

        if ($result->success && $result->paymentMethod?->verified) {
            $account = $result->paymentMethod;

            try {
                $payment_meta = new \stdClass();
                $payment_meta->brand = (string) $account->bankName;
                $payment_meta->last4 = (string) $account->last4;
                $payment_meta->type = GatewayType::BANK_TRANSFER;
                $payment_meta->state = 'authorized';

                $data = [
                    'payment_meta' => $payment_meta,
                    'token' => $account->token,
                    'payment_method_id' => $request->gateway_type_id,
                ];

                $this->braintree->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);

                if ($request->authorize_then_redirect) {
                    $this->braintree->payment_hash = PaymentHash::where('hash', $request->payment_hash)->firstOrFail();

                    $data = [
                        'invoices' => collect($this->braintree->payment_hash->data->invoices)->map(fn ($invoice) => $invoice->invoice_id)->toArray(),
                        'action' => 'payment',
                    ];

                    $request = new ProcessInvoicesInBulkRequest();
                    $request->replace($data);

                    session()->flash('message', ctrans('texts.payment_method_added'));

                    return app(InvoiceController::class)->bulk($request);
                }

                return redirect()->route('client.payment_methods.index')->withMessage(ctrans('texts.payment_method_added'));
            } catch (\Exception $e) {
                return $this->braintree->processInternallyFailedPayment($this->braintree, $e);
            }
        }

        return back()->withMessage(ctrans('texts.unable_to_verify_payment_method'));
    }

    public function paymentView(array $data)
    {
        $data = $this->paymentData($data);

        if (array_key_exists('authorize_then_redirect', $data)) {
            return render('gateways.braintree.ach.authorize', array_merge($data));
        }

        return render('gateways.braintree.ach.pay', $data);
    }

    public function paymentResponse(PaymentResponseRequest $request)
    {
        $request->validate([
            'source' => ['required'],
            'payment_hash' => ['required'],
        ]);

        $customer = $this->braintree->findOrCreateCustomer();

        $token = ClientGatewayToken::query()
            ->where('client_id', auth()->guard('contact')->user()->client->id)
            ->where('id', $this->decodePrimaryKey($request->source))
            ->firstOrFail();

        $result = $this->braintree->gateway->transaction()->sale([
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'paymentMethodToken' => $token->token,
            'channel' => 'invoiceninja_BT',
            'options' => [
                'submitForSettlement' => true,
            ],
        ]);

        if ($result->success) {
            $this->braintree->logSuccessfulGatewayResponse(['response' => $request->server_response, 'data' => $this->braintree->payment_hash->data], SystemLog::TYPE_BRAINTREE);

            return $this->processSuccessfulPayment($result);
        }

        return $this->processUnsuccessfulPayment($result);
    }

    private function processSuccessfulPayment($response)
    {
        $state = $this->braintree->payment_hash->data;

        $data = [
            'payment_type' => PaymentType::ACH,
            'amount' => $this->braintree->payment_hash->data->amount_with_fee,
            'transaction_reference' => $response->transaction->id,
            'gateway_type_id' => GatewayType::BANK_TRANSFER,
        ];

        $payment = $this->braintree->createPayment($data, Payment::STATUS_COMPLETED);

        SystemLogger::dispatch(
            ['response' => $response, 'data' => $data],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        return redirect()->route('client.payments.show', ['payment' => $this->braintree->encodePrimaryKey($payment->id)]);
    }

    private function processUnsuccessfulPayment($response)
    {
        $this->braintree->sendFailureMail($response->transaction->additionalProcessorResponse);

        $message = [
            'server_response' => $response,
            'data' => $this->braintree->payment_hash->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_BRAINTREE,
            $this->braintree->client,
            $this->braintree->client->company,
        );

        throw new PaymentFailed($response->transaction->additionalProcessorResponse, $response->transaction->processorResponseCode);
    }
    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        if (array_key_exists('authorize_then_redirect', $data)) {
            return 'gateways.braintree.ach.authorize_livewire';
        }

        return 'gateways.braintree.ach.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->braintree;
        $data['currency'] = $this->braintree->client->getCurrencyCode();
        $data['payment_method_id'] = GatewayType::BANK_TRANSFER;
        $data['amount'] = $this->braintree->payment_hash->data->amount_with_fee;
        $data['client_token'] = $this->braintree->gateway->clientToken()->generate();
        $data['payment_hash'] = $this->braintree->payment_hash->hash;

        if (count($data['tokens']) === 0) {
            $data['authorize_then_redirect'] = true;
        }

        return $data;
    }
}

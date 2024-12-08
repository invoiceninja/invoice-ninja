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

namespace App\PaymentDrivers\Rotessa;

use App\Http\Controllers\ClientPortal\InvoiceController;
use App\Http\Requests\ClientPortal\Invoices\ProcessInvoicesInBulkRequest;
use App\Models\Payment;
use App\Models\PaymentHash;
use App\Models\SystemLog;
use App\PaymentDrivers\Common\LivewireMethodInterface;
use Illuminate\View\View;
use App\Models\GatewayType;
use App\Models\PaymentType;
use Illuminate\Http\Request;
use App\Jobs\Util\SystemLogger;
use App\Exceptions\PaymentFailed;
use App\DataProviders\Frequencies;
use App\Models\ClientGatewayToken;
use Illuminate\Http\RedirectResponse;
use App\PaymentDrivers\RotessaPaymentDriver;
use App\PaymentDrivers\Common\MethodInterface;
use Omnipay\Common\Exception\InvalidResponseException;
use App\Http\Requests\ClientPortal\Payments\PaymentResponseRequest;

class PaymentMethod implements MethodInterface, LivewireMethodInterface
{
    private array $transaction = [
        "financial_transactions" => [],
        "frequency" => 'Once',
        "installments" => 1
    ];

    public function __construct(protected RotessaPaymentDriver $rotessa)
    {
        $this->rotessa->init();
    }

    /**
     * Show the authorization page for Rotessa.
     *
     * @param array $data
     * @return \Illuminate\View\View
     */
    public function authorizeView(array $data): View
    {
        $data['contact'] = collect($data['client']->contacts->first()->toArray())->merge([
            'home_phone' => $data['client']->phone,
            'custom_identifier' => $data['client']->number,
            'name' => $data['client']->name,
            'id' => null
        ])->all();
        $data['gateway'] = $this->rotessa;
        $data['gateway_type_id'] =   GatewayType::ACSS ;
        $data['account'] = [
            'routing_number' => $data['client']->routing_id,
            'country' => $data['client']->country->iso_3166_2
        ];
        $data['address'] = collect($data['client']->toArray())->merge(['country' => $data['client']->country->iso_3166_2 ])->all();

        return render('gateways.rotessa.bank_transfer.authorize', $data);
    }
    /**
     * Handle the authorization page for Rotessa.
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function authorizeResponse($request)
    {

        $request->validate([
            'gateway_type_id' => ['required','integer'],
            'country' => ['required','in:US,CA,United States,Canada'],
            'name' => ['required'],
            'address_1' => ['required'],
            'city' => ['required'],
            'email' => ['required','email:filter'],
            'province_code' => ['required','size:2','alpha'],
            'postal_code' => ['required'],
            'authorization_type' => ['required'],
            'account_number' => ['required'],
            'bank_name' => ['required'],
            'phone' => ['required'],
            'home_phone' => ['required','size:10'],
            'bank_account_type' => ['required_if:country,US'],
            'routing_number' => ['required_if:country,US'],
            'institution_number' => ['required_if:country,CA','numeric','digits:3'],
            'transit_number' => ['required_if:country,CA','numeric','digits:5'],
            'custom_identifier' => ['required_without:customer_id'],
            'customer_id' => ['required_without:custom_identifier','integer'],
            'customer_type' => ['required', 'in:Personal,Business'],
        ]);

        $customer = array_merge(['address' => $request->only('address_1', 'address_2', 'city', 'postal_code', 'province_code', 'country'), 'custom_identifier' => $request->input('custom_identifier') ], $request->all());

        try {
            $this->rotessa->findOrCreateCustomer($customer);
        } catch (\Exception $e) {

            $message = json_decode($e->getMessage(), true);

            return redirect()->route('client.payment_methods.index')->withErrors(array_values($message['errors'] ?? [$e->getMessage()]));

        }

        if ($request->authorize_then_redirect) {
            $this->rotessa->payment_hash = PaymentHash::where('hash', $request->payment_hash)->firstOrFail();

            $data = [
                'invoices' => collect($this->rotessa->payment_hash->data->invoices)->map(fn ($invoice) => $invoice->invoice_id)->toArray(),
                'action' => 'payment',
            ];

            $request = new ProcessInvoicesInBulkRequest();
            $request->replace($data);

            session()->flash('message', ctrans('texts.payment_method_added'));

            return app(InvoiceController::class)->bulk($request);
        }

        return redirect()->route('client.payment_methods.index')->withMessage(ctrans('texts.payment_method_added'));
    }

    /**
     * Payment view for the Rotessa.
     *
     * @param array $data
     * @return \Illuminate\View\View
     */
    public function paymentView(array $data): View
    {
        $data = $this->paymentData($data);

        if ($data['authorize_then_redirect']) {
            return $this->authorizeView($data);
        }

        return render('gateways.rotessa.bank_transfer.pay', $data);
    }

    /**
     * Handle payments page for Rotessa.
     *
     * @param PaymentResponseRequest $request
     */
    public function paymentResponse(PaymentResponseRequest $request)
    {

        $response = null;
        $customer = null;

        try {

            $customer = ClientGatewayToken::query()
                ->where('company_gateway_id', $this->rotessa->company_gateway->id)
                ->where('client_id', $this->rotessa->client->id)
                ->where('is_deleted', 0)
                ->where('token', $request->input('source'))
                ->first();

            if (!$customer) {
                throw new \Exception('Client gateway token not found!', SystemLog::TYPE_ROTESSA);
            }

            $transaction = array_merge($this->transaction, [
                'amount' => $request->input('amount'),
                'process_date' => now()->addSeconds($customer->client->utc_offset())->format('Y-m-d'),
                'comment' => $this->rotessa->getDescription(false),
                'customer_id' => $customer->gateway_customer_reference,
            ]);

            $response = $this->rotessa->gatewayRequest('post', 'transaction_schedules', $transaction);

            if ($response->failed()) {
                $response->throw();
            }

            $response = $response->json();

            return  $this->processPendingPayment($response['id'], (float) $response['amount'], PaymentType::ACSS, $customer->token);
        } catch (\Throwable $e) {
            $this->processUnsuccessfulPayment(new \Exception($e->getMessage(), (int) $e->getCode()));
        }
    }

    public function processPendingPayment($payment_id, float $amount, int $gateway_type_id, $payment_method)
    {
        $data = [
            'payment_method' => $payment_method,
            'payment_type' => $gateway_type_id,
            'amount' => $amount,
            'transaction_reference' => $payment_id,
            'gateway_type_id' => $gateway_type_id,
        ];
        $payment = $this->rotessa->createPayment($data, Payment::STATUS_PENDING);
        SystemLogger::dispatch(
            [ 'data' => $data ],
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            SystemLog::TYPE_ROTESSA,
            $this->rotessa->client,
            $this->rotessa->client->company,
        );

        return redirect()->route('client.payments.show', [ 'payment' => $payment->hashed_id ]);
    }

    /**
     * Handle unsuccessful payment for Rotessa.
     *
     * @param \Exception $exception
     * @throws PaymentFailed
     * @return void
     */
    public function processUnsuccessfulPayment(\Exception $exception): void
    {
        $this->rotessa->sendFailureMail($exception->getMessage());

        SystemLogger::dispatch(
            $exception->getMessage(),
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_ROTESSA,
            $this->rotessa->client,
            $this->rotessa->client->company,
        );

        throw new PaymentFailed($exception->getMessage(), $exception->getCode());
    }

    /**
     * @inheritDoc
     */
    public function livewirePaymentView(array $data): string
    {
        if (array_key_exists('authorize_then_redirect', $data)) {
            return 'gateways.rotessa.bank_transfer.authorize_livewire';
        }

        return 'gateways.rotessa.bank_transfer.pay_livewire';
    }

    /**
     * @inheritDoc
     */
    public function paymentData(array $data): array
    {
        $data['gateway'] = $this->rotessa;
        $data['amount'] = $data['total']['amount_with_fee'];
        $data['due_date'] = date('Y-m-d', min(max(strtotime($data['invoices']->max('due_date')), strtotime('now')), strtotime('+1 day')));
        $data['process_date'] = $data['due_date'];
        $data['currency'] = $this->rotessa->client->getCurrencyCode();
        $data['frequency'] = 'Once';
        $data['installments'] = 1;
        $data['invoice_nums'] = $data['invoices']->pluck('invoice_number')->join(', ');
        $data['payment_hash'] = $this->rotessa->payment_hash->hash;

        if (count($data['tokens']) === 0) {
            $data['authorize_then_redirect'] = true;

            $data['contact'] = collect($data['client']->contacts->first()->toArray())->merge([
                'home_phone' => $data['client']->phone,
                'custom_identifier' => $data['client']->number,
                'name' => $data['client']->name,
                'id' => null
            ])->all();
            $data['gateway'] = $this->rotessa;
            $data['gateway_type_id'] =   GatewayType::ACSS ;
            $data['account'] = [
                'routing_number' => $data['client']->routing_id,
                'country' => $data['client']->country->iso_3166_2
            ];
            $data['address'] = collect($data['client']->toArray())->merge(['country' => $data['client']->country->iso_3166_2 ])->all();

            $this->authorizeView($data);
        }

        return $data;
    }
}

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

namespace App\PaymentDrivers;

use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Client;
use App\Utils\Helpers;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\SystemLog;
use App\Models\GatewayType;
use App\Models\PaymentHash;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use App\Models\ClientContact;
use App\Jobs\Mail\NinjaMailer;
use App\Models\CompanyGateway;
use Illuminate\Support\Carbon;
use App\DataMapper\InvoiceItem;
use App\Factory\PaymentFactory;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Exceptions\PaymentFailed;
use App\Jobs\Mail\NinjaMailerJob;
use App\Models\ClientGatewayToken;
use Illuminate\Support\Facades\App;
use App\Jobs\Mail\NinjaMailerObject;
use App\Utils\Traits\SystemLogTrait;
use App\Events\Invoice\InvoiceWasPaid;
use App\Jobs\Mail\PaymentFailedMailer;
use App\Events\Payment\PaymentWasCreated;
use App\Mail\Admin\ClientPaymentFailureObject;
use App\Services\Subscription\SubscriptionService;

/**
 * Class BaseDriver.
 */
class BaseDriver extends AbstractPaymentDriver
{
    use SystemLogTrait;
    use MakesHash;

    /* The company gateway instance*/
    public $company_gateway;

    /* The Invitation */
    public $invitation;

    /**
     * The Client
     *
     * @var \App\Models\Client|null $client
    */
    public $client;

    /* Gateway capabilities */
    public $refundable = false;

    /* Token billing */
    public $token_billing = false;

    /* Authorise payment methods */
    public $can_authorise_credit_card = false;

    /* The initialized gateway driver class*/
    public $payment_method;

    /**
     * @var PaymentHash
     */
    public $payment_hash;

    /**
     * @var Helpers`
     */
    public $helpers;

    /* Array of payment methods */
    public static $methods = [];

    /** @var array */
    public $required_fields = [];

    public function __construct(CompanyGateway $company_gateway, ?Client $client = null, $invitation = null)
    {
        $this->company_gateway = $company_gateway;
        $this->invitation = $invitation;
        $this->client = $client;
        $this->helpers = new Helpers();
    }

    public function init()
    {
        return $this;
    }

    public function updateCustomer()
    {
        return $this;
    }

    public function getAvailableMethods(): array
    {
        return self::$methods;
    }

    /**
     * Required fields for client to fill, to proceed with gateway actions.
     *
     * @return array[]
     */
    public function getClientRequiredFields(): array
    {
        $fields = [];

        if ($this->company_gateway->require_client_name) {
            $fields[] = ['name' => 'client_name', 'label' => ctrans('texts.client_name'), 'type' => 'text', 'validation' => 'required'];
        }

        $fields[] = ['name' => 'contact_first_name', 'label' => ctrans('texts.first_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_last_name', 'label' => ctrans('texts.last_name'), 'type' => 'text', 'validation' => 'required'];
        $fields[] = ['name' => 'contact_email', 'label' => ctrans('texts.email'), 'type' => 'text', 'validation' => 'required,email:rfc'];

        if ($this->company_gateway->require_client_phone) {
            $fields[] = ['name' => 'client_phone', 'label' => ctrans('texts.client_phone'), 'type' => 'tel', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_billing_address) {
            $fields[] = ['name' => 'client_address_line_1', 'label' => ctrans('texts.address1'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_city', 'label' => ctrans('texts.city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_state', 'label' => ctrans('texts.state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_country_id', 'label' => ctrans('texts.country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_postal_code) {
            $fields[] = ['name' => 'client_postal_code', 'label' => ctrans('texts.postal_code'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_shipping_address) {
            $fields[] = ['name' => 'client_shipping_address_line_1', 'label' => ctrans('texts.shipping_address1'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_city', 'label' => ctrans('texts.shipping_city'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_state', 'label' => ctrans('texts.shipping_state'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_postal_code', 'label' => ctrans('texts.shipping_postal_code'), 'type' => 'text', 'validation' => 'required'];
            $fields[] = ['name' => 'client_shipping_country_id', 'label' => ctrans('texts.shipping_country'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value1) {
            $fields[] = ['name' => 'client_custom_value1', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client1'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value2) {
            $fields[] = ['name' => 'client_custom_value2', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client2'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value3) {
            $fields[] = ['name' => 'client_custom_value3', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client3'), 'type' => 'text', 'validation' => 'required'];
        }

        if ($this->company_gateway->require_custom_value4) {
            $fields[] = ['name' => 'client_custom_value4', 'label' => $this->helpers->makeCustomField($this->client->company->custom_fields, 'client4'), 'type' => 'text', 'validation' => 'required'];
        }

        return $fields;
    }

    /**
     * Authorize a payment method.
     *
     * Returns a reusable token for storage for future payments
     *
     * @param array $data
     * @return mixed Return a view for collecting payment method information
     */
    public function authorizeView(array $data)
    {
    }

    /**
     * The payment authorization response
     *
     * @param  Request $request
     * @return mixed Return a response for collecting payment method information
     */
    public function authorizeResponse(Request $request)
    {
    }

    /**
     * Process a payment
     *
     * @param  array $data
     * @return mixed Return a view for the payment
     */
    public function processPaymentView(array $data)
    {
    }

    /**
     * Process payment response
     *
     * @param  Request $request
     * @return mixed   Return a response for the payment
     */
    public function processPaymentResponse(Request $request)
    {
    }

    /**
     * Executes a refund attempt for a given amount with a transaction_reference.
     *
     * @param  Payment $payment                The Payment Object
     * @param  float   $amount                 The amount to be refunded
     * @param  bool $return_client_response    Whether the method needs to return a response (otherwise we assume an unattended payment)
     * @return mixed
     */
    public function refund(Payment $payment, $amount, $return_client_response = false)
    {
    }

    /**
     * Process an unattended payment.
     *
     * @param ClientGatewayToken $cgt The client gateway token object
     * @param PaymentHash $payment_hash The Payment hash containing the payment meta data
     */
    public function tokenBilling(ClientGatewayToken $cgt, PaymentHash $payment_hash)
    {
    }

    /**
     * Detaches a payment method from the gateway
     *
     * @param  ClientGatewayToken $token The gateway token
     * @return bool                      boolean response
     */
    public function detach(ClientGatewayToken $token)
    {
        return true;
    }

    /**
     * Set the inbound request payment method type for access.
     *
     * @param int $payment_method_id The Payment Method ID
     */
    public function setPaymentMethod($payment_method_id)
    {
    }

    public function setClient(Client $client)
    {
        $this->client = $client;

        return $this;
    }
    /************************** Helper methods *************************************/

    public function setPaymentHash(PaymentHash $payment_hash)
    {
        $this->payment_hash = $payment_hash;

        return $this;
    }

    /**
     * Helper method to attach invoices to a payment.
     *
     * @param Payment $payment The payment
     * @param PaymentHash $payment_hash
     * @return Payment             The payment object
     */
    public function attachInvoices(Payment $payment, PaymentHash $payment_hash): Payment
    {
        $paid_invoices = $payment_hash->invoices();
        $invoices = Invoice::query()->whereIn('id', $this->transformKeys(array_column($paid_invoices, 'invoice_id')))->withTrashed()->get();
        $payment->invoices()->sync($invoices);

        $payment->service()->applyNumber()->save();

        $invoices->each(function ($invoice) use ($payment) {
            event(new InvoiceWasPaid($invoice, $payment, $payment->company, Ninja::eventVars()));
        });

        return $payment;
    }

    /**
     * Create a payment from an online payment.
     *
     * @param  array $data     Payment data
     * @param  int   $status   The payment status_id
     * @return Payment         The payment object
     */
    public function createPayment($data, $status = Payment::STATUS_COMPLETED): Payment
    {
        if (in_array($status, [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING])) {
            $this->confirmGatewayFee($data);
        }

        /*Never create a payment with a duplicate transaction reference*/
        if (array_key_exists('transaction_reference', $data)) {
            $_payment = Payment::where('transaction_reference', $data['transaction_reference'])
                               ->where('client_id', $this->client->id)
                               ->first();

            if ($_payment) {
                return $_payment;
            }
        }

        $payment = PaymentFactory::create($this->client->company->id, $this->client->user->id);
        $payment->client_id = $this->client->id;
        $payment->company_gateway_id = $this->company_gateway->id;
        $payment->status_id = $status;
        $payment->currency_id = $this->client->getSetting('currency_id');
        $payment->date = Carbon::now()->addSeconds($this->client->company->utc_offset())->format('Y-m-d');
        $payment->gateway_type_id = $data['gateway_type_id'];

        $client_contact = $this->getContact();
        $client_contact_id = $client_contact ? $client_contact->id : $this->client->contacts()->first()->id;

        if (isset($data['idempotency_key'])) {
            $payment->idempotency_key = $data['idempotency_key'];
        }

        $payment->amount = $data['amount'];
        $payment->type_id = $data['payment_type'];
        $payment->transaction_reference = $data['transaction_reference'];
        $payment->client_contact_id = $client_contact_id;
        $payment->saveQuietly();

        /* Return early if the payment is not completed or pending*/
        if (! in_array($status, [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING])) {
            return $payment;
        }

        $this->payment_hash->payment_id = $payment->id;
        $this->payment_hash->save();

        $this->attachInvoices($payment, $this->payment_hash);

        if ($this->payment_hash->credits_total() > 0) {
            $payment = $payment->service()->applyCredits($this->payment_hash)->save();
        }

        $payment->service()->updateInvoicePayment($this->payment_hash);

        event('eloquent.created: App\Models\Payment', $payment);

        if ($this->client->getSetting('client_online_payment_notification') && in_array($status, [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING])) {
            $payment->service()->sendEmail();
        }

        //todo
        //catch any payment failures here also and fire a subsequent failure email if necessary? note only need for delayed payment forms
        //perhaps this type of functionality should be handled higher up to provide better context?

        event(new PaymentWasCreated($payment, $payment->company, Ninja::eventVars()));

        if (property_exists($this->payment_hash->data, 'billing_context') && $status == Payment::STATUS_COMPLETED) {
            if (is_int($this->payment_hash->data->billing_context->subscription_id)) {
                $billing_subscription = \App\Models\Subscription::find($this->payment_hash->data->billing_context->subscription_id);
            } else {
                $billing_subscription = \App\Models\Subscription::find($this->decodePrimaryKey($this->payment_hash->data->billing_context->subscription_id));
            }

            // To access campaign hash => $this->payment_hash->data->billing_context->campaign;
            // To access campaign data => Cache::get(CAMPAIGN_HASH)
            // To access utm data => session()->get('utm-' . CAMPAIGN_HASH);

            /** @var \App\Models\Subscription $billing_subscription */
            (new SubscriptionService($billing_subscription))->completePurchase($this->payment_hash);
        }

        return $payment->service()->applyNumber()->save();
    }

    /**
     * When a successful payment is made, we need to append the gateway fee
     * to an invoice.
     *
     * @return void                            Success/Failure
     */
    public function confirmGatewayFee($data = []): void
    {
        nlog("confirming gateway fee");

        /*Fee charged at gateway*/
        $fee_total = $this->payment_hash->fee_total;

        if (!$fee_total || $fee_total == 0) {
            return;
        }

        $invoice = $this->payment_hash->fee_invoice;

        $fee_count = collect($invoice->line_items)
                        ->map(function ($item) {
                            $item->gross_line_total = round($item->gross_line_total, 2);
                            return $item;
                        })
                        ->whereIn('type_id', ['3','4'])
                        ->count();

        if ($invoice && $fee_count == 0) {

            nlog("apparently no fee, so injecting here!");

            if (!$invoice->uses_inclusive_taxes) { //must account for taxes! ? line item taxes also
                $fee_total = round($fee_total / (1 + (($invoice->tax_rate1 + $invoice->tax_rate2 + $invoice->tax_rate3) / 100)), 2);
            }

            $balance = $invoice->balance;

            App::forgetInstance('translator');
            $t = app('translator');
            $t->replace(Ninja::transformTranslations($invoice->company->settings));
            App::setLocale($invoice->client->locale());

            $invoice_item = new InvoiceItem();
            $invoice_item->type_id = '4';
            $invoice_item->product_key = ctrans('texts.surcharge');
            $invoice_item->notes = ctrans('texts.online_payment_surcharge');
            $invoice_item->quantity = 1;
            $invoice_item->cost = (float)$fee_total;

            if ($invoice->discount > 0 && !$invoice->is_amount_discount) {
                $invoice_item->discount = -1 * $invoice->discount;
                $invoice_item->is_amount_discount = false;
            }

            $invoice_items = $invoice->line_items;
            $invoice_items[] = $invoice_item;

            if (isset($data['gateway_type_id']) && $fees_and_limits = $this->company_gateway->getFeesAndLimits($data['gateway_type_id'])) {
                $invoice_item->tax_rate1 = $fees_and_limits->fee_tax_rate1;
                $invoice_item->tax_name1 = $fees_and_limits->fee_tax_name1;
                $invoice_item->tax_rate2 = $fees_and_limits->fee_tax_rate2;
                $invoice_item->tax_name2 = $fees_and_limits->fee_tax_name2;
                $invoice_item->tax_rate3 = $fees_and_limits->fee_tax_rate3;
                $invoice_item->tax_name3 = $fees_and_limits->fee_tax_name3;
                $invoice_item->tax_id = (string)\App\Models\Product::PRODUCT_TYPE_OVERRIDE_TAX;
            }

            $invoice->line_items = array_values($invoice_items);

            /**Refresh Invoice values*/
            $invoice = $invoice->calc()->getInvoice();

            $new_balance = $invoice->balance;

            if (floatval($new_balance) - floatval($balance) != 0) {
                $adjustment = $new_balance - $balance;
                $invoice->client->service()->calculateBalance();
            }

        } else {

            $invoice->service()->toggleFeesPaid()->save();

        }

    }

    /**
     * In case of a payment failure we should always
     * return the invoice to its original state
     *
     * @param  PaymentHash $payment_hash The payment hash containing the list of invoices
     * @return void
     */
    public function unWindGatewayFees(PaymentHash $payment_hash)
    {
        if ($payment_hash->fee_invoice) {
            $payment_hash->fee_invoice->service()->removeUnpaidGatewayFees();
        }
    }

    /**
     * Return the contact if possible.
     *
     */
    public function getContact()
    {
        if ($this->invitation) {
            return ClientContact::withTrashed()->find($this->invitation->client_contact_id);
        } elseif (auth()->guard('contact')->user()) {
            return auth()->guard('contact')->user();
        } else {
            return false;
        }
    }

    /**
     * Store payment method as company gateway token.
     *
     * @param array $data
     * @return null|ClientGatewayToken
     */
    public function storeGatewayToken(array $data, array $additional = []): ?ClientGatewayToken
    {
        $cgt = new ClientGatewayToken();
        $cgt->company_id = $this->client->company->id;
        $cgt->client_id = $this->client->id;
        $cgt->token = $data['token'];
        $cgt->company_gateway_id = $this->company_gateway->id;
        $cgt->gateway_type_id = $data['payment_method_id'];
        $cgt->meta = $data['payment_meta'];

        foreach ($additional as $key => $value) {
            $cgt->{$key} = $value;
        }

        $cgt->save();

        if ($this->client->gateway_tokens->count() > 1) {
            $this->client->gateway_tokens()->update(['is_default' => 0]);
        }

        $cgt->is_default = 1;
        $cgt->save();

        return $cgt;
    }

    public function processInternallyFailedPayment($gateway, $e)
    {
        if (! is_null($this->payment_hash)) {
            $this->unWindGatewayFees($this->payment_hash);
        }

        $error = $e->getMessage();

        if (! $this->payment_hash) {
            throw new PaymentFailed($error, $e->getCode());
        }

        $amount = array_sum(array_column($this->payment_hash->invoices(), 'amount')) + $this->payment_hash->fee_total;

        $this->sendFailureMail($error);

        SystemLogger::dispatch(
            $gateway->payment_hash,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_ERROR,
            $gateway::SYSTEM_LOG_TYPE,
            $gateway->client,
            $gateway->client->company,
        );

        throw new PaymentFailed($error, $e->getCode());
    }

    public function sendFailureMail($error)
    {
        if (is_object($error)) {
            $error = 'Payment Aborted';
        }

        if (! is_null($this->payment_hash)) { //@phpstan-ignore-line
            $this->unWindGatewayFees($this->payment_hash);
        }

        if (! $error) {
            $error = '';
        }

        PaymentFailedMailer::dispatch(
            $this->payment_hash,
            $this->client->company,
            $this->client,
            $error
        );
    }

    public function clientPaymentFailureMailer($error)
    {
        if ($this->payment_hash && is_array($this->payment_hash->invoices())) {
            $nmo = new NinjaMailerObject();
            $nmo->mailable = new NinjaMailer((new ClientPaymentFailureObject($this->client, $error, $this->client->company, $this->payment_hash))->build());
            $nmo->company = $this->client->company;
            $nmo->settings = $this->client->company->settings;

            $invoices = Invoice::query()->whereIn('id', $this->transformKeys(array_column($this->payment_hash->invoices(), 'invoice_id')))->withTrashed()->get();

            $invoices->first()->invitations->each(function ($invitation) use ($nmo) {
                if ((bool) $invitation->contact->send_email !== false && $invitation->contact->email) {
                    $nmo->to_user = $invitation->contact;
                    NinjaMailerJob::dispatch($nmo);
                }
            });
        }
    }

    /**
     * Wrapper method for checking if resource is good.
     *
     * @param mixed $resource
     * @return bool
     */
    public function checkRequiredResource($resource): bool
    {
        if (is_null($resource) || empty($resource)) {
            return true;
        }

        return false;
    }

    /*Generic Global unsuccessful transaction method when the client is present*/
    public function processUnsuccessfulTransaction($response, $client_present = true)
    {
        $error = array_key_exists('error', $response) ? $response['error'] : 'Undefined Error';
        $error_code = array_key_exists('error_code', $response) ? $response['error_code'] : 'Undefined Error Code';

        if ($this->payment_hash) {
            $this->unWindGatewayFees($this->payment_hash);
        }

        $this->sendFailureMail($error);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new NinjaMailer((new ClientPaymentFailureObject($this->client, $error, $this->client->company, $this->payment_hash))->build());
        $nmo->company = $this->client->company;
        $nmo->settings = $this->client->company->settings;

        if ($this->payment_hash) {
            $invoices = Invoice::query()->whereIn('id', $this->transformKeys(array_column($this->payment_hash->invoices(), 'invoice_id')))->withTrashed()->get();

            $invoices->first()->invitations->each(function ($invitation) use ($nmo) {
                if (! $invitation->contact->trashed()) {
                    $nmo->to_user = $invitation->contact;
                    NinjaMailerJob::dispatch($nmo);
                }
            });
        }

        $message = [
            'server_response' => $response,
            'data' => $this->payment_hash?->data,
        ];

        SystemLogger::dispatch(
            $message,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            SystemLog::TYPE_PAYTRACE,
            $this->client,
            $this->client->company,
        );

        if ($client_present) {
            throw new PaymentFailed($error, 500);
        }
    }

    public function livewirePaymentView(array $data): string
    {
        return $this->payment_method->livewirePaymentView($data);
    }

    public function processPaymentViewData(array $data): array
    {
        return $this->payment_method->paymentData($data);
    }

    public function checkRequirements()
    {
        if ($this->company_gateway->require_billing_address) {
            if ($this->checkRequiredResource($this->client->address1)) {
                $this->required_fields[] = 'billing_address1';
            }

            if ($this->checkRequiredResource($this->client->address2)) {
                $this->required_fields[] = 'billing_address2';
            }

            if ($this->checkRequiredResource($this->client->city)) {
                $this->required_fields[] = 'billing_city';
            }

            if ($this->checkRequiredResource($this->client->state)) {
                $this->required_fields[] = 'billing_state';
            }

            if ($this->checkRequiredResource($this->client->postal_code)) {
                $this->required_fields[] = 'billing_postal_code';
            }

            if ($this->checkRequiredResource($this->client->country_id)) {
                $this->required_fields[] = 'billing_country';
            }
        }

        if ($this->company_gateway->require_shipping_address) {
            if ($this->checkRequiredResource($this->client->shipping_address1)) {
                $this->required_fields[] = 'shipping_address1';
            }

            if ($this->checkRequiredResource($this->client->shipping_address2)) {
                $this->required_fields[] = 'shipping_address2';
            }

            if ($this->checkRequiredResource($this->client->shipping_city)) {
                $this->required_fields[] = 'shipping_city';
            }

            if ($this->checkRequiredResource($this->client->shipping_state)) {
                $this->required_fields[] = 'shipping_state';
            }

            if ($this->checkRequiredResource($this->client->shipping_postal_code)) {
                $this->required_fields[] = 'shipping_postal_code';
            }

            if ($this->checkRequiredResource($this->client->shipping_country_id)) {
                $this->required_fields[] = 'shipping_country';
            }
        }

        if ($this->company_gateway->require_client_name) {
            if ($this->checkRequiredResource($this->client->name)) {
                $this->required_fields[] = 'name';
            }
        }

        if ($this->company_gateway->require_client_phone) {
            if ($this->checkRequiredResource($this->client->phone)) {
                $this->required_fields[] = 'phone';
            }
        }


        if ($this->company_gateway->require_postal_code) {
            // In case "require_postal_code" is true, we don't need billing address.

            foreach ($this->required_fields as $position => $field) {
                if (Str::startsWith($field, 'billing')) {
                    unset($this->required_fields[$position]);
                }
            }

            if ($this->checkRequiredResource($this->client->postal_code)) {
                $this->required_fields[] = 'postal_code';
            }
        }

        return $this;
    }

    public function getCompanyGatewayId(): int
    {
        return $this->company_gateway->id;
    }

    public function logSuccessfulGatewayResponse($response, $gateway_const)
    {
        SystemLogger::dispatch(
            $response,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_SUCCESS,
            $gateway_const,
            $this->client,
            $this->client->company,
        );
    }

    public function logUnsuccessfulGatewayResponse($response, $gateway_const)
    {
        SystemLogger::dispatch(
            $response,
            SystemLog::CATEGORY_GATEWAY_RESPONSE,
            SystemLog::EVENT_GATEWAY_FAILURE,
            $gateway_const,
            $this->client,
            $this->client->company,
        );
    }

    public function genericWebhookUrl()
    {
        return route('payment_notification_webhook', [
            'company_key' => $this->client->company->company_key,
            'company_gateway_id' => $this->encodePrimaryKey($this->company_gateway->id),
            'client' => $this->encodePrimaryKey($this->client->id),
        ]);
    }

    /* Performs an extra iterate on the gatewayTypes() array and passes back only the enabled gateways*/
    public function gatewayTypeEnabled($type)
    {
        $types = [];

        $types[] = GatewayType::CREDIT_CARD;
        $types[] = GatewayType::BANK_TRANSFER;

        return $types;
    }

    public function getStatementDescriptor(): string
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->client->getMergedSettings()));
        App::setLocale($this->client->company->locale());

        if (! $this->payment_hash || !$this->client) {
            return 'Descriptor';
        }

        $invoices_string = \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray()) ?: null;

        if (!$invoices_string) {
            return str_replace(["*","<",">","'",'"'], "", $this->client->company->present()->name());
        }

        $invoices_string = str_replace(["*","<",">","'",'"'], "-", $invoices_string);

        // 2023-11-02 - improve the statement descriptor for string

        $company_name = $this->client->company->present()->name();
        $company_name = str_replace(["*","<",">","'",'"'], "-", $company_name);

        if (ctype_digit(substr($company_name, 0, 1))) {
            $company_name = "I" . $company_name;
        }

        $company_name = substr($company_name, 0, 11);
        $descriptor = "{$company_name} {$invoices_string}";
        $descriptor = substr($descriptor, 0, 22);
        return $descriptor;

    }
    /**
     * Generic description handler
     */
    public function getDescription(bool $abbreviated = true)
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->client->getMergedSettings()));
        App::setLocale($this->client->company->locale());

        if (! $this->payment_hash || !$this->client) {
            return 'No description';
        }

        $invoices_string = \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray()) ?: null;
        $amount = Number::formatMoney($this->payment_hash?->amount_with_fee() ?? 0, $this->client); // @phpstan-ignore-line

        if ($abbreviated && $invoices_string) {
            return $invoices_string;
        } elseif ($abbreviated || ! $invoices_string) {
            return ctrans('texts.gateway_payment_text_no_invoice', [
                'amount' => $amount,
                'client' => $this->client->present()->name(),
            ]);
        }

        return ctrans('texts.gateway_payment_text', [
            'invoices' => $invoices_string,
            'amount' => $amount,
            'client' => $this->client->present()->name(),
        ]);

        // return sprintf('%s: %s', ctrans('texts.invoices'), \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray()));
    }

    /**
     * Stub for disconnecting from the gateway.
     *
     * @return bool
     */
    public function disconnect()
    {
        return true;
    }

    /**
     * Stub for checking authentication.
     *
     * @return bool
     */
    public function auth(): bool
    {
        return true;
    }

    public function importCustomers()
    {

    }
}

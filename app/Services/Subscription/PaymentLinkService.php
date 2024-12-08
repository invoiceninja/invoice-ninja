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

namespace App\Services\Subscription;

use App\Models\Client;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\SystemLog;
use App\Models\PaymentHash;
use App\Models\Subscription;
use App\Models\ClientContact;
use GuzzleHttp\RequestOptions;
use App\Jobs\Util\SystemLogger;
use App\Utils\Traits\MakesHash;
use App\Models\RecurringInvoice;
use GuzzleHttp\Exception\ClientException;
use App\Services\Subscription\UpgradePrice;
use App\Services\Subscription\ZeroCostProduct;
use App\Repositories\RecurringInvoiceRepository;
use App\Services\Subscription\ChangePlanInvoice;

class PaymentLinkService
{
    use MakesHash;

    public const WHITE_LABEL = 4316;

    public function __construct(public Subscription $subscription)
    {
    }

    /**
     * CompletePurchase
     *
     * Perform the initial purchase of a one time
     * or recurring product
     *
     * @param  PaymentHash $payment_hash
     * @return  \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|null
     */
    public function completePurchase(PaymentHash $payment_hash): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse|null
    {

        if (!property_exists($payment_hash->data, 'billing_context')) {
            throw new \Exception("Illegal entrypoint into method, payload must contain billing context");
        }

        if ($payment_hash->data->billing_context->context == 'change_plan') {
            return $this->handlePlanChange($payment_hash);
        }

        // if ($payment_hash->data->billing_context->context == 'whitelabel') {
        //     return $this->handleWhiteLabelPurchase($payment_hash);
        // }

        if (strlen($this->subscription->recurring_product_ids) >= 1) {

            $bundle = isset($payment_hash->data->billing_context->bundle) ? $payment_hash->data->billing_context->bundle : [];
            $recurring_invoice = (new InvoiceToRecurring($payment_hash->payment->client_id, $this->subscription, $bundle))->run();

            $recurring_invoice_repo = new RecurringInvoiceRepository();

            $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
            $recurring_invoice->auto_bill = $this->subscription->auto_bill;

            /* Start the recurring service */
            $recurring_invoice->service()
                            ->start()
                            ->save();

            //update the invoice and attach to the recurring invoice!!!!!
            $invoice = Invoice::withTrashed()->find($payment_hash->fee_invoice_id);
            $invoice->recurring_id = $recurring_invoice->id;
            $invoice->is_proforma = false;
            $invoice->save();

            //execute any webhooks
            $context = [
                'context' => 'recurring_purchase',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
                'contact' => auth()->guard('contact')->user() ? auth()->guard('contact')->user()->hashed_id : $recurring_invoice->client->contacts()->whereNotNull('email')->first()->hashed_id,
                'account_key' => $recurring_invoice->client->custom_value2,
            ];

            if (property_exists($payment_hash->data->billing_context, 'campaign')) {
                $context['campaign'] = $payment_hash->data->billing_context->campaign;
            }

            $response = $this->triggerWebhook($context);

            return $this->handleRedirect('/client/recurring_invoices/' . $recurring_invoice->hashed_id);
        } else {
            $invoice = Invoice::withTrashed()->find($payment_hash->fee_invoice_id);

            $context = [
                'context' => 'single_purchase',
                'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
                'client'  => $invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
                'account_key' => $invoice->client->custom_value2,
            ];

            //execute any webhooks
            $this->triggerWebhook($context);

            /* 06-04-2022 */
            /* We may not be in a state where the user is present */
            if (auth()->guard('contact')->user()) {
                return $this->handleRedirect('/client/invoices/' . $this->encodePrimaryKey($payment_hash->fee_invoice_id));
            }
        }

        return null;

    }

    /**
     * isEligible
     * ["message" => "Success", "status_code" => 200];
     * @param  ClientContact $contact
     * @return array{"message": string, "status_code": int}
     */
    public function isEligible(ClientContact $contact): array
    {

        $context = [
                    'context' => 'is_eligible',
                    'subscription' => $this->subscription->hashed_id,
                    'contact' => $contact->hashed_id,
                    'contact_email' => $contact->email,
                    'client' => $contact->client->hashed_id,
                    'account_key' => $contact->client->custom_value2,
                ];

        $response = $this->triggerWebhook($context);

        return $response;

    }

    /* Starts the process to create a trial
        - we create a recurring invoice, which has its next_send_date as now() + trial_duration
        - we then hit the client API end point to advise the trial payload
        - we then return the user to either a predefined user endpoint, OR we return the user to the recurring invoice page.

     * startTrial
     *
     * @param  array $data{contact_id: int, client_id: int, bundle: \Illuminate\Support\Collection, coupon?: string, }
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function startTrial(array $data): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {

        // Redirects from here work just fine. Livewire will respect it.
        $client_contact = ClientContact::find($this->decodePrimaryKey($data['contact_id']));

        if (is_string($data['client_id'])) {
            $data['client_id'] = $this->decodePrimaryKey($data['client_id']);
        }

        if (!$this->subscription->trial_enabled) {
            throw new \Exception("Trials are disabled for this product");
        }

        //create recurring invoice with start date = trial_duration + 1 day
        $recurring_invoice_repo = new RecurringInvoiceRepository();

        $bundle = [];

        if (isset($data['bundle'])) {

            $bundle = $data['bundle']->map(function ($bundle) {
                return (object) $bundle;
            })->toArray();
        }

        $recurring_invoice = (new InvoiceToRecurring($client_contact->client_id, $this->subscription, $bundle))->run();

        $recurring_invoice->next_send_date = now()->addSeconds($this->subscription->trial_duration);
        $recurring_invoice->next_send_date_client = now()->addSeconds($this->subscription->trial_duration);
        $recurring_invoice->backup = 'is_trial';

        if (array_key_exists('coupon', $data) && ($data['coupon'] == $this->subscription->promo_code) && $this->subscription->promo_discount > 0) {
            $recurring_invoice->discount = $this->subscription->promo_discount;
            $recurring_invoice->is_amount_discount = $this->subscription->is_amount_discount;
        } elseif (strlen($this->subscription->promo_code ?? '') == 0 && $this->subscription->promo_discount > 0) {
            $recurring_invoice->discount = $this->subscription->promo_discount;
            $recurring_invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        $recurring_invoice = $recurring_invoice_repo->save($data, $recurring_invoice);

        /* Start the recurring service */
        $recurring_invoice->service()
                        ->start()
                        ->save();

        $context = [
            'context' => 'trial',
            'recurring_invoice' => $recurring_invoice->hashed_id,
            'client' => $recurring_invoice->client->hashed_id,
            'subscription' => $this->subscription->hashed_id,
            'account_key' => $recurring_invoice->client->custom_value2,
        ];

        //execute any webhooks
        $response = $this->triggerWebhook($context);

        return $this->handleRedirect('/client/recurring_invoices/' . $recurring_invoice->hashed_id);

    }

    /**
     * calculateUpdatePriceV2
     *
     * Need to change the naming of the method
     *
     * @param  RecurringInvoice $recurring_invoice - The Current Recurring Invoice for the subscription.
     * @param  Subscription $target - The new target subscription to move to
     * @return float - the upgrade price
     */
    public function calculateUpgradePriceV2(RecurringInvoice $recurring_invoice, Subscription $target): ?float
    {
        return (new UpgradePrice($recurring_invoice, $target))->run()->upgrade_price;
    }

    /**
     * When changing plans, we need to generate a pro rata invoice
     *
     * @param  array $data{recurring_invoice: RecurringInvoice, subscription: Subscription, target: Subscription, hash: string}
     * @return Invoice | Credit
     */
    public function createChangePlanInvoice($data): Invoice | Credit
    {
        $recurring_invoice = $data['recurring_invoice'];
        $old_subscription = $data['subscription'];
        $target_subscription = $data['target'];
        $hash = $data['hash'];

        return (new ChangePlanInvoice($recurring_invoice, $target_subscription, $hash))->run();
    }


    /**
    * 'email' => $this->email ?? $this->contact->email,
    * 'quantity' => $this->quantity,
    * 'contact_id' => $this->contact->id,
    *
    * @param array $data
    * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    */
    public function handleNoPaymentRequired(array $data): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        $context = (new ZeroCostProduct($this->subscription, $data))->run();

        // Forward payload to webhook
        if (array_key_exists('context', $context)) {
            $response = $this->triggerWebhook($context);
        }

        // Hit the redirect
        return $this->handleRedirect($context['redirect_url']);
    }

    /**
     * @param Invoice $invoice
     * @return true
     */
    public function planPaid(Invoice $invoice)
    {
        $recurring_invoice_hashed_id = $invoice->recurring_invoice()->exists() ? $invoice->recurring_invoice->hashed_id : null;

        $context = [
            'context' => 'plan_paid',
            'subscription' => $this->subscription->hashed_id,
            'recurring_invoice' => $recurring_invoice_hashed_id,
            'client' => $invoice->client->hashed_id,
            'contact' => $invoice->client->primary_contact()->first() ? $invoice->client->primary_contact()->first()->hashed_id : $invoice->client->contacts->first()->hashed_id,
            'invoice' => $invoice->hashed_id,
            'account_key' => $invoice->client->custom_value2,
        ];

        $response = $this->triggerWebhook($context);

        nlog($response);

        return true;
    }


    /**
     * Response from payment service on
     * return from a plan change
     *
     * @param  PaymentHash $payment_hash
     */
    private function handlePlanChange(PaymentHash $payment_hash): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        nlog("handle plan change");

        $old_recurring_invoice = RecurringInvoice::query()->find($this->decodePrimaryKey($payment_hash->data->billing_context->recurring_invoice));

        if (!$old_recurring_invoice) {
            return $this->handleRedirect('/client/recurring_invoices/');
        }

        $old_recurring_invoice->service()->stop()->save();

        $recurring_invoice = (new InvoiceToRecurring($old_recurring_invoice->client_id, $this->subscription, []))->run();

        $recurring_invoice->service()
                        ->start()
                        ->save();

        //update the invoice and attach to the recurring invoice!!!!!
        $invoice = Invoice::query()->find($payment_hash->fee_invoice_id);
        $invoice->recurring_id = $recurring_invoice->id;
        $invoice->is_proforma = false;
        $invoice->save();

        // 29-06-2023 handle webhooks for payment intent - user may not be present.
        $context = [
            'context' => 'change_plan',
            'recurring_invoice' => $recurring_invoice->hashed_id,
            'invoice' => $this->encodePrimaryKey($payment_hash->fee_invoice_id),
            'client' => $recurring_invoice->client->hashed_id,
            'subscription' => $this->subscription->hashed_id,
            'contact' => auth()->guard('contact')->user()?->hashed_id ?? $recurring_invoice->client->contacts()->first()->hashed_id,
            'account_key' => $recurring_invoice->client->custom_value2,
        ];

        $response = $this->triggerWebhook($context);

        nlog($response);

        return $this->handleRedirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);
    }











    /**
     * Handles redirecting the user
     */
    private function handleRedirect($default_redirect): \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
    {
        if (array_key_exists('return_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['return_url']) >= 1) {
            return method_exists(redirect(), "send") ? redirect($this->subscription->webhook_configuration['return_url'])->send() : redirect($this->subscription->webhook_configuration['return_url']);
        }

        return method_exists(redirect(), "send") ? redirect($default_redirect)->send() : redirect($default_redirect);
    }

    /**
     * Hit a 3rd party API if defined in the subscription
     *
     * @param  array $context
     * @return array
     */
    public function triggerWebhook($context): array
    {
        if (empty($this->subscription->webhook_configuration['post_purchase_url']) || is_null($this->subscription->webhook_configuration['post_purchase_url']) || strlen($this->subscription->webhook_configuration['post_purchase_url']) < 1) {
            return ["message" => "Success", "status_code" => 200];
        }

        $response = false;

        $body = array_merge($context, [
            'db' => $this->subscription->company->db,
        ]);

        $response = $this->sendLoad($this->subscription, $body);

        /* Append the response to the system logger body */
        if (is_array($response)) {
            $body = $response;
        } else {
            $body = $response->getStatusCode();
        }

        $client = Client::query()->where('id', $this->decodePrimaryKey($body['client']))->withTrashed()->first();

        SystemLogger::dispatch(
            $body,
            SystemLog::CATEGORY_WEBHOOK,
            SystemLog::EVENT_WEBHOOK_RESPONSE,
            SystemLog::TYPE_WEBHOOK_RESPONSE,
            $client,
            $client->company,
        );

        nlog("ready to fire back");

        if (is_array($body)) {
            return $response;
        } else {
            return ['message' => 'There was a problem encountered with the webhook', 'status_code' => 500];
        }
    }

    public function sendLoad($subscription, $body)
    {
        $headers = [
            'Content-Type' => 'application/json',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if (!isset($subscription->webhook_configuration['post_purchase_url']) && !isset($subscription->webhook_configuration['post_purchase_rest_method'])) {
            return [];
        }

        if (count($subscription->webhook_configuration['post_purchase_headers']) >= 1) {
            $headers = array_merge($headers, $subscription->webhook_configuration['post_purchase_headers']);
        }

        $client = new \GuzzleHttp\Client(
            [
                'headers' => $headers,
            ]
        );

        $post_purchase_rest_method = (string) $subscription->webhook_configuration['post_purchase_rest_method'];
        $post_purchase_url = (string) $subscription->webhook_configuration['post_purchase_url'];

        try {
            $response = $client->{$post_purchase_rest_method}($post_purchase_url, [
                RequestOptions::JSON => ['body' => $body], RequestOptions::ALLOW_REDIRECTS => false,
            ]);

            return array_merge($body, json_decode($response->getBody(), true));
        } catch (ClientException $e) {
            $message = $e->getMessage();

            $error = json_decode($e->getResponse()->getBody()->getContents());

            if (is_null($error)) {
                nlog("empty response");
                nlog($e->getMessage());
            }

            if ($error && property_exists($error, 'message')) {
                $message = $error->message;
            }

            return array_merge($body, ['message' => $message, 'status_code' => 500]);
        } catch (\Exception $e) {
            return array_merge($body, ['message' => $e->getMessage(), 'status_code' => 500]);
        }
    }
}

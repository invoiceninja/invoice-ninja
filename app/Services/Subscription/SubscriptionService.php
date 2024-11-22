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

use App\DataMapper\InvoiceItem;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Jobs\Mail\NinjaMailer;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\SystemLogger;
use App\Libraries\MultiDB;
use App\Mail\RecurringInvoice\ClientContactRequestCancellationObject;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Credit;
use App\Models\Invoice;
use App\Models\License;
use App\Models\PaymentHash;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Repositories\CreditRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\SubscriptionRepository;
use App\Services\Email\Email;
use App\Services\Email\EmailObject;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Notifications\UserNotifies;
use App\Utils\Traits\SubscriptionHooker;
use Carbon\Carbon;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Str;
use Illuminate\Mail\Mailables\Address;

class SubscriptionService
{
    use MakesHash;
    use CleanLineItems;
    use SubscriptionHooker;
    use UserNotifies;

    /** @var subscription */
    private $subscription;

    public const WHITE_LABEL = 4316;

    private array $peppol_keys = [
        'peppol_500',
        'peppol_1000',
        'selfhost_peppol_500',
        'selfhost_peppol_1000',
    ];

    private float $credit_payments = 0;

    public function __construct(Subscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /*
        Performs the initial purchase of a
        one time or recurring product
    */
    public function completePurchase(PaymentHash $payment_hash)
    {
        if (!property_exists($payment_hash->data, 'billing_context')) {
            throw new \Exception("Illegal entrypoint into method, payload must contain billing context");
        }

        if ($payment_hash->data->billing_context->context == 'change_plan') {
            return $this->handlePlanChange($payment_hash);
        }

        if ($payment_hash->data->billing_context->context == 'whitelabel') {
            return $this->handleWhiteLabelPurchase($payment_hash);
        }

        if (in_array($payment_hash->data->billing_context->context, $this->peppol_keys)) {
            return $this->handlePeppolPurchase($payment_hash);
        }

        // if we have a recurring product - then generate a recurring invoice
        if (strlen($this->subscription->recurring_product_ids ?? '') >= 1) {
            if (isset($payment_hash->data->billing_context->bundle)) {
                $recurring_invoice = $this->convertInvoiceToRecurringBundle($payment_hash->payment->client_id, $payment_hash->data->billing_context->bundle);
            } else {
                $recurring_invoice = $this->convertInvoiceToRecurring($payment_hash->payment->client_id);
            }

            $recurring_invoice_repo = new RecurringInvoiceRepository();

            $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
            $recurring_invoice->auto_bill = $this->subscription->auto_bill;
            $recurring_invoice->auto_bill_enabled = ($this->subscription->auto_bill == 'always' || $this->subscription->auto_bill == 'optout') ? true : false;
            
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

            return $this->handleRedirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);
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
                return $this->handleRedirect('/client/invoices/'.$this->encodePrimaryKey($payment_hash->fee_invoice_id));
            }
        }
    }

    /* Hits the client endpoint to determine whether the user is able to access this subscription */
    public function isEligible($contact)
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

    private function handlePeppolPurchase(PaymentHash $payment_hash)
    {

        if (class_exists(\Modules\Admin\Jobs\Account\PeppolPurchase::class)) {
            (new \Modules\Admin\Jobs\Account\PeppolPurchase($payment_hash))->run();
        }

    }

    private function handleWhiteLabelPurchase(PaymentHash $payment_hash): bool
    {
        //send license to the user.
        $invoice = $payment_hash->fee_invoice;
        $license_key = "v5_".Str::uuid()->toString();
        $invoice->footer = ctrans('texts.white_label_body', ['license_key' => $license_key]);

        $recurring_invoice = $this->convertInvoiceToRecurring($payment_hash->payment->client_id);

        $recurring_invoice_repo = new RecurringInvoiceRepository();
        $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
        $recurring_invoice->auto_bill = $this->subscription->auto_bill;

        /* Start the recurring service */
        $recurring_invoice->service()
                            ->start()
                            ->save();

        //update the invoice and attach to the recurring invoice!!!!!
        $invoice->recurring_id = $recurring_invoice->id;
        $invoice->is_proforma = false;
        $invoice->save();

        $contact = $invoice->client->contacts()->whereNotNull('email')->first();

        $license = new License();
        $license->license_key = $license_key;
        $license->email = $contact ? $contact->email : ' ';
        $license->first_name = $contact ? $contact->first_name : ' ';
        $license->last_name = $contact ? $contact->last_name : ' ';
        $license->is_claimed = 1;
        $license->transaction_reference = $payment_hash?->payment?->transaction_reference ?: ' '; //@phpstan-ignore-line
        $license->product_id = self::WHITE_LABEL;
        $license->recurring_invoice_id = $recurring_invoice->id;

        $license->save();

        $invitation = $invoice->invitations()->first();

        $email_object = new EmailObject();
        $email_object->to = [new Address($contact->email, $contact->present()->name())];
        $email_object->subject = ctrans('texts.white_label_link') . " " .ctrans('texts.payment_subject');
        $email_object->body = ctrans('texts.white_label_body', ['license_key' => $license_key]);
        $email_object->client_id = $invoice->client_id;
        $email_object->client_contact_id = $contact->id;
        $email_object->invitation_key = $invitation->key;
        $email_object->invitation_id = $invitation->id;
        $email_object->entity_id = $invoice->id;
        $email_object->entity_class = Invoice::class;
        $email_object->user_id = $invoice->user_id;

        Email::dispatch($email_object, $invoice->company);

        return true;
    }

    /* Starts the process to create a trial
        - we create a recurring invoice, which is has its next_send_date as now() + trial_duration
        - we then hit the client API end point to advise the trial payload
        - we then return the user to either a predefined user endpoint, OR we return the user to the recurring invoice page.
    */
    public function startTrial(array $data)
    {
        // Redirects from here work just fine. Livewire will respect it.
        $client_contact = ClientContact::find($this->decodePrimaryKey($data['contact_id']));

        if (is_string($data['client_id'])) {
            $data['client_id'] = $this->decodePrimaryKey($data['client_id']);
        }

        if (!$this->subscription->trial_enabled) {
            return new \Exception("Trials are disabled for this product");
        }

        //create recurring invoice with start date = trial_duration + 1 day
        $recurring_invoice_repo = new RecurringInvoiceRepository();

        if (isset($data['bundle'])) {
            $recurring_invoice = $this->convertInvoiceToRecurringBundle($client_contact->client_id, $data['bundle']->map(function ($bundle) {
                return (object) $bundle;
            }));
        } else {
            $recurring_invoice = $this->convertInvoiceToRecurring($client_contact->client_id);
        }

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

        return $this->handleRedirect('/client/recurring_invoices/'.$recurring_invoice->hashed_id);
    }

    /**
     * Returns an upgrade price when moving between plans
     *
     * However we only allow people to  move between plans
     * if their account is in good standing.
     *
     * @param  RecurringInvoice $recurring_invoice
     * @param  Subscription     $target
     *
     * @return float
     */
    public function calculateUpgradePriceV2(RecurringInvoice $recurring_invoice, Subscription $target): ?float
    {
        $outstanding_credit = 0;

        $use_credit_setting = $recurring_invoice->client->getSetting('use_credits_payment');

        $last_invoice = Invoice::query()
                                ->where('recurring_id', $recurring_invoice->id)
                                ->where('is_deleted', 0)
                                ->where('status_id', Invoice::STATUS_PAID)
                                ->first();

        $refund = $this->calculateProRataRefundForSubscription($last_invoice);

        if ($use_credit_setting != 'off') {
            $outstanding_credit = Credit::query()
                                           ->where('client_id', $recurring_invoice->client_id)
                                           ->whereIn('status_id', [Credit::STATUS_SENT,Credit::STATUS_PARTIAL])
                                           ->where('is_deleted', 0)
                                           ->where('balance', '>', 0)
                                           ->sum('balance');
        }

        nlog("{$target->price} - {$refund} - {$outstanding_credit}");

        return $target->price - $refund - $outstanding_credit;
    }

    /**
     * Returns an upgrade price when moving between plans
     *
     * However we only allow people to  move between plans
     * if their account is in good standing.
     *
     * @param  RecurringInvoice $recurring_invoice
     * @param  Subscription     $target
     * @deprecated in favour of calculateUpgradePriceV2
     * @return float
     */
    public function calculateUpgradePrice(RecurringInvoice $recurring_invoice, Subscription $target): ?float
    {
        //calculate based on daily prices
        $current_amount = $recurring_invoice->amount;
        $currency_frequency = $recurring_invoice->frequency_id;

        $outstanding = Invoice::query()
                                ->where('recurring_id', $recurring_invoice->id)
                                ->where('is_deleted', 0)
                                ->where('is_proforma', 0)
                                ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
                                ->where('balance', '>', 0);

        $outstanding_amounts = $outstanding->sum('balance');

        $outstanding_invoice = Invoice::query()->where('client_id', $recurring_invoice->client_id)
                                         ->where('is_deleted', 0)
                                         ->where('is_proforma', 0)
                                         ->where('subscription_id', $this->subscription->id)
                                         ->orderBy('id', 'desc')
                                         ->first();

        //sometimes the last document could be a credit if the user is paying for their service with credits.
        if (!$outstanding_invoice) {
            $outstanding_invoice = Credit::query()->where('subscription_id', $this->subscription->id)
                                             ->where('client_id', $recurring_invoice->client_id)
                                             ->where('is_proforma', 0)
                                             ->where('is_deleted', 0)
                                             ->orderBy('id', 'desc')
                                             ->first();
        }

        //need to ensure at this point that a refund is appropriate!!
        //28-02-2022
        if ($recurring_invoice->invoices()->count() == 0) {
            return $target->price;
        } elseif ($outstanding->count() == 0) {
            //nothing outstanding
            return $target->price - $this->calculateProRataRefundForSubscription($outstanding_invoice);
        } elseif ($outstanding->count() == 1) {
            //user has multiple amounts outstanding
            return $target->price - $this->calculateProRataRefundForSubscription($outstanding_invoice);
        } elseif ($outstanding->count() > 1) {
            //user is changing plan mid frequency cycle
            //we cannot handle this if there are more than one invoice outstanding.
            return $target->price;
        }

        return $target->price;
    }

    /**
     * We refund unused days left.
     *
     * @param  Invoice $invoice
     *
     * @return float
     */
    private function calculateProRataRefundForSubscription($invoice): float
    {
        if (!$invoice || !$invoice->date || $invoice->status_id != Invoice::STATUS_PAID) {
            return 0;
        }

        /*Remove previous refunds from the calculation of the amount*/
        $invoice->line_items = collect($invoice->line_items)->filter(function ($item) {
            if ($item->product_key == ctrans("texts.refund")) {
                return false;
            }

            return true;
        })->toArray();

        $amount = $invoice->calc()->getTotal();

        $start_date = Carbon::parse($invoice->date);

        $current_date = now();

        $days_of_subscription_used = intval(abs($start_date->diffInDays($current_date)));

        $days_in_frequency = $this->getDaysInFrequency();

        $pro_rata_refund = round((($days_in_frequency - $days_of_subscription_used) / $days_in_frequency) * $amount, 2);

        return max(0, $pro_rata_refund);
    }

    /**
     * We refund unused days left.
     *
     * @param  \App\Models\Invoice | \App\Models\Credit $invoice
     * @return float
     */
    private function calculateProRataRefund($invoice, $subscription = null): float
    {
        if (!$invoice || !$invoice->date) {
            return 0;
        }

        $start_date = Carbon::parse($invoice->date);

        $current_date = now();

        $days_of_subscription_used = intval(abs($start_date->diffInDays($current_date)));

        if ($subscription) {
            $days_in_frequency = $subscription->service()->getDaysInFrequency();
        } else {
            $days_in_frequency = $this->getDaysInFrequency();
        }

        if ($days_of_subscription_used >= $days_in_frequency) {
            return 0;
        }

        $pro_rata_refund = round((($days_in_frequency - $days_of_subscription_used) / $days_in_frequency) * $invoice->amount, 2);

        return $pro_rata_refund;
    }

    /**
     * Returns refundable set of line items
     * transformed for direct injection into
     * the invoice
     *
     * @param  Invoice $invoice
     * @return array
     */
    private function calculateProRataRefundItems($invoice, $is_credit = false): array
    {
        if (!$invoice) {
            return [];
        }

        $handle_discount = false;

        /* depending on whether we are creating an invoice or a credit*/
        $multiplier = $is_credit ? 1 : -1;

        $start_date = Carbon::parse($invoice->date);

        $current_date = now();

        $days_of_subscription_used = intval(abs($start_date->diffInDays($current_date)));

        $days_in_frequency = $invoice->subscription->service()->getDaysInFrequency();

        $ratio = ($days_in_frequency - $days_of_subscription_used) / $days_in_frequency;

        $line_items = [];

        //Handle when we are refunding a discounted invoice. Need to consider the
        //total discount and also the line item discount.
        if ($invoice->discount > 0) {
            $handle_discount = true;
        }


        foreach ($invoice->line_items as $item) {
            if ($item->product_key != ctrans('texts.refund') && ($item->type_id == "1" || $item->type_id == "2")) {
                $discount_ratio = 1;

                if ($handle_discount) {
                    $discount_ratio = $this->calculateDiscountRatio($invoice);
                }

                $item->cost = ($item->cost * $ratio * $multiplier * $discount_ratio);
                $item->product_key = ctrans('texts.refund');
                $item->notes = ctrans('texts.refund') . ": ". $item->notes;

                $line_items[] = $item;
            }
        }

        return $line_items;
    }


    /**
     * We only charge for the used days
     *
     * @param  Invoice $invoice
     * @return float
     */
    public function calculateDiscountRatio($invoice): float
    {
        if ($invoice->is_amount_discount) {
            return $invoice->discount / ($invoice->amount + $invoice->discount);
        } else {
            return $invoice->discount / 100;
        }
    }

    /**
     * We only charge for the used days
     *
     * @param  Invoice $invoice
     * @return float
     */
    private function calculateProRataCharge($invoice): float
    {
        $start_date = Carbon::parse($invoice->date);

        $current_date = now();

        $days_to_charge = intval(abs($start_date->diffInDays($current_date)));

        $days_in_frequency = $this->getDaysInFrequency();

        nlog("days to charge = {$days_to_charge} days in frequency = {$days_in_frequency}");

        $pro_rata_charge = round(($days_to_charge / $days_in_frequency) * $invoice->amount, 2);

        nlog("pro rata charge = {$pro_rata_charge}");

        return $pro_rata_charge;
    }

    /**
     * This entry point assumes the user does not have to make a
     * payment for the service.
     *
     * In this case, we generate a credit note for the old service
     * Generate a new invoice for the new service
     * Apply credits to the invoice
     *
     * @param  array $data
     */
    public function createChangePlanCreditV2($data)
    {
        /* Init vars */
        $recurring_invoice = $data['recurring_invoice'];
        $old_subscription = $data['subscription'];
        $target_subscription = $data['target'];

        $pro_rata_charge_amount = 0;
        $pro_rata_refund_amount = 0;
        $is_credit = false;
        $credit = false;

        /* Get last invoice */
        $last_invoice = Invoice::query()->where('subscription_id', $recurring_invoice->subscription_id)
                                         ->where('client_id', $recurring_invoice->client_id)
                                         ->where('is_proforma', 0)
                                         ->where('is_deleted', 0)
                                         ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID])
                                         ->withTrashed()
                                         ->orderBy('id', 'desc')
                                         ->first();

        //if last payment was created from a credit, do not generate a new credit, refund the old one.

        if ($last_invoice) {
            $last_invoice->payments->each(function ($payment) {
                $payment->credits()->where('is_deleted', 0)->each(function ($credit) {
                    $this->credit_payments += $credit->pivot->sum('amount');
                });
            });

            $invoice_repo = new InvoiceRepository();

            $invoice_repo->delete($last_invoice);

            $payment_repo = new PaymentRepository(new CreditRepository());

            $last_invoice->payments->each(function ($payment) use ($payment_repo) {
                $payment_repo->delete($payment);
            });
        }

        //if there are existing credit payments, then we refund directly to the credit.
        if ($this->calculateProRataRefundForSubscription($last_invoice) > 0 && $this->credit_payments == 0) {
            $credit = $this->createCredit($last_invoice, $target_subscription, false);
        }

        $new_recurring_invoice = $this->createNewRecurringInvoice($recurring_invoice);

        $invoice = $this->changePlanInvoice($target_subscription, $recurring_invoice->client_id);
        $invoice->recurring_id = $new_recurring_invoice->id;
        $invoice->is_proforma = false;
        $invoice->save();

        $payment = PaymentFactory::create($invoice->company_id, $invoice->user_id, $invoice->client_id);
        $payment->type_id = PaymentType::CREDIT;
        $payment->client_id = $invoice->client_id;
        $payment->is_manual = true;
        $payment->save();

        $payment->service()->applyNumber()->applyCreditsToInvoice($invoice);

        $context = [
            'context' => 'change_plan',
            'recurring_invoice' => $new_recurring_invoice->hashed_id,
            'credit' => $credit ? $credit->hashed_id : null,
            'client' => $new_recurring_invoice->client->hashed_id,
            'subscription' => $target_subscription->hashed_id,
            'contact' => auth()->guard('contact')->user()->hashed_id,
            'account_key' => $new_recurring_invoice->client->custom_value2,
        ];

        $response = $this->triggerWebhook($context);

        return '/client/recurring_invoices/'.$new_recurring_invoice->hashed_id;
    }

    /**
     * When downgrading, we may need to create
     * a credit
     *
     * @deprecated in favour of createChangePlanCreditV2
     * @param  array $data
     */
    public function createChangePlanCredit($data)
    {
        $recurring_invoice = $data['recurring_invoice'];
        $old_subscription = $data['subscription'];
        $target_subscription = $data['target'];

        $pro_rata_charge_amount = 0;
        $pro_rata_refund_amount = 0;
        $is_credit = false;

        $last_invoice = Invoice::query()->where('subscription_id', $recurring_invoice->subscription_id)
                                         ->where('client_id', $recurring_invoice->client_id)
                                         ->where('is_deleted', 0)
                                         ->withTrashed()
                                         ->orderBy('id', 'desc')
                                         ->first();

        if ($recurring_invoice->invoices()->count() == 0) {
            $pro_rata_refund_amount = 0;
        } elseif (!$last_invoice) {
            $is_credit = true;

            $last_invoice = Credit::query()->where('subscription_id', $recurring_invoice->subscription_id)
                                 ->where('client_id', $recurring_invoice->client_id)
                                 ->where('is_deleted', 0)
                                 ->withTrashed()
                                 ->orderBy('id', 'desc')
                                 ->first();

            $pro_rata_refund_amount = $this->calculateProRataRefund($last_invoice, $old_subscription);
        } elseif ($last_invoice->balance > 0) {
            $pro_rata_charge_amount = $this->calculateProRataCharge($last_invoice);
            nlog("pro rata charge = {$pro_rata_charge_amount}");
        } else {
            $pro_rata_refund_amount = $this->calculateProRataRefund($last_invoice, $old_subscription) * -1;
            nlog("pro rata refund = {$pro_rata_refund_amount}");
        }

        $total_payable = $pro_rata_refund_amount + $pro_rata_charge_amount + $this->subscription->price;

        nlog("total payable = {$total_payable}");

        $credit = false;

        /* Only generate a credit if the previous invoice was paid in full. */
        if ($last_invoice && $last_invoice->balance == 0) {
            $credit = $this->createCredit($last_invoice, $target_subscription, $is_credit);
        }

        $new_recurring_invoice = $this->createNewRecurringInvoice($recurring_invoice);

        $context = [
            'context' => 'change_plan',
            'recurring_invoice' => $new_recurring_invoice->hashed_id,
            'credit' => $credit ? $credit->hashed_id : null,
            'client' => $new_recurring_invoice->client->hashed_id,
            'subscription' => $target_subscription->hashed_id,
            'contact' => auth()->guard('contact')->user()->hashed_id,
            'account_key' => $new_recurring_invoice->client->custom_value2,
        ];

        $response = $this->triggerWebhook($context);

        nlog($response);

        if ($credit) {
            return '/client/credits/'.$credit->hashed_id;
        } else {
            return '/client/credits';
        }
    }

    public function changePlanPaymentCheck($data)
    {
        $recurring_invoice = $data['recurring_invoice'];
        $old_subscription = $data['subscription'];
        $target_subscription = $data['target'];

        $pro_rata_charge_amount = 0;
        $pro_rata_refund_amount = 0;

        $last_invoice = Invoice::query()->where('subscription_id', $recurring_invoice->subscription_id)
                                         ->where('client_id', $recurring_invoice->client_id)
                                         ->where('is_proforma', 0)
                                         ->where('is_deleted', 0)
                                         ->withTrashed()
                                         ->orderBy('id', 'desc')
                                         ->first();
        if (!$last_invoice) {
            return true;
        }

        if ($last_invoice->balance > 0) {
            $pro_rata_charge_amount = $this->calculateProRataCharge($last_invoice);
            nlog("pro rata charge = {$pro_rata_charge_amount}");
        } else {
            $pro_rata_refund_amount = $this->calculateProRataRefund($last_invoice, $old_subscription) * -1;
            nlog("pro rata refund = {$pro_rata_refund_amount}");
        }

        nlog("{$pro_rata_refund_amount} + {$pro_rata_charge_amount} + {$this->subscription->price}");

        $total_payable = $pro_rata_refund_amount + $pro_rata_charge_amount + $this->subscription->price;

        if ($total_payable > 0) {
            return true;
        }

        return false;
    }

    /**
     * When changing plans, we need to generate a pro rata invoice
     *
     * @param  array $data{recurring_invoice: RecurringInvoice, subscription: Subscription, target: Subscription}
     * @return Invoice
     */
    public function createChangePlanInvoice($data)
    {
        $recurring_invoice = $data['recurring_invoice'];
        $old_subscription = $data['subscription'];
        $target_subscription = $data['target'];

        $pro_rata_charge_amount = 0;
        $pro_rata_refund_amount = 0;

        $last_invoice = Invoice::query()->where('subscription_id', $recurring_invoice->subscription_id)
                                         ->where('client_id', $recurring_invoice->client_id)
                                         ->where('is_deleted', 0)
                                         ->where('is_proforma', 0)
                                         ->withTrashed()
                                         ->orderBy('id', 'desc')
                                         ->first();

        if (!$last_invoice) {
            //do nothing
        } elseif ($last_invoice->balance > 0) {
            $last_invoice = null;
            // $pro_rata_charge_amount = $this->calculateProRataCharge($last_invoice, $old_subscription);
            // nlog("pro rata charge = {$pro_rata_charge_amount}");
        } else {
            $pro_rata_refund_amount = $this->calculateProRataRefund($last_invoice, $old_subscription) * -1;
            nlog("pro rata refund = {$pro_rata_refund_amount}");
        }

        $total_payable = $pro_rata_refund_amount + $pro_rata_charge_amount + $this->subscription->price;

        return $this->proRataInvoice($last_invoice, $target_subscription, $recurring_invoice->client_id);
    }

    /**
     * Response from payment service on
     * return from a plan change
     *
     * @param  PaymentHash $payment_hash
     */
    private function handlePlanChange($payment_hash)
    {
        nlog("handle plan change");

        $old_recurring_invoice = RecurringInvoice::query()->find($this->decodePrimaryKey($payment_hash->data->billing_context->recurring_invoice));

        if (!$old_recurring_invoice) {
            return $this->handleRedirect('/client/recurring_invoices/');
        }

        $recurring_invoice = $this->createNewRecurringInvoice($old_recurring_invoice);

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
     * Creates a new recurring invoice when changing
     * plans
     *
     * @param  RecurringInvoice $old_recurring_invoice
     * @return RecurringInvoice
     */
    public function createNewRecurringInvoice($old_recurring_invoice): RecurringInvoice
    {
        $old_recurring_invoice->service()->stop()->save();

        $recurring_invoice_repo = new RecurringInvoiceRepository();
        $recurring_invoice_repo->delete($old_recurring_invoice);

        $recurring_invoice = $this->convertInvoiceToRecurring($old_recurring_invoice->client_id);
        $recurring_invoice = $recurring_invoice_repo->save([], $recurring_invoice);
        $recurring_invoice->next_send_date = now()->format('Y-m-d');
        $recurring_invoice->next_send_date_client = now()->format('Y-m-d');
        $recurring_invoice->next_send_date = $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();

        /* Start the recurring service */
        $recurring_invoice->service()
                          ->start()
                          ->save();

        return $recurring_invoice;
    }

    /**
     * Creates a credit note if the plan change requires
     *
     * @param  Invoice $last_invoice
     * @param  Subscription $target
     * @return Credit
     */
    private function createCredit($last_invoice, $target, $is_credit = false)
    {
        $last_invoice_is_credit = $is_credit ? false : true;

        $subscription_repo = new SubscriptionRepository();
        $credit_repo = new CreditRepository();

        $credit = CreditFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $credit->status_id = Credit::STATUS_SENT;
        $credit->date = now()->format('Y-m-d');
        $credit->subscription_id = $this->subscription->id;
        $credit->discount = $last_invoice->discount;
        $credit->is_amount_discount = $last_invoice->is_amount_discount;

        $credit->line_items = $this->calculateProRataRefundItems($last_invoice, true);

        $data = [
            'client_id' => $last_invoice->client_id,
            'quantity' => 1,
            'date' => now()->format('Y-m-d'),
        ];

        return $credit_repo->save($data, $credit)->service()->markSent()->fillDefaults()->save();
    }

    /**
     * When changing plans we need to generate a pro rata
     * invoice which takes into account any credits.
     *
     * @param  Invoice $last_invoice
     * @param  Subscription $target
     * @return Invoice
     */
    private function proRataInvoice($last_invoice, $target, $client_id)
    {
        $subscription_repo = new SubscriptionRepository();
        $invoice_repo = new InvoiceRepository();

        $invoice = InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $invoice->date = now()->format('Y-m-d');
        $invoice->subscription_id = $target->id;

        $invoice->line_items = array_merge($subscription_repo->generateLineItems($target), $this->calculateProRataRefundItems($last_invoice));
        $invoice->is_proforma = true;

        $data = [
            'client_id' => $client_id,
            'quantity' => 1,
            'date' => now()->format('Y-m-d'),
        ];

        return $invoice_repo->save($data, $invoice)
                            ->service()
                            ->markSent()
                            ->fillDefaults()
                            ->save();
    }

    /**
     * When changing plans we need to generate a pro rata
     * invoice which takes into account any credits.
     *
     * @param  Subscription $target
     * @return Invoice
     */
    private function changePlanInvoice($target, $client_id)
    {
        $subscription_repo = new SubscriptionRepository();
        $invoice_repo = new InvoiceRepository();

        $invoice = InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $invoice->date = now()->format('Y-m-d');
        $invoice->subscription_id = $target->id;

        $invoice->line_items = $subscription_repo->generateLineItems($target);
        $invoice->is_proforma = true;

        $data = [
            'client_id' => $client_id,
            'quantity' => 1,
            'date' => now()->format('Y-m-d'),
        ];

        $invoice_repo->save($data, $invoice)
                            ->service()
                            ->markSent()
                            ->fillDefaults()
                            ->save();

        if ($invoice->fresh()->balance == 0) {
            $invoice->service()->markPaid()->save();
        }

        return $invoice->fresh();
    }


    public function createInvoiceV2($bundle, $client_id, $valid_coupon = false)
    {
        $invoice_repo = new InvoiceRepository();
        $subscription_repo = new SubscriptionRepository();

        $invoice = InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $invoice->subscription_id = $this->subscription->id;
        $invoice->client_id = $client_id;
        $invoice->is_proforma = true;
        $invoice->number = "####" . ctrans('texts.subscription') . "_" . now()->format('Y-m-d') . "_" . rand(0, 100000);
        $line_items = $bundle->map(function ($item) {
            $line_item = new InvoiceItem();
            $line_item->product_key = $item['product_key'];
            $line_item->quantity = (float)$item['qty'];
            $line_item->cost = (float)$item['unit_cost'];
            $line_item->notes = $item['description'];

            return $line_item;
        })->toArray();

        $invoice->line_items = $line_items;

        if ($valid_coupon) {
            $invoice->discount = $this->subscription->promo_discount;
            $invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        return $invoice_repo->save([], $invoice);
    }

    /**
     * Generates the first invoice when a subscription is purchased
     *
     * @param  array $data
     * @return Invoice
     */
    public function createInvoice($data, $quantity = 1): ?\App\Models\Invoice
    {
        $invoice_repo = new InvoiceRepository();
        $subscription_repo = new SubscriptionRepository();
        $subscription_repo->quantity = $quantity;

        $invoice = InvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $invoice->line_items = $subscription_repo->generateLineItems($this->subscription);
        $invoice->subscription_id = $this->subscription->id;
        $invoice->is_proforma = true;

        if (strlen($data['coupon'] ?? '') >= 1 && ($data['coupon'] == $this->subscription->promo_code) && $this->subscription->promo_discount > 0) {
            $invoice->discount = $this->subscription->promo_discount;
            $invoice->is_amount_discount = $this->subscription->is_amount_discount;
        } elseif (strlen($this->subscription->promo_code ?? '') == 0 && $this->subscription->promo_discount > 0) {
            $invoice->discount = $this->subscription->promo_discount;
            $invoice->is_amount_discount = $this->subscription->is_amount_discount;
        }

        return $invoice_repo->save($data, $invoice);
    }

    /**
     * Generates a recurring invoice based on
     * the specifications of the subscription
     *
     * @param  int $client_id The Client Id
     * @return RecurringInvoice
     */
    public function convertInvoiceToRecurring($client_id): RecurringInvoice
    {
        MultiDB::setDb($this->subscription->company->db);

        $client = Client::withTrashed()->find($client_id);

        $subscription_repo = new SubscriptionRepository();

        $recurring_invoice = RecurringInvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $recurring_invoice->client_id = $client_id;
        $recurring_invoice->line_items = $subscription_repo->generateLineItems($this->subscription, true, false);
        $recurring_invoice->subscription_id = $this->subscription->id;
        $recurring_invoice->frequency_id = $this->subscription->frequency_id ?: RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->remaining_cycles = $this->subscription->remaining_cycles ?? -1;
        $recurring_invoice->date = now();
        $recurring_invoice->auto_bill = $this->subscription->auto_bill ?? $client->getSetting('auto_bill');
        $recurring_invoice->auto_bill_enabled =  $this->setAutoBillFlag($recurring_invoice->auto_bill);
        $recurring_invoice->due_date_days = 'terms';
        $recurring_invoice->next_send_date = now()->format('Y-m-d');
        $recurring_invoice->next_send_date_client = now()->format('Y-m-d');
        $recurring_invoice->next_send_date =  $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();
        return $recurring_invoice;
    }


    /**
     * Generates a recurring invoice based on
     * the specifications of the subscription USING BUNDLE
     *
     * @param  int $client_id The Client Id
     * @return RecurringInvoice
     */
    public function convertInvoiceToRecurringBundle($client_id, $bundle): RecurringInvoice
    {
        MultiDB::setDb($this->subscription->company->db);

        $client = Client::withTrashed()->find($client_id);

        $subscription_repo = new SubscriptionRepository();

        $recurring_invoice = RecurringInvoiceFactory::create($this->subscription->company_id, $this->subscription->user_id);
        $recurring_invoice->client_id = $client_id;
        $recurring_invoice->line_items = $subscription_repo->generateBundleLineItems($bundle, true, false);
        $recurring_invoice->subscription_id = $this->subscription->id;
        $recurring_invoice->frequency_id = $this->subscription->frequency_id ?: RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->date = now()->addSeconds($client->timezone_offset());
        $recurring_invoice->remaining_cycles = $this->subscription->remaining_cycles ?? -1;
        $recurring_invoice->auto_bill = $this->subscription->auto_bill ?? $client->getSetting('auto_bill');
        $recurring_invoice->auto_bill_enabled =  $this->setAutoBillFlag($recurring_invoice->auto_bill);
        $recurring_invoice->due_date_days = 'terms';
        $recurring_invoice->next_send_date = now()->addSeconds($client->timezone_offset())->format('Y-m-d');
        $recurring_invoice->next_send_date_client = now()->format('Y-m-d');
        $recurring_invoice->next_send_date =  $recurring_invoice->nextSendDate();
        $recurring_invoice->next_send_date_client = $recurring_invoice->nextSendDateClient();

        return $recurring_invoice;
    }


    private function setAutoBillFlag($auto_bill): bool
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }

    /**
     * Hit a 3rd party API if defined in the subscription
     *
     * @param  array $context
     */
    public function triggerWebhook($context)
    {
        if (empty($this->subscription->webhook_configuration['post_purchase_url']) || is_null($this->subscription->webhook_configuration['post_purchase_url']) || strlen($this->subscription->webhook_configuration['post_purchase_url'] ?? '') < 1) { //@phpstan-ignore-line
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

    public function fireNotifications()
    {
        //scan for any notification we are required to send
    }

    /**
     * Get the single charge products for the
     * subscription
     *
     */
    public function products()
    {
        if (!$this->subscription->product_ids) {
            return collect();
        }

        $keys = $this->transformKeys(explode(",", $this->subscription->product_ids));

        if (is_array($keys)) {
            return Product::query()->where('company_id', $this->subscription->company_id)->whereIn('id', $keys)->get();
        } else {
            return Product::query()->where('company_id', $this->subscription->company_id)->where('id', $keys)->get();
        }
    }

    /**
     * Get the recurring products for the
     * subscription
     *
     */
    public function recurring_products()
    {
        if (!$this->subscription->recurring_product_ids) {
            return collect();
        }

        $keys = $this->transformKeys(explode(",", $this->subscription->recurring_product_ids));

        if (is_array($keys)) {
            return Product::query()->where('company_id', $this->subscription->company_id)->whereIn('id', $keys)->get();
        } else {
            return Product::query()->where('company_id', $this->subscription->company_id)->where('id', $keys)->get();
        }
    }

    /* OPTIONAL PRODUCTS*/
    /**
     * Get the single charge products for the
     * subscription
     *
     */
    public function optional_products()
    {
        if (!$this->subscription->optional_product_ids) {
            return collect();
        }

        $keys = $this->transformKeys(explode(",", $this->subscription->optional_product_ids));

        if (is_array($keys)) {
            return Product::query()->where('company_id', $this->subscription->company_id)->whereIn('id', $keys)->get();
        } else {
            return Product::query()->where('company_id', $this->subscription->company_id)->where('id', $keys)->get();
        }
    }

    /**
     * Get the recurring products for the
     * subscription
     *
     */
    public function optional_recurring_products()
    {
        if (!$this->subscription->optional_recurring_product_ids) {
            return collect();
        }

        $keys = $this->transformKeys(explode(",", $this->subscription->optional_recurring_product_ids));

        if (is_array($keys)) {
            return Product::query()->where('company_id', $this->subscription->company_id)->whereIn('id', $keys)->get();
        } else {
            return Product::query()->where('company_id', $this->subscription->company_id)->where('id', $keys)->get();
        }
    }








    /**
     * Get available upgrades & downgrades for the plan.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getPlans()
    {
        return Subscription::query()
                            ->where('company_id', $this->subscription->company_id)
                            ->where('group_id', $this->subscription->group_id)
                            ->whereNotNull('group_id')
                            ->where('id', '!=', $this->subscription->id)
                            ->get();
    }

    /**
     * Handle the cancellation of a subscription
     *
     * @param  RecurringInvoice $recurring_invoice
     *
     */
    public function handleCancellation(RecurringInvoice $recurring_invoice)
    {
        $invoice_start_date = false;
        $refund_end_date = false;
        $gateway_refund_attempted = false;

        //only refund if they are in the refund window.
        $outstanding_invoice = Invoice::query()->where('subscription_id', $this->subscription->id)
                                     ->where('client_id', $recurring_invoice->client_id)
                                     ->where('status_id', Invoice::STATUS_PAID)
                                     ->where('is_deleted', 0)
                                     ->where('is_proforma', 0)
                                     ->where('balance', 0)
                                     ->orderBy('id', 'desc')
                                     ->first();

        if ($outstanding_invoice) {
            $invoice_start_date = Carbon::parse($outstanding_invoice->date);
            $refund_end_date = $invoice_start_date->addSeconds($this->subscription->refund_period);
        }

        /* Stop the recurring invoice and archive */
        $recurring_invoice->service()->stop()->save();
        $recurring_invoice_repo = new RecurringInvoiceRepository();
        $recurring_invoice_repo->archive($recurring_invoice);

        /* Refund only if we are in the window - and there is nothing outstanding on the invoice */
        if ($refund_end_date && $refund_end_date->greaterThan(now())) {
            if ($outstanding_invoice->payments()->exists()) {
                $payment = $outstanding_invoice->payments()->first();

                $data = [
                    'id' => $payment->id,
                    'gateway_refund' => $outstanding_invoice->amount >= 1 ? true : false,
                    'send_email' => true,
                    'email_receipt',
                    'invoices' => [
                        ['invoice_id' => $outstanding_invoice->id, 'amount' => $outstanding_invoice->amount],
                    ],

                ];

                $payment->refund($data);
                $gateway_refund_attempted = true;
            }
        }

        $context = [
            'context' => 'cancellation',
            'subscription' => $this->subscription->hashed_id,
            'recurring_invoice' => $recurring_invoice->hashed_id,
            'client' => $recurring_invoice->client->hashed_id,
            'contact' => auth()->guard('contact')->user()->hashed_id,
            'account_key' => $recurring_invoice->client->custom_value2,
        ];

        $this->triggerWebhook($context);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = (new NinjaMailer((new ClientContactRequestCancellationObject($recurring_invoice, auth()->guard('contact')->user(), $gateway_refund_attempted))->build()));
        $nmo->company = $recurring_invoice->company;
        $nmo->settings = $recurring_invoice->company->settings;

        $recurring_invoice->company->company_users->each(function ($company_user) use ($nmo) {
            $methods = $this->findCompanyUserNotificationType($company_user, ['recurring_cancellation', 'all_notifications']);

            //if mail is a method type -fire mail!!
            if (($key = array_search('mail', $methods)) !== false) {
                unset($methods[$key]);

                $nmo->to_user = $company_user->user;
                NinjaMailerJob::dispatch($nmo);
            }
        });

        return $this->handleRedirect('client/subscriptions');
    }

    /**
     * Get the number of days in the currency frequency
     *
     * @return int Number of days
     */
    public function getDaysInFrequency(): int
    {
        switch ($this->subscription->frequency_id) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return 1;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return 7;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return 14;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return intval(abs(now()->diffInDays(now()->addWeeks(4))));
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return intval(abs(now()->diffInDays(now()->addMonthNoOverflow())));
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(2))));
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(3))));
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(4))));
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return intval(abs(now()->diffInDays(now()->addMonthsNoOverflow(6))));
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return intval(abs(now()->diffInDays(now()->addYear())));
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return intval(abs(now()->diffInDays(now()->addYears(2))));
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return intval(abs(now()->diffInDays(now()->addYears(3))));
            default:
                return 0;
        }
    }

    /**
     * Get the next date by frequency_id
     *
     * @param  Carbon $date      The current carbon date
     * @param  int               $frequency The frequncy_id of the subscription
     *
     * @return ?Carbon           The next date carbon object
     */
    public function getNextDateForFrequency($date, $frequency): ?Carbon
    {
        switch ($frequency) {
            case RecurringInvoice::FREQUENCY_DAILY:
                return $date->addDay();
            case RecurringInvoice::FREQUENCY_WEEKLY:
                return $date->addDays(7);
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                return $date->addDays(13);
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                return $date->addWeeks(4);
            case RecurringInvoice::FREQUENCY_MONTHLY:
                return $date->addMonthNoOverflow();
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                return $date->addMonthsNoOverflow(2);
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                return $date->addMonthsNoOverflow(3);
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                return $date->addMonthsNoOverflow(4);
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                return $date->addMonthsNoOverflow(6);
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                return $date->addYear();
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                return $date->addYears(2);
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
                return $date->addYears(3);
            default:
                return null;
        }
    }


    /**
     * Handle case where no payment is required
     * @param  Invoice       $invoice The Invoice
     * @param  array         $bundle  The bundle array
     * @param  ClientContact $contact The Client Contact
     *
     * @return \Illuminate\Routing\Redirector|\Illuminate\Http\RedirectResponse
     */
    public function handleNoPaymentFlow(Invoice $invoice, $bundle, ClientContact $contact)
    {
        if (strlen($this->subscription->recurring_product_ids ?? '') >= 1) {
            $recurring_invoice = $this->convertInvoiceToRecurringBundle($contact->client_id, collect($bundle)->map(function ($bund) {
                return (object) $bund;
            }));

            /* Start the recurring service */
            $recurring_invoice->service()
                              ->start()
                              ->save();

            $invoice->recurring_id = $recurring_invoice->id;
            $invoice->save();

            $context = [
                'context' => 'recurring_purchase',
                'recurring_invoice' => $recurring_invoice->hashed_id,
                'invoice' => $invoice->hashed_id,
                'client' => $recurring_invoice->client->hashed_id,
                'subscription' => $this->subscription->hashed_id,
                'contact' => $contact->hashed_id,
                'redirect_url' => "/client/recurring_invoices/{$recurring_invoice->hashed_id}",
            ];

            $this->triggerWebhook($context);

            return $this->handleRedirect($context['redirect_url']);
        }

        $redirect_url = "/client/invoices/{$invoice->hashed_id}";

        return $this->handleRedirect($redirect_url);
    }

    /**
    * 'email' => $this->email ?? $this->contact->email,
    * 'quantity' => $this->quantity,
    * 'contact_id' => $this->contact->id,
    */
    public function handleNoPaymentRequired(array $data)
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
     * Handles redirecting the user
     */
    private function handleRedirect($default_redirect)
    {
        if (array_key_exists('return_url', $this->subscription->webhook_configuration) && strlen($this->subscription->webhook_configuration['return_url'] ?? '') >= 1) {
            return method_exists(redirect(), "send") ? redirect($this->subscription->webhook_configuration['return_url'])->send() : redirect($this->subscription->webhook_configuration['return_url']);
        }

        return method_exists(redirect(), "send") ? redirect($default_redirect)->send() : redirect($default_redirect);
    }

    /**
     * @param Invoice $invoice
     * @return true
     * @throws BindingResolutionException
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
}

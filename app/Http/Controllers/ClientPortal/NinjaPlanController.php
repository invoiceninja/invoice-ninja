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

namespace App\Http\Controllers\ClientPortal;

use App\DataMapper\Analytics\TrialStarted;
use App\Factory\RecurringInvoiceFactory;
use App\Http\Controllers\Controller;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use App\Models\Invoice;
use App\Models\RecurringInvoice;
use App\Models\Subscription;
use App\Notifications\Ninja\NewAccountNotification;
use App\Repositories\RecurringInvoiceRepository;
use App\Repositories\SubscriptionRepository;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Turbo124\Beacon\Facades\LightLogs;

class NinjaPlanController extends Controller
{
    use MakesHash;

    public function index(string $contact_key, string $account_or_company_key)
    {
        MultiDB::findAndSetDbByCompanyKey($account_or_company_key);
        $company = Company::query()->where('company_key', $account_or_company_key)->first();

        if (! $company) {
            MultiDB::findAndSetDbByAccountKey($account_or_company_key);

            /** @var \App\Models\Account $account **/
            $account = Account::query()->where('key', $account_or_company_key)->first();
        } else {
            $account = $company->account;
        }

        if (MultiDB::findAndSetDbByContactKey($contact_key) && $client_contact = ClientContact::where('contact_key', $contact_key)->first()) {
            nlog('Ninja Plan Controller - Found and set Client Contact');

            request()->session()->invalidate();
            Auth::guard('contact')->loginUsingId($client_contact->id, true);

            return $this->plan();
        }

        return redirect()->route('client.catchall');
    }

    public function trial()
    {
        $gateway = CompanyGateway::on('db-ninja-01')->find(config('ninja.ninja_default_company_gateway_id'));

        $data['gateway'] = $gateway;

        $gateway_driver = $gateway->driver(auth()->guard('contact')->user()->client)->init();

        $customer = $gateway_driver->findOrCreateCustomer();

        $setupIntent = \Stripe\SetupIntent::create([
            'payment_method_types' => ['card'],
            'usage' => 'off_session',
            'customer' => $customer->id,
        ]);

        $data['intent'] = $setupIntent;
        $data['client'] = Auth::guard('contact')->user()->client;

        return $this->render('plan.trial', $data);
    }

    public function trial_confirmation(Request $request)
    {
        $trial_started = "Trial Started @ ".now()->format('Y-m-d H:i:s');

        auth()->guard('contact')->user()->fill($request->only(['first_name','last_name']))->save();

        $client = auth()->guard('contact')->user()->client;
        $client->private_notes = $trial_started;
        $client->fill($request->all());
        $client->save();

        //store payment method
        $gateway = CompanyGateway::on('db-ninja-01')->find(config('ninja.ninja_default_company_gateway_id'));
        $gateway_driver = $gateway->driver(auth()->guard('contact')->user()->client)->init();

        $stripe_response = json_decode($request->input('gateway_response'));
        $customer = $gateway_driver->findOrCreateCustomer();

        //27-08-2022 Ensure customer is updated appropriately
        $update_client_object['name'] = $client->present()->name();
        $update_client_object['phone'] = substr($client->present()->phone(), 0, 20);

        $update_client_object['address']['line1'] = $client->address1 ?: '';
        $update_client_object['address']['line2'] = $client->address2 ?: '';
        $update_client_object['address']['city'] = $client->city ?: '';
        $update_client_object['address']['postal_code'] = $client->postal_code ?: '';
        $update_client_object['address']['state'] = $client->state ?: '';
        $update_client_object['address']['country'] = $client->country ? $client->country->iso_3166_2 : '';

        $update_client_object['shipping']['name'] = $client->present()->name();
        $update_client_object['shipping']['address']['line1'] = $client->shipping_address1 ?: '';
        $update_client_object['shipping']['address']['line2'] = $client->shipping_address2 ?: '';
        $update_client_object['shipping']['address']['city'] = $client->shipping_city ?: '';
        $update_client_object['shipping']['address']['postal_code'] = $client->shipping_postal_code ?: '';
        $update_client_object['shipping']['address']['state'] = $client->shipping_state ?: '';
        $update_client_object['shipping']['address']['country'] = $client->shipping_country ? $client->shipping_country->iso_3166_2 : '';

        \Stripe\Customer::update($customer->id, $update_client_object, $gateway_driver->stripe_connect_auth);

        $gateway_driver->attach($stripe_response->payment_method, $customer);
        $method = $gateway_driver->getStripePaymentMethod($stripe_response->payment_method);

        $payment_meta = new \stdClass();
        $payment_meta->exp_month = (string) $method->card->exp_month;
        $payment_meta->exp_year = (string) $method->card->exp_year;
        $payment_meta->brand = (string) $method->card->brand;
        $payment_meta->last4 = (string) $method->card->last4;
        $payment_meta->type = GatewayType::CREDIT_CARD;

        $data = [
            'payment_meta' => $payment_meta,
            'token' => $method->id,
            'payment_method_id' => GatewayType::CREDIT_CARD,
        ];

        $gateway_driver->storeGatewayToken($data, ['gateway_customer_reference' => $customer->id]);

        //set free trial
        if (auth()->guard('contact')->user()->client->custom_value2) {
            MultiDB::findAndSetDbByAccountKey(auth()->guard('contact')->user()->client->custom_value2);

            /** @var \App\Models\Account $account **/
            $account = Account::where('key', auth()->guard('contact')->user()->client->custom_value2)->first();
            // $account->trial_started = now();
            // $account->trial_plan = 'pro';
            $account->plan = 'pro';
            $account->plan_term = 'month';
            $account->plan_started = now();
            $account->plan_expires = now()->addDays(14);
            $account->is_trial = true;
            $account->hosted_company_count = 10;
            $account->trial_started = now();
            $account->trial_plan = 'pro';
            $account->save();
        }

        MultiDB::setDB('db-ninja-01');

        //create recurring invoice
        $subscription_repo = new SubscriptionRepository();

        /** @var \App\Models\Subscription $subscription **/
        $subscription = Subscription::find(6);

        $recurring_invoice = RecurringInvoiceFactory::create($subscription->company_id, $subscription->user_id);
        $recurring_invoice->client_id = $client->id;
        $recurring_invoice->line_items = $subscription_repo->generateLineItems($subscription, true, false);
        $recurring_invoice->subscription_id = $subscription->id;
        $recurring_invoice->frequency_id = $subscription->frequency_id ?: RecurringInvoice::FREQUENCY_MONTHLY;
        $recurring_invoice->date = now()->addDays(14);
        $recurring_invoice->remaining_cycles = -1;
        $recurring_invoice->auto_bill = $client->getSetting('auto_bill');
        $recurring_invoice->auto_bill_enabled = $this->setAutoBillFlag($recurring_invoice->auto_bill);
        $recurring_invoice->due_date_days = 'terms';
        $recurring_invoice->next_send_date = now()->addDays(14)->format('Y-m-d');
        $recurring_invoice->next_send_date_client = now()->addDays(14)->format('Y-m-d');

        $recurring_invoice->save();
        $r = $recurring_invoice->calc()->getRecurringInvoice();

        $recurring_invoice->service()->applyNumber()->start()->save();

        LightLogs::create(new TrialStarted())
                 ->increment()
                 ->queue();

        $old_recurring = RecurringInvoice::query()->where('company_id', config('ninja.ninja_default_company_id'))
                                            ->where('client_id', $client->id)
                                            ->where('id', '!=', $recurring_invoice->id)
                                            ->first();

        if ($old_recurring) {
            $old_recurring->service()->stop()->save();
            $old_recurring_repo = new RecurringInvoiceRepository();
            $old_recurring_repo->archive($old_recurring);
        }

        $ninja_company = Company::on('db-ninja-01')->find(config('ninja.ninja_default_company_id'));
        $ninja_company->notification(new NewAccountNotification($subscription->company->account, $client))->ninja();

        return $this->render('plan.trial_confirmed', $data);
    }

    private function setAutoBillFlag($auto_bill)
    {
        if ($auto_bill == 'always' || $auto_bill == 'optout') {
            return true;
        }

        return false;
    }

    public function plan()
    {
        // return $this->trial();
        //harvest the current plan
        $data = [];
        $data['late_invoice'] = false;

        if (MultiDB::findAndSetDbByAccountKey(Auth::guard('contact')->user()->client->custom_value2)) {
            $account = Account::query()->where('key', Auth::guard('contact')->user()->client->custom_value2)->first();

            if ($account) {
                //offer the option to have a free trial
                if (!$account->plan && !$account->is_trial) {
                    return $this->trial();
                }

                if (Carbon::parse($account->plan_expires)->lt(now())) {
                    //expired get the most recent invoice for payment

                    $late_invoice = Invoice::on('db-ninja-01')
                                           ->where('company_id', Auth::guard('contact')->user()->company->id)
                                           ->where('client_id', Auth::guard('contact')->user()->client->id)
                                           ->where('status_id', Invoice::STATUS_SENT)
                                           ->whereNotNull('subscription_id')
                                           ->orderBy('id', 'DESC')
                                           ->first();

                    //account status means user cannot perform upgrades until they pay their account.
                    // $data['late_invoice'] = $late_invoice;

                    //14-01-2022 remove late invoices from blocking upgrades
                    $data['late_invoice'] = false;
                }

                $recurring_invoice = RecurringInvoice::on('db-ninja-01')
                                            ->where('client_id', auth()->guard('contact')->user()->client->id)
                                            ->where('company_id', Auth::guard('contact')->user()->company->id)
                                            ->whereNotNull('subscription_id')
                                            ->where('status_id', RecurringInvoice::STATUS_ACTIVE)
                                            ->orderBy('id', 'desc')
                                            ->first();

                $monthly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 6)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $yearly_plans = Subscription::on('db-ninja-01')
                                             ->where('company_id', Auth::guard('contact')->user()->company->id)
                                             ->where('group_id', 31)
                                             ->orderBy('promo_price', 'ASC')
                                             ->get();

                $monthly_plans = $monthly_plans->merge($yearly_plans);

                $current_subscription_id = $recurring_invoice ? $this->encodePrimaryKey($recurring_invoice->subscription_id) : false;

                //remove existing subscription
                if ($current_subscription_id) {
                    $monthly_plans = $monthly_plans->filter(function ($plan) use ($current_subscription_id) {
                        return (string) $plan->hashed_id != (string) $current_subscription_id;
                    });
                }

                $data['account'] = $account;
                $data['client'] = Auth::guard('contact')->user()->client;
                $data['plans'] = $monthly_plans;
                $data['current_subscription_id'] = $current_subscription_id;
                $data['current_recurring_id'] = $recurring_invoice ? $recurring_invoice->hashed_id : false;

                return $this->render('plan.index', $data);
            }
        } else {
            return redirect('/client/dashboard');
        }
    }
}

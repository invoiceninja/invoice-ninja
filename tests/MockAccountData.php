<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests;

use App\DataMapper\ClientRegistrationFields;
use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Factory\CompanyUserFactory;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\InvoiceToRecurringInvoiceFactory;
use App\Factory\PurchaseOrderFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Models\Account;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyToken;
use App\Models\Country;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GroupSetting;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Scheduler;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use App\Utils\TruthSource;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Class MockAccountData.
 */
trait MockAccountData
{
    use MakesHash;
    use GeneratesCounter;

    /**
     * @var
     */
    public $project;

    /**
     * @var
     */
    public $account;

    /**
     * @var
     */
    public $company;

    /**
     * @var
     */
    public $user;

    /**
     * @var
     */
    public $client;

    /**
     * @var
     */
    public $token;

    /**
     * @var
     */
    public $recurring_expense;

    /**
     * @var
     */
    public $recurring_quote;

    /**
     * @var \App\Models\Credit
     */
    public $credit;

    /**
     * @var \App\Models\Invoice
     */
    public $invoice;

    /**
     * @var
     */
    public $quote;

    /**
     * @var
     */
    public $vendor;

    /**
     * @var
     */
    public $expense;

    /**
     * @var
     */
    public $task;

    /**
     * @var
     */
    public $task_status;

    /**
     * @var
     */
    public $expense_category;

    /**
     * @var
     */
    public $cu;

    /**
     * @var
     */
    public $bank_integration;

    /**
     * @var
     */
    public $bank_transaction;

    /**
     * @var
     */
    public $bank_transaction_rule;


    /**
     * @var
     */
    public $payment;

    /**
     * @var
     */
    public $tax_rate;

    /**
     * @var
     */
    public $scheduler;

    /**
     * @var
     */
    public $purchase_order;

    public $contact;

    public $product;

    public $recurring_invoice;

    public function makeTestData()
    {
        config(['database.default' => config('ninja.db.default')]);

        if(Country::count() == 0){
            Artisan::call('db:seed', ['--force' => true]);
        }

        $this->faker = \Faker\Factory::create();
        $fake_email = $this->faker->email();

        $this->account = Account::factory()->create([
            'hosted_client_count' => 1000000,
            'hosted_company_count' => 1000000,
            'account_sms_verified' => true,
        ]);

        $this->account->num_users = 3;
        $this->account->save();

        $this->company = Company::factory()->create([
            'account_id' => $this->account->id,
        ]);

        $this->company->client_registration_fields = ClientRegistrationFields::generate();

        Storage::makeDirectory($this->company->company_key.'/documents', 0755, true);
        Storage::makeDirectory($this->company->company_key.'/images', 0755, true);

        $settings = CompanySettings::defaults();

        $settings->company_logo = 'https://pdf.invoicing.co/favicon-v2.png';
        $settings->website = 'www.invoiceninja.com';
        $settings->address1 = 'Address 1';
        $settings->address2 = 'Address 2';
        $settings->city = 'City';
        $settings->state = 'State';
        $settings->postal_code = 'Postal Code';
        $settings->phone = '555-343-2323';
        $settings->email = $fake_email;
        $settings->country_id = '840';
        $settings->vat_number = 'vat number';
        $settings->id_number = 'id number';
        $settings->use_credits_payment = 'always';
        $settings->timezone_id = '1';
        $settings->entity_send_time = 0;

        $this->company->track_inventory = true;
        $this->company->settings = $settings;
        $this->company->save();

        $this->account->default_company_id = $this->company->id;
        $this->account->plan = 'pro';
        $this->account->plan_expires = now()->addMonth();
        $this->account->plan_term = "month";
        $this->account->save();

        $user = User::whereEmail($fake_email)->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $this->account->id,
                'confirmation_code' => $this->createDbHash(config('database.default')),
                'email' =>  $fake_email,
            ]);
        }

        $user->password = Hash::make('ALongAndBriliantPassword');

        $user_id = $user->id;
        $this->user = $user;

        // auth()->login($user);
        // auth()->user()->setCompany($this->company);

        CreateCompanyTaskStatuses::dispatchSync($this->company, $this->user);

        $this->cu = CompanyUserFactory::create($user->id, $this->company->id, $this->account->id);
        $this->cu->is_owner = true;
        $this->cu->is_admin = true;
        $this->cu->is_locked = false;
        $this->cu->save();

        $this->token = \Illuminate\Support\Str::random(64);

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $this->company->id;
        $company_token->account_id = $this->account->id;
        $company_token->name = 'test token';
        $company_token->token = $this->token;
        $company_token->is_system = true;

        $company_token->save();

        $truth = app()->make(TruthSource::class);
        $truth->setCompanyUser($company_token->first());
        $truth->setUser($this->user);
        $truth->setCompany($this->company);

        //todo create one token with token name TOKEN - use firstOrCreate

        Product::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $this->client = Client::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        Storage::makeDirectory($this->company->company_key.'/'.$this->client->client_hash.'/invoices', 0755, true);
        Storage::makeDirectory($this->company->company_key.'/'.$this->client->client_hash.'/credits', 0755, true);
        Storage::makeDirectory($this->company->company_key.'/'.$this->client->client_hash.'/quotes', 0755, true);

        $contact = ClientContact::factory()->create([
            'user_id' => $user_id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $this->contact = $contact;

        $this->payment = Payment::factory()->create([
            'user_id' => $user_id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'amount' => 10,
        ]);

        $contact2 = ClientContact::factory()->create([
            'user_id' => $user_id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
            'send_email' => true,
        ]);

        $this->vendor = Vendor::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'currency_id' => 1,
            // 'country_id' => 840,
        ]);

        $vendor_contact = VendorContact::factory()->create([
            'user_id' => $user_id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $this->company->id,
            'is_primary' => 1,
            'send_email' => true,
        ]);

        $vendor_contact2 = VendorContact::factory()->create([
            'user_id' => $user_id,
            'vendor_id' => $this->vendor->id,
            'company_id' => $this->company->id,
            'send_email' => true,
        ]);

        $this->project = Project::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $this->product = Product::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $this->recurring_invoice = RecurringInvoice::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $this->expense = Expense::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $this->recurring_expense = RecurringExpense::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'frequency_id' => 5,
            'remaining_cycles' => 5,
        ]);

        $this->recurring_quote = RecurringQuote::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_id' => $this->client->id,
        ]);

        $this->task = Task::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $this->task->status_id = TaskStatus::where('company_id', $this->company->id)->first()->id;
        $this->task->save();

        $this->expense_category = ExpenseCategory::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $this->task_status = TaskStatus::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $this->tax_rate = TaxRate::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $gs = new GroupSetting();
        $gs->name = 'Test';
        $gs->company_id = $this->client->company_id;
        $gs->settings = ClientSettings::buildClientSettings($this->company->settings, $this->client->settings);

        $gs_settings = $gs->settings;
        $gs_settings->website = 'http://staging.invoicing.co';
        $gs->settings = $gs_settings;
        $gs->save();

        $this->client->group_settings_id = $gs->id;
        $this->client->save();

        $this->invoice = InvoiceFactory::create($this->company->id, $user_id); //stub the company and user_id
        $this->invoice->client_id = $this->client->id;

        $this->invoice->line_items = $this->buildLineItems();
        $this->invoice->uses_inclusive_taxes = false;

        $this->invoice->save();

        $this->invoice_calc = new InvoiceSum($this->invoice);
        $this->invoice_calc->build();

        $this->invoice = $this->invoice_calc->getInvoice();

        $this->invoice->setRelation('client', $this->client);
        $this->invoice->setRelation('company', $this->company);

        $this->invoice->save();

        $this->invoice->load('client');

        InvoiceInvitation::factory()->create([
            'user_id' => $this->invoice->user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact->id,
            'invoice_id' => $this->invoice->id,
        ]);

        InvoiceInvitation::factory()->create([
            'user_id' => $this->invoice->user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact2->id,
            'invoice_id' => $this->invoice->id,
        ]);

        $this->invoice->fresh()->service()->markSent();
        // $this->invoice->service()->markSent();

        $this->quote = Quote::factory()->create([
            'user_id' => $user_id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        $this->quote->line_items = $this->buildLineItems();
        $this->quote->uses_inclusive_taxes = false;

        $this->quote->save();

        $this->quote_calc = new InvoiceSum($this->quote);
        $this->quote_calc->build();

        $this->quote = $this->quote_calc->getQuote();

        $this->quote->status_id = Quote::STATUS_SENT;
        $this->quote->number = $this->getNextQuoteNumber($this->client, $this->quote);

        //$this->quote->service()->createInvitations()->markSent();

        QuoteInvitation::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact->id,
            'quote_id' => $this->quote->id,
        ]);

        QuoteInvitation::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact2->id,
            'quote_id' => $this->quote->id,
        ]);

        $this->quote->setRelation('client', $this->client);
        $this->quote->setRelation('company', $this->company);

        $this->quote->save();


        $this->credit = Credit::factory()->create([
            'user_id' => $user_id,
            'client_id' => $this->client->id,
            'company_id' => $this->company->id,
        ]);

        $this->credit->line_items = $this->buildLineItems();
        $this->credit->uses_inclusive_taxes = false;

        $this->credit->save();

        $this->credit_calc = new InvoiceSum($this->credit);
        $this->credit_calc->build();

        $this->credit = $this->credit_calc->getCredit();

        $this->credit->status_id = Quote::STATUS_SENT;
        $this->credit->number = $this->getNextCreditNumber($this->client, $this->credit);


        CreditInvitation::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact->id,
            'credit_id' => $this->credit->id,
        ]);

        CreditInvitation::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact2->id,
            'credit_id' => $this->credit->id,
        ]);

        $this->credit->setRelation('client', $this->client);
        $this->credit->setRelation('company', $this->company);

        $this->credit->save();

        $this->credit->service()->createInvitations()->markSent();


        $this->purchase_order = PurchaseOrderFactory::create($this->company->id, $user_id);
        $this->purchase_order->vendor_id = $this->vendor->id;

        $this->purchase_order->amount = 10;
        $this->purchase_order->balance = 10;

        $this->purchase_order->tax_name1 = '';
        $this->purchase_order->tax_name2 = '';
        $this->purchase_order->tax_name3 = '';

        $this->purchase_order->tax_rate1 = 0;
        $this->purchase_order->tax_rate2 = 0;
        $this->purchase_order->tax_rate3 = 0;

        $this->purchase_order->line_items = InvoiceItemFactory::generate(5);

        $this->purchase_order->uses_inclusive_taxes = false;
        $this->purchase_order->save();

        PurchaseOrderInvitation::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'vendor_contact_id' => $vendor_contact->id,
            'purchase_order_id' => $this->purchase_order->id,
        ]);

        $this->purchase_order->service()->markSent();

        $this->purchase_order->setRelation('vendor', $this->vendor);
        $this->purchase_order->setRelation('company', $this->company);

        $this->purchase_order->save();

        $this->credit = CreditFactory::create($this->company->id, $user_id);
        $this->credit->client_id = $this->client->id;

        $this->credit->line_items = $this->buildLineItems();
        $this->credit->amount = 10;
        $this->credit->balance = 10;

        // $this->credit->due_date = now()->addDays(200);

        $this->credit->tax_name1 = '';
        $this->credit->tax_name2 = '';
        $this->credit->tax_name3 = '';

        $this->credit->tax_rate1 = 0;
        $this->credit->tax_rate2 = 0;
        $this->credit->tax_rate3 = 0;

        $this->credit->uses_inclusive_taxes = false;
        $this->credit->save();

        $this->credit_calc = new InvoiceSum($this->credit);
        $this->credit_calc->build();

        $this->credit = $this->credit_calc->getCredit();

        $this->client->service()->adjustCreditBalance($this->credit->balance)->save();
        $this->credit->ledger()->updateCreditBalance($this->credit->balance)->save();
        $this->credit->number = $this->getNextCreditNumber($this->client, $this->credit);

        CreditInvitation::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact->id,
            'credit_id' => $this->credit->id,
        ]);

        CreditInvitation::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'client_contact_id' => $contact2->id,
            'credit_id' => $this->credit->id,
        ]);

        $this->bank_integration = BankIntegration::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'account_id' => $this->account->id,
        ]);

        $this->bank_transaction = BankTransaction::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'bank_integration_id' => $this->bank_integration->id,
        ]);

        BankTransaction::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'bank_integration_id' => $this->bank_integration->id,
        ]);

        BankTransaction::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'bank_integration_id' => $this->bank_integration->id,
        ]);

        BankTransaction::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'bank_integration_id' => $this->bank_integration->id,
        ]);

        BankTransaction::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
            'bank_integration_id' => $this->bank_integration->id,
        ]);

        $this->bank_transaction_rule = BankTransactionRule::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $invitations = CreditInvitation::whereCompanyId($this->credit->company_id)
                                        ->whereCreditId($this->credit->id);

        $this->credit->setRelation('invitations', $invitations);

        $this->credit->service()->markSent();

        $this->credit->setRelation('client', $this->client);
        $this->credit->setRelation('company', $this->company);

        $this->credit->save();

        $contacts = $this->invoice->client->contacts;

        $contacts->each(function ($contact) {
            $invitation = InvoiceInvitation::whereCompanyId($this->invoice->company_id)
                                        ->whereClientContactId($contact->id)
                                        ->whereInvoiceId($this->invoice->id)
                                        ->first();

            if (! $invitation && $contact->send_email) {
                $ii = InvoiceInvitationFactory::create($this->invoice->company_id, $this->invoice->user_id);
                $ii->key = $this->createDbHash(config('database.default'));
                $ii->invoice_id = $this->invoice->id;
                $ii->client_contact_id = $contact->id;
                $ii->save();
            } elseif ($invitation && ! $contact->send_email) {
                $invitation->delete();
            }
        });

        $invitations = InvoiceInvitation::whereCompanyId($this->invoice->company_id)
                                        ->whereInvoiceId($this->invoice->id);

        $this->invoice->setRelation('invitations', $invitations);

        $this->invoice->save();

        $this->invoice->ledger()->updateInvoiceBalance($this->invoice->amount);

        $user_id = $this->invoice->user_id;

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $user_id;
        $recurring_invoice->next_send_date = Carbon::now();
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = Carbon::now();
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $user_id;
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(2);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = Carbon::now();
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $user_id;
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = Carbon::now();
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $user_id;
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(15);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = Carbon::now();
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $user_id;
        $recurring_invoice->next_send_date = Carbon::now()->addMinutes(20);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = Carbon::now();
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $recurring_invoice = InvoiceToRecurringInvoiceFactory::create($this->invoice);
        $recurring_invoice->user_id = $user_id;
        $recurring_invoice->next_send_date = Carbon::now()->addDays(10);
        $recurring_invoice->status_id = RecurringInvoice::STATUS_ACTIVE;
        $recurring_invoice->remaining_cycles = 2;
        $recurring_invoice->next_send_date = Carbon::now()->addDays(10);
        $recurring_invoice->save();

        $recurring_invoice->number = $this->getNextRecurringInvoiceNumber($this->invoice->client, $this->invoice);
        $recurring_invoice->save();

        $gs = new GroupSetting();
        $gs->company_id = $this->company->id;
        $gs->user_id = $user_id;
        $gs->settings = ClientSettings::buildClientSettings(CompanySettings::defaults(), ClientSettings::defaults());
        $gs->name = 'Default Client Settings';
        $gs->save();

        if (config('ninja.testvars.stripe')) {
            $data = [];
            $data[1]['min_limit'] = 22;
            $data[1]['max_limit'] = 65317;
            $data[1]['fee_amount'] = 0.00;
            $data[1]['fee_percent'] = 0.000;
            $data[1]['fee_tax_name1'] = '';
            $data[1]['fee_tax_rate1'] = '';
            $data[1]['fee_tax_name2'] = '';
            $data[1]['fee_tax_rate2'] = '';
            $data[1]['fee_tax_name3'] = '';
            $data[1]['fee_tax_rate3'] = 0;
            $data[1]['fee_cap'] = '';
            $data[1]['is_enabled'] = true;

            $cg = new CompanyGateway();
            $cg->company_id = $this->company->id;
            $cg->user_id = $user_id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->fees_and_limits = $data;
            $cg->save();


            $cg = new CompanyGateway();
            $cg->company_id = $this->company->id;
            $cg->user_id = $user_id;
            $cg->gateway_key = 'd14dd26a37cecc30fdd65700bfb55b23';
            $cg->require_cvv = true;
            $cg->require_billing_address = true;
            $cg->require_shipping_address = true;
            $cg->update_details = true;
            $cg->fees_and_limits = $data;
            $cg->config = encrypt(config('ninja.testvars.stripe'));
            $cg->save();
        }

        $this->client = $this->client->fresh();
        $this->invoice = $this->invoice->fresh();

        $this->scheduler = Scheduler::factory()->create([
            'user_id' => $user_id,
            'company_id' => $this->company->id,
        ]);

        $this->scheduler->save();
    }

    /**
     * @return array
     */
    private function buildLineItems()
    {
        $line_items = [];

        $item = InvoiceItemFactory::create();
        $item->quantity = 1;
        $item->notes = $this->faker->sentence;
        $item->cost = 10;
        $item->task_id = $this->encodePrimaryKey($this->task->id);
        $item->expense_id = $this->encodePrimaryKey($this->expense->id);

        $line_items[] = $item;

        return $line_items;
    }

}

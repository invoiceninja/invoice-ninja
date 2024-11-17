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

namespace App\Jobs\Util;

use App\DataMapper\Analytics\MigrationFailure;
use App\DataMapper\CompanySettings;
use App\Exceptions\ClientHostedMigrationException;
use App\Exceptions\MigrationValidatorFailed;
use App\Exceptions\ResourceDependencyMissing;
use App\Factory\ClientFactory;
use App\Factory\CompanyLedgerFactory;
use App\Factory\CreditFactory;
use App\Factory\InvoiceFactory;
use App\Factory\PaymentFactory;
use App\Factory\ProductFactory;
use App\Factory\QuoteFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Factory\TaxRateFactory;
use App\Factory\UserFactory;
use App\Factory\VendorFactory;
use App\Http\Requests\Company\UpdateCompanyRequest;
use App\Http\ValidationRules\ValidCompanyGatewayFeesAndLimitsRule;
use App\Http\ValidationRules\ValidUserForCompany;
use App\Jobs\Company\CreateCompanyToken;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Ninja\CheckCompanyData;
use App\Libraries\MultiDB;
use App\Mail\Migration\StripeConnectMigration;
use App\Mail\MigrationCompleted;
use App\Models\Activity;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\Credit;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Repositories\ClientContactRepository;
use App\Repositories\ClientRepository;
use App\Repositories\CompanyRepository;
use App\Repositories\CreditRepository;
use App\Repositories\Migration\InvoiceMigrationRepository;
use App\Repositories\Migration\PaymentMigrationRepository;
use App\Repositories\ProductRepository;
use App\Repositories\UserRepository;
use App\Repositories\VendorContactRepository;
use App\Repositories\VendorRepository;
use App\Utils\Ninja;
use App\Utils\Traits\CleanLineItems;
use App\Utils\Traits\CompanyGatewayFeesAndLimitsSaver;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\SavesDocuments;
use App\Utils\Traits\Uploadable;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\UploadedFile;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Turbo124\Beacon\Facades\LightLogs;

class Import implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use CompanyGatewayFeesAndLimitsSaver;
    use MakesHash;
    use CleanLineItems;
    use Uploadable;
    use SavesDocuments;

    private string $file_path; //the file path - using a different JSON parser here.

    /**
     * @var Company
     */
    private $company;

    private $token;

    /**
     * @var array
     */
    private $available_imports = [
        'account',
        'company',
        'users',
        'payment_terms',
        'tax_rates',
        'clients',
        'company_gateways',
        'client_gateway_tokens',
        'vendors',
        'projects',
        'products',
        'credits',
        'recurring_invoices',
        'invoices',
        'quotes',
        'payments',
        'expense_categories',
        'task_statuses',
        'expenses',
        'recurring_expenses',
        'tasks',
        'documents',
        'activities',
    ];

    /**
     * @var User
     */
    private $user;

    /**
     * Custom list of resources to be imported.
     *
     * @var array
     */
    private $resources;

    /**
     * Local state manager for ids.
     *
     * @var array
     */
    private $ids = [];

    public $tries = 1;

    public $timeout = 10000000;

    public $silent_migration;

    // public $backoff = 86430;

    //  public $maxExceptions = 2;
    /**
     * Create a new job instance.
     *
     * @param string $file_path
     * @param Company $company
     * @param User $user
     * @param array $resources
     * @param bool $silent_migration
     */
    public function __construct(string $file_path, Company $company, User $user, array $resources = [], $silent_migration = false)
    {
        $this->file_path = $file_path;
        $this->company = $company;
        $this->user = $user;
        $this->resources = $resources;
        $this->silent_migration = $silent_migration;
    }

    public function middleware()
    {
        return [(new WithoutOverlapping($this->company->company_key))];
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        set_time_limit(0);

        nlog("Starting Migration");
        nlog($this->user->email);
        nlog("Company ID = ");
        nlog($this->company->id);

        auth()->login($this->user, false);

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $user->setCompany($this->company);

        $array = json_decode(file_get_contents($this->file_path), true);
        $data = $array['data'];

        foreach ($this->available_imports as $import) {
            if (! array_key_exists($import, $data)) {
                info("Resource {$import} is not available for migration.");
                continue;
            }

            $method = sprintf('process%s', Str::ucfirst(Str::camel($import)));

            info("Importing {$import}");

            $this->{$method}($data[$import]);
        }

        $task_statuses = [
            ['name' => ctrans('texts.backlog'), 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now(), 'status_order' => 1],
            ['name' => ctrans('texts.ready_to_do'), 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now(), 'status_order' => 2],
            ['name' => ctrans('texts.in_progress'), 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now(), 'status_order' => 3],
            ['name' => ctrans('texts.done'), 'company_id' => $this->company->id, 'user_id' => $this->user->id, 'created_at' => now(), 'updated_at' => now(), 'status_order' => 4],
        ];

        TaskStatus::insert($task_statuses);

        $account = $this->company->account;
        $account->default_company_id = $this->company->id;
        $account->is_migrated = true;
        $account->save();

        //company size check
        if ($this->company->invoices()->count() > 500 || $this->company->products()->count() > 500 || $this->company->clients()->count() > 500) {
            $this->company->account->companies()->update(['is_large' => true]);
        }

        $this->company->smtp_port = (int)$this->company->smtp_port;
        $this->company->client_registration_fields = \App\DataMapper\ClientRegistrationFields::generate();
        $this->company->save();

        $this->setInitialCompanyLedgerBalances();

        // $this->fixClientBalances();
        $check_data = (new CheckCompanyData($this->company, md5(time())))->handle(); //@phpstan-ignore-line

        // if(Ninja::isHosted() && array_key_exists('ninja_tokens', $data))
        $this->processNinjaTokens($data['ninja_tokens']);

        // $this->fixData();
        try {
            App::forgetInstance('translator');
            $t = app('translator');
            $t->replace(Ninja::transformTranslations($this->company->settings));

            if(!$this->silent_migration) {
                Mail::to($this->user->email, $this->user->name())->send(new MigrationCompleted($this->company->id, $this->company->db, implode("<br>", $check_data)));
            }

        } catch(\Exception $e) {
            nlog($e->getMessage());
        }

        /*After a migration first some basic jobs to ensure the system is up to date*/
        if (Ninja::isSelfHost()) {
            VersionCheck::dispatch();
        }

        info('Completed🚀🚀🚀🚀🚀 at '.now());

        try {
            unlink($this->file_path);
        } catch(\Exception $e) {
            nlog("problem unsetting file");
        }
    }

    private function fixData()
    {
        $this->company->clients()->withTrashed()->where('is_deleted', 0)->cursor()->each(function ($client) {
            $total_invoice_payments = 0;
            $credit_total_applied = 0;

            foreach ($client->invoices()->where('is_deleted', false)->where('status_id', '>', 1)->get() as $invoice) {
                $total_amount = $invoice->payments()->where('is_deleted', false)->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])->get()->sum('pivot.amount');
                $total_refund = $invoice->payments()->where('is_deleted', false)->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])->get()->sum('pivot.refunded');

                $total_invoice_payments += ($total_amount - $total_refund);
            }

            // 10/02/21
            foreach ($client->payments as $payment) {
                $credit_total_applied += $payment->paymentables()->where('paymentable_type', \App\Models\Credit::class)->get()->sum('amount');
            }

            if ($credit_total_applied < 0) {
                $total_invoice_payments += $credit_total_applied;
            }


            if (round($total_invoice_payments, 2) != round($client->paid_to_date, 2)) {
                $client->paid_to_date = $total_invoice_payments;
                $client->saveQuietly();
            }
        });
    }

    private function setInitialCompanyLedgerBalances()
    {
        Client::query()->where('company_id', $this->company->id)->cursor()->each(function ($client) {
            $invoice_balances = $client->invoices->where('is_deleted', false)->where('status_id', '>', 1)->sum('balance');

            $company_ledger = CompanyLedgerFactory::create($client->company_id, $client->user_id);
            $company_ledger->client_id = $client->id;
            $company_ledger->adjustment = $invoice_balances;
            $company_ledger->notes = 'Migrated Client Balance';
            $company_ledger->balance = $invoice_balances;
            $company_ledger->activity_id = Activity::CREATE_CLIENT;
            $company_ledger->saveQuietly();

            $client->company_ledger()->save($company_ledger);

            $client->balance = $invoice_balances;
            $client->saveQuietly();
        });
    }

    private function processAccount(array $data): void
    {
        if (array_key_exists('token', $data)) {
            $this->token = $data['token'];
            unset($data['token']);
        }

        $account = $this->company->account;

        /* If the user has upgraded their account, do not wipe their payment plan*/
        if ($account->isPaid() || (isset($data['plan']) && $data['plan'] == 'white_label')) {
            if (isset($data['plan'])) {
                unset($data['plan']);
            }

            if (isset($data['plan_term'])) {
                unset($data['plan_term']);
            }

            if (isset($data['plan_paid'])) {
                unset($data['plan_paid']);
            }

            if (isset($data['plan_started'])) {
                unset($data['plan_started']);
            }

            if (isset($data['plan_expires'])) {
                unset($data['plan_expires']);
            }
        } else {

            if(isset($data['plan'])) {
                $account->plan = $data['plan'];
            }

            if (isset($data['plan_term'])) {
                $account->plan_term = $data['plan_term'];
            }

            if (isset($data['plan_paid'])) {
                $account->plan_paid = $data['plan_paid'];
            }

            if (isset($data['plan_started'])) {
                $account->plan_started = $data['plan_started'];
            }

            if (isset($data['plan_expires'])) {
                $account->plan_expires = $data['plan_expires'];
            }

        }

        $account->fill($data);
        $account->save();

        //Prevent hosted users being pushed into a trial
        if (Ninja::isHosted() && $account->plan != '') {
            $account->trial_plan = '';
            $account->save();
        }
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processCompany(array $data): void
    {
        Company::unguard();

        if (
            $data['settings']['invoice_design_id'] > 9 ||
            $data['settings']['invoice_design_id'] > "9"
        ) {
            $data['settings']['invoice_design_id'] = 1;
        }
        $data['settings']['email_sending_method'] = 'default';

        $data = $this->transformCompanyData($data);

        if (Ninja::isHosted()) {

            $data['subdomain'] = str_replace("_", "", ($data['subdomain'] ?? ''));

            if (!MultiDB::checkDomainAvailable($data['subdomain'])) {
                $data['subdomain'] = MultiDB::randomSubdomainGenerator();
            }

            if (strlen($data['subdomain']) == 0) {
                $data['subdomain'] = MultiDB::randomSubdomainGenerator();
            }
        }

        // $rules = (new UpdateCompanyRequest())->rules();

        // unset($rules['expense_mailbox']);
        // unset($rules['e_invoice']);
        // unset($rules['smtp_port']);
        // unset($rules['settings']);

        // $validator = Validator::make($data, $rules);

        // nlog($validator->errors());

        // if ($validator->fails()) {
        //     throw new MigrationValidatorFailed(json_encode($validator->errors()));
        // }

        if (isset($data['account_id'])) {
            unset($data['account_id']);
        }

        if (isset($data['version'])) {
            unset($data['version']);
        }

        if (isset($data['referral_code'])) {
            $account = $this->company->account;
            $account->referral_code = $data['referral_code'];
            $account->save();

            unset($data['referral_code']);
        }

        if (isset($data['custom_fields']) && is_array($data['custom_fields'])) {
            $data['custom_fields'] = $this->parseCustomFields($data['custom_fields']);
        }

        $company_repository = new CompanyRepository();
        $company_repository->save($data, $this->company);

        if (isset($data['settings']->company_logo) && strlen($data['settings']->company_logo) > 0) {
            try {
                $tempImage = tempnam(sys_get_temp_dir(), basename($data['settings']->company_logo));
                copy($data['settings']->company_logo, $tempImage);
                $this->uploadLogo($tempImage, $this->company, $this->company);
            } catch (\Exception $e) {
                $settings = $this->company->settings;
                $settings->company_logo = '';
                $this->company->settings = $settings;
                $this->company->save();
            }
        }

        Company::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
        $company_repository = null;
    }

    private function parseCustomFields($fields): array
    {
        if (array_key_exists('account1', $fields)) {
            $fields['company1'] = $fields['account1'];
        }

        if (array_key_exists('account2', $fields)) {
            $fields['company2'] = $fields['account2'];
        }

        if (array_key_exists('invoice1', $fields)) {
            $fields['surcharge1'] = $fields['invoice1'];
        }

        if (array_key_exists('invoice2', $fields)) {
            $fields['surcharge2'] = $fields['invoice2'];
        }

        if (array_key_exists('invoice_text1', $fields)) {
            $fields['invoice1'] = $fields['invoice_text1'];
        }

        if (array_key_exists('invoice_text2', $fields)) {
            $fields['invoice2'] = $fields['invoice_text2'];
        }

        foreach ($fields as &$value) {
            $value = (string) $value;
        }

        return $fields;
    }

    private function transformCompanyData(array $data): array
    {
        $company_settings = CompanySettings::defaults();

        if (array_key_exists('settings', $data)) {
            foreach ($data['settings'] as $key => $value) {
                if ($key == 'invoice_design_id' || $key == 'quote_design_id' || $key == 'credit_design_id') {
                    $value = $this->encodePrimaryKey($value);

                    if (!$value) {
                        $value = $this->encodePrimaryKey(1);
                    }
                }

                /* changes $key = '' to $value == '' and changed the return value from -1 to "0" 06/01/2022 */
                if ($key == 'payment_terms' && $value == '') {
                    $value = "0";
                }

                $company_settings->{$key} = $value;

                if ($key == 'payment_terms') {
                    settype($company_settings->payment_terms, 'string');
                }
            }

            if (Ninja::isHosted()) {
                $data['portal_mode'] = 'subdomain';
                $data['portal_domain'] = '';
            }

            $company_settings->font_size = 16;

            $data['settings'] = $company_settings;
        }


        return $data;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processTaxRates(array $data): void
    {
        TaxRate::unguard();

        $rules = [
            '*.name' => 'required',
            //'*.name' => 'required|distinct|unique:tax_rates,name,null,null,company_id,' . $this->company->id,
            '*.rate' => 'required|numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        foreach ($data as $resource) {
            $modified = $resource;
            $company_id = $this->company->id;
            $user_id = $this->processUserId($resource);

            if (isset($resource['user_id'])) {
                unset($resource['user_id']);
            }

            if (isset($resource['company_id'])) {
                unset($resource['company_id']);
            }

            $tax_rate = TaxRateFactory::create($this->company->id, $user_id);
            $tax_rate->fill($resource);

            $tax_rate->save();
        }

        TaxRate::reguard();

        if (TaxRate::count() > 0) {
            $this->company->enabled_tax_rates = 2;
            $this->company->save();
        }

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
    }

    private function testUserDbLocationSanity(array $data): bool
    {
        if (Ninja::isSelfHost()) {
            return true;
        }

        $current_db = config('database.default');

        $db1_count = User::on('db-ninja-01')->withTrashed()->whereIn('email', array_column($data, 'email'))->count();
        $db2_count = User::on('db-ninja-02')->withTrashed()->whereIn('email', array_column($data, 'email'))->count();

        MultiDB::setDb($current_db);

        if ($db2_count == 0 && $db1_count == 0) {
            return true;
        }

        if ($db1_count >= 1 && $db2_count >= 1) {
            return false;
        }

        return true;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processUsers(array $data): void
    {
        if (!$this->testUserDbLocationSanity($data)) {
            throw new ClientHostedMigrationException('You have users that belong to different accounts registered in the system, please contact us to resolve.', 400);
        }

        User::unguard();

        $rules = [
            '*.first_name' => ['string'],
            '*.last_name' => ['string'],
            '*.email' => ['distinct', 'email', new ValidUserForCompany()],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $user_repository = new UserRepository();

        foreach ($data as $resource) {
            $modified = $resource;
            unset($modified['id']);
            // unset($modified['password']); //cant import passwords.
            unset($modified['confirmation_code']); //cant import passwords.
            unset($modified['oauth_user_id']);
            unset($modified['oauth_provider_id']);

            $user = $user_repository->save($modified, $this->fetchUser($resource['email']), true, true);
            $user->email_verified_at = now();

            if ($modified['deleted_at']) {
                $user->deleted_at = now();
            }

            $user->password = $modified['password'];
            $user->save();

            $user_agent = array_key_exists('token_name', $resource) ?: request()->server('HTTP_USER_AGENT');

            (new CreateCompanyToken($this->company, $user, $user_agent))->handle();

            $key = "users_{$resource['id']}";

            $this->ids['users'][$key] = [
                'old' => $resource['id'],
                'new' => $user->id,
            ];
        }

        User::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $rules = null;
        $validator = null;
        $user_repository = null;
    }

    private function checkUniqueConstraint($model, $column, $value)
    {
        $value = trim($value);

        $model_query = $model::where($column, $value)
                             ->where('company_id', $this->company->id)
                             ->withTrashed()
                             ->exists();

        if ($model_query) {
            return $value . '_' . Str::random(5);
        }

        return $value;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processClients(array $data): void
    {
        Client::unguard();

        $contact_repository = new ClientContactRepository();
        $client_repository = new ClientRepository($contact_repository);

        foreach ($data as $key => $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);
            $modified['balance'] = $modified['balance'] ?: 0;
            $modified['paid_to_date'] = $modified['paid_to_date'] ?: 0;
            $modified['number'] = $this->checkUniqueConstraint(Client::class, 'number', $modified['number']);

            unset($modified['id']);
            unset($modified['contacts']);

            $client = $client_repository->save(
                $modified,
                ClientFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );

            if (array_key_exists('created_at', $modified)) {
                $client->created_at = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $client->updated_at = Carbon::parse($modified['updated_at']);
            }

            $client->country_id = array_key_exists('country_id', $modified) ? $modified['country_id'] : $this->company->settings->country_id;
            $client->save(['timestamps' => false]);
            $client->fresh();

            $client->contacts()->forceDelete();

            if (array_key_exists('contacts', $resource)) { // need to remove after importing new migration.json
                $modified_contacts = $resource['contacts'];

                foreach ($modified_contacts as $key => $client_contacts) {
                    $modified_contacts[$key]['company_id'] = $this->company->id;
                    $modified_contacts[$key]['user_id'] = $this->processUserId($resource);
                    $modified_contacts[$key]['client_id'] = $client->id;
                    $modified_contacts[$key]['password'] = Str::random(8);
                    unset($modified_contacts[$key]['id']);
                }

                $saveable_contacts['contacts'] = $modified_contacts;

                $contact_repository->save($saveable_contacts, $client);

                //link contact ids

                foreach ($resource['contacts'] as $key => $old_contact) {
                    $contact_match = ClientContact::where('contact_key', $old_contact['contact_key'])
                                                 ->where('company_id', $this->company->id)
                                                 ->where('client_id', $client->id)
                                                 ->withTrashed()
                                                 ->first();

                    if ($contact_match) {
                        $this->ids['client_contacts']['client_contacts_'.$old_contact['id']] = [
                            'old' => $old_contact['id'],
                            'new' => $contact_match->id,
                        ];
                    }
                }
            }

            $key = "clients_{$resource['id']}";

            $this->ids['clients'][$key] = [
                'old' => $resource['id'],
                'new' => $client->id,
            ];

            $client = null;
        }

        Client::reguard();

        Client::withTrashed()->with('contacts')->where('company_id', $this->company->id)->cursor()->each(function ($client) {
            $contact = $client->contacts->sortByDesc('is_primary')->first();
            $contact->is_primary = true;
            $contact->save();
        });


        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $contact_repository = null;
        $client_repository = null;
    }

    /**
     * @param array $data
     * @throws Exception
     */
    private function processVendors(array $data): void
    {
        Vendor::unguard();

        $contact_repository = new VendorContactRepository();
        $vendor_repository = new VendorRepository($contact_repository);

        foreach ($data as $key => $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);
            $modified['number'] = $this->checkUniqueConstraint(Vendor::class, 'number', $modified['number']);

            unset($modified['id']);
            unset($modified['contacts']);

            if (array_key_exists('created_at', $modified)) {
                $modified['created_at'] = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $modified['updated_at'] = Carbon::parse($modified['updated_at']);
            }

            if (!array_key_exists('currency_id', $modified) || !$modified['currency_id']) {
                $modified['currency_id'] = $this->company->settings->currency_id;
            }

            $vendor = $vendor_repository->save(
                $modified,
                VendorFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );

            $vendor->contacts()->forceDelete();

            if (array_key_exists('contacts', $resource)) { // need to remove after importing new migration.json
                $modified_contacts = $resource['contacts'];

                foreach ($modified_contacts as $key => $vendor_contacts) {
                    $modified_contacts[$key]['company_id'] = $this->company->id;
                    $modified_contacts[$key]['user_id'] = $this->processUserId($resource);
                    $modified_contacts[$key]['vendor_id'] = $vendor->id;
                    $modified_contacts[$key]['password'] = 'mysuperpassword'; // @todo, and clean up the code..
                    unset($modified_contacts[$key]['id']);
                }

                $saveable_contacts['contacts'] = $modified_contacts;

                $contact_repository->save($saveable_contacts, $vendor);
            }

            $key = "vendors_{$resource['id']}";

            $this->ids['vendors'][$key] = [
                'old' => $resource['id'],
                'new' => $vendor->id,
            ];
        }

        Vendor::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $contact_repository = null;
        $client_repository = null;
    }


    private function processProducts(array $data): void
    {
        Product::unguard();

        $rules = [
            //'*.product_key' => 'required|distinct|unique:products,product_key,null,null,company_id,' . $this->company->id,
            '*.cost' => 'numeric',
            '*.price' => 'numeric',
            '*.quantity' => 'numeric',
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $product_repository = new ProductRepository();

        foreach ($data as $resource) {
            $modified = $resource;
            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (array_key_exists('created_at', $modified)) {
                $modified['created_at'] = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $modified['updated_at'] = Carbon::parse($modified['updated_at']);
            }

            unset($modified['id']);

            $product_repository->save(
                $modified,
                ProductFactory::create(
                    $this->company->id,
                    $modified['user_id']
                )
            );
        }

        Product::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $product_repository = null;
    }

    private function processRecurringExpenses(array $data): void
    {
        RecurringExpense::unguard();

        $rules = [
            '*.amount' => ['numeric'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (isset($resource['client_id'])) {
                $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            }

            if (isset($resource['category_id'])) {
                $modified['category_id'] = $this->transformId('expense_categories', $resource['category_id']);
            }

            if (isset($resource['vendor_id'])) {
                $modified['vendor_id'] = $this->transformId('vendors', $resource['vendor_id']);
            }

            /** @var \App\Models\Expense $expense */
            $expense = RecurringExpense::create($modified);

            if (array_key_exists('created_at', $modified)) {
                $expense->created_at = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $expense->updated_at = Carbon::parse($modified['updated_at']);
            }

            $expense->save(['timestamps' => false]);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "recurring_expenses_{$resource['id']}";

            $this->ids['recurring_expenses'][$key] = [
                'old' => $resource['id'],
                'new' => $expense->id,
            ];
        }


        RecurringExpense::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processRecurringInvoices(array $data): void
    {
        RecurringInvoice::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $invoice_repository = new InvoiceMigrationRepository();

        foreach ($data as $key => $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing invoices failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;
            $modified['line_items'] = $this->cleanItems($modified['line_items']);

            if (array_key_exists('next_send_date', $resource)) {
                $modified['next_send_date_client'] = $resource['next_send_date'];
            }

            if (array_key_exists('created_at', $modified)) {
                $modified['created_at'] = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $modified['updated_at'] = Carbon::parse($modified['updated_at']);
            }

            unset($modified['id']);

            if (array_key_exists('invitations', $resource)) {
                foreach ($resource['invitations'] as $key => $invite) {
                    $resource['invitations'][$key]['client_contact_id'] = $this->transformId('client_contacts', $invite['client_contact_id']);
                    $resource['invitations'][$key]['user_id'] = $modified['user_id'];
                    $resource['invitations'][$key]['company_id'] = $this->company->id;
                    $resource['invitations'][$key]['email_status'] = '';

                    unset($resource['invitations'][$key]['recurring_invoice_id']);
                    unset($resource['invitations'][$key]['id']);
                }

                $modified['invitations'] = $this->deDuplicateInvitations($resource['invitations']);
            }

            $invoice = $invoice_repository->save(
                $modified,
                RecurringInvoiceFactory::create($this->company->id, $modified['user_id'])
            );

            if ($invoice->status_id == 4 && $invoice->remaining_cycles == -1) {
                $invoice->status_id = 2;
                $invoice->save();
            }

            $key = "recurring_invoices_{$resource['id']}";

            $this->ids['recurring_invoices'][$key] = [
                'old' => $resource['id'],
                'new' => $invoice->id,
            ];
        }

        RecurringInvoice::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $invoice_repository = null;
    }

    private function processInvoices(array $data): void
    {
        Invoice::unguard();

        // $rules = [
        //     '*.client_id' => ['required'],
        // ];

        // // $validator = Validator::make($data, $rules);

        // if ($validator->fails()) {
        //     throw new MigrationValidatorFailed(json_encode($validator->errors()));
        // }

        $invoice_repository = new InvoiceMigrationRepository();

        foreach ($data as $key => $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing invoices failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);

            if (array_key_exists('recurring_id', $resource) && !is_null($resource['recurring_id'])) {
                $modified['recurring_id'] = $this->transformId('recurring_invoices', (string)$resource['recurring_id']);
            }

            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;
            $modified['line_items'] = $this->cleanItems($modified['line_items']);

            //31/08-2023 set correct paid to date here:
            $modified['paid_to_date'] = $modified['amount'] - $modified['balance'] ?? 0;

            unset($modified['id']);

            if (array_key_exists('invitations', $resource)) {
                foreach ($resource['invitations'] as $key => $invite) {
                    $resource['invitations'][$key]['client_contact_id'] = $this->transformId('client_contacts', $invite['client_contact_id']);
                    $resource['invitations'][$key]['user_id'] = $modified['user_id'];
                    $resource['invitations'][$key]['company_id'] = $this->company->id;
                    $resource['invitations'][$key]['email_status'] = '';
                    unset($resource['invitations'][$key]['invoice_id']);
                    unset($resource['invitations'][$key]['id']);
                }

                $modified['invitations'] = $this->deDuplicateInvitations($resource['invitations']);
            }

            $invoice = $invoice_repository->save(
                $modified,
                InvoiceFactory::create($this->company->id, $modified['user_id'])
            );

            $key = "invoices_{$resource['id']}";

            $this->ids['invoices'][$key] = [
                'old' => $resource['id'],
                'new' => $invoice->id,
            ];
        }

        Invoice::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $invoice_repository = null;
    }


    /* Prevent edge case where V4 has inserted multiple invitations for a resource for a client contact */
    private function deDuplicateInvitations($invitations)
    {
        return  array_intersect_key($invitations, array_unique(array_column($invitations, 'client_contact_id')));
    }

    private function processCredits(array $data): void
    {
        Credit::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $credit_repository = new CreditRepository();

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing credits failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            if (array_key_exists('created_at', $modified)) {
                $modified['created_at'] = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $modified['updated_at'] = Carbon::parse($modified['updated_at']);
            }

            unset($modified['id']);


            $credit = $credit_repository->save(
                $modified,
                CreditFactory::create($this->company->id, $modified['user_id'])
            );

            if($credit->status_id == 4) {

                $client = $credit->client;
                $client->balance -= $credit->balance;
                $client->credit_balance -= $credit->amount;
                $client->saveQuietly();

                $credit->paid_to_date = $credit->amount;
                $credit->balance = 0;
                $credit->saveQuietly();

            }

            //remove credit balance from ledger
            if ($credit->balance > 0 && $credit->client->balance > 0) {
                $client = $credit->client;
                $client->balance -= $credit->balance;
                $client->save();
            }


            $key = "credits_{$resource['id']}";

            $this->ids['credits'][$key] = [
                'old' => $resource['id'],
                'new' => $credit->id,
            ];
        }

        Credit::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $credit_repository = null;
    }

    private function processQuotes(array $data): void
    {
        Quote::unguard();

        $rules = [
            '*.client_id' => ['required'],
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        $quote_repository = new InvoiceMigrationRepository();

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing quotes failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);

            if (array_key_exists('invoice_id', $resource) && isset($resource['invoice_id']) && $this->tryTransformingId('invoices', $resource['invoice_id'])) {
                $modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
            }

            $modified['user_id'] = $this->processUserId($resource);

            $modified['company_id'] = $this->company->id;

            if (array_key_exists('created_at', $modified)) {
                $modified['created_at'] = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $modified['updated_at'] = Carbon::parse($modified['updated_at']);
            }

            if (array_key_exists('tax_rate1', $modified) && is_null($modified['tax_rate1'])) {
                $modified['tax_rate1'] = 0;
            }

            if (array_key_exists('tax_rate2', $modified) && is_null($modified['tax_rate2'])) {
                $modified['tax_rate2'] = 0;
            }

            unset($modified['id']);


            if (array_key_exists('invitations', $resource)) {
                foreach ($resource['invitations'] as $key => $invite) {
                    $resource['invitations'][$key]['client_contact_id'] = $this->transformId('client_contacts', $invite['client_contact_id']);
                    $resource['invitations'][$key]['user_id'] = $modified['user_id'];
                    $resource['invitations'][$key]['company_id'] = $this->company->id;
                    $resource['invitations'][$key]['email_status'] = '';
                    unset($resource['invitations'][$key]['quote_id']);
                    unset($resource['invitations'][$key]['id']);
                }

                $modified['invitations'] = $this->deDuplicateInvitations($resource['invitations']);
            }

            $quote = $quote_repository->save(
                $modified,
                QuoteFactory::create($this->company->id, $modified['user_id'])
            );

            if (array_key_exists('created_at', $modified)) {
                $quote->created_at = $modified['created_at'];
            }

            if (array_key_exists('updated_at', $modified)) {
                $quote->updated_at = $modified['updated_at'];
            }

            $quote->save(['timestamps' => false]);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "quotes_{$resource['id']}";

            $this->ids['quotes'][$key] = [
                'old' => $resource['id'],
                'new' => $quote->id,
            ];
        }

        Quote::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $quote_repository = null;
    }

    private function processPayments(array $data): void
    {
        Payment::reguard();

        $rules = [
            '*.amount' => ['required'],
            '*.client_id' => ['required'],
        ];

        // $validator = Validator::make($data, $rules);

        // if ($validator->fails()) {
        //     throw new MigrationValidatorFailed(json_encode($validator->errors()));
        // }

        $payment_repository = new PaymentMigrationRepository(new CreditRepository());

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('client_id', $resource) && ! array_key_exists('clients', $this->ids)) {
                throw new ResourceDependencyMissing('Processing payments failed, because of missing dependency - clients.');
            }

            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            unset($modified['invoice_id']);

            if (isset($modified['invoices'])) {
                foreach ($modified['invoices'] as $key => $invoice) {
                    if ($this->tryTransformingId('invoices', $invoice['invoice_id'])) {
                        $modified['invoices'][$key]['invoice_id'] = $this->transformId('invoices', $invoice['invoice_id']);
                    } else {
                        nlog($modified['invoices']);
                        unset($modified['invoices']);
                        //if the transformation didn't work - you _must_ unset this data as it will be incorrect!
                    }
                }
            }

            $payment = $payment_repository->save(
                $modified,
                PaymentFactory::create($this->company->id, $modified['user_id'])
            );

            if (array_key_exists('created_at', $modified)) {
                $payment->created_at = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $payment->updated_at = Carbon::parse($modified['updated_at']);
            }

            $payment->save(['timestamps' => false]);

            if (array_key_exists('company_gateway_id', $resource) && isset($resource['company_gateway_id']) && $resource['company_gateway_id'] != 'NULL') {
                if ($this->tryTransformingId('company_gateways', $resource['company_gateway_id'])) {
                    $payment->company_gateway_id = $this->transformId('company_gateways', $resource['company_gateway_id']);
                }

                $payment->save();
            }

            nlog($payment->id);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['payments'] = [
                "payments_{$old_user_key}" => [
                    'old' => $old_user_key,
                    'new' => $payment->id,
                ],
            ];

            if (in_array($payment->status_id, [Payment::STATUS_REFUNDED, Payment::STATUS_PARTIALLY_REFUNDED])) {
                $this->processPaymentRefund($payment);
            }
        }

        Payment::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
        $payment_repository = null;
    }

    private function processPaymentRefund($payment)
    {
        $invoices = $payment->invoices()->get();

        $invoices->each(function ($invoice) use ($payment) {
            if ($payment->refunded > 0 && in_array($invoice->status_id, [Invoice::STATUS_SENT])) {
                $invoice->service()
                        ->updateBalance($payment->refunded)
                        ->updatePaidToDate($payment->refunded * -1)
                        ->updateStatus()
                        ->save();
            }
        });
    }

    private function updatePaymentForStatus($payment, $status_id): Payment
    {
        // define('PAYMENT_STATUS_PENDING', 1);
        // define('PAYMENT_STATUS_VOIDED', 2);
        // define('PAYMENT_STATUS_FAILED', 3);
        // define('PAYMENT_STATUS_COMPLETED', 4);
        // define('PAYMENT_STATUS_PARTIALLY_REFUNDED', 5);
        // define('PAYMENT_STATUS_REFUNDED', 6);

        switch ($status_id) {
            case 1:
                return $payment;
            case 2:
                return $payment->service()->deletePayment();
            case 3:
                return $payment->service()->deletePayment();
            case 4:
                return $payment;
            case 5:
                $payment->status_id = Payment::STATUS_PARTIALLY_REFUNDED;
                $payment->save();
                return $payment;
            case 6:
                $payment->status_id = Payment::STATUS_REFUNDED;
                $payment->save();
                return $payment;

            default:
                return $payment;
        }
    }

    private function processDocuments(array $data): void
    {
        // Document::unguard();
        /* No validators since data provided by database is already valid. */

        foreach ($data as $resource) {
            $modified = $resource;

            if (array_key_exists('invoice_id', $resource) && $resource['invoice_id'] && ! array_key_exists('invoices', $this->ids)) {
                return;
                //throw new ResourceDependencyMissing('Processing documents failed, because of missing dependency - invoices.');
            }

            if (array_key_exists('expense_id', $resource) && $resource['expense_id'] && ! array_key_exists('expenses', $this->ids)) {
                return;
                //throw new ResourceDependencyMissing('Processing documents failed, because of missing dependency - expenses.');
            }

            if (array_key_exists('invoice_id', $resource) && $resource['invoice_id'] && array_key_exists('invoices', $this->ids)) {
                $try_quote = false;
                $exception = false;
                $entity = false;

                try {
                    $invoice_id = $this->transformId('invoices', $resource['invoice_id']);
                    $entity = Invoice::query()->where('id', $invoice_id)->withTrashed()->first();
                } catch(\Exception $e) {
                    nlog("i couldn't find the invoice document {$resource['invoice_id']}, perhaps it is a quote?");
                    nlog($e->getMessage());

                    $try_quote = true;
                }

                if ($try_quote && array_key_exists('quotes', $this->ids)) {
                    try {
                        $quote_id = $this->transformId('quotes', $resource['invoice_id']);
                        $entity = Quote::query()->where('id', $quote_id)->withTrashed()->first();
                    } catch(\Exception $e) {
                        nlog("i couldn't find the quote document {$resource['invoice_id']}, perhaps it is a quote?");
                        nlog($e->getMessage());
                    }
                }

                // throw new Exception("Resource invoice/quote document not available.");
            }

            $entity = false;

            if (array_key_exists('expense_id', $resource) && $resource['expense_id'] && array_key_exists('expenses', $this->ids)) {
                $expense_id = $this->transformId('expenses', $resource['expense_id']);
                $entity = Expense::query()->where('id', $expense_id)->withTrashed()->first();
            }

            if (!$entity) {
                continue;
            }

            $file_url = $resource['url'];
            $file_name = $resource['name'];
            $file_path = sys_get_temp_dir().'/'.$file_name;

            try {
                file_put_contents($file_path, $this->curlGet($file_url));
                $finfo = new \finfo(FILEINFO_MIME_TYPE);
                $file_info = $finfo->file($file_path);

                $uploaded_file = new UploadedFile(
                    $file_path,
                    $file_name,
                    $file_info,
                    0,
                    false
                );

                // $this->saveDocument($uploaded_file, $entity, $is_public = true);

                $document = (new \App\Jobs\Util\UploadFile(
                    $uploaded_file,
                    \App\Jobs\Util\UploadFile::DOCUMENT,
                    $this->user,
                    $this->company,
                    $entity,
                    null,
                    true
                ))->handle();


            } catch(\Exception $e) {
                nlog($e->getMessage());
                //do nothing, gracefully :)
            }
        }
    }

    private function processPaymentTerms(array $data): void
    {
        PaymentTerm::unguard();

        $modified = collect($data)->map(function ($item) {
            $item['user_id'] = $this->user->id;
            $item['company_id'] = $this->company->id;
            $item['is_deleted'] = isset($item['is_deleted']) ? $item['is_deleted'] : 0;

            return $item;
        })->toArray();

        PaymentTerm::insert($modified);

        PaymentTerm::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processCompanyGateways(array $data): void
    {
        CompanyGateway::unguard();

        $rules = [
            '*.gateway_key' => 'required',
            '*.fees_and_limits' => new ValidCompanyGatewayFeesAndLimitsRule(),
        ];

        $validator = Validator::make($data, $rules);

        if ($validator->fails()) {
            throw new MigrationValidatorFailed(json_encode($validator->errors()));
        }

        foreach ($data as $resource) {
            $modified = $resource;

            $modified['user_id'] = $this->processUserId($resource);
            $modified['company_id'] = $this->company->id;

            unset($modified['id']);

            if (isset($modified['config'])) {
                $modified['config'] = encrypt($modified['config']);
            }

            if (isset($modified['fees_and_limits'])) {
                $modified['fees_and_limits'] = $this->cleanFeesAndLimits($modified['fees_and_limits']);
            }

            if (!array_key_exists('accepted_credit_cards', $modified) || (array_key_exists('accepted_credit_cards', $modified) && empty($modified['accepted_credit_cards']))) {
                $modified['accepted_credit_cards'] = 0;
            }

            // /* On Hosted platform we need to advise Stripe users to connect with Stripe Connect */
            if (Ninja::isHosted() && $modified['gateway_key'] == 'd14dd26a37cecc30fdd65700bfb55b23') {
                $nmo = new NinjaMailerObject();
                $nmo->mailable = new StripeConnectMigration($this->company);
                $nmo->company = $this->company;
                $nmo->settings = $this->company->settings;
                $nmo->to_user = $this->user;

                if(!$this->silent_migration) {
                    NinjaMailerJob::dispatch($nmo, true);
                }

                $modified['gateway_key'] = 'd14dd26a47cecc30fdd65700bfb67b34';
            }

            if (Ninja::isSelfHost() && $modified['gateway_key'] == 'd14dd26a47cecc30fdd65700bfb67b34') {
                $modified['gateway_key'] = 'd14dd26a37cecc30fdd65700bfb55b23';
            }

            /** @var \App\Models\CompanyGateway $company_gateway */
            $company_gateway = CompanyGateway::create($modified);

            $key = "company_gateways_{$resource['id']}";

            $this->ids['company_gateways'][$key] = [
                    'old' => $resource['id'],
                    'new' => $company_gateway->id,
            ];
        }

        CompanyGateway::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processClientGatewayTokens(array $data): void
    {
        ClientGatewayToken::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            $modified['company_gateway_id'] = $this->transformId('company_gateways', $resource['company_gateway_id']);

            //$modified['user_id'] = $this->processUserId($resource);
            /** @var \App\Models\ClientGatewayToken $cgt **/
            $cgt = ClientGatewayToken::create($modified);

            $key = "client_gateway_tokens_{$resource['id']}";

            $this->ids['client_gateway_tokens'][$key] = [
                'old' => $resource['id'],
                'new' => $cgt->id,
            ];
        }

        ClientGatewayToken::reguard();

        /*Improve memory handling by setting everything to null when we have finished*/
        $data = null;
    }

    private function processTaskStatuses(array $data): void
    {
        info('in task statuses');
        TaskStatus::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            /** @var \App\Models\TaskStatus $task_status **/
            $task_status = TaskStatus::create($modified);

            $key = "task_statuses_{$resource['id']}";

            $this->ids['task_statuses'][$key] = [
                'old' => $resource['id'],
                'new' => $task_status->id,
            ];
        }

        TaskStatus::reguard();

        $data = null;
        info('finished task statuses');
    }

    private function processExpenseCategories(array $data): void
    {
        ExpenseCategory::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);
            $modified['is_deleted'] = isset($modified['is_deleted']) ? (bool)$modified['is_deleted'] : false;

            /** @var \App\Models\ExpenseCategory $expense_category **/
            $expense_category = ExpenseCategory::create($modified);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "expense_categories_{$resource['id']}";

            $this->ids['expense_categories'][$key] = [
                'old' => $resource['id'],
                'new' => $expense_category->id,
            ];
        }

        ExpenseCategory::reguard();

        $data = null;
    }

    private function processTasks(array $data): void
    {
        Task::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (isset($modified['client_id'])) {
                $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            }

            if (isset($modified['invoice_id'])) {
                $modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
            }

            if (isset($modified['project_id'])) {
                $modified['project_id'] = $this->transformId('projects', $resource['project_id']);
            }

            if (isset($modified['status_id'])) {
                $modified['status_id'] = $this->transformId('task_statuses', $resource['status_id']);
            }

            /** @var \App\Models\Task $task **/
            $task = Task::create($modified);

            if (array_key_exists('created_at', $modified)) {
                $task->created_at = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $task->updated_at = Carbon::parse($modified['updated_at']);
            }

            $task->save(['timestamps' => false]);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $this->ids['tasks'] = [
                "tasks_{$old_user_key}" => [
                    'old' => $resource['id'],
                    'new' => $task->id,
                ],
            ];
        }

        Task::reguard();

        $data = null;
    }

    private function processProjects(array $data): void
    {
        Project::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (isset($modified['client_id'])) {
                $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            }

            /** @var \App\Models\Project $project **/
            $project = Project::create($modified);

            $key = "projects_{$resource['id']}";

            $this->ids['projects'][$key] = [
                'old' => $resource['id'],
                'new' => $project->id,
            ];
        }

        Project::reguard();

        $data = null;
    }

    private function processActivities(array $data): void
    {
        Activity::query()->where('company_id', $this->company->id)->cursor()->each(function ($a) {
            $a->forceDelete();
            nlog("deleting {$a->id}");
        });

        Activity::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            try {
                if (isset($modified['client_id'])) {
                    $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
                }

                if (isset($modified['invoice_id'])) {
                    $modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
                }

                if (isset($modified['quote_id'])) {
                    $modified['quote_id'] = $this->transformId('quotes', $resource['quote_id']);
                }

                if (isset($modified['recurring_invoice_id'])) {
                    $modified['recurring_invoice_id'] = $this->transformId('recurring_invoices', $resource['recurring_invoice_id']);
                }

                if (isset($modified['payment_id'])) {
                    $modified['payment_id'] = $this->transformId('payments', $resource['payment_id']);
                }

                if (isset($modified['credit_id'])) {
                    $modified['credit_id'] = $this->transformId('credits', $resource['credit_id']);
                }

                if (isset($modified['expense_id'])) {
                    $modified['expense_id'] = $this->transformId('expenses', $resource['expense_id']);
                }

                if (isset($modified['task_id'])) {
                    $modified['task_id'] = $this->transformId('tasks', $resource['task_id']);
                }

                if (isset($modified['client_contact_id'])) {
                    $modified['client_contact_id'] = $this->transformId('client_contacts', $resource['client_contact_id']);
                }

                $modified['updated_at'] = $modified['created_at'];

                /** @var \App\Models\Activity $act **/
                $act = Activity::make($modified);

                $act->save(['timestamps' => false]);
            } catch (\Exception $e) {

                nlog("could not import activity: {$e->getMessage()}");

            }

        }


        Activity::reguard();

    }


    private function processExpenses(array $data): void
    {
        Expense::unguard();

        foreach ($data as $resource) {
            $modified = $resource;

            unset($modified['id']);

            $modified['company_id'] = $this->company->id;
            $modified['user_id'] = $this->processUserId($resource);

            if (isset($resource['client_id'])) {
                $modified['client_id'] = $this->transformId('clients', $resource['client_id']);
            }

            if (isset($resource['category_id'])) {
                $modified['category_id'] = $this->transformId('expense_categories', $resource['category_id']);
            }

            if (isset($resource['invoice_id'])) {
                $modified['invoice_id'] = $this->transformId('invoices', $resource['invoice_id']);
            }

            if (isset($resource['project_id'])) {
                $modified['project_id'] = $this->transformId('projects', $resource['project_id']);
            }

            if (isset($resource['vendor_id'])) {
                $modified['vendor_id'] = $this->transformId('vendors', $resource['vendor_id']);
            }

            $modified['tax_amount1'] = 0;
            $modified['tax_amount2'] = 0;
            $modified['tax_amount3'] = 0;

            /** @var \App\Models\Expense $expense **/
            $expense = Expense::create($modified);

            if (array_key_exists('created_at', $modified)) {
                $expense->created_at = Carbon::parse($modified['created_at']);
            }

            if (array_key_exists('updated_at', $modified)) {
                $expense->updated_at = Carbon::parse($modified['updated_at']);
            }

            $expense->save(['timestamps' => false]);

            $old_user_key = array_key_exists('user_id', $resource) ?? $this->user->id;

            $key = "expenses_{$resource['id']}";

            $this->ids['expenses'][$key] = [
                'old' => $resource['id'],
                'new' => $expense->id,
            ];
        }

        Expense::reguard();

        $data = null;
    }
    /**
     * |--------------------------------------------------------------------------
     * | Additional migration methods.
     * |--------------------------------------------------------------------------
     * |
     * | These methods aren't initialized automatically, so they don't depend on
     * | the migration data.
     */

    /**
     * Cloned from App\Http\Requests\User\StoreUserRequest.
     *
     * @param string $data
     * @return User
     */
    public function fetchUser(string $data): User
    {
        $user = MultiDB::hasUser(['email' => $data]);

        if (! $user) {
            $user = UserFactory::create($this->company->account->id);
        }

        return $user;
    }

    /**
     * @param string $resource
     * @param string $old
     * @return int
     * @throws Exception
     */
    public function transformId($resource, string $old): int
    {
        if (! array_key_exists($resource, $this->ids)) {
            nlog($resource);
            throw new Exception("Resource {$resource} not available.");
        }

        if (! array_key_exists("{$resource}_{$old}", $this->ids[$resource])) {
            throw new Exception("Missing resource key: {$resource}_{$old}");
        }

        return $this->ids[$resource]["{$resource}_{$old}"]['new'];
    }

    private function tryTransformingId($resource, string $old): ?int
    {
        if (! array_key_exists($resource, $this->ids)) {
            return false;
        }

        if (! array_key_exists("{$resource}_{$old}", $this->ids[$resource])) {
            return false;
        }

        return $this->ids[$resource]["{$resource}_{$old}"]['new'];
    }

    /**
     * Process & handle user_id.
     *
     * @param array $resource
     * @return int|mixed
     * @throws Exception
     */
    public function processUserId(array $resource)
    {
        if (! array_key_exists('user_id', $resource)) {
            return $this->user->id;
        }

        if (array_key_exists('user_id', $resource) && ! array_key_exists('users', $this->ids)) {
            return $this->user->id;
        }

        return $this->transformId('users', $resource['user_id']);
    }

    public function failed($exception = null)
    {
        nlog('the job failed');

        config(['queue.failed.driver' => null]);

        $job_failure = new MigrationFailure();
        $job_failure->string_metric5 = get_class($this);
        $job_failure->string_metric6 = $exception->getMessage();

        LightLogs::create($job_failure)
                 ->queue();

        nlog($exception->getMessage());

        app('sentry')->captureException($exception);

    }


    public function curlGet($url, $headers = false)
    {
        return $this->exec('GET', $url, null);
    }

    public function exec($method, $url, $data)
    {
        $client =  new \GuzzleHttp\Client(['headers' =>
            [
            'X-Ninja-Token' => $this->token,
            ]
        ]);

        $response = $client->request('GET', $url);

        return $response->getBody();
    }



    private function processNinjaTokens(array $data)
    {
        nlog("attempting to process Ninja Tokens");

        if (Ninja::isHosted()) {
            try {
                \Modules\Admin\Jobs\Account\NinjaUser::dispatch($data, $this->company);
            } catch(\Exception $e) {
                nlog($e->getMessage());
            }
        }
    }

    /* In V4 we use negative invoices (credits) and add then into the client balance. In V5, these sit off ledger and are applied later.
     This next section will check for credit balances and reduce the client balance so that the V5 balances are correct
    */
    // private function fixClientBalances()
    // {

    //     Client::cursor()->each(function ($client) {

    //         $credit_balance = $client->credits->where('is_deleted', false)->sum('balance');

    //         if($credit_balance > 0){
    //             $client->balance += $credit_balance;
    //             $client->save();
    //         }

    //     });

    // }
}

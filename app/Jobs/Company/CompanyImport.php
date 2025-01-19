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

namespace App\Jobs\Company;

use App\Exceptions\ImportCompanyFailed;
use App\Exceptions\NonExistingMigrationFile;
use App\Factory\ClientContactFactory;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Libraries\MultiDB;
use App\Mail\Import\CompanyImportFailure;
use App\Mail\Import\ImportCompleted;
use App\Models\Activity;
use App\Models\Backup;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\ClientGatewayToken;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Design;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\Paymentable;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Models\Webhook;
use App\Utils\Ninja;
use App\Utils\TempFile;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use JsonMachine\JsonDecoder\ExtJsonDecoder;
use JsonMachine\JsonMachine;
use ZipArchive;

class CompanyImport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;
    use GeneratesCounter;

    public $tries = 1;

    public $timeout = 0;

    protected $current_app_version;

    private $account;

    public Company $company;

    public $user;

    private $file_location;

    public $backup_file;

    public $ids = [];

    private $request_array = [];

    public $message = '';

    public $pre_flight_checks_pass = true;

    public $force_user_coalesce = false;

    public $company_owner;

    private $file_path;

    private string $import_version = '';

    private $importables = [
        // 'company',
        'users',
        'company_users',
        'payment_terms',
        'tax_rates',
        'expense_categories',
        'task_statuses',
        'group_settings',
        'clients',
        'client_contacts',
        'vendors',
        'vendor_contacts',
        'projects',
        'products',
        'company_gateways',
        'client_gateway_tokens',
        'subscriptions',
        'recurring_invoices',
        'recurring_invoice_invitations',
        'invoices',
        'invoice_invitations',
        'quotes',
        'quote_invitations',
        'credits',
        'credit_invitations',
        'recurring_expenses',
        'expenses',
        'tasks',
        'company_ledger',
        'designs',
        'documents',
        'webhooks',
        'system_logs',
        'purchase_orders',
        'purchase_order_invitations',
        'bank_integrations',
        'bank_transactions',
        'payments',
        'schedulers',
    ];

    private $company_properties = [
        "convert_products",
        "fill_products",
        "update_products",
        "show_product_details",
        "client_can_register",
        "custom_surcharge_taxes1",
        "custom_surcharge_taxes2",
        "custom_surcharge_taxes3",
        "custom_surcharge_taxes4",
        "show_product_cost",
        "enabled_tax_rates",
        "enabled_modules",
        "enable_product_cost",
        "enable_product_quantity",
        "default_quantity",
        "subdomain",
        "size_id",
        "first_day_of_week",
        "first_month_of_year",
        "portal_mode",
        "portal_domain",
        "enable_modules",
        "custom_fields",
        "industry_id",
        "slack_webhook_url",
        "google_analytics_key",
        "created_at",
        "updated_at",
        "enabled_item_tax_rates",
        "is_large",
        "enable_shop_api",
        "default_auto_bill",
        "mark_expenses_invoiceable",
        "mark_expenses_paid",
        "invoice_expense_documents",
        "auto_start_tasks",
        "invoice_task_timelog",
        "invoice_task_documents",
        "show_tasks_table",
        "is_disabled",
        "default_task_is_date_based",
        "enable_product_discount",
        "calculate_expense_tax_by_amount",
        "expense_inclusive_taxes",
        "session_timeout",
        "oauth_password_required",
        "invoice_task_datelog",
        "default_password_timeout",
        "show_task_end_date",
        "markdown_enabled",
        "use_comma_as_decimal_place",
        "report_include_drafts",
        "client_registration_fields",
        "convert_rate_to_client",
    ];

    private array $protected_input = [
        'client_portal_privacy_policy',
        'client_portal_terms',
        'portal_custom_footer',
        'portal_custom_css',
        'portal_custom_head'
    ];

    private array $version_keys = [
        'baseline' => [],
        '5.7.35' => [
            Payment::class => [
                'refund_meta',
                'category_id',
            ],
            User::class => [
                'user_logged_in_notification',
            ],
            Design::class => [
                'is_template',
            ]
        ],
        '5.8.51' => [
            CompanyGateway::class => [
                'always_show_required_fields',
            ]
        ],
        '5.8.57' => [
            Company::class => [
                'einvoice',
                'e_invoice',
            ],
            Invoice::class => [
                'einvoice',
                'e_invoice',
            ],
            Quote::class => [
                'einvoice',
                'e_invoice',
            ],
            Credit::class => [
                'einvoice',
                'e_invoice',
            ],
            PurchaseOrder::class => [
                'einvoice',
                'e_invoice',
            ],
            Expense::class => [
                'einvoice',
                'e_invoice',
            ],
        ]
    ];

    /**
     * Create a new job instance.
     *
     * @param Company $company
     * @param User $user
     * @param string $file_location - the cache hash of the import data.
     * @param array $request_array
     */
    public function __construct(Company $company, User $user, string $file_location, array $request_array)
    {
        $this->company = $company;
        $this->user = $user;
        $this->file_location = $file_location;
        $this->request_array = $request_array;
        $this->current_app_version = config('ninja.app_version');
    }

    private function getObject($key, $force_array = false)
    {
        set_time_limit(0);

        $json = JsonMachine::fromFile($this->file_path, '/'.$key, new ExtJsonDecoder());

        if ($force_array) {
            return iterator_to_array($json);
        }

        return $json;
    }

    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $this->company = Company::query()->where('company_key', $this->company->company_key)->firstOrFail();
        $this->account = $this->company->account;
        $this->company_owner = $this->company->owner();

        nlog("Company ID = {$this->company->id}");
        nlog("file_location ID = {$this->file_location}");

        if (empty($this->file_location)) {
            throw new \Exception('No import data found, has the cache expired?');
        }

        $tmp_file = $this->unzipFile();

        $this->file_path = $tmp_file;

        $this->checkUserCount();

        if (array_key_exists('import_settings', $this->request_array) && $this->request_array['import_settings'] == 'true') {
            $this->preFlightChecks()->importSettings();
        }

        if (array_key_exists('import_data', $this->request_array) && $this->request_array['import_data'] == 'true') {
            try {
                $this->preFlightChecks()
                     ->purgeCompanyData()
                     ->importCompany()
                     ->importData()
                     ->miscTransformations()
                     ->postImportCleanup();

                $data = [
                    'errors'  => []
                ];

                $_company = Company::query()->find($this->company->id);

                $nmo = new NinjaMailerObject();
                $nmo->mailable = new ImportCompleted($_company, $data);
                $nmo->company = $_company;
                $nmo->settings = $_company->settings;
                $nmo->to_user = $_company->owner();
                NinjaMailerJob::dispatch($nmo);
            } catch (\Exception $e) {
                info($e->getMessage());
            }
        }

        if (file_exists($tmp_file)) {
            unlink($tmp_file);
        }

        if (Storage::exists($this->file_location)) {
            Storage::delete($this->file_location);
        }

    }

    private function miscTransformations()
    {
        Invoice::withTrashed()
                ->where('company_id', $this->company->id)
                ->cursor()
                ->each(function ($invoice) {

                    $items = $invoice->line_items;

                    foreach ($items as $key => $value) {

                        if (isset($value->task_id) && strlen($value->task_id) > 1) {

                            $t_id = $this->transformId('tasks', $value->task_id);

                            if ($t = Task::withTrashed()->where('company_id', $this->company->id)->where('id', $t_id)->first()) {
                                $items[$key]->task_id = $t->hashed_id;
                            }

                        }



                        if (isset($value->expense_id) && strlen($value->expense_id) > 1) {

                            $e_id = $this->transformId('expenses', $value->expense_id);

                            if ($e = Expense::withTrashed()->where('company_id', $this->company->id)->where('id', $e_id)->first()) {
                                $items[$key]->expense_id = $e->hashed_id;
                            }

                        }

                    }

                    $invoice->line_items = array_values($items);
                    $invoice->saveQuietly();


                });

        return $this;
    }

    //
    private function postImportCleanup()
    {
        //ensure all clients have a contact

        $this->company
             ->clients()
             ->whereDoesntHave('contacts')
             ->cursor()
             ->each(function ($client) {
                 $new_contact = ClientContactFactory::create($client->company_id, $client->user_id);
                 $new_contact->client_id = $client->id;
                 $new_contact->contact_key = Str::random(40);
                 $new_contact->is_primary = true;
                 $new_contact->confirmed = true;
                 $new_contact->email = ' ';
                 $new_contact->save();
             });
    }

    private function unzipFile()
    {
        $path = TempFile::filePath(Storage::disk(config('filesystems.default'))->get($this->file_location), basename($this->file_location));

        $zip = new ZipArchive();
        $res = $zip->open($path);
        $file_path = sys_get_temp_dir().'/'.sha1(microtime());

        if ($res === true) {
            echo "ok";
            $extraction_res = $zip->extractTo($file_path);

            nlog($extraction_res);

            $closer = $zip->close();
            nlog($closer);

        } else {
            echo "failed, code: " . $res;
        }

        $file_path = "{$file_path}/backup.json";

        nlog($file_path);

        if (! file_exists($file_path)) {
            throw new NonExistingMigrationFile('Backup file does not exist, or is corrupted.');
        }

        return $file_path;
    }


    /**
     * On the hosted platform we cannot allow the
     * import to start if there are users > plan number
     * due to entity user_id dependencies
     *
     * @return self
     */
    private function checkUserCount()
    {
        if (Ninja::isSelfHost()) {
            $this->pre_flight_checks_pass = true;
        }

        // $backup_users = $this->backup_file->users;
        $backup_users = $this->getObject('users', true);

        $company_users = $this->company->users;

        nlog("Backup user count = ".count($backup_users));

        if (count($backup_users) > 1) {
        }

        nlog("backup users email = " . $backup_users[0]->email);

        if (count($backup_users) == 1 && $this->company_owner->email != $backup_users[0]->email) {
        }

        $backup_users_emails = array_column($backup_users, 'email');

        $company_users_emails = $company_users->pluck('email')->toArray();

        $existing_user_count = count(array_intersect($backup_users_emails, $company_users_emails));

        nlog("existing user count = {$existing_user_count}");

        if ($existing_user_count > 1) {
            if ($this->account->plan == 'pro') {
            }

            if ($this->account->plan == 'enterprise') {
            }
        }

        if ($this->company->account->isFreeHostedClient() && (count($this->getObject('clients', true)) > config('ninja.quotas.free.clients'))) {
            nlog("client quota busted");

            $client_limit = config('ninja.quotas.free.clients');
            $client_count = count($this->getObject('clients', true));

            $this->message = "You are attempting to import ({$client_count}) clients, your current plan allows a total of ({$client_limit})";

            $this->pre_flight_checks_pass = false;
        }

        return $this;
    }

    //check if this is a complete company import OR if it is selective
    /*
     Company and settings only
     Data
     */

    private function preFlightChecks()
    {
        //check the file version and perform any necessary adjustments to the file in order to proceed - needed when we change schema

        $data = (object)$this->getObject('app_version', true);

        $this->import_version = $data->app_version;

        if ($this->pre_flight_checks_pass === false) {
            $this->sendImportMail($this->message);

            throw new \Exception($this->message);
        }

        return $this;
    }

    private function importSettings()
    {
        $co = (object)$this->getObject("company", true);

        $settings = $co->settings;
        $settings->invoice_number_counter = 1;
        $settings->recurring_invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;
        $settings->task_number_counter = 1;
        $settings->expense_number_counter = 1;
        $settings->recurring_expense_number_counter = 1;
        $settings->recurring_quote_number_counter = 1;
        $settings->vendor_number_counter = 1;
        $settings->ticket_number_counter = 1;
        $settings->payment_number_counter = 1;
        $settings->project_number_counter = 1;
        $settings->purchase_order_number_counter = 1;

        $settings->email_style_custom = str_replace(['{!!','!!}','{{','}}','@dd', '@dump', '@if', '@if(','@endif','@isset','@unless','@auth','@empty','@guest','@env','@section','@switch', '@foreach', '@while', '@include', '@each', '@once', '@push', '@use', '@forelse', '@verbatim', '<?php', '@php', '@for','@class','</s','<s','html;base64'], '', $settings->email_style_custom);
        $settings->company_logo = (strlen($settings->company_logo) > 2 && stripos($settings->company_logo, 'http') !== false) ? $settings->company_logo : "https://{$settings->company_logo}";

        foreach ($this->protected_input as $protected_var) {
            $settings->{$protected_var} = str_replace("script", "", $settings->{$protected_var});
        }

        $this->company->saveSettings($settings, $this->company);

        $this->company->save();

        return $this;
    }

    private function purgeCompanyData()
    {
        $this->company->clients()->forceDelete();
        $this->company->all_activities()->forceDelete();
        $this->company->products()->forceDelete();
        $this->company->projects()->forceDelete();
        $this->company->tasks()->forceDelete();
        $this->company->vendors()->forceDelete();
        $this->company->expenses()->forceDelete();
        $this->company->subscriptions()->forceDelete();
        $this->company->purchase_orders()->forceDelete();
        $this->company->bank_integrations()->forceDelete();
        $this->company->bank_transactions()->forceDelete();
        $this->company->schedulers()->forceDelete();
        $this->company->system_log_relation()->forceDelete();

        $this->company->save();

        return $this;
    }

    private function importCompany()
    {
        //$tmp_company = $this->backup_file->company;
        $tmp_company = (object)$this->getObject("company", true);
        $tmp_company->company_key = $this->createHash();
        $tmp_company->db = config('database.default');
        $tmp_company->account_id = $this->account->id;

        if (Ninja::isHosted()) {
            $tmp_company->subdomain = MultiDB::randomSubdomainGenerator();
        } else {
            $tmp_company->subdomain = '';
        }

        foreach ($this->company_properties as $value) {
            if (property_exists($tmp_company, $value)) {
                $this->company->{$value} = $tmp_company->{$value};
            }
        }

        if (Ninja::isHosted()) {
            $this->company->portal_mode = 'subdomain';
            $this->company->portal_domain = '';
        }

        $this->company->save();

        return $this;
    }

    private function importData()
    {
        foreach ($this->importables as $import) {
            $method = "import_{$import}";

            nlog($method);

            $this->{$method}();
        }

        nlog("finished importing company data");

        return $this;
    }

    private function import_schedulers()
    {
        $this->genericNewClassImport(
            \App\Models\Scheduler::class,
            ['company_id', 'id', 'hashed_id'],
            [
                ['users' => 'user_id'],
            ],
            'schedulers'
        );

        return $this;
    }

    private function import_bank_integrations()
    {
        $this->genericImport(
            BankIntegration::class,
            ['assigned_user_id','account_id', 'company_id', 'id', 'hashed_id'],
            [
                ['users' => 'user_id'],
            ],
            'bank_integrations',
            'description'
        );

        return $this;
    }

    private function import_bank_transactions()
    {
        $this->genericImport(
            BankTransaction::class,
            ['assigned_user_id','company_id', 'id', 'hashed_id', 'user_id'],
            [
                ['users' => 'user_id'],
                ['expenses' => 'expense_id'],
                ['vendors' => 'vendor_id'],
                ['expense_categories' => 'ninja_category_id'],
                // ['expense_categories' => 'category_id'],
                ['bank_integrations' => 'bank_integration_id']
            ],
            'bank_transactions',
            null
        );

        return $this;
    }

    private function import_recurring_expenses()
    {
        //unset / transforms / object_property / match_key
        $this->genericImport(
            RecurringExpense::class,
            ['assigned_user_id', 'user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'project_id', 'vendor_id','recurring_expense_id'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['clients' => 'client_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
                ['invoices' => 'invoice_id'],
                ['expense_categories' => 'category_id'],
            ],
            'recurring_expenses',
            'number'
        );

        return $this;
    }

    private function import_payment_terms()
    {
        $this->genericImport(
            PaymentTerm::class,
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id'],
            [['users' => 'user_id']],
            'payment_terms',
            'num_days'
        );

        return $this;
    }

    /* Cannot use generic as we are matching on two columns for existing data */
    private function import_tax_rates()
    {
        // foreach($this->backup_file->tax_rates as $obj)
        foreach ((object)$this->getObject("tax_rates") as $obj) {
            $user_id = $this->transformId('users', $obj->user_id);

            $obj_array = (array)$obj;
            unset($obj_array['user_id']);
            unset($obj_array['company_id']);
            unset($obj_array['hashed_id']);
            unset($obj_array['id']);
            unset($obj_array['tax_rate_id']);

            /** @var \App\Models\TaxRate $new_obj */
            $new_obj = TaxRate::firstOrNew(
                ['name' => $obj->name, 'company_id' => $this->company->id, 'rate' => $obj->rate],
                $obj_array,
            );

            $new_obj->company_id = $this->company->id;
            $new_obj->user_id = $user_id;
            $new_obj->save(['timestamps' => false]);
        }

        return $this;
    }

    private function import_expense_categories()
    {
        $this->genericImport(
            ExpenseCategory::class,
            ['user_id', 'company_id', 'id', 'hashed_id'],
            [['users' => 'user_id']],
            'expense_categories',
            'name'
        );

        return $this;
    }

    private function import_task_statuses()
    {
        $this->genericImport(
            TaskStatus::class,
            ['user_id', 'company_id', 'id', 'hashed_id'],
            [['users' => 'user_id']],
            'task_statuses',
            'name'
        );

        return $this;
    }

    private function import_clients()
    {
        $this->genericImport(
            Client::class,
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id', 'gateway_tokens', 'contacts', 'documents', 'country', 'sync'],
            [['users' => 'user_id'], ['users' => 'assigned_user_id'],['group_settings' => 'group_settings_id']],
            'clients',
            'number'
        );

        return $this;
    }

    private function import_client_contacts()
    {
        $this->genericImport(
            ClientContact::class,
            ['user_id', 'company_id', 'id', 'hashed_id','company'],
            [['users' => 'user_id'], ['clients' => 'client_id']],
            'client_contacts',
            'email'
        );

        return $this;
    }

    private function import_vendors()
    {
        $this->genericImport(
            Vendor::class,
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id'],
            [['users' => 'user_id'], ['users' => 'assigned_user_id']],
            'vendors',
            'number'
        );

        return $this;
    }

    private function import_vendor_contacts()
    {
        $this->genericImport(
            VendorContact::class,
            ['user_id', 'company_id', 'id', 'hashed_id','company','assigned_user_id'],
            [['users' => 'user_id'], ['vendors' => 'vendor_id']],
            'vendor_contacts',
            'email'
        );

        return $this;
    }

    private function import_projects()
    {
        $this->genericImport(
            Project::class,
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id','client_id'],
            [['users' => 'user_id'], ['users' => 'assigned_user_id'], ['clients' => 'client_id']],
            'projects',
            'number'
        );

        return $this;
    }

    private function import_products()
    {
        $this->genericNewClassImport(
            Product::class,
            ['user_id', 'company_id', 'hashed_id', 'id', 'sync'],
            [['users' => 'user_id'], ['users' => 'assigned_user_id'], ['vendors' => 'vendor_id'], ['projects' => 'project_id']],
            'products'
        );

        return $this;
    }

    private function import_company_gateways()
    {
        $this->genericNewClassImport(
            CompanyGateway::class,
            ['user_id', 'company_id', 'hashed_id', 'id'],
            [['users' => 'user_id']],
            'company_gateways'
        );

        return $this;
    }

    private function import_client_gateway_tokens()
    {
        $this->genericNewClassImport(
            ClientGatewayToken::class,
            ['company_id', 'id', 'hashed_id','client_id'],
            [['clients' => 'client_id', 'company_gateways' => 'company_gateway_id']],
            'client_gateway_tokens'
        );

        return $this;
    }

    private function import_group_settings()
    {
        $this->genericImport(
            GroupSetting::class,
            ['user_id', 'company_id', 'id', 'hashed_id'],
            [['users' => 'user_id']],
            'group_settings',
            'name'
        );

        return $this;
    }

    private function import_subscriptions()
    {
        $this->genericImport(
            Subscription::class,
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id'],
            [['group_settings' => 'group_id'], ['users' => 'user_id'], ['users' => 'assigned_user_id']],
            'subscriptions',
            'name'
        );

        return $this;
    }

    private function import_recurring_invoices()
    {
        $this->genericImport(
            RecurringInvoice::class,
            ['user_id', 'assigned_user_id', 'company_id', 'id', 'hashed_id', 'client_id','subscription_id','project_id','vendor_id','status'],
            [
                ['subscriptions' => 'subscription_id'],
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['clients' => 'client_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
                ['clients' => 'client_id'],
            ],
            'recurring_invoices',
            'number'
        );

        return $this;
    }

    private function import_recurring_invoice_invitations()
    {
        $this->genericImport(
            RecurringInvoiceInvitation::class,
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'recurring_invoice_id'],
            [
                ['users' => 'user_id'],
                ['recurring_invoices' => 'recurring_invoice_id'],
                ['client_contacts' => 'client_contact_id'],
            ],
            'recurring_invoice_invitations',
            'key'
        );

        return $this;
    }

    private function import_invoices()
    {
        $this->genericImport(
            Invoice::class,
            ['user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'recurring_id','status', 'sync'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['recurring_invoices' => 'recurring_id'],
                ['clients' => 'client_id'],
                ['subscriptions' => 'subscription_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ],
            'invoices',
            'number'
        );

        return $this;
    }

    private function import_invoice_invitations()
    {
        $this->genericImport(
            InvoiceInvitation::class,
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'invoice_id'],
            [
                ['users' => 'user_id'],
                ['invoices' => 'invoice_id'],
                ['client_contacts' => 'client_contact_id'],
            ],
            'invoice_invitations',
            'key'
        );

        return $this;
    }

    private function import_purchase_orders()
    {
        $this->genericImport(
            PurchaseOrder::class,
            ['user_id', 'company_id', 'id', 'hashed_id', 'recurring_id','status', 'vendor_id', 'subscription_id','client_id'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['recurring_invoices' => 'recurring_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ],
            'purchase_orders',
            'number'
        );

        return $this;
    }

    private function import_purchase_order_invitations()
    {
        $this->genericImport(
            PurchaseOrderInvitation::class,
            ['user_id', 'vendor_contact_id', 'company_id', 'id', 'hashed_id', 'purchase_order_id'],
            [
                ['users' => 'user_id'],
                ['purchase_orders' => 'purchase_order_id'],
                ['vendor_contacts' => 'vendor_contact_id'],
            ],
            'purchase_order_invitations',
            'key'
        );

        return $this;
    }


    private function import_quotes()
    {
        $this->genericImport(
            Quote::class,
            ['user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'recurring_id','status'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['recurring_invoices' => 'recurring_id'],
                ['clients' => 'client_id'],
                ['subscriptions' => 'subscription_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ],
            'quotes',
            'number'
        );

        return $this;
    }

    private function import_quote_invitations()
    {
        $this->genericImport(
            QuoteInvitation::class,
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'quote_id'],
            [
                ['users' => 'user_id'],
                ['quotes' => 'quote_id'],
                ['client_contacts' => 'client_contact_id'],
            ],
            'quote_invitations',
            'key'
        );


        return $this;
    }

    private function import_credits()
    {
        $this->genericImport(
            Credit::class,
            ['user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'recurring_id','status'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['recurring_invoices' => 'recurring_id'],
                ['clients' => 'client_id'],
                ['subscriptions' => 'subscription_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
            ],
            'credits',
            'number'
        );

        return $this;
    }

    private function import_credit_invitations()
    {
        $this->genericImport(
            CreditInvitation::class,
            ['user_id', 'client_contact_id', 'company_id', 'id', 'hashed_id', 'credit_id'],
            [
                ['users' => 'user_id'],
                ['credits' => 'credit_id'],
                ['client_contacts' => 'client_contact_id'],
            ],
            'credit_invitations',
            'key'
        );

        return $this;
    }

    private function import_expenses()
    {
        $this->genericImport(
            Expense::class,
            ['assigned_user_id', 'user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'project_id','vendor_id','recurring_expense_id'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['clients' => 'client_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
                ['invoices' => 'invoice_id'],
                // ['recurring_expenses' => 'recurring_expense_id'],
                ['expense_categories' => 'category_id'],
            ],
            'expenses',
            'number'
        );

        return $this;
    }

    private function import_tasks()
    {
        $this->genericImport(
            Task::class,
            ['assigned_user_id', 'user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'invoice_id','project_id'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['clients' => 'client_id'],
                ['projects' => 'project_id'],
                ['invoices' => 'invoice_id'],
            ],
            'tasks',
            'number'
        );

        return $this;
    }

    private function import_payments()
    {
        $this->genericImport(
            Payment::class,
            ['assigned_user_id', 'user_id', 'client_id', 'company_id', 'id', 'hashed_id', 'client_contact_id','invitation_id','vendor_id','paymentables'],
            [
                ['users' => 'user_id'],
                ['users' => 'assigned_user_id'],
                ['clients' => 'client_id'],
                ['client_contacts' => 'client_contact_id'],
                ['vendors' => 'vendor_id'],
                ['invoice_invitations' => 'invitation_id'],
                ['company_gateways' => 'company_gateway_id'],
                ['bank_transactions' => 'transaction_id'],
            ],
            'payments',
            'number'
        );

        $this->paymentablesImport();

        return $this;
    }

    private function import_activities()
    {
        $activities = [];


        $this->genericNewClassImport(
            Activity::class,
            [
                'hashed_id',
                'company_id',
                'backup',
                'invitation_id',
                'payment_id',
            ],
            [
                ['users' => 'user_id'],
                ['clients' => 'client_id'],
                ['client_contacts' => 'client_contact_id'],
                ['projects' => 'project_id'],
                ['vendors' => 'vendor_id'],
                // ['payments' => 'payment_id'],
                ['invoices' => 'invoice_id'],
                ['credits' => 'credit_id'],
                ['tasks' => 'task_id'],
                ['expenses' => 'expense_id'],
                ['quotes' => 'quote_id'],
                ['subscriptions' => 'subscription_id'],
                ['recurring_invoices' => 'recurring_invoice_id'],
                // ['recurring_expenses' => 'recurring_expense_id'],
                // ['invitations' => 'invitation_id'],
            ],
            'activities'
        );

        return $this;
    }

    private function import_backups()
    {
        $this->genericImportWithoutCompany(
            Backup::class,
            ['hashed_id','id'],
            [
                ['activities' => 'activity_id'],
            ],
            'backups',
            'created_at'
        );


        return $this;
    }

    private function import_company_ledger()
    {
        $this->genericImport(
            CompanyLedger::class,
            ['company_id', 'user_id', 'client_id', 'activity_id', 'id','account_id'],
            [
                ['users' => 'user_id'],
                ['clients' => 'client_id'],
                // ['activities' => 'activity_id'],
            ],
            'company_ledger',
            'created_at'
        );

        return $this;
    }

    private function import_designs()
    {
        $this->genericImport(
            Design::class,
            ['company_id', 'user_id', 'hashed_id'],
            [
                ['users' => 'user_id'],
            ],
            'designs',
            'name'
        );

        return $this;
    }

    private function import_documents()
    {
        foreach ((object)$this->getObject("documents") as $document) {
            //todo enable this for v5.5.51
            if (!$this->transformDocumentId($document->documentable_id, $document->documentable_type)) {
                continue;
            }

            /** @var string $storage_url */
            $storage_url = (object)$this->getObject('storage_url', true);

            if (!Storage::exists($document->url) && is_string($storage_url)) {
                $url = $storage_url . $document->url;

                $file = @file_get_contents($url);


                if ($file) {
                    try {
                        Storage::disk(config('filesystems.default'))->put($document->url, $file);


                    } catch (\Exception $e) {
                        nlog($e->getMessage());
                        nlog("I could not upload {$document->url}");

                    }
                } else {
                    continue;
                }

            } else {
                continue;
            }

            $new_document = new Document();
            $new_document->disk = config('filesystems.default');
            $new_document->user_id = $this->transformId('users', $document->user_id);
            $new_document->assigned_user_id = $this->transformId('users', $document->assigned_user_id);
            $new_document->company_id = $this->company->id;
            $new_document->project_id = $this->transformId('projects', $document->project_id);
            $new_document->vendor_id = $this->transformId('vendors', $document->vendor_id);
            $new_document->url = $document->url;
            $new_document->preview = $document->preview;
            $new_document->name = str_replace("/", "-", $document->name);
            $new_document->type = $document->type;
            $new_document->disk = $document->disk;
            $new_document->hash = $document->hash;
            $new_document->size = $document->size;
            $new_document->width = $document->width;
            $new_document->height = $document->height;
            $new_document->is_default = $document->is_default;
            $new_document->custom_value1 = $document->custom_value1;
            $new_document->custom_value2 = $document->custom_value2;
            $new_document->custom_value3 = $document->custom_value3;
            $new_document->custom_value4 = $document->custom_value4;
            $new_document->deleted_at = $document->deleted_at;
            $new_document->documentable_id = $this->transformDocumentId($document->documentable_id, $document->documentable_type);
            $new_document->documentable_type = $document->documentable_type;

            $new_document->save(['timestamps' => false]);

        }

        return $this;
    }

    private function import_webhooks()
    {
        $this->genericImport(
            Webhook::class,
            ['company_id', 'user_id', 'hashed_id', 'id',],
            [
                ['users' => 'user_id'],
            ],
            'webhooks',
            'created_at'
        );

        return $this;
    }


    private function import_system_logs()
    {
        return $this;
    }

    private function import_users()
    {
        User::unguard();

        //foreach ($this->backup_file->users as $user)
        foreach ((object)$this->getObject("users") as $user) {
            if (User::withTrashed()->where('email', $user->email)->where('account_id', '!=', $this->account->id)->exists()) {
                throw new ImportCompanyFailed("{$user->email} is already in the system attached to a different account");
            }

            $user_array = (array)$user;
            unset($user_array['laravel_through_key']);
            unset($user_array['hashed_id']);
            unset($user_array['id']);

            /*Make sure we are searching for archived users also and restore if we find them.*/

            $new_user = User::withTrashed()->firstOrNew(
                ['email' => $user->email],
                $user_array,
            );

            $new_user->account_id = $this->account->id;
            $new_user->save(['timestamps' => false]);

            $this->ids['users']["{$user->hashed_id}"] = $new_user->id;
        }

        User::reguard();
    }

    private function import_company_users()
    {
        CompanyUser::unguard();

        // foreach($this->backup_file->company_users as $cu)
        foreach ((object)$this->getObject("company_users") as $cu) {
            $user_id = $this->transformId('users', $cu->user_id);

            $cu_array = (array)$cu;
            unset($cu_array['id']);
            unset($cu_array['company_id']);
            unset($cu_array['user_id']);
            unset($cu_array['user']);
            unset($cu_array['account']);

            // $cu_array['settings'] = json_encode($cu_array['settings']);
            // $cu_array['notifications'] = json_encode($cu_array['notifications']);
            // $cu_array['permissions'] = json_encode($cu_array['permissions']);

            $new_cu = CompanyUser::withTrashed()->firstOrNew(
                ['user_id' => $user_id, 'company_id' => $this->company->id],
                $cu_array,
            );

            $new_cu->account_id = $this->account->id;
            $new_cu->save(['timestamps' => false]);
        }

        CompanyUser::reguard();
    }

    private function transformDocumentId($id, $type)
    {
        switch ($type) {
            case Company::class:
                return $this->company->id;
            case Client::class:
                return $this->transformId('clients', $id);
            case ClientContact::class:
                return $this->transformId('client_contacts', $id);
            case Credit::class:
                return $this->transformId('credits', $id);
            case Expense::class:
                return $this->transformId('expenses', $id);
            case 'invoices':
                return $this->transformId('invoices', $id);
            case Payment::class:
                return $this->transformId('payments', $id);
            case Project::class:
                return $this->transformId('projects', $id);
            case Product::class:
                return $this->transformId('products', $id);
            case Quote::class:
                return $this->transformId('quotes', $id);
            case RecurringInvoice::class:
                return $this->transformId('recurring_invoices', $id);
            case Company::class:
                return $this->transformId('clients', $id);

            default:
                return false;
        }
    }

    private function paymentablesImport()
    {
        // foreach($this->backup_file->payments as $payment)
        foreach ((object)$this->getObject("payments") as $payment) {
            foreach ($payment->paymentables as $paymentable_obj) {
                $paymentable = new Paymentable();
                $paymentable->payment_id = $this->transformId('payments', $paymentable_obj->payment_id);
                $paymentable->paymentable_type = $paymentable_obj->paymentable_type;
                $paymentable->amount = $paymentable_obj->amount;
                $paymentable->refunded = $paymentable_obj->refunded;
                $paymentable->created_at = $paymentable_obj->created_at;
                $paymentable->deleted_at = $paymentable_obj->deleted_at;
                $paymentable->updated_at = $paymentable_obj->updated_at;
                $paymentable->paymentable_id = $this->convertPaymentableId($paymentable_obj->paymentable_type, $paymentable_obj->paymentable_id);
                $paymentable->paymentable_type = $paymentable_obj->paymentable_type;
                $paymentable->save(['timestamps' => false]);
            }
        }

        return $this;
    }

    private function convertPaymentableId($type, $id)
    {
        switch ($type) {
            case 'invoices':
                return $this->transformId('invoices', $id);

            case Credit::class:
                return $this->transformId('credits', $id);

            case Payment::class:
                return $this->transformId('payments', $id);
            default:
                # code...
                break;
        }
    }

    private function filterVersionProps($class, array $obj_array): array
    {

        if ($this->current_app_version == $this->import_version) {
            return $obj_array;
        }

        $version_index = 0;
        $index = 0;

        $filters = collect($this->version_keys)
            ->map(function ($value, $key) use (&$version_index, &$index) {
                if ($this->import_version == $key) {
                    $version_index = $index;
                }

                $index++;
                return $value;

            })
            ->when($version_index == 0, function ($collection) {
                return collect([]);
            })
            ->when($version_index > 0, function ($collection) use (&$version_index, $class) {
                return $collection->slice($version_index)->pluck($class)->filter();
            });

        return collect($obj_array)->diffKeys($filters->flatten()->flip())->toArray();

    }

    private function genericNewClassImport($class, $unset, $transforms, $object_property)
    {
        $class::unguard();

        foreach ((object)$this->getObject($object_property) as $obj) {
            /* Remove unwanted keys*/
            $obj_array = (array)$obj;
            foreach ($unset as $un) {
                unset($obj_array[$un]);
            }

            if ($class == 'App\Models\CompanyGateway') {

                if (Ninja::isHosted() && $obj_array['gateway_key'] == 'd14dd26a37cecc30fdd65700bfb55b23') {
                    $obj_array['gateway_key'] = 'd14dd26a47cecc30fdd65700bfb67b34';
                }

                if (Ninja::isSelfHost() && $obj_array['gateway_key'] == 'd14dd26a47cecc30fdd65700bfb67b34') {
                    $obj_array['gateway_key'] = 'd14dd26a37cecc30fdd65700bfb55b23';
                }

                if (!isset($obj_array['fees_and_limits'])) {
                    $obj_array['fees_and_limits'] = \json_encode([]);
                }
            }

            if (array_key_exists('deleted_at', $obj_array) && $obj_array['deleted_at'] > 1) {
                $obj_array['deleted_at'] = now();
            }

            $activity_invitation_key = false;

            if ($class == 'App\Models\Activity') {
                if (isset($obj->invitation_id)) {
                    if (isset($obj->invoice_id)) {
                        $activity_invitation_key = 'invoice_invitations';
                    } elseif (isset($obj->quote_id)) {
                        $activity_invitation_key = 'quote_invitations';
                    } elseif (isset($obj->credit_id)) {
                        $activity_invitation_key  = 'credit_invitations';
                    }
                }

                $obj_array['account_id'] = $this->account->id;
            }

            /* Transform old keys to new keys */
            foreach ($transforms as $transform) {
                foreach ($transform as $key => $value) {
                    if ($class == 'App\Models\Activity' && $activity_invitation_key && $key == 'invitations') {
                        $key = $activity_invitation_key;
                    }

                    $obj_array["{$value}"] = $this->transformId($key, $obj->{$value});
                }
            }

            if ($class == 'App\Models\CompanyGateway') {
                $obj_array['config'] = encrypt($obj_array['config']);
            }

            /**
             *  @class \stdClass $parameters
             *
             *  @property string $entity
             *  @property string $entity_id
            */

            if ($class == 'App\Models\Scheduler') {

                /** @var \stdClass $parameters */
                $parameters = $obj_array['parameters'];

                if (isset($parameters->clients)) {

                    $parameters->clients =
                    collect($parameters->clients)->map(function ($client_hash) {
                        return $this->encodePrimaryKey($this->transformId('clients', $client_hash));
                    })->toArray();

                }

                if (isset($parameters->entity_id)) {
                    $parameters->entity_id = $this->encodePrimaryKey($this->transformId($parameters->entity."s", $parameters->entity_id));
                }

                $obj_array['parameters'] = $parameters;
            }

            $new_obj = new $class();
            $new_obj->company_id = $this->company->id;
            $obj_array = $this->filterVersionProps($class, $obj_array);

            $new_obj->fill($obj_array);

            $new_obj->save(['timestamps' => false]);

            $this->ids["{$object_property}"]["{$obj->hashed_id}"] = $new_obj->id;
        }

        $class::reguard();
    }

    private function genericImportWithoutCompany($class, $unset, $transforms, $object_property, $match_key)
    {
        $class::unguard();

        //foreach($this->backup_file->{$object_property} as $obj)
        foreach ((object)$this->getObject($object_property) as $obj) {
            if (is_null($obj)) {
                continue;
            }

            /* Remove unwanted keys*/
            $obj_array = (array)$obj;
            foreach ($unset as $un) {
                unset($obj_array[$un]);
            }

            /* Transform old keys to new keys */
            foreach ($transforms as $transform) {
                foreach ($transform as $key => $value) {
                    $obj_array["{$value}"] = $this->transformId($key, $obj->{$value});
                }
            }

            if (array_key_exists('deleted_at', $obj_array) && $obj_array['deleted_at'] > 1) {
                $obj_array['deleted_at'] = now();
            }

            /* New to convert product ids from old hashes to new hashes*/
            if ($class == 'App\Models\Subscription') {
                $obj_array['product_ids'] = $this->recordProductIds($obj_array['product_ids']);
                $obj_array['recurring_product_ids'] = $this->recordProductIds($obj_array['recurring_product_ids']);
                $obj_array['webhook_configuration'] = \json_encode($obj_array['webhook_configuration']);
            }

            $obj_array = $this->filterVersionProps($class, $obj_array);

            $new_obj = $class::firstOrNew(
                [$match_key => $obj->{$match_key}],
                $obj_array,
            );

            $new_obj->save(['timestamps' => false]);

            if ($new_obj instanceof CompanyLedger) {
            } else {
                $this->ids["{$object_property}"]["{$obj->hashed_id}"] = $new_obj->id;
            }
        }

        $class::reguard();
    }

    /* Ensure if no number is set, we don't overwrite a record with an existing number */
    private function genericImport($class, $unset, $transforms, $object_property, $match_key)
    {
        $class::unguard();
        $x = 0;

        foreach ((object)$this->getObject($object_property) as $obj) {
            /* Remove unwanted keys*/
            $obj_array = (array)$obj;
            foreach ($unset as $un) {
                unset($obj_array[$un]);
            }

            /* Transform old keys to new keys */
            foreach ($transforms as $transform) {
                foreach ($transform as $key => $value) {
                    $obj_array["{$value}"] = $this->transformId($key, $obj->{$value});
                }
            }

            if (array_key_exists('deleted_at', $obj_array) && $obj_array['deleted_at'] > 1) {
                $obj_array['deleted_at'] = now();
            }

            /* New to convert product ids from old hashes to new hashes*/
            if ($class == 'App\Models\Subscription') {
                if (array_key_exists('company', $obj_array)) {
                    unset($obj_array['company']);
                }

                $obj_array['webhook_configuration'] = (array)$obj_array['webhook_configuration'];
                $obj_array['recurring_product_ids'] = '';
                $obj_array['product_ids'] = '';
            }

            $obj_array = $this->filterVersionProps($class, $obj_array);

            /* Expenses that don't have a number will not be inserted - so need to override here*/
            if ($class == 'App\Models\Expense' && is_null($obj->{$match_key})) {
                $new_obj = new Expense();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextExpenseNumber($new_obj);
            } elseif ($class == 'App\Models\Invoice' && is_null($obj->{$match_key})) {
                $new_obj = new Invoice();
                $new_obj->company_id = $this->company->id;
                $new_obj->design_id = 2;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextInvoiceNumber($client = Client::withTrashed()->find($obj_array['client_id']), $new_obj);
            } elseif ($class == 'App\Models\PurchaseOrder' && is_null($obj->{$match_key})) {
                $new_obj = new PurchaseOrder();
                $new_obj->company_id = $this->company->id;
                $new_obj->design_id = 2;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextPurchaseOrderNumber($new_obj);
            } elseif ($class == 'App\Models\Payment' && is_null($obj->{$match_key})) {
                $new_obj = new Payment();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextPaymentNumber($client = Client::withTrashed()->find($obj_array['client_id']), $new_obj);
            } elseif ($class == 'App\Models\Quote' && is_null($obj->{$match_key})) {
                $new_obj = new Quote();
                $new_obj->company_id = $this->company->id;
                $new_obj->design_id = 2;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextQuoteNumber($client = Client::withTrashed()->find($obj_array['client_id']), $new_obj);
            } elseif ($class == 'App\Models\Credit' && is_null($obj->{$match_key})) {
                $new_obj = new Credit();
                $new_obj->company_id = $this->company->id;
                $new_obj->design_id = 2;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextCreditNumber($client = Client::withTrashed()->find($obj_array['client_id']), $new_obj);
            } elseif ($class == 'App\Models\ClientContact') {
                $new_obj = new ClientContact();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
            } elseif ($class == 'App\Models\VendorContact') {
                $new_obj = new VendorContact();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
            } elseif ($class == 'App\Models\RecurringExpense' && is_null($obj->{$match_key})) {
                $new_obj = new RecurringExpense();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextRecurringExpenseNumber($new_obj);
            } elseif ($class == 'App\Models\Project' && is_null($obj->{$match_key})) {
                $new_obj = new Project();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextProjectNumber($new_obj);
            } elseif ($class == 'App\Models\Task' && is_null($obj->{$match_key})) {
                $new_obj = new Task();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextTaskNumber($new_obj);
            } elseif ($class == 'App\Models\Vendor' && is_null($obj->{$match_key})) {
                $new_obj = new Vendor();
                $new_obj->company_id = $this->company->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
                $new_obj->number = $this->getNextVendorNumber($new_obj);
            } elseif ($class == 'App\Models\CompanyLedger') {
                $new_obj = $class::firstOrNew(
                    [$match_key => $obj->{$match_key}, 'company_id' => $this->company->id],
                    $obj_array,
                );
            } elseif ($class == 'App\Models\BankIntegration') {
                $new_obj = new BankIntegration();
                $new_obj->company_id = $this->company->id;
                $new_obj->account_id = $this->account->id;
                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
            } elseif ($class == 'App\Models\BankTransaction') {
                $new_obj = new BankTransaction();
                $new_obj->company_id = $this->company->id;

                $obj_array['invoice_ids'] = collect(explode(",", $obj_array['invoice_ids']))->map(function ($id) {
                    return $this->transformId('invoices', $id);
                })->map(function ($encodeable) {
                    return $this->encodePrimaryKey($encodeable);
                })->implode(",");

                $obj_array['expense_id'] = collect(explode(",", $obj_array['expense_id']))->map(function ($id) {
                    return $this->transformId('expenses', $id);
                })->map(function ($encodeable) {
                    return $this->encodePrimaryKey($encodeable);
                })->implode(",");

                $new_obj->fill($obj_array);
                $new_obj->save(['timestamps' => false]);
            } else {
                $new_obj = $class::withTrashed()->firstOrNew(
                    [$match_key => $obj->{$match_key}, 'company_id' => $this->company->id],
                    $obj_array,
                );
            }

            $new_obj->save(['timestamps' => false]);

            if ($new_obj instanceof CompanyLedger) {
            } else {
                $this->ids["{$object_property}"]["{$obj->hashed_id}"] = $new_obj->id;
            }
        }

        $class::reguard();
    }

    private function recordProductIds($ids)
    {
        $id_array = explode(",", $ids);

        $tmp_arr = [];

        foreach ($id_array as $id) {
            if (!$id) {
                continue;
            }

            $id = $this->decodePrimaryKey($id);

            nlog($id);
            $tmp_arr[] = $this->encodePrimaryKey($this->transformId('products', $id));
        }

        return implode(",", $tmp_arr);
    }

    /* Transform all IDs from old to new
     *
     * In the case of users - we need to check if the system
     * is attempting to migrate resources above their quota,
     *
     * ie. > 50 clients or more than 1 user
    */
    private function transformId(string $resource, ?string $old): ?int
    {

        // WjnegYbwZ1 == 0 return null;
        if (empty($old) || $old == 'WjnegYbwZ1') {
            return null;
        }

        if ($resource == 'users' && $this->force_user_coalesce) {
            return $this->company_owner->id;
        }

        if (! array_key_exists($resource, $this->ids)) {

            $this->sendImportMail("The Import failed due to missing data in the import file. Resource {$resource} not available.");
            // nlog($resource);
            throw new \Exception("Resource {$resource} not available.");
        }

        if (! array_key_exists("{$old}", $this->ids[$resource])) {
            // nlog($this->ids[$resource]);
            nlog("searching for {$old} in {$resource}");

            if ($resource == 'users') {
                return $this->company_owner->id;
            }

            $this->sendImportMail("The Import failed due to missing data in the import file. Key {$old} not found in {$resource}.");

            throw new \Exception("Missing {$resource} key: {$old}");
        }

        // if($resource == 'vendors'){
        //     nlog($this->ids[$resource]);
        // }
        return $this->ids[$resource]["{$old}"];
    }


    private function sendImportMail($message)
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $_company = Company::find($this->company->id);

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new CompanyImportFailure($_company, $message);
        $nmo->company = $this->company;
        $nmo->settings = $this->company->settings;
        $nmo->to_user = $this->company->owner();
        NinjaMailerJob::dispatch($nmo);
    }
}

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

namespace App\Console\Commands;

use App\DataMapper\CompanySettings;
use App\Events\Invoice\InvoiceWasCreated;
use App\Factory\InvoiceFactory;
use App\Factory\InvoiceItemFactory;
use App\Factory\RecurringInvoiceFactory;
use App\Helpers\Invoice\InvoiceSum;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\Jobs\Ninja\CompanySizeCheck;
use App\Jobs\Util\VersionCheck;
use App\Models\Account;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Company;
use App\Models\CompanyToken;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Repositories\InvoiceRepository;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DemoMode extends Command
{
    use MakesHash;
    use GeneratesCounter;
    use AppSetup;

    protected $signature = 'ninja:demo-mode';

    protected $description = 'Setup demo mode';

    protected $invoice_repo;

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        set_time_limit(0);

        if (config('ninja.is_docker') || Ninja::isHosted()) {
            return;
        }

        $this->invoice_repo = new InvoiceRepository();

        $this->info('Migrating');
        Artisan::call('migrate:fresh --force');

        $this->info('Seeding');

        Artisan::call('db:seed --force');
        Artisan::call('cache:clear');

        $this->info('Seeding Random Data');
        $this->createSmallAccount();

        (new VersionCheck())->handle();

        (new CompanySizeCheck())->handle();
    }

    private function createSmallAccount()
    {
        $faker = \Faker\Factory::create();

        $this->count = 25;

        $this->info('Creating Small Account and Company');

        $account = Account::factory()->create([
            "set_react_as_default_ap" => 0,
        ]);
        $company = Company::factory()->create([
            'account_id' => $account->id,
            'slack_webhook_url' => config('ninja.notification.slack'),
            'enabled_modules' => 32767,
            'company_key' => 'KEY',
            'enable_shop_api' => true,
            'markdown_email_enabled' => true,
            'markdown_enabled' => false,
        ]);

        $settings = $company->settings;

        $settings->name = $faker->company();
        $settings->address1 = $faker->buildingNumber();
        $settings->address2 = $faker->streetAddress();
        $settings->city = $faker->city();
        $settings->state = $faker->state();
        $settings->postal_code = $faker->postcode();
        $settings->website = $faker->url();
        $settings->vat_number = (string) $faker->numberBetween(123456789, 987654321);
        $settings->phone = (string) $faker->phoneNumber();

        $company->settings = $settings;
        $company->save();

        $account->default_company_id = $company->id;
        $account->save();

        $user = User::whereEmail('small@example.com')->first();

        if (! $user) {
            $user = User::factory()->create([
                'account_id' => $account->id,
                'email' => 'small@example.com',
                'confirmation_code' => $this->createDbHash(config('database.default')),
                'email_verified_at' => now(),
            ]);
        }

        (new CreateCompanyPaymentTerms($company, $user))->handle();
        (new CreateCompanyTaskStatuses($company, $user))->handle();

        $company_token = new CompanyToken();
        $company_token->user_id = $user->id;
        $company_token->company_id = $company->id;
        $company_token->account_id = $account->id;
        $company_token->name = 'test token';
        $company_token->token = Str::random(64);
        $company_token->is_system = true;

        $company_token->save();

        $user->companies()->attach($company->id, [
            'account_id' => $account->id,
            'is_owner' => 1,
            'is_admin' => 1,
            'is_locked' => 0,
            'notifications' => CompanySettings::notificationDefaults(),
            // 'permissions' => '',
            'settings' => null,
        ]);

        $u2 = User::where('email', 'demo@invoiceninja.com')->first();

        if (! $u2) {
            $u2 = User::factory()->create([
                'email'             => 'demo@invoiceninja.com',
                'password'          => Hash::make('Password0'),
                'account_id' => $account->id,
                'confirmation_code' => $this->createDbHash(config('database.default')),
                'email_verified_at' => now(),
            ]);

            $company_token = new CompanyToken();
            $company_token->user_id = $u2->id;
            $company_token->company_id = $company->id;
            $company_token->account_id = $account->id;
            $company_token->name = 'test token';
            $company_token->token = 'TOKEN';
            $company_token->is_system = true;
            $company_token->save();

            $u2->companies()->attach($company->id, [
                'account_id' => $account->id,
                'is_owner' => 1,
                'is_admin' => 1,
                'is_locked' => 0,
                'notifications' => CompanySettings::notificationDefaults(),
                'permissions' => '',
                'settings' => null,
            ]);
        }

        Product::factory()->count(50)->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $bi = BankIntegration::factory()->create([
            'account_id' => $account->id,
            'company_id' => $company->id,
            'user_id' => $user->id,
        ]);

        BankTransaction::factory()->count(50)->create([
            'bank_integration_id' => $bi->id,
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        $this->info('Creating '.$this->count.' clients');

        for ($x = 0; $x < $this->count; $x++) {
            $z = $x + 1;
            $this->info('Creating client # '.$z);

            $this->createClient($company, $user, $u2->id);
        }

        for ($x = 0; $x < $this->count; $x++) {
            $client = $company->clients->random();

            $this->info('creating entities for client #'.$client->id);
            $this->createInvoice($client, $u2->id);

            $this->createRecurringInvoice($client, $u2->id);

            $client = $company->clients->random();
            $this->createCredit($client, $u2->id);

            $client = $company->clients->random();
            $this->createQuote($client, $u2->id);

            $client = $company->clients->random();
            $this->createExpense($client);

            $client = $company->clients->random();
            $this->createVendor($client, $u2->id);

            $client = $company->clients->random();
            $this->createTask($client, $u2->id);

            $client = $company->clients->random();
            $this->createProject($client, $u2->id);
        }
    }

    private function createClient($company, $user, $assigned_user_id = null)
    {
        // dispatch(function () use ($company, $user) {

        // });
        $client = Client::factory()->create([
            'user_id' => $user->id,
            'company_id' => $company->id,
        ]);

        ClientContact::factory()->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
            'is_primary' => 1,
        ]);

        ClientContact::factory()->count(rand(1, 5))->create([
            'user_id' => $user->id,
            'client_id' => $client->id,
            'company_id' => $company->id,
        ]);

        $client->number = $this->getNextClientNumber($client);

        $settings = $client->settings;
        $settings->currency_id = (string) rand(1, 3);
        $client->settings = $settings;

        if (rand(0, 1)) {
            $client->assigned_user_id = $assigned_user_id;
        }

        $client->country_id = array_rand([36, 392, 840, 124, 276, 826]);
        $client->save();
    }

    private function createExpense($client)
    {
        Expense::factory()->count(rand(1, 5))->create([
            'user_id' => $client->user_id,
            'client_id' => $client->id,
            'company_id' => $client->company_id,
        ]);

        Expense::all()->each(function ($expense) {
            $expense->number = $this->getNextExpenseNumber($expense);
            $expense->save();
        });
    }

    private function createVendor($client, $assigned_user_id = null)
    {
        $vendor = Vendor::factory()->create([
            'user_id' => $client->user_id,
            'company_id' => $client->company_id,
        ]);

        VendorContact::factory()->create([
            'user_id' => $client->user->id,
            'vendor_id' => $vendor->id,
            'company_id' => $client->company_id,
            'is_primary' => 1,
        ]);

        VendorContact::factory()->count(rand(1, 5))->create([
            'user_id' => $client->user->id,
            'vendor_id' => $vendor->id,
            'company_id' => $client->company_id,
            'is_primary' => 0,
        ]);

        $vendor->number = $this->getNextVendorNumber($vendor);
        $vendor->save();
    }

    private function createTask($client, $assigned_user_id = null)
    {
        $task = Task::factory()->create([
            'user_id' => $client->user->id,
            'company_id' => $client->company_id,
            'client_id' => $client->id,
        ]);

        $task->status_id = TaskStatus::all()->random()->id;

        $task->number = $this->getNextTaskNumber($task);
        $task->save();
    }

    private function createProject($client, $assigned_user_id = null)
    {
        $project = Project::factory()->create([
            'user_id' => $client->user->id,
            'company_id' => $client->company_id,
            'client_id' => $client->id,
        ]);

        $project->number = $this->getNextProjectNumber($project);
        $project->save();
    }

    private function createInvoice($client, $assigned_user_id = null)
    {
        $faker = \Faker\Factory::create();

        $invoice = InvoiceFactory::create($client->company->id, $client->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;

        if ((bool) rand(0, 1)) {
            $dateable = Carbon::now()->subDays(rand(0, 90));
        } else {
            $dateable = Carbon::now()->addDays(rand(0, 90));
        }

        $invoice->date = $dateable;

        $invoice->line_items = $this->buildLineItems(rand(1, 10));
        $invoice->uses_inclusive_taxes = false;

        // if (rand(0, 1)) {
        //     $invoice->tax_name1 = 'GST';
        //     $invoice->tax_rate1 = 10.00;
        // }

        // if (rand(0, 1)) {
        //     $invoice->tax_name2 = 'VAT';
        //     $invoice->tax_rate2 = 17.50;
        // }

        // if (rand(0, 1)) {
        //     $invoice->tax_name3 = 'CA Sales Tax';
        //     $invoice->tax_rate3 = 5;
        // }

        // $invoice->custom_value1 = $faker->date();
        // $invoice->custom_value2 = rand(0, 1) ? 'yes' : 'no';

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        if (rand(0, 1)) {
            $invoice->assigned_user_id = $assigned_user_id;
        }

        $invoice->save();
        $invoice->service()->createInvitations()->markSent();

        $this->invoice_repo->markSent($invoice);

        if ((bool) rand(0, 2)) {
            $invoice = $invoice->service()->markPaid()->save();

            $invoice->payments->each(function ($payment) {
                $payment->date = now()->addDays(rand(-30, 30));
                $payment->save();
            });
        }

        event(new InvoiceWasCreated($invoice, $invoice->company, Ninja::eventVars()));
    }

    private function createRecurringInvoice($client, $assigned_user_id = null)
    {
        $faker = \Faker\Factory::create();

        $invoice = RecurringInvoiceFactory::create($client->company->id, $client->user->id); //stub the company and user_id
        $invoice->client_id = $client->id;
        $invoice->frequency_id = RecurringInvoice::FREQUENCY_MONTHLY;
        $invoice->last_sent_date = now()->subMonth();
        $invoice->next_send_date = now()->addMonthNoOverflow();

        if ((bool) rand(0, 1)) {
            $dateable = Carbon::now()->subDays(rand(0, 90));
        } else {
            $dateable = Carbon::now()->addDays(rand(0, 90));
        }

        $invoice->date = $dateable;

        $invoice->line_items = $this->buildLineItems(rand(1, 10));
        $invoice->uses_inclusive_taxes = false;

        // if (rand(0, 1)) {
        //     $invoice->tax_name1 = 'GST';
        //     $invoice->tax_rate1 = 10.00;
        // }

        // if (rand(0, 1)) {
        //     $invoice->tax_name2 = 'VAT';
        //     $invoice->tax_rate2 = 17.50;
        // }

        // if (rand(0, 1)) {
        //     $invoice->tax_name3 = 'CA Sales Tax';
        //     $invoice->tax_rate3 = 5;
        // }

        // $invoice->custom_value1 = $faker->date();
        // $invoice->custom_value2 = rand(0, 1) ? 'yes' : 'no';

        $invoice->save();

        $invoice_calc = new InvoiceSum($invoice);
        $invoice_calc->build();

        $invoice = $invoice_calc->getInvoice();

        if (rand(0, 1)) {
            $invoice->assigned_user_id = $assigned_user_id;
        }
        $invoice->number = $this->getNextRecurringInvoiceNumber($client, $invoice);
        $invoice->save();
    }

    private function createCredit($client, $assigned_user_id = null)
    {
        $faker = \Faker\Factory::create();

        $credit = Credit::factory()->create(['user_id' => $client->user->id, 'company_id' => $client->company->id, 'client_id' => $client->id]);

        if ((bool) rand(0, 1)) {
            $dateable = Carbon::now()->subDays(rand(0, 90));
        } else {
            $dateable = Carbon::now()->addDays(rand(0, 90));
        }

        $credit->date = $dateable;

        $credit->line_items = $this->buildLineItems(rand(1, 10));
        $credit->uses_inclusive_taxes = false;

        // if (rand(0, 1)) {
        //     $credit->tax_name1 = 'GST';
        //     $credit->tax_rate1 = 10.00;
        // }

        // if (rand(0, 1)) {
        //     $credit->tax_name2 = 'VAT';
        //     $credit->tax_rate2 = 17.50;
        // }

        // if (rand(0, 1)) {
        //     $credit->tax_name3 = 'CA Sales Tax';
        //     $credit->tax_rate3 = 5;
        // }

        $credit->save();

        $invoice_calc = new InvoiceSum($credit);
        $invoice_calc->build();

        $credit = $invoice_calc->getCredit();

        if (rand(0, 1)) {
            $credit->assigned_user_id = $assigned_user_id;
        }

        $credit->save();
        $credit->service()->markSent()->save();
        $credit->service()->createInvitations();
    }

    private function createQuote($client, $assigned_user_id = null)
    {
        $faker = \Faker\Factory::create();

        $quote = Quote::factory()->create(['user_id' => $client->user->id, 'company_id' => $client->company_id, 'client_id' => $client->id]);

        if ((bool) rand(0, 1)) {
            $dateable = Carbon::now()->subDays(rand(1, 30));
            $dateable_due = $dateable->addDays(rand(1, 30));
        } else {
            $dateable = Carbon::now()->addDays(rand(1, 30));
            $dateable_due = $dateable->addDays(rand(-10, 30));
        }

        $quote->date = $dateable;
        $quote->due_date = $dateable_due;

        $quote->client_id = $client->id;

        $quote->setRelation('client', $client);

        $quote->line_items = $this->buildLineItems(rand(1, 10));
        $quote->uses_inclusive_taxes = false;

        // if (rand(0, 1)) {
        //     $quote->tax_name1 = 'GST';
        //     $quote->tax_rate1 = 10.00;
        // }

        // if (rand(0, 1)) {
        //     $quote->tax_name2 = 'VAT';
        //     $quote->tax_rate2 = 17.50;
        // }

        // if (rand(0, 1)) {
        //     $quote->tax_name3 = 'CA Sales Tax';
        //     $quote->tax_rate3 = 5;
        // }

        $quote->save();

        $quote_calc = new InvoiceSum($quote);
        $quote_calc->build();

        $quote = $quote_calc->getQuote();

        if (rand(0, 1)) {
            $quote->assigned_user_id = $assigned_user_id;
        }

        $quote->save();

        $quote->service()->markSent()->save();
        $quote->service()->createInvitations();
    }

    private function buildLineItems($count = 1)
    {
        $line_items = [];

        for ($x = 0; $x < $count; $x++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            //$item->cost = 10;

            // if (rand(0, 1)) {
            //     $item->tax_name1 = 'GST';
            //     $item->tax_rate1 = 10.00;
            // }

            // if (rand(0, 1)) {
            //     $item->tax_name1 = 'VAT';
            //     $item->tax_rate1 = 17.50;
            // }

            // if (rand(0, 1)) {
            //     $item->tax_name1 = 'Sales Tax';
            //     $item->tax_rate1 = 5;
            // }

            $product = Product::all()->random();

            $item->cost = (float) $product->cost;
            $item->product_key = $product->product_key;
            $item->notes = $product->notes;
            $item->custom_value1 = $product->custom_value1;
            $item->custom_value2 = $product->custom_value2;
            $item->custom_value3 = $product->custom_value3;
            $item->custom_value4 = $product->custom_value4;

            $line_items[] = $item;
        }

        return $line_items;
    }

}

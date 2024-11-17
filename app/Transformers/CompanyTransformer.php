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

namespace App\Transformers;

use App\Models\Account;
use App\Models\Activity;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\BankTransactionRule;
use App\Models\Client;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\CompanyLedger;
use App\Models\CompanyToken;
use App\Models\CompanyUser;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Document;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\GroupSetting;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\PaymentTerm;
use App\Models\Product;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\Scheduler;
use App\Models\Subscription;
use App\Models\SystemLog;
use App\Models\Task;
use App\Models\TaskStatus;
use App\Models\TaxRate;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Webhook;
use App\Utils\Traits\MakesHash;
use stdClass;

/**
 * Class CompanyTransformer.
 */
class CompanyTransformer extends EntityTransformer
{
    use MakesHash;

    /**
     * @var array
     */
    protected array $defaultIncludes = [
        'documents',
    ];

    /**
     * @var array
     */
    protected array $availableIncludes = [
        'documents',
        'users',
        'designs',
        'account',
        'clients',
        'contacts',
        'invoices',
        'recurring_invoices',
        'tax_rates',
        'products',
        'country',
        'timezone',
        'language',
        'expenses',
        'vendors',
        'payments',
        'payment_terms',
        'company_user',
        'groups',
        'company_gateways',
        'activities',
        'quotes',
        'credits',
        'projects',
        'tasks',
        'ledger',
        'webhooks',
        'tokens',
        'tokens_hashed',
        'system_logs',
        'expense_categories',
        'task_statuses',
        'subscriptions',
        'recurring_expenses',
        'purchase_orders',
        'bank_integrations',
        'bank_transactions',
        'bank_transaction_rules',
        'task_schedulers',
        'schedulers',
    ];

    /**
     * @param Company $company
     *
     * @return array
     */
    public function transform(Company $company)
    {
        $std = new stdClass();

        return [
            'id' => (string) $this->encodePrimaryKey($company->id),
            'company_key' => (string) $company->company_key ?: '',
            'update_products' => (bool) $company->update_products,
            'fill_products' => (bool) $company->fill_products,
            'convert_products' => (bool) $company->convert_products,
            'custom_surcharge_taxes1' => (bool) $company->custom_surcharge_taxes1,
            'custom_surcharge_taxes2' => (bool) $company->custom_surcharge_taxes2,
            'custom_surcharge_taxes3' => (bool) $company->custom_surcharge_taxes3,
            'custom_surcharge_taxes4' => (bool) $company->custom_surcharge_taxes4,
            'show_product_cost' => (bool) $company->show_product_cost,
            'enable_product_cost' => (bool) $company->enable_product_cost,
            'show_product_details' => (bool) $company->show_product_details,
            'enable_product_quantity' => (bool) $company->enable_product_quantity,
            'default_quantity' => (bool) $company->default_quantity,
            'custom_fields' => (object) $company->custom_fields ?? $std,
            'size_id' => (string) $company->size_id ?: '',
            'industry_id' => (string) $company->industry_id ?: '',
            'first_month_of_year' => (string) $company->first_month_of_year ?: '1',
            'first_day_of_week' => (string) $company->first_day_of_week ?: '',
            'subdomain' => (string) $company->subdomain ?: '',
            'portal_mode' => (string) $company->portal_mode ?: '',
            'portal_domain' => (string) $company->portal_domain ?: '',
            'settings' => $company->settings ?? '',
            'enabled_tax_rates' => (int) $company->enabled_tax_rates,
            'enabled_modules' => (int) $company->enabled_modules,
            'updated_at' => (int) $company->updated_at,
            'archived_at' => (int) $company->deleted_at,
            'created_at' => (int) $company->created_at,
            'slack_webhook_url' => (string) $company->slack_webhook_url,
            'google_analytics_url' => (string) $company->google_analytics_key, //@deprecate 1-2-2021
            'google_analytics_key' => (string) $company->google_analytics_key,
            'matomo_url' => (string) $company->matomo_url,
            'matomo_id' => (string) $company->matomo_id ?: '',
            'enabled_item_tax_rates' => (int) $company->enabled_item_tax_rates,
            'client_can_register' => (bool) $company->client_can_register,
            // 'is_large' => (bool) $company->is_large,
            'is_large' => (bool) $this->isLarge($company),
            'is_disabled' => (bool) $company->is_disabled,
            'enable_shop_api' => (bool) $company->enable_shop_api,
            'mark_expenses_invoiceable' => (bool) $company->mark_expenses_invoiceable,
            'mark_expenses_paid' => (bool) $company->mark_expenses_paid,
            'invoice_expense_documents' => (bool) $company->invoice_expense_documents,
            'invoice_task_timelog' => (bool) $company->invoice_task_timelog,
            'auto_start_tasks' => (bool) $company->auto_start_tasks,
            'invoice_task_documents' => (bool) $company->invoice_task_documents,
            'show_tasks_table' => (bool) $company->show_tasks_table,
            'use_credits_payment' => 'always', // @deprecate 1-2-2021
            'default_task_is_date_based' => (bool) $company->default_task_is_date_based,
            'enable_product_discount' => (bool) $company->enable_product_discount,
            'calculate_expense_tax_by_amount' => (bool) $company->calculate_expense_tax_by_amount,
            'hide_empty_columns_on_pdf' => false, // @deprecate 1-2-2021
            'expense_inclusive_taxes' => (bool) $company->expense_inclusive_taxes,
            'expense_amount_is_pretax' => (bool) true, //@deprecate 1-2-2021
            'oauth_password_required' => (bool) $company->oauth_password_required,
            'session_timeout' => (int) $company->session_timeout,
            'default_password_timeout' => (int) $company->default_password_timeout,
            'invoice_task_datelog' => (bool) $company->invoice_task_datelog,
            'show_task_end_date' => (bool) $company->show_task_end_date,
            'markdown_enabled' => (bool) $company->markdown_enabled,
            'use_comma_as_decimal_place' => (bool) $company->use_comma_as_decimal_place,
            'report_include_drafts' => (bool) $company->report_include_drafts,
            'client_registration_fields' => (array) $company->client_registration_fields,
            'convert_rate_to_client' => (bool) $company->convert_rate_to_client,
            'markdown_email_enabled' => (bool) $company->markdown_email_enabled,
            'stop_on_unpaid_recurring' => (bool) $company->stop_on_unpaid_recurring,
            'use_quote_terms_on_conversion' => (bool) $company->use_quote_terms_on_conversion,
            'stock_notification' => (bool) $company->stock_notification,
            'inventory_notification_threshold' => (int) $company->inventory_notification_threshold,
            'track_inventory' => (bool) $company->track_inventory,
            'enable_applying_payments' => (bool) $company->enable_applying_payments,
            'enabled_expense_tax_rates' => (int) $company->enabled_expense_tax_rates,
            'invoice_task_project' => (bool) $company->invoice_task_project,
            'report_include_deleted' => (bool) $company->report_include_deleted,
            'invoice_task_lock' => (bool) $company->invoice_task_lock,
            'convert_payment_currency' => (bool) $company->convert_payment_currency,
            'convert_expense_currency' => (bool) $company->convert_expense_currency,
            'notify_vendor_when_paid' => (bool) $company->notify_vendor_when_paid,
            'invoice_task_hours' => (bool) $company->invoice_task_hours,
            'calculate_taxes' => (bool) $company->calculate_taxes,
            'tax_data' => $company->tax_data ?: new \stdClass(),
            'has_e_invoice_certificate' => $company->e_invoice_certificate ? true : false,
            'has_e_invoice_certificate_passphrase' => $company->e_invoice_certificate_passphrase ? true : false,
            'invoice_task_project_header' => (bool) $company->invoice_task_project_header,
            'invoice_task_item_description' => (bool) $company->invoice_task_item_description,
            'origin_tax_data' => $company->origin_tax_data ?: new \stdClass,
            'expense_mailbox' => (string) $company->expense_mailbox,
            'expense_mailbox_active' => (bool) $company->expense_mailbox_active,
            'inbound_mailbox_allow_company_users' => (bool) $company->inbound_mailbox_allow_company_users,
            'inbound_mailbox_allow_vendors' => (bool) $company->inbound_mailbox_allow_vendors,
            'inbound_mailbox_allow_clients' => (bool) $company->inbound_mailbox_allow_clients,
            'inbound_mailbox_allow_unknown' => (bool) $company->inbound_mailbox_allow_unknown,
            'inbound_mailbox_blacklist' => (string) $company->inbound_mailbox_blacklist,
            'inbound_mailbox_whitelist' => (string) $company->inbound_mailbox_whitelist,
            'smtp_host' => (string) $company->smtp_host ?? '',
            'smtp_port' => (int) $company->smtp_port ?? 25,
            'smtp_encryption' => (string) $company->smtp_encryption ?? 'tls',
            'smtp_username' => $company->smtp_username ? '********' : '',
            'smtp_password' => $company->smtp_password ? '********' : '',
            'smtp_local_domain' => (string) $company->smtp_local_domain ?? '',
            'smtp_verify_peer' => (bool) $company->smtp_verify_peer,
            'e_invoice' => $company->e_invoice ?: new \stdClass(),
            'has_quickbooks_token' => $company->quickbooks ? true : false,
            'is_quickbooks_token_active' => $company->quickbooks?->accessTokenKey ?? false,
            'legal_entity_id' => $company->legal_entity_id ?? null,
        ];
    }

    private function isLarge(Company $company): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        //if the user is attached to more than one company AND they are not an admin across all companies
        if ($company->is_large || ($user->company_users()->count() > 1 && ($user->company_users()->where('is_admin', 1)->count() != $user->company_users()->count()))) {
            return true;
        }

        return false;
    }

    public function includeExpenseCategories(Company $company)
    {
        $transformer = new ExpenseCategoryTransformer($this->serializer);

        return $this->includeCollection($company->expense_categories, $transformer, ExpenseCategory::class);
    }

    public function includeTaskStatuses(Company $company)
    {
        $transformer = new TaskStatusTransformer($this->serializer);

        return $this->includeCollection($company->task_statuses, $transformer, TaskStatus::class);
    }

    public function includeDocuments(Company $company)
    {
        $transformer = new DocumentTransformer($this->serializer);

        return $this->includeCollection($company->documents, $transformer, Document::class);
    }

    public function includeCompanyUser(Company $company)
    {
        $transformer = new CompanyUserTransformer($this->serializer);

        return $this->includeItem($company->company_users->where('user_id', auth()->user()->id)->first(), $transformer, CompanyUser::class);
    }

    public function includeTokens(Company $company)
    {
        $transformer = new CompanyTokenTransformer($this->serializer);

        return $this->includeCollection($company->tokens, $transformer, CompanyToken::class);
    }


    public function includeBankTransactions(Company $company)
    {
        $transformer = new BankTransactionTransformer($this->serializer);

        return $this->includeCollection($company->bank_transactions, $transformer, BankTransaction::class);
    }


    public function includeTaskSchedulers(Company $company)
    {
        $transformer = new SchedulerTransformer($this->serializer);

        return $this->includeCollection($company->schedulers, $transformer, Scheduler::class);
    }

    public function includeSchedulers(Company $company)
    {
        $transformer = new SchedulerTransformer($this->serializer);

        return $this->includeCollection($company->schedulers, $transformer, Scheduler::class);
    }

    public function includeBankTransactionRules(Company $company)
    {
        $transformer = new BankTransactionRuleTransformer($this->serializer);

        return $this->includeCollection($company->bank_transaction_rules, $transformer, BankTransactionRule::class);
    }

    public function includeBankIntegrations(Company $company)
    {
        $transformer = new BankIntegrationTransformer($this->serializer);

        return $this->includeCollection($company->bank_integrations, $transformer, BankIntegration::class);
    }

    public function includeTokensHashed(Company $company)
    {
        $transformer = new CompanyTokenHashedTransformer($this->serializer);

        return $this->includeCollection($company->tokens, $transformer, CompanyToken::class);
    }

    public function includeWebhooks(Company $company)
    {
        $transformer = new WebhookTransformer($this->serializer);

        return $this->includeCollection($company->webhooks, $transformer, Webhook::class);
    }

    public function includeActivities(Company $company)
    {
        $transformer = new ActivityTransformer($this->serializer);

        return $this->includeCollection($company->activities, $transformer, Activity::class);
    }

    public function includeUsers(Company $company)
    {
        $transformer = new UserTransformer($this->serializer);

        $users = $company->users->map(function ($user) use ($company) {
            $user->company_id = $company->id;

            return $user;
        });

        return $this->includeCollection($users, $transformer, User::class);
    }

    public function includeCompanyGateways(Company $company)
    {
        $transformer = new CompanyGatewayTransformer($this->serializer);

        return $this->includeCollection($company->company_gateways, $transformer, CompanyGateway::class);
    }

    public function includeClients(Company $company)
    {
        $transformer = new ClientTransformer($this->serializer);

        return $this->includeCollection($company->clients, $transformer, Client::class);
    }

    public function includeProjects(Company $company)
    {
        $transformer = new ProjectTransformer($this->serializer);

        return $this->includeCollection($company->projects, $transformer, Project::class);
    }

    public function includeTasks(Company $company)
    {
        $transformer = new TaskTransformer($this->serializer);

        return $this->includeCollection($company->tasks, $transformer, Task::class);
    }

    public function includeExpenses(Company $company)
    {
        $transformer = new ExpenseTransformer($this->serializer);

        return $this->includeCollection($company->expenses, $transformer, Expense::class);
    }

    public function includeVendors(Company $company)
    {
        $transformer = new VendorTransformer($this->serializer);

        return $this->includeCollection($company->vendors, $transformer, Vendor::class);
    }

    public function includeGroups(Company $company)
    {
        $transformer = new GroupSettingTransformer($this->serializer);

        return $this->includeCollection($company->groups, $transformer, GroupSetting::class);
    }

    public function includeInvoices(Company $company)
    {
        $transformer = new InvoiceTransformer($this->serializer);

        return $this->includeCollection($company->invoices, $transformer, Invoice::class);
    }

    public function includeRecurringInvoices(Company $company)
    {
        $transformer = new RecurringInvoiceTransformer($this->serializer);

        return $this->includeCollection($company->recurring_invoices, $transformer, RecurringInvoice::class);
    }

    public function includeRecurringExpenses(Company $company)
    {
        $transformer = new RecurringExpenseTransformer($this->serializer);

        return $this->includeCollection($company->recurring_expenses, $transformer, RecurringExpense::class);
    }

    public function includeQuotes(Company $company)
    {
        $transformer = new QuoteTransformer($this->serializer);

        return $this->includeCollection($company->quotes, $transformer, Quote::class);
    }

    public function includeCredits(Company $company)
    {
        $transformer = new CreditTransformer($this->serializer);

        return $this->includeCollection($company->credits, $transformer, Credit::class);
    }

    public function includeAccount(Company $company)
    {
        $transformer = new AccountTransformer($this->serializer);

        return $this->includeItem($company->account, $transformer, Account::class);
    }

    public function includeTaxRates(Company $company)
    {
        $transformer = new TaxRateTransformer($this->serializer);

        return $this->includeCollection($company->tax_rates, $transformer, TaxRate::class);
    }

    public function includeProducts(Company $company)
    {
        $transformer = new ProductTransformer($this->serializer);

        return $this->includeCollection($company->products, $transformer, Product::class);
    }

    public function includePayments(Company $company)
    {
        $transformer = new PaymentTransformer($this->serializer);

        return $this->includeCollection($company->payments, $transformer, Payment::class);
    }

    public function includeDesigns(Company $company)
    {
        $transformer = new DesignTransformer($this->serializer);

        return $this->includeCollection($company->designs()->get(), $transformer, Design::class);
    }

    public function includeLedger(Company $company)
    {
        $transformer = new CompanyLedgerTransformer($this->serializer);

        return $this->includeCollection($company->ledger, $transformer, CompanyLedger::class);
    }

    public function includePaymentTerms(Company $company)
    {
        $transformer = new PaymentTermTransformer($this->serializer);

        return $this->includeCollection($company->payment_terms()->get(), $transformer, PaymentTerm::class);
    }

    public function includeSystemLogs(Company $company)
    {
        $transformer = new SystemLogTransformer($this->serializer);

        return $this->includeCollection($company->system_logs, $transformer, SystemLog::class);
    }

    public function includeSubscriptions(Company $company)
    {
        $transformer = new SubscriptionTransformer($this->serializer);

        return $this->includeCollection($company->subscriptions, $transformer, Subscription::class);
    }

    public function includePurchaseOrders(Company $company)
    {
        $transformer = new PurchaseOrderTransformer($this->serializer);

        return $this->includeCollection($company->purchase_orders, $transformer, PurchaseOrder::class);
    }
}

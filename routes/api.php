<?php

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
use App\Http\Controllers\EInvoicePeppolController;
use App\Http\Controllers\EInvoiceTokenController;
use App\Http\Controllers\ProductAllocationController;
use App\Http\Controllers\SubscriptionStepsController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\BaseController;
use App\Http\Controllers\BrevoController;
use App\Http\Controllers\PingController;
use App\Http\Controllers\SmtpController;
use App\Http\Controllers\TaskController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\ChartController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\QuoteController;
use App\Http\Controllers\TokenController;
use App\Http\Controllers\ClientController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\DesignController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FilterController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LogoutController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StaticController;
use App\Http\Controllers\StripeController;
use App\Http\Controllers\TwilioController;
use App\Http\Controllers\VendorController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\CompanyController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\InvoiceController;
use App\Http\Controllers\LicenseController;
use App\Http\Controllers\MailgunController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\OneTimeTokenController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PreviewController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\WebCronController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\ActivityController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\PostMarkController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\SchedulerController;
use App\Http\Controllers\SubdomainController;
use App\Http\Controllers\SystemLogController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\ImportJsonController;
use App\Http\Controllers\ImportQuickbooksController;
use App\Http\Controllers\SelfUpdateController;
use App\Http\Controllers\TaskStatusController;
use App\Http\Controllers\Bank\YodleeController;
use App\Http\Controllers\CompanyUserController;
use App\Http\Controllers\PaymentTermController;
use App\PaymentDrivers\PayPalPPCPPaymentDriver;
use App\PaymentDrivers\BlockonomicsPaymentDriver;
use App\Http\Controllers\EmailHistoryController;
use App\Http\Controllers\GroupSettingController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\Bank\NordigenController;
use App\Http\Controllers\CompanyLedgerController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\TaskSchedulerController;
use App\Http\Controllers\CompanyGatewayController;
use App\Http\Controllers\PaymentWebhookController;
use App\Http\Controllers\RecurringQuoteController;
use App\Http\Controllers\BankIntegrationController;
use App\Http\Controllers\BankTransactionController;
use App\Http\Controllers\ClientStatementController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\HostedMigrationController;
use App\Http\Controllers\TemplatePreviewController;
use App\Http\Controllers\ConnectedAccountController;
use App\Http\Controllers\RecurringExpenseController;
use App\Http\Controllers\RecurringInvoiceController;
use App\Http\Controllers\ProtectedDownloadController;
use App\Http\Controllers\ClientGatewayTokenController;
use App\Http\Controllers\Reports\TaskReportController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\BankTransactionRuleController;
use App\Http\Controllers\InAppPurchase\AppleController;
use App\Http\Controllers\Reports\QuoteReportController;
use App\Http\Controllers\Auth\PasswordTimeoutController;
use App\Http\Controllers\EInvoiceController;
use App\Http\Controllers\PreviewPurchaseOrderController;
use App\Http\Controllers\Reports\ClientReportController;
use App\Http\Controllers\Reports\CreditReportController;
use App\Http\Controllers\Reports\ReportExportController;
use App\Http\Controllers\Reports\VendorReportController;
use App\Http\Controllers\Gateways\BlockonomicsController;
use App\Http\Controllers\Reports\ExpenseReportController;
use App\Http\Controllers\Reports\InvoiceReportController;
use App\Http\Controllers\Reports\PaymentReportController;
use App\Http\Controllers\Reports\ProductReportController;
use App\Http\Controllers\Reports\ProfitAndLossController;
use App\Http\Controllers\Reports\ReportPreviewController;
use App\Http\Controllers\Reports\ActivityReportController;
use App\Http\Controllers\Reports\ARDetailReportController;
use App\Http\Controllers\Reports\DocumentReportController;
use App\Http\Controllers\Reports\ARSummaryReportController;
use App\Http\Controllers\Reports\QuoteItemReportController;
use App\Http\Controllers\Reports\UserSalesReportController;
use App\Http\Controllers\Reports\TaxSummaryReportController;
use App\Http\Controllers\Support\Messages\SendingController;
use App\Http\Controllers\Reports\ClientSalesReportController;
use App\Http\Controllers\Reports\InvoiceItemReportController;
use App\Http\Controllers\PaymentNotificationWebhookController;
use App\Http\Controllers\Reports\ProductSalesReportController;
use App\Http\Controllers\Reports\ClientBalanceReportController;
use App\Http\Controllers\Reports\ClientContactReportController;
use App\Http\Controllers\Reports\PurchaseOrderReportController;
use App\Http\Controllers\Reports\RecurringInvoiceReportController;
use App\Http\Controllers\Reports\PurchaseOrderItemReportController;

Route::group(['middleware' => ['throttle:api', 'api_secret_check']], function () {
    Route::post('api/v1/signup', [AccountController::class, 'store'])->name('signup.submit');
    Route::post('api/v1/oauth_login', [LoginController::class, 'oauthApiLogin']);
});

Route::group(['middleware' => ['throttle:login', 'api_secret_check', 'email_db']], function () {
    Route::post('api/v1/login', [LoginController::class, 'apiLogin'])->name('login.submit');
    Route::post('api/v1/reset_password', [ForgotPasswordController::class, 'sendResetLinkEmail']);
});

Route::group(['middleware' => ['throttle:api', 'api_db', 'token_auth', 'locale'], 'prefix' => 'api/v1', 'as' => 'api.'], function () {

    Route::post('password_timeout', PasswordTimeoutController::class)->name('password_timeout');
    Route::put('accounts/{account}', [AccountController::class, 'update'])->name('account.update');
    Route::resource('bank_integrations', BankIntegrationController::class); // name = (clients. index / create / show / update / destroy / edit
    Route::post('bank_integrations/refresh_accounts', [BankIntegrationController::class, 'refreshAccounts'])->name('bank_integrations.refresh_accounts')->middleware('throttle:30,1');
    Route::post('bank_integrations/remove_account/{acc_id}', [BankIntegrationController::class, 'removeAccount'])->name('bank_integrations.remove_account');
    Route::post('bank_integrations/get_transactions/{acc_id}', [BankIntegrationController::class, 'getTransactions'])->name('bank_integrations.transactions')->middleware('throttle:1,1');

    Route::post('bank_integrations/bulk', [BankIntegrationController::class, 'bulk'])->name('bank_integrations.bulk');

    Route::resource('bank_transactions', BankTransactionController::class); // name = (bank_transactions. index / create / show / update / destroy / edit
    Route::post('bank_transactions/bulk', [BankTransactionController::class, 'bulk'])->name('bank_transactions.bulk');
    Route::post('bank_transactions/match', [BankTransactionController::class, 'match'])->name('bank_transactions.match');

    Route::resource('bank_transaction_rules', BankTransactionRuleController::class); // name = (clients. index / create / show / update / destroy / edit
    Route::post('bank_transaction_rules/bulk', [BankTransactionRuleController::class, 'bulk'])->name('bank_transaction_rules.bulk');

    Route::post('check_subdomain', [SubdomainController::class, 'index'])->name('check_subdomain');
    Route::get('ping', [PingController::class, 'index'])->name('ping');
    Route::get('health_check', [PingController::class, 'health'])->name('health_check');
    Route::get('last_error', [PingController::class, 'lastError'])->name('last_error');

    Route::get('activities', [ActivityController::class, 'index']);
    Route::post('activities/entity', [ActivityController::class, 'entityActivity']);
    Route::post('activities/notes', [ActivityController::class, 'note']);
    Route::get('activities/download_entity/{activity}', [ActivityController::class, 'downloadHistoricalEntity']);

    Route::post('charts/totals', [ChartController::class, 'totals'])->name('chart.totals');
    Route::post('charts/chart_summary', [ChartController::class, 'chart_summary'])->name('chart.chart_summary');

    Route::post('charts/totals_v2', [ChartController::class, 'totalsV2'])->name('chart.totals_v2');
    Route::post('charts/chart_summary_v2', [ChartController::class, 'chart_summaryV2'])->name('chart.chart_summary_v2');
    Route::post('charts/calculated_fields', [ChartController::class, 'calculatedFields'])->name('chart.calculated_fields');

    Route::post('claim_license', [LicenseController::class, 'index'])->name('license.index');
    Route::post('check_license', [LicenseController::class, 'check'])->name('license.check');

    Route::resource('clients', ClientController::class); // name = (clients. index / create / show / update / destroy / edit
    Route::put('clients/{client}/upload', [ClientController::class, 'upload'])->name('clients.upload');
    Route::post('clients/{client}/purge', [ClientController::class, 'purge'])->name('clients.purge')->middleware('password_protected');
    Route::post('clients/{client}/updateTaxData', [ClientController::class, 'updateTaxData'])->name('clients.update_tax_data')->middleware('throttle:3,1');
    Route::post('clients/{client}/{mergeable_client}/merge', [ClientController::class, 'merge'])->name('clients.merge')->middleware('password_protected');
    Route::post('clients/bulk', [ClientController::class, 'bulk'])->name('clients.bulk');
    Route::post('clients/{client}/documents', [ClientController::class, 'documents'])->name('clients.documents');

    Route::post('reactivate_email/{bounce_id}', [ClientController::class, 'reactivateEmail'])->name('clients.reactivate_email');

    Route::post('filters/{entity}', [FilterController::class, 'index'])->name('filters');


    Route::resource('client_gateway_tokens', ClientGatewayTokenController::class);
    Route::post('client_gateway_tokens/{client_gateway_token}/setAsDefault', [ClientGatewayTokenController::class, 'setAsDefault'])->name('client_gateway_tokens.set_as_default');

    Route::post('connected_account', [ConnectedAccountController::class, 'index']);
    Route::post('connected_account/gmail', [ConnectedAccountController::class, 'handleGmailOauth']);

    Route::post('client_statement', [ClientStatementController::class, 'statement'])->name('client.statement');

    Route::post('companies/current', [CompanyController::class, 'current'])->name('companies.current');
    Route::post('companies/purge/{company}', [MigrationController::class, 'purgeCompany'])->middleware('password_protected');
    Route::post('companies/purge_save_settings/{company}', [MigrationController::class, 'purgeCompanySaveSettings'])->middleware('password_protected');
    Route::resource('companies', CompanyController::class); // name = (companies. index / create / show / update / destroy / edit

    Route::get('companies/{company}/logo', [CompanyController::class, 'logo']);
    Route::put('companies/{company}/upload', [CompanyController::class, 'upload']);
    Route::post('companies/{company}/default', [CompanyController::class, 'default']);
    Route::post('companies/updateOriginTaxData/{company}', [CompanyController::class, 'updateOriginTaxData'])->middleware('throttle:3,1');

    Route::get('company_ledger', [CompanyLedgerController::class, 'index'])->name('company_ledger.index');

    Route::resource('company_gateways', CompanyGatewayController::class);

    Route::post('company_gateways/bulk', [CompanyGatewayController::class, 'bulk'])->name('company_gateways.bulk');
    Route::post('company_gateways/{company_gateway}/test', [CompanyGatewayController::class, 'test'])->name('company_gateways.test');
    Route::post('company_gateways/{company_gateway}/import_customers', [CompanyGatewayController::class, 'importCustomers'])->name('company_gateways.import_customers');

    Route::put('company_users/{user}', [CompanyUserController::class, 'update']);
    Route::put('company_users/{user}/preferences', [CompanyUserController::class, 'updatePreferences']);

    Route::resource('credits', CreditController::class); // name = (credits. index / create / show / update / destroy / edit
    Route::put('credits/{credit}/upload', [CreditController::class, 'upload'])->name('credits.upload');
    Route::get('credits/{credit}/{action}', [CreditController::class, 'action'])->name('credits.action');
    Route::post('credits/bulk', [CreditController::class, 'bulk'])->name('credits.bulk');
    Route::get('credit/{invitation_key}/download', [CreditController::class, 'downloadPdf'])->name('credits.downloadPdf');
    Route::get('credit/{invitation_key}/download_e_credit', [CreditController::class, 'downloadECredit'])->name('credits.downloadECredit');

    Route::resource('designs', DesignController::class); // name = (payments. index / create / show / update / destroy / edit
    Route::post('designs/bulk', [DesignController::class, 'bulk'])->name('designs.bulk');
    Route::post('designs/set/default', [DesignController::class, 'default'])->name('designs.default');

    Route::resource('documents', DocumentController::class); // name = (documents. index / create / show / update / destroy / edit
    Route::get('documents/{document}/download', [DocumentController::class, 'download'])->name('documents.download');
    Route::post('documents/bulk', [DocumentController::class, 'bulk'])->name('documents.bulk');

    Route::post('einvoice/validateEntity', [EInvoiceController::class, 'validateEntity'])->name('einvoice.validateEntity');
    Route::post('einvoice/configurations', [EInvoiceController::class, 'configurations'])->name('einvoice.configurations');

    Route::post('einvoice/peppol/legal_entity', [EInvoicePeppolController::class, 'show'])->name('einvoice.peppol.legal_entity');
    Route::post('einvoice/peppol/setup', [EInvoicePeppolController::class, 'setup'])->name('einvoice.peppol.setup');
    Route::post('einvoice/peppol/disconnect', [EInvoicePeppolController::class, 'disconnect'])->name('einvoice.peppol.disconnect');
    Route::put('einvoice/peppol/update', [EInvoicePeppolController::class, 'updateLegalEntity'])->name('einvoice.peppol.update_legal_entity');
    Route::post('einvoice/peppol/add_additional_legal_identifier', [EInvoicePeppolController::class, 'addAdditionalTaxIdentifier'])->name('einvoice.peppol.add_additional_legal_identifier');
    Route::delete('einvoice/peppol/remove_additional_legal_identifier', [EInvoicePeppolController::class, 'removeAdditionalTaxIdentifier'])->name('einvoice.peppol.remove_additional_legal_identifier');
    Route::post('einvoice/peppol/send', [EInvoicePeppolController::class, 'retrySend'])->name('einvoice.peppol.retry_send');

    Route::post('einvoice/token/update', EInvoiceTokenController::class)->name('einvoice.token.update');
    Route::get('einvoice/quota', [EInvoiceController::class, 'quota'])->name('einvoice.quota');
    Route::get('einvoice/health_check', [EInvoiceController::class, 'healthcheck'])->name('einvoice.healthcheck');

    Route::post('emails', [EmailController::class, 'send'])->name('email.send')->middleware('user_verified');
    Route::post('emails/clientHistory/{client}', [EmailHistoryController::class, 'clientHistory'])->name('email.clientHistory');
    Route::post('emails/entityHistory', [EmailHistoryController::class, 'entityHistory'])->name('email.entityHistory');

    Route::resource('expenses', ExpenseController::class); // name = (expenses. index / create / show / update / destroy / edit
    Route::put('expenses/{expense}/upload', [ExpenseController::class, 'upload']);
    Route::post('expenses/bulk', [ExpenseController::class, 'bulk'])->name('expenses.bulk');
    Route::post('export', [ExportController::class, 'index'])->name('export.index');
    Route::put('edocument/upload', [ExpenseController::class, "edocument"])->name("expenses.edocument");

    Route::resource('expense_categories', ExpenseCategoryController::class); // name = (expense_categories. index / create / show / update / destroy / edit
    Route::post('expense_categories/bulk', [ExpenseCategoryController::class, 'bulk'])->name('expense_categories.bulk');

    Route::resource('group_settings', GroupSettingController::class);
    Route::post('group_settings/bulk', [GroupSettingController::class, 'bulk']);
    Route::put('group_settings/{group_setting}/upload', [GroupSettingController::class, 'upload'])->name('group_settings.upload');

    Route::post('import', [ImportController::class, 'import'])->name('import.import');
    Route::post('import_json', [ImportJsonController::class, 'import'])->name('import.import_json');
    Route::post('preimport', [ImportController::class, 'preimport'])->name('import.preimport');

    Route::resource('invoices', InvoiceController::class); // name = (invoices. index / create / show / update / destroy / edit
    Route::get('invoices/{invoice}/delivery_note', [InvoiceController::class, 'deliveryNote'])->name('invoices.delivery_note');
    Route::get('invoices/{invoice}/{action}', [InvoiceController::class, 'action'])->name('invoices.action');
    Route::put('invoices/{invoice}/upload', [InvoiceController::class, 'upload'])->name('invoices.upload');
    Route::get('invoice/{invitation_key}/download', [InvoiceController::class, 'downloadPdf'])->name('invoices.downloadPdf');
    Route::get('invoice/{invitation_key}/download_e_invoice', [InvoiceController::class, 'downloadEInvoice'])->name('invoices.downloadEInvoice');
    Route::post('invoices/bulk', [InvoiceController::class, 'bulk'])->name('invoices.bulk');
    Route::post('invoices/update_reminders', [InvoiceController::class, 'update_reminders'])->name('invoices.update_reminders');

    Route::post('logout', [LogoutController::class, 'index'])->name('logout');

    Route::post('migrate', [MigrationController::class, 'index'])->name('migrate.start');

    Route::post('migration/purge/{company}', [MigrationController::class, 'purgeCompany'])->middleware('password_protected');
    Route::post('migration/purge_save_settings/{company}', [MigrationController::class, 'purgeCompanySaveSettings'])->middleware('password_protected');
    Route::post('migration/start', [MigrationController::class, 'startMigration']);

    Route::post('one_time_token', [OneTimeTokenController::class, 'create']);

    Route::resource('payments', PaymentController::class); // name = (payments. index / create / show / update / destroy / edit
    Route::post('payments/refund', [PaymentController::class, 'refund'])->name('payments.refund');
    Route::post('payments/bulk', [PaymentController::class, 'bulk'])->name('payments.bulk');
    Route::put('payments/{payment}/upload', [PaymentController::class, 'upload']);

    Route::resource('payment_terms', PaymentTermController::class); // name = (payments. index / create / show / update / destroy / edit
    Route::post('payment_terms/bulk', [PaymentTermController::class, 'bulk'])->name('payment_terms.bulk');

    Route::post('preview', [PreviewController::class, 'show'])->name('preview.show');
    Route::post('live_preview', [PreviewController::class, 'live'])->name('preview.live');
    Route::post('live_design', [PreviewController::class, 'design'])->name('preview.design');

    Route::post('preview/purchase_order', [PreviewPurchaseOrderController::class, 'show'])->name('preview_purchase_order.show');
    Route::post('live_preview/purchase_order', [PreviewPurchaseOrderController::class, 'live'])->name('preview_purchase_order.live');

    Route::resource('products', ProductController::class); // name = (products. index / create / show / update / destroy / edit
    Route::post('products/bulk', [ProductController::class, 'bulk'])->name('products.bulk');
    Route::put('products/{product}/upload', [ProductController::class, 'upload']);

    Route::resource('product_allocations', ProductAllocationController::class); // name = (productAllocations. index / create / show / update / destroy / edit
    Route::post('product_allocations/bulk', [ProductAllocationController::class, 'bulk'])->name('product_allocations.bulk');
    Route::put('product_allocations/{productAllocation}/upload', [ProductAllocationController::class, 'upload']);

    Route::resource('projects', ProjectController::class); // name = (projects. index / create / show / update / destroy / edit
    Route::post('projects/bulk', [ProjectController::class, 'bulk'])->name('projects.bulk');
    Route::put('projects/{project}/upload', [ProjectController::class, 'upload'])->name('projects.upload');
    Route::post('projects/{project}/invoice', [ProjectController::class, 'invoice'])->name('projects.invoice');

    Route::resource('purchase_orders', PurchaseOrderController::class);
    Route::post('purchase_orders/bulk', [PurchaseOrderController::class, 'bulk'])->name('purchase_orders.bulk');
    Route::put('purchase_orders/{purchase_order}/upload', [PurchaseOrderController::class, 'upload']);
    Route::get('purchase_orders/{purchase_order}/{action}', [PurchaseOrderController::class, 'action'])->name('purchase_orders.action');
    Route::get('purchase_order/{invitation_key}/download', [PurchaseOrderController::class, 'downloadPdf'])->name('purchase_orders.downloadPdf');
    Route::get('purchase_order/{invitation_key}/download_e_purchase_order', [PurchaseOrderController::class, 'downloadEPurchaseOrder'])->name('purchase_orders.downloadEPurchaseOrder');

    Route::resource('quotes', QuoteController::class); // name = (quotes. index / create / show / update / destroy / edit
    Route::get('quotes/{quote}/{action}', [QuoteController::class, 'action'])->name('quotes.action');
    Route::post('quotes/bulk', [QuoteController::class, 'bulk'])->name('quotes.bulk');
    Route::put('quotes/{quote}/upload', [QuoteController::class, 'upload']);
    Route::get('quote/{invitation_key}/download', [QuoteController::class, 'downloadPdf'])->name('quotes.downloadPdf');
    Route::get('quote/{invitation_key}/download_e_quote', [QuoteController::class, 'downloadEQuote'])->name('quotes.downloadEQuote');

    Route::resource('recurring_expenses', RecurringExpenseController::class);
    Route::post('recurring_expenses/bulk', [RecurringExpenseController::class, 'bulk'])->name('recurring_expenses.bulk');
    Route::put('recurring_expenses/{recurring_expense}/upload', [RecurringExpenseController::class, 'upload']);

    Route::resource('recurring_invoices', RecurringInvoiceController::class); // name = (recurring_invoices. index / create / show / update / destroy / edit
    Route::post('recurring_invoices/bulk', [RecurringInvoiceController::class, 'bulk'])->name('recurring_invoices.bulk');
    Route::put('recurring_invoices/{recurring_invoice}/upload', [RecurringInvoiceController::class, 'upload']);
    Route::resource('recurring_quotes', RecurringQuoteController::class); // name = (recurring_invoices. index / create / show / update / destroy / edit
    Route::post('recurring_quotes/bulk', [RecurringQuoteController::class, 'bulk'])->name('recurring_quotes.bulk');
    Route::put('recurring_quotes/{recurring_quote}/upload', [RecurringQuoteController::class, 'upload']);

    Route::post('refresh', [LoginController::class, 'refresh'])->middleware('throttle:refresh');

    Route::post('reports/clients', ClientReportController::class)->middleware('throttle:20,1');
    Route::post('reports/activities', ActivityReportController::class)->middleware('throttle:20,1');
    Route::post('reports/client_contacts', ClientContactReportController::class)->middleware('throttle:20,1');
    Route::post('reports/contacts', ClientContactReportController::class)->middleware('throttle:20,1');
    Route::post('reports/credits', CreditReportController::class)->middleware('throttle:20,1');
    Route::post('reports/documents', DocumentReportController::class)->middleware('throttle:20,1');
    Route::post('reports/expenses', ExpenseReportController::class)->middleware('throttle:20,1');
    Route::post('reports/invoices', InvoiceReportController::class)->middleware('throttle:20,1');
    Route::post('reports/invoice_items', InvoiceItemReportController::class)->middleware('throttle:20,1');
    Route::post('reports/purchase_orders', PurchaseOrderReportController::class)->middleware('throttle:20,1');
    Route::post('reports/purchase_order_items', PurchaseOrderItemReportController::class)->middleware('throttle:20,1');
    Route::post('reports/quotes', QuoteReportController::class)->middleware('throttle:20,1');
    Route::post('reports/quote_items', QuoteItemReportController::class)->middleware('throttle:20,1');
    Route::post('reports/recurring_invoices', RecurringInvoiceReportController::class)->middleware('throttle:20,1');
    Route::post('reports/payments', PaymentReportController::class)->middleware('throttle:20,1');
    Route::post('reports/products', ProductReportController::class)->middleware('throttle:20,1');
    Route::post('reports/product_sales', ProductSalesReportController::class)->middleware('throttle:20,1');
    Route::post('reports/tasks', TaskReportController::class)->middleware('throttle:20,1');
    Route::post('reports/vendors', VendorReportController::class)->middleware('throttle:20,1');
    Route::post('reports/profitloss', ProfitAndLossController::class);
    Route::post('reports/ar_detail_report', ARDetailReportController::class);
    Route::post('reports/ar_summary_report', ARSummaryReportController::class);
    Route::post('reports/client_balance_report', ClientBalanceReportController::class);
    Route::post('reports/client_sales_report', ClientSalesReportController::class);
    Route::post('reports/tax_summary_report', TaxSummaryReportController::class);
    Route::post('reports/user_sales_report', UserSalesReportController::class);
    Route::post('reports/preview/{hash}', ReportPreviewController::class);
    Route::post('exports/preview/{hash}', ReportExportController::class);

    Route::post('templates/preview/{hash}', TemplatePreviewController::class);
    Route::post('search', SearchController::class);

    Route::resource('task_schedulers', TaskSchedulerController::class);
    Route::post('task_schedulers/bulk', [TaskSchedulerController::class, 'bulk'])->name('task_schedulers.bulk');

    Route::get('scheduler', [SchedulerController::class, 'index']);
    Route::post('support/messages/send', SendingController::class);

    Route::post('self-update', [SelfUpdateController::class, 'update'])->middleware('password_protected');
    Route::post('self-update/check_version', [SelfUpdateController::class, 'checkVersion']);

    Route::resource('system_logs', SystemLogController::class);

    Route::resource('tasks', TaskController::class); // name = (tasks. index / create / show / update / destroy / edit
    Route::post('tasks/bulk', [TaskController::class, 'bulk'])->name('tasks.bulk');
    Route::put('tasks/{task}/upload', [TaskController::class, 'upload']);
    Route::post('tasks/sort', [TaskController::class, 'sort']);

    Route::resource('task_statuses', TaskStatusController::class); // name = (task_statuses. index / create / show / update / destroy / edit
    Route::post('task_statuses/bulk', [TaskStatusController::class, 'bulk'])->name('task_statuses.bulk');

    Route::resource('tax_rates', TaxRateController::class); // name = (tax_rates. index / create / show / update / destroy / edit
    Route::post('tax_rates/bulk', [TaxRateController::class, 'bulk'])->name('tax_rates.bulk');

    Route::post('templates', [TemplateController::class, 'show'])->name('templates.show');

    Route::resource('tokens', TokenController::class); // name = (tokens. index / create / show / update / destroy / edit
    Route::post('tokens/bulk', [TokenController::class, 'bulk'])->name('tokens.bulk');

    Route::get('settings/enable_two_factor', [TwoFactorController::class, 'setupTwoFactor']);
    Route::post('settings/enable_two_factor', [TwoFactorController::class, 'enableTwoFactor']);
    Route::post('settings/disable_two_factor', [TwoFactorController::class, 'disableTwoFactor']);

    Route::post('verify', [TwilioController::class, 'generate'])->name('verify.generate')->middleware('throttle:3,1');
    Route::post('verify/confirm', [TwilioController::class, 'confirm'])->name('verify.confirm');

    Route::resource('vendors', VendorController::class); // name = (vendors. index / create / show / update / destroy / edit
    Route::post('vendors/bulk', [VendorController::class, 'bulk'])->name('vendors.bulk');
    Route::put('vendors/{vendor}/upload', [VendorController::class, 'upload']);
    Route::post('vendors/{vendor}/{mergeable_vendor}/merge', [VendorController::class, 'merge'])->name('vendors.merge')->middleware('password_protected');

    Route::get('users', [UserController::class, 'index']);
    Route::get('users/create', [UserController::class, 'create'])->middleware('password_protected');
    Route::get('users/{user}', [UserController::class, 'show'])->middleware('password_protected');
    Route::put('users/{user}', [UserController::class, 'update'])->middleware('password_protected');
    Route::post('users', [UserController::class, 'store'])->middleware('password_protected');
    //Route::post('users/{user}/attach_to_company', [UserController::class, 'attach')->middleware('password_protected');
    Route::delete('users/{user}/detach_from_company', [UserController::class, 'detach'])->middleware('password_protected');

    Route::post('users/bulk', [UserController::class, 'bulk'])->name('users.bulk')->middleware('password_protected');
    Route::post('/users/{user}/invite', [UserController::class, 'invite'])->middleware('password_protected');
    Route::post('/users/{user}/disconnect_mailer', [UserController::class, 'disconnectOauthMailer']);
    Route::post('/users/{user}/disconnect_oauth', [UserController::class, 'disconnectOauth']);
    Route::post('/user/{user}/reconfirm', [UserController::class, 'reconfirm']);

    Route::resource('webhooks', WebhookController::class);
    Route::post('webhooks/bulk', [WebhookController::class, 'bulk'])->name('webhooks.bulk');
    Route::post('webhooks/{webhook}/retry', [WebhookController::class, 'retry'])->name('webhooks.retry');

    /*Subscription and Webhook routes */
    // Route::post('hooks', [SubscriptionController::class, 'subscribe'])->name('hooks.subscribe');
    // Route::delete('hooks/{subscription_id}', [SubscriptionController::class, 'unsubscribe'])->name('hooks.unsubscribe');

    Route::post('smtp/check', [SmtpController::class, 'check'])->name('smtp.check')->middleware('throttle:10,1');

    Route::post('stripe/update_payment_methods', [StripeController::class, 'update'])->middleware('password_protected')->name('stripe.update');
    Route::post('stripe/import_customers', [StripeController::class, 'import'])->middleware('password_protected')->name('stripe.import');

    Route::post('stripe/verify', [StripeController::class, 'verify'])->middleware('password_protected')->name('stripe.verify');
    Route::post('stripe/disconnect/{company_gateway_id}', [StripeController::class, 'disconnect'])->middleware('password_protected')->name('stripe.disconnect');

    Route::get('subscriptions/steps', [SubscriptionStepsController::class, 'index']);
    Route::post('subscriptions/steps/check', [SubscriptionStepsController::class, 'check']);

    Route::resource('subscriptions', SubscriptionController::class);

    Route::post('subscriptions/bulk', [SubscriptionController::class, 'bulk'])->name('subscriptions.bulk');
    Route::get('statics', StaticController::class);
    // Route::post('apple_pay/upload_file','ApplyPayController::class, 'upload');

    Route::post('yodlee/status/{account_number}', [YodleeController::class, 'accountStatus']); // @todo @turbo124 check route-path?!

    Route::get('nordigen/institutions', [NordigenController::class, 'institutions'])->name('nordigen.institutions');

});

Route::post('api/v1/sms_reset', [TwilioController::class, 'generate2faResetCode'])->name('sms_reset.generate')->middleware('throttle:3,1');
Route::post('api/v1/sms_reset/confirm', [TwilioController::class, 'confirm2faResetCode'])->name('sms_reset.confirm')->middleware('throttle:3,1');

Route::match(['get', 'post'], 'payment_webhook/{company_key}/{company_gateway_id}', PaymentWebhookController::class)
    ->middleware('throttle:1000,1')
    ->name('payment_webhook');

Route::match(['get', 'post'], 'payment_notification_webhook/{company_key}/{company_gateway_id}/{client}', PaymentNotificationWebhookController::class)
    ->middleware('throttle:1000,1')
    ->name('payment_notification_webhook');


Route::post('api/v1/postmark_webhook', [PostMarkController::class, 'webhook'])->middleware('throttle:2000,1');
Route::post('api/v1/postmark_inbound_webhook', [PostMarkController::class, 'inboundWebhook'])->middleware('throttle:1000,1');
Route::post('api/v1/mailgun_webhook', [MailgunController::class, 'webhook'])->middleware('throttle:1000,1');
Route::post('api/v1/mailgun_inbound_webhook', [MailgunController::class, 'inboundWebhook'])->middleware('throttle:1000,1');
Route::post('api/v1/brevo_webhook', [BrevoController::class, 'webhook'])->middleware('throttle:1000,1');
Route::post('api/v1/brevo_inbound_webhook', [BrevoController::class, 'inboundWebhook'])->middleware('throttle:1000,1');
Route::get('token_hash_router', [OneTimeTokenController::class, 'router'])->middleware('throttle:500,1');
Route::get('webcron', [WebCronController::class, 'index'])->middleware('throttle:100,1');
Route::post('api/v1/get_migration_account', [HostedMigrationController::class, 'getAccount'])->middleware('guest')->middleware('throttle:100,1');
Route::post('api/v1/confirm_forwarding', [HostedMigrationController::class, 'confirmForwarding'])->middleware('guest')->middleware('throttle:100,1');
Route::post('api/v1/check_status', [HostedMigrationController::class, 'checkStatus'])->middleware('guest')->middleware('throttle:100,1');
Route::post('api/v1/process_webhook', [AppleController::class, 'process_webhook'])->middleware('throttle:1000,1');
Route::post('api/v1/confirm_purchase', [AppleController::class, 'confirm_purchase'])->middleware('throttle:1000,1');

Route::post('api/v1/yodlee/refresh', [YodleeController::class, 'refreshWebhook'])->middleware('throttle:100,1');
Route::post('api/v1/yodlee/data_updates', [YodleeController::class, 'dataUpdatesWebhook'])->middleware('throttle:100,1');
Route::post('api/v1/yodlee/refresh_updates', [YodleeController::class, 'refreshUpdatesWebhook'])->middleware('throttle:100,1');
Route::post('api/v1/yodlee/balance', [YodleeController::class, 'balanceWebhook'])->middleware('throttle:100,1');

Route::get('api/v1/protected_download/{hash}', [ProtectedDownloadController::class, 'index'])->name('protected_download')->middleware('throttle:300,1');
Route::post('api/v1/ppcp/webhook', [PayPalPPCPPaymentDriver::class, 'processWebhookRequest'])->middleware('throttle:1000,1');
Route::get('api/v1/get-btc-price', [BlockonomicsController::class, 'getBTCPrice'])->middleware('throttle:1000,1');
Route::get('api/v1/get-blockonomics-qr-code', [BlockonomicsController::class, 'getQRCode'])->middleware('throttle:1000,1');

Route::get('quickbooks/authorize/{token}', [ImportQuickbooksController::class, 'authorizeQuickbooks'])->name('quickbooks.authorize');
Route::get('quickbooks/authorized', [ImportQuickbooksController::class, 'onAuthorized'])->name('quickbooks.authorized');

Route::fallback([BaseController::class, 'notFound'])->middleware('throttle:404');

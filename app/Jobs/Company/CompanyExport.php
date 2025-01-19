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

use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Mail\NinjaMailerObject;
use App\Jobs\Util\UnlinkFile;
use App\Libraries\MultiDB;
use App\Mail\DownloadBackup;
use App\Models\Company;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Models\User;
use App\Models\VendorContact;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Hyvor\JsonExporter\File;

class CompanyExport implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;

    private $export_format = 'json';

    private $export_data = [];
    private $writer;
    private $file_name;
    /**
     * Create a new job instance.
     *
     * @param \App\Models\Company $company
     * @param \App\Models\User $user
     * @param string $hash
     */
    public function __construct(public Company $company, private User $user, public string $hash)
    {
    }

    /**
     * Execute the job.
     *
     */
    public function handle()
    {
        MultiDB::setDb($this->company->db);

        $this->file_name = date('Y-m-d') . '_' . str_replace([" ", "/"], ["_",""], $this->company->present()->name() . '_' . $this->company->company_key . '.json');

        $this->writer = new File(sys_get_temp_dir().'/'.$this->file_name);

        set_time_limit(0);

        $this->writer->value('app_version', config('ninja.app_version'));
        $this->writer->value('storage_url', Storage::url(''));

        $this->export_data['activities'] = $this->company->all_activities->map(function ($activity) {
            $activity = $this->transformArrayOfKeys($activity, [
                'user_id',
                'company_id',
                'client_id',
                'client_contact_id',
                'account_id',
                'project_id',
                'vendor_id',
                'payment_id',
                'invoice_id',
                'credit_id',
                'invitation_id',
                'task_id',
                'expense_id',
                'token_id',
                'quote_id',
                'subscription_id',
                'recurring_invoice_id'
            ]);

            return $activity;
        })->makeHidden(['id'])->all();


        $x = $this->writer->collection('activities');
        $x->addItems($this->export_data['activities']);
        $this->export_data = null;


        $this->export_data['users'] = $this->company->users()->withTrashed()->cursor()->map(function ($user) {
            /** @var \App\Models\User $user */
            $user->account_id = $this->encodePrimaryKey($user->account_id); //@phpstan-ignore-line
            return $user;
        })->all();

        $x = $this->writer->collection('users');
        $x->addItems($this->export_data['users']);
        $this->export_data = null;


        $this->export_data['client_contacts'] = $this->company->client_contacts->map(function ($client_contact) {
            $client_contact = $this->transformArrayOfKeys($client_contact, ['company_id', 'user_id', 'client_id']);

            return $client_contact->makeVisible([
                'password',
                'remember_token',
                'user_id',
                'company_id',
                'client_id',
                'google_2fa_secret',
                'id',
                'oauth_provider_id',
                'oauth_user_id',
                'token',
                'hashed_id',
            ]);
        })->all();


        $x = $this->writer->collection('client_contacts');
        $x->addItems($this->export_data['client_contacts']);
        $this->export_data = null;

        $this->export_data['client_gateway_tokens'] = $this->company->client_gateway_tokens->map(function ($client_gateway_token) {
            $client_gateway_token = $this->transformArrayOfKeys($client_gateway_token, ['company_id', 'client_id', 'company_gateway_id']);

            return $client_gateway_token->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('client_gateway_tokens');
        $x->addItems($this->export_data['client_gateway_tokens']);
        $this->export_data = null;

        $this->export_data['clients'] = $this->company->clients()->orderBy('number', 'DESC')->cursor()->map(function ($client) {
            $client = $this->transformArrayOfKeys($client, ['company_id', 'user_id', 'assigned_user_id', 'group_settings_id']);
            $client->tax_data = '';
            return $client->makeVisible(['id','private_notes','user_id','company_id','last_login','hashed_id'])->makeHidden(['is_tax_exempt','has_valid_vat_number']);
        })->all();


        $x = $this->writer->collection('clients');
        $x->addItems($this->export_data['clients']);
        $this->export_data = null;

        // $this->export_data['company'] = $this->company->toArray();
        // $this->export_data['company']['company_key'] = $this->createHash();

        $this->writer->value('company', $this->company->toJson(), encode: false);

        // $x = $this->writer->collection('company');
        // $x->addItems($this->export_data['company']);
        // $this->export_data = null;


        $this->export_data['company_gateways'] = $this->company->company_gateways()->withTrashed()->cursor()->map(function ($company_gateway) {
            $company_gateway = $this->transformArrayOfKeys($company_gateway, ['company_id', 'user_id']);
            $company_gateway->config = decrypt($company_gateway->config);

            return $company_gateway->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('company_gateways');
        $x->addItems($this->export_data['company_gateways']);
        $this->export_data = null;




        $this->export_data['company_tokens'] = $this->company->tokens->map(function ($token) {
            $token = $this->transformArrayOfKeys($token, ['company_id', 'account_id', 'user_id']);

            return $token;
        })->all();


        $x = $this->writer->collection('company_tokens');
        $x->addItems($this->export_data['company_tokens']);
        $this->export_data = null;


        $this->export_data['company_ledger'] = $this->company->ledger->map(function ($ledger) {
            $ledger = $this->transformArrayOfKeys($ledger, ['activity_id', 'client_id', 'company_id', 'account_id', 'user_id','company_ledgerable_id']);

            return $ledger;
        })->all();


        $x = $this->writer->collection('company_ledger');
        $x->addItems($this->export_data['company_ledger']);
        $this->export_data = null;


        $this->export_data['company_users'] = $this->company->company_users()->without(['user','account'])->cursor()->map(function ($company_user) {
            $company_user = $this->transformArrayOfKeys($company_user, ['company_id', 'account_id', 'user_id']);
            return $company_user;
        })->all();


        $x = $this->writer->collection('company_users');
        $x->addItems($this->export_data['company_users']);
        $this->export_data = null;


        $this->export_data['credits'] = $this->company->credits()->orderBy('number', 'DESC')->cursor()->map(function ($credit) {
            $credit = $this->transformBasicEntities($credit);
            $credit = $this->transformArrayOfKeys($credit, ['recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id','invoice_id']);

            return $credit->makeVisible(['id']);
        })->all();

        $x = $this->writer->collection('credits');
        $x->addItems($this->export_data['credits']);
        $this->export_data = null;


        $this->export_data['credit_invitations'] = CreditInvitation::query()->where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($credit) {
            $credit = $this->transformArrayOfKeys($credit, ['company_id', 'user_id', 'client_contact_id', 'credit_id']);

            return $credit->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('credit_invitations');
        $x->addItems($this->export_data['credit_invitations']);
        $this->export_data = null;


        $this->export_data['designs'] = $this->company->user_designs->makeHidden(['id'])->all();


        $x = $this->writer->collection('designs');
        $x->addItems($this->export_data['designs']);
        $this->export_data = null;


        $this->export_data['documents'] = $this->company->all_documents->map(function ($document) {
            $document = $this->transformArrayOfKeys($document, ['user_id', 'assigned_user_id', 'company_id', 'project_id', 'vendor_id','documentable_id']);
            $document->hashed_id = $this->encodePrimaryKey($document->id);

            return $document->makeVisible(['id']);
        })->all();

        $x = $this->writer->collection('documents');
        $x->addItems($this->export_data['documents']);
        $this->export_data = null;

        $this->export_data['expense_categories'] = $this->company->expense_categories()->cursor()->map(function ($expense_category) {
            $expense_category = $this->transformArrayOfKeys($expense_category, ['user_id', 'company_id']);

            return $expense_category->makeVisible(['id']);
        })->all();

        $x = $this->writer->collection('expense_categories');
        $x->addItems($this->export_data['expense_categories']);
        $this->export_data = null;


        $this->export_data['expenses'] = $this->company->expenses()->orderBy('number', 'DESC')->cursor()->map(function ($expense) {
            $expense = $this->transformBasicEntities($expense);
            $expense = $this->transformArrayOfKeys($expense, ['vendor_id', 'invoice_id', 'client_id', 'category_id', 'recurring_expense_id','project_id']);

            return $expense->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('expenses');
        $x->addItems($this->export_data['expenses']);
        $this->export_data = null;


        $this->export_data['group_settings'] = $this->company->group_settings->map(function ($gs) {
            $gs = $this->transformArrayOfKeys($gs, ['user_id', 'company_id']);

            return $gs->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('group_settings');
        $x->addItems($this->export_data['group_settings']);
        $this->export_data = null;


        $this->export_data['invoices'] = $this->company->invoices()->orderBy('number', 'DESC')->cursor()->map(function ($invoice) {
            $invoice = $this->transformBasicEntities($invoice);
            $invoice = $this->transformArrayOfKeys($invoice, ['recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);
            $invoice->tax_data = '';

            return $invoice->makeHidden(['gateway_fee'])->makeVisible(['id',
                                        'private_notes',
                                        'user_id',
                                        'client_id',
                                        'company_id',]);
        })->all();


        $x = $this->writer->collection('invoices');
        $x->addItems($this->export_data['invoices']);
        $this->export_data = null;

        $this->export_data['invoice_invitations'] = InvoiceInvitation::query()->where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($invoice) {
            $invoice = $this->transformArrayOfKeys($invoice, ['company_id', 'user_id', 'client_contact_id', 'invoice_id']);

            return $invoice->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('invoice_invitations');
        $x->addItems($this->export_data['invoice_invitations']);
        $this->export_data = null;


        $this->export_data['payment_terms'] = $this->company->user_payment_terms->map(function ($term) {
            $term = $this->transformArrayOfKeys($term, ['user_id', 'company_id']);

            return $term;
        })->makeHidden(['id'])->all();


        $x = $this->writer->collection('payment_terms');
        $x->addItems($this->export_data['payment_terms']);
        $this->export_data = null;


        $this->export_data['payments'] = $this->company->payments()->orderBy('number', 'DESC')->cursor()->map(function ($payment) {
            $payment = $this->transformBasicEntities($payment);
            $payment = $this->transformArrayOfKeys($payment, ['client_id','project_id', 'vendor_id', 'client_contact_id', 'invitation_id', 'company_gateway_id', 'transaction_id']);

            $payment->paymentables = $this->transformPaymentable($payment);

            return $payment->makeVisible(['id']);
        })->all();



        $x = $this->writer->collection('payments');
        $x->addItems($this->export_data['payments']);
        $this->export_data = null;


        $this->export_data['products'] = $this->company->products->map(function ($product) {
            $product = $this->transformBasicEntities($product);
            $product = $this->transformArrayOfKeys($product, ['vendor_id','project_id']);

            return $product->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('products');
        $x->addItems($this->export_data['products']);
        $this->export_data = null;


        $this->export_data['projects'] = $this->company->projects()->orderBy('number', 'DESC')->cursor()->map(function ($project) {
            $project = $this->transformBasicEntities($project);
            $project = $this->transformArrayOfKeys($project, ['client_id']);

            return $project->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('projects');
        $x->addItems($this->export_data['projects']);
        $this->export_data = null;


        $this->export_data['quotes'] = $this->company->quotes()->orderBy('number', 'DESC')->cursor()->map(function ($quote) {
            $quote = $this->transformBasicEntities($quote);
            $quote = $this->transformArrayOfKeys($quote, ['invoice_id','recurring_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);

            return $quote->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('quotes');
        $x->addItems($this->export_data['quotes']);
        $this->export_data = null;


        $this->export_data['quote_invitations'] = QuoteInvitation::query()->where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($quote) {
            $quote = $this->transformArrayOfKeys($quote, ['company_id', 'user_id', 'client_contact_id', 'quote_id']);

            return $quote->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('quote_invitations');
        $x->addItems($this->export_data['quote_invitations']);
        $this->export_data = null;


        $this->export_data['recurring_expenses'] = $this->company->recurring_expenses()->orderBy('number', 'DESC')->cursor()->map(function ($expense) {
            $expense = $this->transformBasicEntities($expense);
            $expense = $this->transformArrayOfKeys($expense, ['vendor_id', 'invoice_id', 'client_id', 'category_id', 'project_id']);

            return $expense->makeVisible(['id']);
        })->all();



        $x = $this->writer->collection('recurring_expenses');
        $x->addItems($this->export_data['recurring_expenses']);
        $this->export_data = null;


        $this->export_data['recurring_invoices'] = $this->company->recurring_invoices()->orderBy('number', 'DESC')->cursor()->map(function ($ri) {
            $ri = $this->transformBasicEntities($ri);
            $ri = $this->transformArrayOfKeys($ri, ['client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id']);

            return $ri->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('recurring_invoices');
        $x->addItems($this->export_data['recurring_invoices']);
        $this->export_data = null;


        $this->export_data['recurring_invoice_invitations'] = RecurringInvoiceInvitation::query()->where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($ri) {
            $ri = $this->transformArrayOfKeys($ri, ['company_id', 'user_id', 'client_contact_id', 'recurring_invoice_id']);

            return $ri;
        })->all();


        $x = $this->writer->collection('recurring_invoice_invitations');
        $x->addItems($this->export_data['recurring_invoice_invitations']);
        $this->export_data = null;



        $this->export_data['subscriptions'] = $this->company->subscriptions->map(function ($subscription) {
            $subscription = $this->transformBasicEntities($subscription);
            $subscription->group_id = $this->encodePrimaryKey($subscription->group_id);

            return $subscription->makeVisible([ 'id',
                                                'user_id',
                                                'assigned_user_id',
                                                'company_id',
                                                'product_ids',
                                                'recurring_product_ids',
                                                'group_id']);
        })->all();


        $x = $this->writer->collection('subscriptions');
        $x->addItems($this->export_data['subscriptions']);
        $this->export_data = null;


        $this->export_data['system_logs'] = $this->company->system_logs->map(function ($log) {
            $log->client_id = $this->encodePrimaryKey($log->client_id);/** @phpstan-ignore-line */
            $log->company_id = $this->encodePrimaryKey($log->company_id);/** @phpstan-ignore-line */
            return $log;
        })->makeHidden(['id'])->all();


        $x = $this->writer->collection('system_logs');
        $x->addItems($this->export_data['system_logs']);
        $this->export_data = null;


        $this->export_data['tasks'] = $this->company->tasks()->orderBy('number', 'DESC')->cursor()->map(function ($task) {
            $task = $this->transformBasicEntities($task);
            $task = $this->transformArrayOfKeys($task, ['client_id', 'invoice_id', 'project_id', 'status_id']);

            return $task->makeHidden(['hash','meta'])->makeVisible(['id']);
        })->all();

        $x = $this->writer->collection('tasks');
        $x->addItems($this->export_data['tasks']);
        $this->export_data = null;


        $this->export_data['task_statuses'] = $this->company->task_statuses->map(function ($status) {
            $status->id = $this->encodePrimaryKey($status->id); /** @phpstan-ignore-line */
            $status->user_id = $this->encodePrimaryKey($status->user_id);/** @phpstan-ignore-line */
            $status->company_id = $this->encodePrimaryKey($status->company_id); /** @phpstan-ignore-line */

            return $status;
        })->all();



        $x = $this->writer->collection('task_statuses');
        $x->addItems($this->export_data['task_statuses']);
        $this->export_data = null;


        $this->export_data['tax_rates'] = $this->company->tax_rates->map(function ($rate) {
            $rate->company_id = $this->encodePrimaryKey($rate->company_id); /** @phpstan-ignore-line */
            $rate->user_id = $this->encodePrimaryKey($rate->user_id); /** @phpstan-ignore-line */


            return $rate;
        })->makeHidden(['id'])->all();



        $x = $this->writer->collection('tax_rates');
        $x->addItems($this->export_data['tax_rates']);
        $this->export_data = null;


        $this->export_data['vendors'] = $this->company->vendors()->orderBy('number', 'DESC')->cursor()->map(function ($vendor) {
            return $this->transformBasicEntities($vendor)->makeVisible(['id']);
        })->all();



        $x = $this->writer->collection('vendors');
        $x->addItems($this->export_data['vendors']);
        $this->export_data = null;


        $this->export_data['vendor_contacts'] = VendorContact::where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($vendor) {
            $vendor = $this->transformBasicEntities($vendor);
            $vendor = $this->transformArrayOfKeys($vendor, ['vendor_id']);

            return $vendor->makeVisible(['id','user_id']);
        })->all();



        $x = $this->writer->collection('vendor_contacts');
        $x->addItems($this->export_data['vendor_contacts']);
        $this->export_data = null;


        $this->export_data['webhooks'] = $this->company->webhooks->map(function ($hook) {
            $hook->user_id = $this->encodePrimaryKey($hook->user_id);/** @phpstan-ignore-line */
            $hook->company_id = $this->encodePrimaryKey($hook->company_id);/** @phpstan-ignore-line */
            return $hook;
        })->makeHidden(['id'])->all();


        $x = $this->writer->collection('webhooks');
        $x->addItems($this->export_data['webhooks']);
        $this->export_data = null;


        $this->export_data['purchase_orders'] = $this->company->purchase_orders()->orderBy('number', 'DESC')->cursor()->map(function ($purchase_order) {
            $purchase_order = $this->transformBasicEntities($purchase_order);
            $purchase_order = $this->transformArrayOfKeys($purchase_order, ['expense_id','client_id', 'vendor_id', 'project_id', 'design_id', 'subscription_id','project_id']);

            return $purchase_order->makeVisible(['id',
                                        'private_notes',
                                        'user_id',
                                        'client_id',
                                        'vendor_id',
                                        'company_id',]);
        })->all();


        $x = $this->writer->collection('purchase_orders');
        $x->addItems($this->export_data['purchase_orders']);
        $this->export_data = null;



        $this->export_data['purchase_order_invitations'] = PurchaseOrderInvitation::query()->where('company_id', $this->company->id)->withTrashed()->cursor()->map(function ($purchase_order) {
            $purchase_order = $this->transformArrayOfKeys($purchase_order, ['company_id', 'user_id', 'vendor_contact_id', 'purchase_order_id']);

            return $purchase_order->makeVisible(['id']);
        })->all();


        $x = $this->writer->collection('purchase_order_invitations');
        $x->addItems($this->export_data['purchase_order_invitations']);
        $this->export_data = null;

        $this->export_data['bank_integrations'] = $this->company->bank_integrations()->withTrashed()->orderBy('id', 'ASC')->cursor()->map(function ($bank_integration) {
            $bank_integration = $this->transformArrayOfKeys($bank_integration, ['account_id','company_id', 'user_id']);

            return $bank_integration->makeVisible(['id','user_id','company_id','account_id','hashed_id']);
        })->all();

        $x = $this->writer->collection('bank_integrations');
        $x->addItems($this->export_data['bank_integrations']);
        $this->export_data = null;

        $this->export_data['bank_transactions'] = $this->company->bank_transactions()->withTrashed()->orderBy('id', 'ASC')->cursor()->map(function ($bank_transaction) {
            $bank_transaction = $this->transformArrayOfKeys($bank_transaction, ['company_id', 'user_id','bank_integration_id','expense_id','ninja_category_id','vendor_id']);

            return $bank_transaction->makeVisible(['id','user_id','company_id']);
        })->all();

        $x = $this->writer->collection('bank_transactions');
        $x->addItems($this->export_data['bank_transactions']);
        $this->export_data = null;

        $this->export_data['schedulers'] = $this->company->schedulers()->withTrashed()->orderBy('id', 'ASC')->cursor()->map(function ($scheduler) {
            $scheduler = $this->transformArrayOfKeys($scheduler, ['company_id', 'user_id']);

            return $scheduler->makeVisible(['id','user_id','company_id']);
        })->all();

        $x = $this->writer->collection('schedulers');
        $x->addItems($this->export_data['schedulers']);
        $this->export_data = null;

        //write to tmp and email to owner();


        $this->writer->end();


        $this->zipAndSend();

        return true;
    }

    private function transformBasicEntities($model)
    {
        return $this->transformArrayOfKeys($model, ['user_id', 'assigned_user_id', 'company_id']);
    }

    private function transformArrayOfKeys($model, $keys)
    {
        foreach ($keys as $key) {
            $model->{$key} = $this->encodePrimaryKey($model->{$key});
        }

        return $model;
    }

    private function transformPaymentable($payment)
    {
        $new_arr = [];

        foreach ($payment->paymentables as $paymentable) {
            $paymentable->payment_id = $this->encodePrimaryKey($paymentable->payment_id);
            $paymentable->paymentable_id = $this->encodePrimaryKey($paymentable->paymentable_id);

            $new_arr[] = $paymentable;
        }

        return $new_arr;
    }

    private function zipAndSend()
    {

        $zip_path = sys_get_temp_dir().'/'.\Illuminate\Support\Str::ascii(str_replace(".json", ".zip", $this->file_name));

        $zip = new \ZipArchive();

        if ($zip->open($zip_path, \ZipArchive::CREATE) !== true) {
            nlog("cannot open {$zip_path}");
        }

        $zip->addFile(sys_get_temp_dir().'/'.$this->file_name, 'backup.json');
        // $zip->renameName($this->file_name, 'backup.json');

        $zip->close();

        Storage::disk(config('filesystems.default'))->put('backups/'.str_replace(".json", ".zip", $this->file_name), file_get_contents($zip_path));

        if (file_exists($zip_path)) {
            unlink($zip_path);
        }

        if (file_exists(sys_get_temp_dir().'/'.$this->file_name)) {
            unlink(sys_get_temp_dir().'/'.$this->file_name);
        }

        if (Ninja::isSelfHost()) {
            $storage_path = 'backups/'.str_replace(".json", ".zip", $this->file_name);
        } else {
            $storage_path = Storage::disk(config('filesystems.default'))->path('backups/'.str_replace(".json", ".zip", $this->file_name));
        }

        $url = Cache::get($this->hash);

        Cache::put($this->hash, $storage_path, 3600);

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $nmo = new NinjaMailerObject();
        $nmo->mailable = new DownloadBackup($url, $this->company->withoutRelations());
        $nmo->to_user = $this->user;
        $nmo->company = $this->company->withoutRelations();
        $nmo->settings = $this->company->settings;

        (new NinjaMailerJob($nmo, true))->handle();

        UnlinkFile::dispatch(config('filesystems.default'), $storage_path)->delay(now()->addHours(1));

        if (Ninja::isHosted()) {
            sleep(3);

            if (file_exists(sys_get_temp_dir().'/'.$zip_path)) {
                unlink(sys_get_temp_dir().'/'.$zip_path);
            }
        }
    }
}

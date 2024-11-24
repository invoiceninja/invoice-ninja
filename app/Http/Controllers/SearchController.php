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

namespace App\Http\Controllers;

use App\Models\User;
use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Invoice;
use Elastic\Elasticsearch\ClientBuilder;
use App\Http\Requests\Search\GenericSearchRequest;

class SearchController extends Controller
{
    private array $clients = [];

    private array $client_contacts = [];

    private array $invoices = [];

    private array $quotes = [];
    
    private array $expenses = [];

    private array $credits = [];
    
    private array $recurring_invoices = [];
    
    private array $vendors = [];
    
    private array $vendor_contacts = [];
    
    private array $purchase_orders = [];


    public function __invoke(GenericSearchRequest $request)
    {
        if (config('scout.driver') == 'elastic') {
            try {
                return $this->search($request->input('search', '*'));
            } catch (\Exception $e) {
                nlog("elk down?" . $e->getMessage());
            }
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $this->clientMap($user);

        $this->invoiceMap($user);

        return response()->json([
            'clients' => $this->clients,
            'client_contacts' => $this->client_contacts,
            'invoices' => $this->invoices,
            'settings' => $this->settingsMap(),
        ], 200);

    }

    public function search(string $search)
    {
        $user = auth()->user();
        $company = $user->company();

        \Illuminate\Support\Facades\App::setLocale($company->locale());

        $elastic = ClientBuilder::fromConfig(config('elastic.client.connections.default'));

        $params = [
            'index' => 'clients,invoices,client_contacts,quotes,expenses,credits,recurring_invoices,vendors,vendor_contacts,purchase_orders',
            'body'  => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $search,
                                'fields' => ['*'],
                                'fuzziness' => 'AUTO',
                            ],
                        ],
                        'filter' => [
                            'match' => [
                                'company_key' => $company->company_key,
                            ],
                        ],
                    ],
                ],
                'size' => 100,
            ],
        ];

        $results = $elastic->search($params);

        $this->mapResults($results['hits']['hits'] ?? []);

        return response()->json([
            'clients' => $this->clients,
            'client_contacts' => $this->client_contacts,
            'invoices' => $this->invoices,
            'quotes' => $this->quotes,

            'expenses' => $this->expenses,
            'credits' => $this->credits,
            'recurring_invoices' => $this->recurring_invoices,
            'vendors' => $this->vendors,
            'vendor_contacts' => $this->vendor_contacts,
            'purchase_orders' => $this->purchase_orders,
            'settings' => $this->settingsMap(),
        ], 200);

    }

    private function mapResults(array $results)
    {

        foreach ($results as $result) {
            switch ($result['_index']) {
                case 'clients':

                    if ($result['_source']['is_deleted']) { //do not return deleted results
                        break;
                    }

                    $this->clients[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/client',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/clients/{$result['_source']['hashed_id']}"
                    ];

                    break;
                case 'invoices':

                    if ($result['_source']['is_deleted']) {  //do not return deleted invoices
                        break;
                    }


                    $this->invoices[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/invoice',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/invoices/{$result['_source']['hashed_id']}/edit"
                    ];
                    break;
                case 'client_contacts':

                    if ($result['_source']['__soft_deleted']) { 
                        break;
                    }

                    $this->client_contacts[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/client',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/clients/{$result['_source']['hashed_id']}"
                    ];
                    break;
                case 'quotes':

                    if ($result['_source']['__soft_deleted']) { 
                        break;
                    }

                    $this->quotes[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/quote',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/quotes/{$result['_source']['hashed_id']}"
                    ];

                    break;

                case 'expenses':
                    
                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->expenses[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/expense',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/expenses/{$result['_source']['hashed_id']}"
                    ];

                    break;

                case 'credits':

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->credits[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/credit',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/credits/{$result['_source']['hashed_id']}"
                    ];

                    break;

                case 'recurring_invoices':

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->recurring_invoices[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/recurring_invoice',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/recurring_invoices/{$result['_source']['hashed_id']}"
                    ];

                    break;

                case 'vendors':

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->vendors[] = [
                       'name' => $result['_source']['name'],
                       'type' => '/vendor',
                       'id' => $result['_source']['hashed_id'],
                       'path' => "/vendors/{$result['_source']['hashed_id']}"
                   ];

                    break;

                case 'vendor_contacts':

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->vendor_contacts[] = [
                        'name' => $result['_source']['name'],
                        'type' => '/client',
                        'id' => $result['_source']['hashed_id'],
                        'path' => "/clients/{$result['_source']['hashed_id']}"
                    ];

                    break;

                case 'purchase_orders':

                    if ($result['_source']['__soft_deleted']) {
                        break;
                    }

                    $this->purchase_orders[] = [
                       'name' => $result['_source']['name'],
                       'type' => '/purchase_order',
                       'id' => $result['_source']['hashed_id'],
                       'path' => "/purchase_orders/{$result['_source']['hashed_id']}"
                   ];

                    break;

            }
        }
    }

    private function clientMap(User $user)
    {

        $clients =  Client::query()
                     ->company()
                     ->where('is_deleted', 0)
                     ->when(!$user->hasPermission('view_all') || !$user->hasPermission('view_client'), function ($query) use ($user) {
                         $query->where('user_id', $user->id);
                     })
                     ->orderBy('updated_at', 'desc')
                     ->take(1000)
                     ->get();

        foreach ($clients as $client) {
            $this->clients[] = [
                'name' => $client->present()->name(),
                'type' => '/client',
                'id' => $client->hashed_id,
                'path' => "/clients/{$client->hashed_id}"
            ];

            $client->contacts->each(function ($contact) {
                $this->client_contacts[] = [
                    'name' => $contact->present()->search_display(),
                    'type' => '/client',
                    'id' => $contact->client->hashed_id,
                    'path' => "/clients/{$contact->client->hashed_id}"
                ];
            });
        }


    }

    private function invoiceMap(User $user)
    {

        $invoices = Invoice::query()
                     ->company()
                     ->with('client')
                     ->where('is_deleted', 0)
                     ->whereHas('client', function ($q) {
                         $q->where('is_deleted', 0);
                     })
                     ->when(!$user->hasPermission('view_all') || !$user->hasPermission('view_invoice'), function ($query) use ($user) {
                         $query->where('invoices.user_id', $user->id);
                     })
                     ->orderBy('id', 'desc')
                    ->take(3000)
                    ->get();

        foreach ($invoices as $invoice) {
            $this->invoices[] = [
                'name' => $invoice->client->present()->name() . ' - ' . $invoice->number,
                'type' => '/invoice',
                'id' => $invoice->hashed_id,
                'path' => "/invoices/{$invoice->hashed_id}/edit"
            ];
        }

    }

    private function settingsMap()
    {

        $paths = [
            'user_details' => '/settings/user_details',
            'password' => '/settings/user_details/password',
            'connect' => '/settings/user_details/connect',
            'accent_color' => '/settings/user_details/accent_color',
            'notifications' => '/settings/user_details/notifications',
            'enable_two_factor' => '/settings/user_details/enable_two_factor',
            'custom_fields' => '/settings/user_details/custom_fields',
            'preferences' => '/settings/user_details/preferences',
            'company_details' => '/settings/company_details',
            'company_details,details' => '/settings/company_details/details',
            'company_details,address' => '/settings/company_details/address',
            'company_details,logo' => '/settings/company_details/logo',
            'company_details,defaults' => '/settings/company_details/defaults',
            'company_details,documents' => '/settings/company_details/documents',
            'company_details,custom_fields' => '/settings/company_details/custom_fields',
            'localization' => '/settings/localization',
            'localization,custom_labels' => '/settings/localization/custom_labels',
            'online_payments' => '/settings/online_payments',
            'tax_settings' => '/settings/tax_settings',
            'product_settings' => '/settings/product_settings',
            'task_settings' => '/settings/task_settings',
            'expense_settings' => '/settings/expense_settings',
            'workflow_settings' => '/settings/workflow_settings',
            'import_export' => '/settings/import_export',
            'account_management' => '/settings/account_management',
            'account_management,overview' => '/settings/account_management/overview',
            'account_management,enabled_modules' => '/settings/account_management/enabled_modules',
            'account_management,integrations' => '/settings/account_management/integrations',
            'account_management,security_settings' => '/settings/account_management/security_settings',
            'account_management,danger_zone' => '/settings/account_management/danger_zone',
            'backup_restore' => '/settings/backup_restore',
            'backup_restore,restore' => '/settings/backup_restore/restore',
            'backup_restore,backup' => '/settings/backup_restore/backup',
            'custom_fields' => '/settings/custom_fields',
            'custom_fields,company' => '/settings/custom_fields/company',
            'custom_fields,clients' => '/settings/custom_fields/clients',
            'custom_fields,products' => '/settings/custom_fields/products',
            'custom_fields,invoices' => '/settings/custom_fields/invoices',
            'custom_fields,payments' => '/settings/custom_fields/payments',
            'custom_fields,projects' => '/settings/custom_fields/projects',
            'custom_fields,tasks' => '/settings/custom_fields/tasks',
            'custom_fields,vendors' => '/settings/custom_fields/vendors',
            'custom_fields,expenses' => '/settings/custom_fields/expenses',
            'custom_fields,users' => '/settings/custom_fields/users',
            'generated_numbers' => '/settings/generated_numbers',
            'client_portal' => '/settings/client_portal',
            'email_settings' => '/settings/email_settings',
            'templates_and_reminders' => '/settings/templates_and_reminders',
            'bank_accounts' => '/settings/bank_accounts',
            'group_settings' => '/settings/group_settings',
            'subscriptions' => '/settings/subscriptions',
            'schedules' => '/settings/schedules',
            'users' => '/settings/users',
            'system_logs' => '/settings/system_logs',
            'payment_terms' => '/settings/payment_terms',
            'tax_rates' => '/settings/tax_rates',
            'task_statuses' => '/settings/task_statuses',
            'expense_categories' => '/settings/expense_categories',
            'integrations' => '/settings/integrations',
            'integrations,api_tokens' => '/settings/integrations/api_tokens',
            'integrations,api_webhooks' => '/settings/integrations/api_webhooks',
            'integrations,analytics' => '/settings/integrations/analytics',
            'gateways' => '/settings/online_payments',
            'gateways,create' => '/settings/gateways/create',
            'bank_accounts,transaction_rules' => '/settings/bank_accounts/transaction_rules',
            'bank_accounts,transaction_rules,create' => '/settings/bank_accounts/transaction_rules/create',
        ];

        $data = [];

        foreach ($paths as $key => $value) {

            $translation = '';

            foreach (explode(",", $key) as $transkey) {
                $translation .= ctrans("texts.{$transkey}")." ";
            }

            $translation = rtrim($translation, " ");

            $data[] = [
                'id' => $translation,
                'path' => $value,
                'type' => $transkey,
                'name' => $translation,
            ];
        }

        ksort($data);

        return $data;
    }

}

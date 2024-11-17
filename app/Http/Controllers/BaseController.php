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
use App\Models\Design;
use App\Utils\Statics;
use App\Models\Account;
use App\Models\TaxRate;
use App\Models\Webhook;
use App\Models\Scheduler;
use App\Models\TaskStatus;
use App\Models\PaymentTerm;
use Illuminate\Support\Str;
use League\Fractal\Manager;
use App\Models\GroupSetting;
use Illuminate\Http\Response;
use App\Models\CompanyGateway;
use App\Utils\Traits\AppSetup;
use App\Models\BankIntegration;
use App\Models\BankTransaction;
use App\Models\ExpenseCategory;
use League\Fractal\Resource\Item;
use App\Models\BankTransactionRule;
use Illuminate\Support\Facades\Auth;
use App\Transformers\ArraySerializer;
use Illuminate\Support\Facades\Schema as DbSchema;
use App\Transformers\EntityTransformer;
use League\Fractal\Resource\Collection;
use Illuminate\Database\Eloquent\Builder;
use InvoiceNinja\EInvoice\Decoder\Schema;
use League\Fractal\Serializer\JsonApiSerializer;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use Illuminate\Contracts\Container\BindingResolutionException;

/**
 * Class BaseController.
 * @method static Illuminate\Database\Eloquent\Builder exclude($columns)
 */
class BaseController extends Controller
{
    use AppSetup;

    /**
     * Passed from the parent when we need to force
     * includes internally rather than externally via
     * the $_REQUEST 'include' variable.
     *
     * @var array
     */
    public $forced_includes = [];

    /**
     * Passed from the parent when we need to force
     * the key of the response object.
     * @var string
     */
    public $forced_index = 'data';

    /**
     * The calling controller Model Type
     */
    protected $entity_type;

    /**
     * The calling controller Transformer type
     *
     */
    protected $entity_transformer;

    /**
     * The serializer in use with Fractal
     *
     */
    protected $serializer;

    /* Grouped permissions when we want to hide columns for particular permission groups*/

    private array $client_exclusion_fields = ['balance', 'paid_to_date', 'credit_balance', 'client_hash'];
    private array $client_excludable_permissions = ['view_client'];
    private array $client_excludable_overrides = ['edit_client', 'edit_all', 'view_invoice', 'view_all', 'edit_invoice'];

    /* Grouped permissions when we want to hide columns for particular permission groups*/


    /**
     * Fractal manager.
     * @var Manager $manager
     */
    protected Manager $manager;

    /**
     * An array of includes to be loaded by default.
     */
    private $first_load = [
          'account',
          'user.company_user',
          'token.company_user',
          'company.activities',
          'company.designs.company',
          'company.task_statuses',
          'company.expense_categories',
          'company.documents',
          'company.users.company_user',
          'company.clients.contacts.company',
          'company.clients.gateway_tokens',
          'company.clients.documents',
          'company.company_gateways.gateway',
          'company.credits.invitations.contact',
          'company.credits.invitations.company',
          'company.credits.documents',
          'company.expenses.documents',
          'company.groups.documents',
          'company.invoices.invitations.contact',
          'company.invoices.invitations.company',
          'company.purchase_orders.invitations',
          'company.invoices.documents',
          'company.products',
          'company.products.documents',
          'company.payments.paymentables',
          'company.payments.documents',
          'company.purchase_orders.documents',
          'company.payment_terms.company',
          'company.projects.documents',
          'company.recurring_expenses',
          'company.recurring_invoices',
          'company.recurring_invoices.invitations.contact',
          'company.recurring_invoices.invitations.company',
          'company.recurring_invoices.documents',
          'company.quotes.invitations.contact',
          'company.quotes.invitations.company',
          'company.quotes.documents',
          'company.tasks.documents',
        //   'company.tasks.project',
          'company.subscriptions',
          'company.tax_rates',
          'company.tokens_hashed',
          'company.vendors.contacts.company',
          'company.vendors.documents',
          'company.webhooks',
          'company.system_logs',
          'company.bank_integrations',
          'company.bank_transactions',
          'company.bank_transaction_rules',
          'company.task_schedulers',
        ];

    /**
     * An array of includes to be loaded by default
     * when the company is large.
     */
    private $mini_load = [
        'account',
        'user.company_user',
        'token',
        'company.activities',
        'company.tax_rates',
        'company.documents',
        'company.company_gateways.gateway',
        'company.users.company_user',
        'company.task_statuses',
        'company.payment_terms',
        'company.groups',
        'company.designs.company',
        'company.expense_categories',
        'company.subscriptions',
        'company.bank_integrations',
        'company.bank_transaction_rules',
        'company.task_schedulers',
    ];

    /**
     * __construct
     *
     * @return void
     */
    public function __construct()
    {
        $this->manager = new Manager();
    }

    /**
     * Initializes the Manager and transforms
     * the required includes
     *
     * @return void
     */
    private function buildManager()
    {
        $include = '';

        if (request()->has('first_load') && request()->input('first_load') == 'true') {
            $include = implode(',', array_merge($this->forced_includes, $this->getRequestIncludes([])));
        } elseif (request()->input('include') !== null) {
            $include = array_merge($this->forced_includes, explode(',', request()->input('include')));
            $include = implode(',', $include);
        } elseif (count($this->forced_includes) >= 1) {
            $include = implode(',', $this->forced_includes);
        }

        // $include = $this->filterIncludes($include);

        $this->manager->parseIncludes($include);

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }
    }

    /**
     * Catch all fallback route.
     */
    public function notFound()
    {
        return response()->json(['message' => ctrans('texts.api_404')], 404)
                         ->header('X-API-VERSION', config('ninja.minimum_client_version'))
                         ->header('X-APP-VERSION', config('ninja.app_version'));
    }

    /**
     * Filters the includes to ensure the
     * end user has the correct permissions to
     * view the includes
     *
     * @param  string  $includes The includes for the object
     * @return string            The filtered array of includes
     */
    // private function filterIncludes(string $includes): string
    // {
    //     $permissions_array = [
    //         'payments' => 'view_payment',
    //         'client' => 'view_client',
    //         'clients' => 'view_client',
    //         'vendor' => 'view_vendor',
    //         'vendors' => 'view_vendors',
    //         'expense' => 'view_expense',
    //         'expenses' => 'view_expense',
    //     ];

    //     $collection = collect(explode(",", $includes));

    //     $filtered_includes = $collection->filter(function ($include) use ($permissions_array) {
    //         return auth()->user()->hasPermission($permissions_array[$include]);
    //     });

    //     return $filtered_includes->implode(",");
    // }

    /**
     * 404 for the client portal.
     * @return Response| \Illuminate\Http\JsonResponse 404 response
     */
    public function notFoundClient()
    {
        abort(404, 'Page not found in the client portal.');
    }

    public function notFoundVendor()
    {
        abort(404, 'Page not found in the vendor portal.');
    }

    /**
     * API Error response.
     *
     * @param string|array    $message        The return error message
     * @param int       $httpErrorCode  404/401/403 etc
     * @return Response| \Illuminate\Http\JsonResponse                 The JSON response
     * @throws BindingResolutionException
     */
    protected function errorResponse($message, $httpErrorCode = 400)
    {
        $error['error'] = $message;

        $error = json_encode($error, JSON_PRETTY_PRINT);

        $headers = self::getApiHeaders();

        return response()->make($error, $httpErrorCode, $headers);
    }

    /**
     * Refresh API response with latest cahnges
     *
     * @param  Builder           $query
     * @return Response| \Illuminate\Http\JsonResponse
     */
    protected function refreshResponse($query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $this->manager->parseIncludes($this->first_load);

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer($this->serializer);

        $updated_at = request()->has('updated_at') ? request()->input('updated_at') : 0;

        if ($user->getCompany()->is_large && $updated_at == 0) {
            $updated_at = time();
        }

        $updated_at = date('Y-m-d H:i:s', $updated_at);

        $query->with(
            [
                'company' => function ($query) {
                    $query->whereNotNull('updated_at')->with('documents', 'users');
                },
                'company.clients' => function ($query) use ($updated_at, $user) {
                    $query->where('clients.updated_at', '>=', $updated_at)->with('contacts.company', 'gateway_tokens', 'documents');

                    if (! $user->hasPermission('view_client')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('clients.user_id', $user->id)->orWhere('clients.assigned_user_id', $user->id);
                        });
                    }

                    if ($user->hasExcludedPermissions($this->client_excludable_permissions, $this->client_excludable_overrides)) {
                        $query->exclude($this->client_exclusion_fields);
                    }
                },
                'company.company_gateways' => function ($query) use ($user) {
                    $query->whereNotNull('updated_at')->with('gateway');

                    if (! $user->isAdmin()) {
                        $query->where('company_gateways.user_id', $user->id);
                    }
                },
                'company.credits' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_credit')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('credits.user_id', $user->id)->orWhere('credits.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.designs' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('company');

                    if (! $user->isAdmin()) {
                        $query->where('designs.user_id', $user->id);
                    }
                },
                'company.documents' => function ($query) use ($updated_at) {
                    $query->where('updated_at', '>=', $updated_at);
                },
                'company.expenses' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_expense')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('expenses.user_id', $user->id)->orWhere('expenses.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.groups' => function ($query) {
                    $query->whereNotNull('updated_at')->with('documents');
                },
                'company.invoices' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_invoice')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('invoices.user_id', $user->id)->orWhere('invoices.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.payments' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('paymentables', 'documents');

                    if (! $user->hasPermission('view_payment')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('payments.user_id', $user->id)->orWhere('payments.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.payment_terms' => function ($query) use ($user) {
                    $query->whereNotNull('updated_at');

                    if (! $user->isAdmin()) {
                        $query->where('payment_terms.user_id', $user->id);
                    }
                },
                'company.products' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_product')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('products.user_id', $user->id)->orWhere('products.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.projects' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_project')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('projects.user_id', $user->id)->orWhere('projects.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.purchase_orders' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_purchase_order')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('purchase_orders.user_id', $user->id)->orWhere('purchase_orders.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.quotes' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_quote')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('quotes.user_id', $user->id)->orWhere('quotes.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.recurring_invoices' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('invitations', 'documents', 'client.gateway_tokens', 'client.group_settings', 'client.company');

                    if (! $user->hasPermission('view_recurring_invoice')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('recurring_invoices.user_id', $user->id)->orWhere('recurring_invoices.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.recurring_expenses' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('documents');

                    if (! $user->hasPermission('view_recurring_expense')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('recurring_expenses.user_id', $user->id)->orWhere('recurring_expenses.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.tasks' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('project', 'documents');

                    if (! $user->hasPermission('view_task')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('tasks.user_id', $user->id)->orWhere('tasks.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.tax_rates' => function ($query) {
                    $query->whereNotNull('updated_at');
                },
                'company.vendors' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at)->with('contacts', 'documents');

                    if (! $user->hasPermission('view_vendor')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('vendors.user_id', $user->id)->orWhere('vendors.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.expense_categories' => function ($query) {
                    $query->whereNotNull('updated_at');
                },
                'company.task_statuses' => function ($query) {
                    $query->whereNotNull('updated_at');
                },
                'company.activities' => function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('activities.user_id', $user->id);
                    }
                },
                'company.subscriptions' => function ($query) use ($user) {
                    $query->whereNotNull('updated_at');

                    if (! $user->isAdmin()) {
                        $query->where('subscriptions.user_id', $user->id);
                    }
                },
                'company.bank_integrations' => function ($query) use ($user) {
                    $query->whereNotNull('updated_at');

                    //scopes down permissions for users with no permissions
                    if (! $user->hasPermission('view_bank_transaction')) {
                        $query->where('bank_integrations.user_id', $user->id);
                    }

                    //allows us to return integrations for users who can create bank transactions
                    if (!$user->isSuperUser() && $user->hasIntersectPermissions(['create_bank_transaction','edit_bank_transaction','view_bank_transaction'])) {
                        $query->exclude(["balance"]);
                    }
                },
                'company.bank_transactions' => function ($query) use ($updated_at, $user) {
                    $query->where('updated_at', '>=', $updated_at);

                    if (! $user->hasPermission('view_bank_transaction')) {
                        $query->where('bank_transactions.user_id', $user->id);
                    }
                },
                'company.bank_transaction_rules' => function ($query) {
                    $query->whereNotNull('updated_at');
                },
                'company.task_schedulers' => function ($query) {
                    $query->whereNotNull('updated_at');
                },
            ]
        );

        if ($query instanceof Builder) {
            $limit = $this->resolveQueryLimit();

            $paginator = $query->paginate($limit);

            /** @phpstan-ignore-next-line */
            $query = $paginator->getCollection(); // @phpstan-ignore-line

            $resource = new Collection($query, $transformer, $this->entity_type);

            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        }

        // else {
        //     $resource = new Collection($query, $transformer, $this->entity_type);
        // }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    /**
     * Returns the per page limit for the query.
     *
     * @return int
     */
    private function resolveQueryLimit(): int
    {
        if (request()->has('per_page')) {
            return min(abs((int)request()->input('per_page', 20)), 5000);
        }

        return 20;
    }

    /**
     * Mini Load Query
     *
     * @param  Builder $query
     *
     */
    protected function miniLoadResponse($query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer($this->serializer);
        $created_at = request()->has('created_at') ? request()->input('created_at') : 0;

        $created_at = date('Y-m-d H:i:s', $created_at);

        $query->with(
            [
                'company' => function ($query) {
                    $query->whereNotNull('created_at')->with('documents', 'users');
                },
                'company.designs' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at)->with('company');
                },
                'company.documents' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.groups' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at)->with('documents');
                },
                'company.payment_terms' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.tax_rates' => function ($query) {
                    $query->whereNotNull('created_at');
                },
                'company.activities' => function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('activities.user_id', $user->id);
                    }
                },
                'company.bank_integrations' => function ($query) use ($user) {
                    if (! $user->hasPermission('view_bank_transaction')) {
                        $query->where('bank_integrations.user_id', $user->id);
                    }

                    if (!$user->isSuperUser() && $user->hasIntersectPermissions(['create_bank_transaction','edit_bank_transaction','view_bank_transaction'])) {
                        $query->exclude(["balance"]);
                    }
                },
                'company.bank_transaction_rules' => function ($query) use ($user) {
                    if (! $user->isAdmin() && !$user->hasIntersectPermissions(['create_bank_transaction','edit_bank_transaction','view_bank_transaction'])) {
                        $query->where('bank_transaction_rules.user_id', $user->id);
                    }
                },
                'company.task_schedulers' => function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('schedulers.user_id', $user->id);
                    }
                },
            ]
        );

        if ($query instanceof Builder) {
            $limit = $this->resolveQueryLimit();

            $paginator = $query->paginate($limit);

            /** @phpstan-ignore-next-line **/
            $query = $paginator->getCollection();// @phpstan-ignore-line

            $resource = new Collection($query, $transformer, $this->entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        }
        //  else {
        //     $resource = new Collection($query, $transformer, $this->entity_type);
        // }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    /**
     * Passes back the miniloaded data response
     *
     * @param  mixed $query
     *
     */
    protected function timeConstrainedResponse($query)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($user->getCompany()->is_large) {
            $this->manager->parseIncludes($this->mini_load);

            return $this->miniLoadResponse($query);
        } else {
            $this->manager->parseIncludes($this->first_load);
        }

        $this->serializer = request()->input('serializer') ?: EntityTransformer::API_SERIALIZER_ARRAY;

        if ($this->serializer === EntityTransformer::API_SERIALIZER_JSON) {
            $this->manager->setSerializer(new JsonApiSerializer());
        } else {
            $this->manager->setSerializer(new ArraySerializer());
        }

        $transformer = new $this->entity_transformer($this->serializer);
        $created_at = request()->has('created_at') ? request()->input('created_at') : 0;

        $created_at = date('Y-m-d H:i:s', $created_at);

        $query->with(
            [
                'company' => function ($query) {
                    $query->whereNotNull('created_at')->with('documents', 'users');
                },
                'company.clients' => function ($query) use ($created_at, $user) {
                    $query->where('clients.created_at', '>=', $created_at)->with('contacts.company', 'gateway_tokens', 'documents');

                    if (! $user->hasPermission('view_client')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('clients.user_id', $user->id)->orWhere('clients.assigned_user_id', $user->id);
                        });
                    }

                    if ($user->hasExcludedPermissions($this->client_excludable_permissions, $this->client_excludable_overrides)) {
                        $query->exclude($this->client_exclusion_fields);
                    }
                },
                'company.company_gateways' => function ($query) use ($user) {
                    $query->whereNotNull('created_at')->with('gateway');

                    if (! $user->isAdmin()) {
                        $query->where('company_gateways.user_id', $user->id);
                    }
                },
                'company.credits' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_credit')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('credits.user_id', $user->id)->orWhere('credits.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.documents' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.expenses' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_expense')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('expenses.user_id', $user->id)->orWhere('expenses.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.groups' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at)->with('documents');
                },
                'company.invoices' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_invoice')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('invoices.user_id', $user->id)->orWhere('invoices.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.payments' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('paymentables', 'documents');

                    if (! $user->hasPermission('view_payment')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('payments.user_id', $user->id)->orWhere('payments.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.payment_terms' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.products' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_product')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('products.user_id', $user->id)->orWhere('products.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.projects' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_project')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('projects.user_id', $user->id)->orWhere('projects.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.purchase_orders' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_purchase_order')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('purchase_orders.user_id', $user->id)->orWhere('purchase_orders.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.quotes' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents');

                    if (! $user->hasPermission('view_quote')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('quotes.user_id', $user->id)->orWhere('quotes.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.recurring_invoices' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('invitations', 'documents', 'client.gateway_tokens', 'client.group_settings', 'client.company');

                    if (! $user->hasPermission('view_recurring_invoice')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('recurring_invoices.user_id', $user->id)->orWhere('recurring_invoices.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.tasks' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('project.documents', 'documents');

                    if (! $user->hasPermission('view_task')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('tasks.user_id', $user->id)->orWhere('tasks.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.tax_rates' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.vendors' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('contacts', 'documents');

                    if (! $user->hasPermission('view_vendor')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('vendors.user_id', $user->id)->orWhere('vendors.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.expense_categories' => function ($query) {
                    $query->whereNotNull('created_at');
                },
                'company.task_statuses' => function ($query) use ($created_at) {
                    $query->where('created_at', '>=', $created_at);
                },
                'company.activities' => function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('activities.user_id', $user->id);
                    }
                },
                'company.webhooks' => function ($query) use ($user) {
                    if (! $user->isAdmin()) {
                        $query->where('webhooks.user_id', $user->id);
                    }
                },
                'company.tokens' => function ($query) use ($user) {
                    $query->where('company_tokens.user_id', $user->id);
                },
                'company.system_logs',
                'company.subscriptions' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);

                    if (! $user->isAdmin()) {
                        $query->where('subscriptions.user_id', $user->id);
                    }
                },
                'company.recurring_expenses' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at)->with('documents');

                    if (! $user->hasPermission('view_recurring_expense')) {
                        $query->whereNested(function ($query) use ($user) {
                            $query->where('recurring_expenses.user_id', $user->id)->orWhere('recurring_expenses.assigned_user_id', $user->id);
                        });
                    }
                },
                'company.bank_integrations' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);

                    if (! $user->hasPermission('view_bank_transaction')) {
                        $query->where('bank_integrations.user_id', $user->id);
                    }

                    if (!$user->isSuperUser() && $user->hasIntersectPermissions(['create_bank_transaction','edit_bank_transaction','view_bank_transaction'])) {
                        $query->exclude(["balance"]);
                    }
                },
                'company.bank_transactions' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);

                    if (! $user->hasPermission('bank_transaction')) {
                        $query->where('bank_transactions.user_id', $user->id);
                    }
                },
                'company.task_schedulers' => function ($query) use ($created_at, $user) {
                    $query->where('created_at', '>=', $created_at);

                    if (! $user->isAdmin()) {
                        $query->where('schedulers.user_id', $user->id);
                    }
                },
            ]
        );

        if ($query instanceof Builder) {
            $limit = $this->resolveQueryLimit();

            $paginator = $query->paginate($limit);

            /** @phpstan-ignore-next-line **/
            $query = $paginator->getCollection();// @phpstan-ignore-line

            $resource = new Collection($query, $transformer, $this->entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        }

        return $this->response($this->manager->createData($resource)->toArray()); //@phpstan-ignore-line
    }

    /**
     * List response
     *
     * @param Builder $query
     */
    protected function listResponse(Builder $query)
    {
        $this->buildManager();

        $transformer = new $this->entity_transformer(request()->input('serializer'));

        $includes = $transformer->getDefaultIncludes();

        $includes = $this->getRequestIncludes($includes);

        $query->with($includes);

        $user = Auth::user();

        if ($user && ! $user->hasPermission('view_'.Str::snake(class_basename($this->entity_type)))) {
            if (in_array($this->entity_type, [User::class])) {
                $query->where('id', $user->id);
            } elseif (in_array($this->entity_type, [BankTransactionRule::class,CompanyGateway::class, TaxRate::class, BankIntegration::class, Scheduler::class, BankTransaction::class, Webhook::class, ExpenseCategory::class])) { //table without assigned_user_id
                if ($this->entity_type == BankIntegration::class && !$user->isSuperUser() && $user->hasIntersectPermissions(['create_bank_transaction','edit_bank_transaction','view_bank_transaction'])) {
                    $query->exclude(["balance"]);
                } //allows us to selective display bank integrations back to the user if they can view / create bank transactions but without the bank balance being present in the response
                elseif($this->entity_type == TaxRate::class && $user->hasIntersectPermissions(['create_invoice','edit_invoice','create_quote','edit_quote','create_purchase_order','edit_purchase_order'])) {
                    // need to show tax rates if the user has the ability to create documents.
                } elseif($this->entity_type == ExpenseCategory::class && $user->hasPermission('create_expense')) {
                    // need to show expense categories if the user has the ability to create expenses.
                } else {
                    $query->where('user_id', '=', $user->id);
                }
            } elseif (in_array($this->entity_type, [Design::class, GroupSetting::class, PaymentTerm::class, TaskStatus::class])) {
                // nlog($this->entity_type);
            } else {
                $query->where(function ($q) use ($user) { //grouping these together improves query performance significantly)
                    $q->where('user_id', '=', $user->id)->orWhere('assigned_user_id', $user->id);
                });
            }
        }

        if ($this->entity_type == Client::class && $user->hasExcludedPermissions($this->client_excludable_permissions, $this->client_excludable_overrides)) {
            $query->exclude($this->client_exclusion_fields);
        }

        if (request()->has('updated_at') && request()->input('updated_at') > 0) {
            $query->where('updated_at', '>=', date('Y-m-d H:i:s', intval(request()->input('updated_at'))));
        }

        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $this->entity_type = null;
        }

        if ($query instanceof Builder) {
            $limit = $this->resolveQueryLimit();
            $paginator = $query->paginate($limit);
            $query = $paginator->getCollection();// @phpstan-ignore-line

            $resource = new Collection($query, $transformer, $this->entity_type);
            $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));
        }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    /**
     * Sorts the response by keys
     *
     * @param  mixed $response
     * @return Response| \Illuminate\Http\JsonResponse
     */
    protected function response($response)
    {
        $index = request()->input('index') ?: $this->forced_index;

        if ($index == 'none') {
            unset($response['meta']);
        } else {
            $meta = isset($response['meta']) ? $response['meta'] : null;
            $response = [
                $index => $response,
            ];

            if ($meta) {
                $response['meta'] = $meta;
                unset($response[$index]['meta']);
            }

            if (request()->include_static) {

                /** @var \App\Models\User $user */
                $user = auth()->user();

                $response_data = Statics::company($user->getCompany()->getLocale());

                if(request()->has('einvoice')) {

                    if(class_exists(Schema::class)) {
                        $ro = new Schema();
                        $response_data['einvoice_schema'] = $ro('Peppol');
                    }
                }

                $response['static'] = $response_data;

            }
        }

        ksort($response);

        $response = json_encode($response, JSON_PRETTY_PRINT);

        $headers = self::getApiHeaders();

        return response()->make($response, 200, $headers);
    }

    /**
     * Item Response
     *
     * @param  mixed $item
     * @return Response| \Illuminate\Http\JsonResponse
     */
    protected function itemResponse($item)
    {
        $this->buildManager();

        $transformer = new $this->entity_transformer(request()->input('serializer'));

        if ($this->serializer && $this->serializer != EntityTransformer::API_SERIALIZER_JSON) {
            $this->entity_type = null;
        }

        $resource = new Item($item, $transformer, $this->entity_type);

        /** @var ?\App\Models\User $user */
        $user = auth()->user();

        if ($user && request()->include_static) {
            $data['static'] = Statics::company($user->getCompany()->getLocale());
        }

        return $this->response($this->manager->createData($resource)->toArray());
    }

    /**
     * Returns the API headers.
     *
     * @return array
     */
    public static function getApiHeaders(): array
    {
        return [
            'Content-Type' => 'application/json',
            'X-Api-Version' => config('ninja.minimum_client_version'),
            'X-App-Version' => config('ninja.app_version'),
        ];
    }

    /**
     * Returns the parsed relationship includes
     *
     * @param  mixed $data
     * @return array
     */
    protected function getRequestIncludes($data): array
    {
        /*
         * Thresholds for displaying large account on first load
         */
        if (request()->has('first_load') && request()->input('first_load') == 'true') {

            /** @var \App\Models\User $user */
            $user = auth()->user();

            if ($user->getCompany()->is_large && request()->missing('updated_at')) {
                $data = $this->mini_load;
            } else {
                $data = $this->first_load;
            }
        } else {
            $included = request()->input('include', '');
            $included = explode(',', $included);

            foreach ($included as $include) {
                if ($include == 'clients') {
                    $data[] = 'clients.contacts';
                } elseif ($include) {
                    $data[] = $include;
                }
            }
        }

        return $data;
    }

    /**
     * Main entrypoint for the default /  route.
     *
     * @return mixed
     */
    public function flutterRoute()
    {

        if ((bool) $this->checkAppSetup() !== false && DbSchema::hasTable('accounts') && $account = Account::first()) {

            /** @var \App\Models\Account $account */

            //always redirect invoicing.co to invoicing.co
            if (Ninja::isHosted() && !in_array(request()->getSchemeAndHttpHost(), ['https://staging.invoicing.co', 'https://invoicing.co', 'https://demo.invoicing.co', 'https://invoiceninja.net', config('ninja.app_url')])) {
                return redirect()->secure(config('ninja.app_url'));
            }

            if (config('ninja.require_https') && ! request()->isSecure()) {
                return redirect()->secure(request()->getRequestUri());
            }

            /* Clean up URLs and remove query parameters from the URL*/
            if (request()->has('login') && request()->input('login') == 'true') {
                return redirect('/')->with(['login' => 'true']);
            }

            if (request()->has('signup') && request()->input('signup') == 'true') {
                return redirect('/')->with(['signup' => 'true']);
            }

            // 06-09-2022 - parse the path if loaded in a subdirectory for canvaskit resolution
            $canvas_path_array = parse_url(config('ninja.app_url'));
            $canvas_path = (array_key_exists('path', $canvas_path_array)) ? $canvas_path_array['path'] : '';
            $canvas_path = rtrim(str_replace("index.php", "", $canvas_path), '/');

            $data = [];

            //pass report errors bool to front end
            $data['report_errors'] = Ninja::isSelfHost() ? $account->report_errors : true;

            //pass whitelabel bool to front end
            $data['white_label'] = Ninja::isSelfHost() ? $account->isPaid() : false;

            //pass referral code to front end
            $data['rc'] = request()->has('rc') && is_string(request()->input('rc')) ? request()->input('rc') : '';
            $data['build'] = request()->has('build') && is_string(request()->input('build')) ? request()->input('build') : '';
            $data['login'] = request()->has('login') && is_string(request()->input('input')) ? request()->input('login') : 'false';
            $data['signup'] = request()->has('signup') && is_string(request()->input('signup')) ? request()->input('signup') : 'false';
            $data['canvas_path'] = $canvas_path;

            if (request()->session()->has('login')) {
                $data['login'] = 'true';
            }

            if (request()->session()->has('signup')) {
                $data['signup'] = 'true';
            }

            $data['user_agent'] = request()->server('HTTP_USER_AGENT');

            $data['path'] = $this->setBuild();

            if (Ninja::isSelfHost() && $account->set_react_as_default_ap) {
                return response()->view('react.index', $data)->header('X-Frame-Options', 'SAMEORIGIN', false);
            } else {
                return response()->view('index.index', $data)->header('X-Frame-Options', 'SAMEORIGIN', false);
            }
        }

        return redirect('/setup');
    }

    /**
     * Sets the Flutter build to serve
     *
     * @return string
     */
    private function setBuild(): string
    {
        $build = '';

        if (request()->has('build')) {
            $build = request()->input('build');
        } elseif (Ninja::isHosted()) {
            return 'main.dart.js';
        }

        switch ($build) {
            case 'wasm':
                return 'main.wasm.dart.js';
            case 'foss':
                return 'main.foss.dart.js';
            case 'last':
                return 'main.last.dart.js';
            case 'next':
                return 'main.next.dart.js';
            case 'profile':
                return 'main.profile.dart.js';
            default:
                return 'main.foss.dart.js';
        }
    }

    /**
     * Checks in a account has a required feature
     *
     * @param  mixed $feature
     * @return bool
     */
    public function checkFeature($feature)
    {
        if (auth()->user()->account->hasFeature($feature)) {
            return true;
        }

        return false;
    }

    /**
     * Feature failure response
     *
     * @return mixed
     */
    public function featureFailure()
    {
        return response()->json(['message' => 'Upgrade to a paid plan for this feature.'], 403);
    }

        
    /**
     * GetEncodedFilename
     *
     * @param  string $filename
     * @return string
     */
    public function getEncodedFilename(string $filename): string
    {
        $ascii_filename = str_replace(['%', '/', '\\'], '', $filename);
        $ascii_filename = preg_replace('/[\x00-\x1F\x7F-\xFF]/', '', $ascii_filename);

        $encoded_filename = rawurlencode($filename);

        return "filename=\"$ascii_filename\"; filename*=UTF-8''$encoded_filename";
    }
}

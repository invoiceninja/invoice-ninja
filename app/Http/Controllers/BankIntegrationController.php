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

use App\Factory\BankIntegrationFactory;
use App\Filters\BankIntegrationFilters;
use App\Helpers\Bank\Yodlee\Yodlee;
use App\Helpers\Bank\Nordigen\Nordigen;
use App\Http\Requests\BankIntegration\AdminBankIntegrationRequest;
use App\Http\Requests\BankIntegration\BulkBankIntegrationRequest;
use App\Http\Requests\BankIntegration\CreateBankIntegrationRequest;
use App\Http\Requests\BankIntegration\DestroyBankIntegrationRequest;
use App\Http\Requests\BankIntegration\EditBankIntegrationRequest;
use App\Http\Requests\BankIntegration\ShowBankIntegrationRequest;
use App\Http\Requests\BankIntegration\StoreBankIntegrationRequest;
use App\Http\Requests\BankIntegration\UpdateBankIntegrationRequest;
use App\Jobs\Bank\ProcessBankTransactionsYodlee;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Models\Account;
use App\Models\BankIntegration;
use App\Models\User;
use App\Repositories\BankIntegrationRepository;
use App\Transformers\BankIntegrationTransformer;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class BankIntegrationController extends BaseController
{
    use MakesHash;

    protected $entity_type = BankIntegration::class;

    protected $entity_transformer = BankIntegrationTransformer::class;

    protected $bank_integration_repo;

    public function __construct(BankIntegrationRepository $bank_integration_repo)
    {
        parent::__construct();

        $this->bank_integration_repo = $bank_integration_repo;
    }

    /**
     * @param BankIntegrationFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse
     */
    public function index(BankIntegrationFilters $filters)
    {
        $bank_integrations = BankIntegration::filter($filters);

        return $this->listResponse($bank_integrations);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function show(ShowBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        return $this->itemResponse($bank_integration);
    }


    /**
     * Show the form for editing the specified resource.
     *
     * @param EditBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function edit(EditBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        return $this->itemResponse($bank_integration);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function update(UpdateBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        //stubs for updating the model
        $bank_integration = $this->bank_integration_repo->save($request->all(), $bank_integration);

        return $this->itemResponse($bank_integration->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateBankIntegrationRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     *
     */
    public function create(CreateBankIntegrationRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $bank_integration = BankIntegrationFactory::create($user->company()->id, $user->id, $user->account_id);

        return $this->itemResponse($bank_integration);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreBankIntegrationRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function store(StoreBankIntegrationRequest $request)
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        //stub to store the model
        $bank_integration = $this->bank_integration_repo->save($request->all(), BankIntegrationFactory::create($user->company()->id, $user->id, $user->account_id));

        return $this->itemResponse($bank_integration);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyBankIntegrationRequest $request
     * @param BankIntegration $bank_integration
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(DestroyBankIntegrationRequest $request, BankIntegration $bank_integration)
    {
        $this->bank_integration_repo->delete($bank_integration);

        return $this->itemResponse($bank_integration->fresh());
    }


    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function bulk(BulkBankIntegrationRequest $request)
    {
        $action = request()->input('action');

        $ids = request()->input('ids');

        BankIntegration::withTrashed()->whereIn('id', $this->transformKeys($ids))
            ->company()
            ->cursor()
            ->each(function ($bank_integration, $key) use ($action) {
                $this->bank_integration_repo->{$action}($bank_integration);
            });

        /* Need to understand which permission are required for the given bulk action ie. view / edit */

        return $this->listResponse(BankIntegration::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }


    /**
     * Return the remote list of accounts stored on the third party provider.
     *
     * @return JsonResponse
     */
    public function refreshAccounts(AdminBankIntegrationRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /** @var \App\Models\Account $user_account */
        $user_account = $user->account;

        $this->refreshAccountsYodlee($user);

        $this->refreshAccountsNordigen($user);

        if (Cache::get("throttle_polling:{$user_account->key}")) {
            return response()->json(BankIntegration::query()->company(), 200);
        }

        // Processing transactions for each bank account
        if (Ninja::isHosted() && $user->account->bank_integration_account_id) {
            $user_account->bank_integrations->where("integration_type", BankIntegration::INTEGRATION_TYPE_YODLEE)->each(function ($bank_integration) use ($user_account) {
                /** @var \App\Models\BankIntegration $bank_integration */
                ProcessBankTransactionsYodlee::dispatch($user_account->bank_integration_account_id, $bank_integration);
            });
        }

        if (config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key') && (Ninja::isSelfHost() || (Ninja::isHosted() && $user_account->isEnterprisePaidClient()))) {
            $user_account->bank_integrations->where("integration_type", BankIntegration::INTEGRATION_TYPE_NORDIGEN)->each(function ($bank_integration) {
                /** @var \App\Models\BankIntegration $bank_integration */
                ProcessBankTransactionsNordigen::dispatch($bank_integration);
            });
        }

        Cache::put("throttle_polling:{$user_account->key}", true, 300);

        return response()->json(BankIntegration::query()->company(), 200);
    }

    private function refreshAccountsYodlee(User $user)
    {
        if (!Ninja::isHosted() || !$user->account->bank_integration_account_id) {
            return;
        }

        $yodlee = new Yodlee($user->account->bank_integration_account_id);

        $accounts = $yodlee->getAccounts();

        foreach ($accounts as $account) {
            if ($bi = BankIntegration::withTrashed()->where("integration_type", BankIntegration::INTEGRATION_TYPE_YODLEE)->where('bank_account_id', $account['id'])->where('company_id', $user->company()->id)->first()) {
                $bi->balance = $account['current_balance'];
                $bi->currency = $account['account_currency'];
                $bi->disabled_upstream = false;
                $bi->save();
            } else {
                $bank_integration = new BankIntegration();
                $bank_integration->company_id = $user->company()->id;
                $bank_integration->account_id = $user->account_id;
                $bank_integration->user_id = $user->id;
                $bank_integration->bank_account_id = $account['id'];
                $bank_integration->bank_account_type = $account['account_type'];
                $bank_integration->bank_account_name = $account['account_name'];
                $bank_integration->bank_account_status = $account['account_status'];
                $bank_integration->bank_account_number = $account['account_number'];
                $bank_integration->provider_id = $account['provider_id'];
                $bank_integration->provider_name = $account['provider_name'];
                $bank_integration->nickname = $account['nickname'];
                $bank_integration->balance = $account['current_balance'];
                $bank_integration->currency = $account['account_currency'];
                $bank_integration->auto_sync = true;

                $bank_integration->save();
            }
        }
    }

    private function refreshAccountsNordigen(User $user)
    {
        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key'))) {
            return;
        }

        $nordigen = new Nordigen();

        BankIntegration::where("integration_type", BankIntegration::INTEGRATION_TYPE_NORDIGEN)->where('account_id', $user->account_id)->whereNotNull('nordigen_account_id')->each(function (BankIntegration $bank_integration) use ($nordigen) {
            $is_account_active = $nordigen->isAccountActive($bank_integration->nordigen_account_id);
            $account = $nordigen->getAccount($bank_integration->nordigen_account_id);

            if (!$is_account_active || !$account) {
                $bank_integration->disabled_upstream = true;
                $bank_integration->save();

                $nordigen->disabledAccountEmail($bank_integration);
                return;
            }

            $bank_integration->disabled_upstream = false;
            $bank_integration->bank_account_status = $account['account_status'];
            $bank_integration->balance = $account['current_balance'];
            $bank_integration->currency = $account['account_currency'];

            $bank_integration->save();
        });
    }

    /**
     * Return the remote list of accounts stored on the third party provider
     * and update our local cache.
     *
     * @return Response| \Illuminate\Http\JsonResponse | JsonResponse
     *
     */

    public function removeAccount(AdminBankIntegrationRequest $request, $acc_id)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $account = $user->account;

        $bank_integration = BankIntegration::withTrashed()
            ->where('bank_account_id', $acc_id)
            ->orWhere('nordigen_account_id', $acc_id)
            ->company()
            ->firstOrFail();

        if ($bank_integration->integration_type == BankIntegration::INTEGRATION_TYPE_YODLEE) {
            $this->removeAccountYodlee($account, $bank_integration);
        }

        $this->bank_integration_repo->delete($bank_integration);

        return $this->itemResponse($bank_integration->fresh());
    }

    private function removeAccountYodlee(Account $account, BankIntegration $bank_integration)
    {
        if (!$account->bank_integration_account_id) {
            return response()->json(['message' => 'Not yet authenticated with Bank Integration service'], 400);
        }

        $yodlee = new Yodlee($account->bank_integration_account_id);
        $yodlee->deleteAccount($bank_integration->bank_account_id);
    }

    /**
     * Return the remote list of accounts stored on the third party provider
     * and update our local cache.
     *
     * @return JsonResponse
     *
     */
    public function getTransactions(AdminBankIntegrationRequest $request)
    {
        /** @var \App\Models\Account $account */
        $account = auth()->user()->account;

        if (Ninja::isHosted() && $account->isPaid() && $account->plan == 'enterprise') {
            $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_YODLEE)->where('auto_sync', true)->cursor()->each(function ($bank_integration) use ($account) {
                (new ProcessBankTransactionsYodlee($account->bank_integration_account_id, $bank_integration))->handle();
            });
        }

        if (config("ninja.nordigen.secret_id") && config("ninja.nordigen.secret_key") && (Ninja::isSelfHost() || (Ninja::isHosted() && $account->isPaid() && $account->plan == 'enterprise'))) {
            $account->bank_integrations()->where('integration_type', BankIntegration::INTEGRATION_TYPE_NORDIGEN)->where('auto_sync', true)->cursor()->each(function ($bank_integration) {
                (new ProcessBankTransactionsNordigen($bank_integration))->handle();
            });
        }

        return response()->json(['message' => 'Fetching transactions....'], 200);
    }
}

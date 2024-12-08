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

use App\Factory\BankTransactionFactory;
use App\Filters\BankTransactionFilters;
use App\Http\Requests\BankTransaction\BulkBankTransactionRequest;
use App\Http\Requests\BankTransaction\CreateBankTransactionRequest;
use App\Http\Requests\BankTransaction\DestroyBankTransactionRequest;
use App\Http\Requests\BankTransaction\EditBankTransactionRequest;
use App\Http\Requests\BankTransaction\MatchBankTransactionRequest;
use App\Http\Requests\BankTransaction\ShowBankTransactionRequest;
use App\Http\Requests\BankTransaction\StoreBankTransactionRequest;
use App\Http\Requests\BankTransaction\UpdateBankTransactionRequest;
use App\Jobs\Bank\MatchBankTransactions;
use App\Models\BankTransaction;
use App\Repositories\BankTransactionRepository;
use App\Transformers\BankTransactionTransformer;
use App\Utils\Traits\MakesHash;

class BankTransactionController extends BaseController
{
    use MakesHash;

    protected $entity_type = BankTransaction::class;

    protected $entity_transformer = BankTransactionTransformer::class;

    protected $bank_transaction_repo;

    public function __construct(BankTransactionRepository $bank_transaction_repo)
    {
        parent::__construct();

        $this->bank_transaction_repo = $bank_transaction_repo;
    }

    public function index(BankTransactionFilters $filters)
    {
        $bank_transactions = BankTransaction::filter($filters);

        return $this->listResponse($bank_transactions);
    }

    public function show(ShowBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        return $this->itemResponse($bank_transaction);
    }

    public function edit(EditBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        return $this->itemResponse($bank_transaction);
    }

    public function update(UpdateBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        //stubs for updating the model
        $bank_transaction = $this->bank_transaction_repo->save($request->all(), $bank_transaction);

        return $this->itemResponse($bank_transaction->fresh());
    }

    public function create(CreateBankTransactionRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $bank_transaction = BankTransactionFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($bank_transaction);
    }

    public function store(StoreBankTransactionRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        //stub to store the model
        $bank_transaction = $this->bank_transaction_repo->save($request->all(), BankTransactionFactory::create($user->company()->id, $user->id));

        return $this->itemResponse($bank_transaction);
    }

    public function destroy(DestroyBankTransactionRequest $request, BankTransaction $bank_transaction)
    {
        $this->bank_transaction_repo->delete($bank_transaction);

        return $this->itemResponse($bank_transaction->fresh());
    }

    public function bulk(BulkBankTransactionRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $action = $request->input('action');

        $ids = request()->input('ids');

        $bank_transactions = BankTransaction::withTrashed()->whereIn('id', $this->transformKeys($ids))->company()->get();

        if ($action == 'convert_matched' && $user->can('edit', $bank_transactions->first())) { //catch this action
            $this->bank_transaction_repo->convert_matched($bank_transactions);
        } else {
            $bank_transactions->each(function ($bank_transaction, $key) use ($action, $user) {
                if ($user->can('edit', $bank_transaction)) {
                    $this->bank_transaction_repo->{$action}($bank_transaction);
                }
            });
        }

        return $this->listResponse(BankTransaction::withTrashed()->whereIn('id', $this->transformKeys($ids))->company());
    }

    public function match(MatchBankTransactionRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $bts = (new MatchBankTransactions($user->company()->id, $user->company()->db, $request->all()))->handle();

        return $this->listResponse($bts);
    }
}

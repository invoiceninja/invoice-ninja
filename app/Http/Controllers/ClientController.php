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

use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Account;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Document;
use App\Models\SystemLog;
use Postmark\PostmarkClient;
use Illuminate\Http\Response;
use App\Factory\ClientFactory;
use App\Filters\ClientFilters;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Uploadable;
use App\Utils\Traits\BulkOptions;
use App\Jobs\Client\UpdateTaxData;
use App\Utils\Traits\SavesDocuments;
use App\Repositories\ClientRepository;
use App\Events\Client\ClientWasCreated;
use App\Events\Client\ClientWasUpdated;
use App\Transformers\ClientTransformer;
use Illuminate\Support\Facades\Storage;
use App\Services\Template\TemplateAction;
use App\Jobs\PostMark\ProcessPostmarkWebhook;
use App\Http\Requests\Client\BulkClientRequest;
use App\Http\Requests\Client\EditClientRequest;
use App\Http\Requests\Client\ShowClientRequest;
use App\Http\Requests\Client\PurgeClientRequest;
use App\Http\Requests\Client\StoreClientRequest;
use App\Http\Requests\Client\CreateClientRequest;
use App\Http\Requests\Client\UpdateClientRequest;
use App\Http\Requests\Client\UploadClientRequest;
use App\Http\Requests\Client\DestroyClientRequest;
use App\Http\Requests\Client\ClientDocumentsRequest;
use App\Http\Requests\Client\ReactivateClientEmailRequest;
use App\Models\Expense;
use App\Models\Payment;
use App\Models\Project;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\Task;
use App\Transformers\DocumentTransformer;

/**
 * Class ClientController.
 * @covers App\Http\Controllers\ClientController
 */
class ClientController extends BaseController
{
    use MakesHash;
    use Uploadable;
    use BulkOptions;
    use SavesDocuments;

    protected $entity_type = Client::class;

    protected $entity_transformer = ClientTransformer::class;

    /**
     * @var ClientRepository
     */
    protected $client_repo;

    /**
     * ClientController constructor.
     * @param ClientRepository $client_repo
     */
    public function __construct(ClientRepository $client_repo)
    {
        parent::__construct();

        $this->client_repo = $client_repo;
    }

    /**
     *
     * @param ClientFilters $filters
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function index(ClientFilters $filters)
    {
        set_time_limit(45);

        $clients = Client::filter($filters);

        return $this->listResponse($clients);
    }

    /**
     * Display the specified resource.
     *
     * @param ShowClientRequest $request
     * @param Client $client
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function show(ShowClientRequest $request, Client $client)
    {
        return $this->itemResponse($client);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param EditClientRequest $request
     * @param Client $client
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function edit(EditClientRequest $request, Client $client)
    {
        return $this->itemResponse($client);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateClientRequest $request
     * @param Client $client
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function update(UpdateClientRequest $request, Client $client)
    {
        if ($request->entityIsDeleted($client)) {
            return $request->disallowUpdate();
        }

        /** @var ?\App\Models\User $user */
        $user = auth()->user();

        $client = $this->client_repo->save($request->all(), $client);

        $this->uploadLogo($request->file('company_logo'), $client->company, $client);

        event(new ClientWasUpdated($client, $client->company, Ninja::eventVars($user ? $user->id : null)));

        return $this->itemResponse($client->fresh());
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param CreateClientRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function create(CreateClientRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $client = ClientFactory::create($user->company()->id, $user->id);

        return $this->itemResponse($client);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreClientRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function store(StoreClientRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $client = $this->client_repo->save($request->all(), ClientFactory::create($user->company()->id, $user->id));

        $client->load('contacts', 'primary_contact');

        /* Set the client country to the company if none is set */
        if (! $client->country_id && strlen($client->company->settings->country_id) > 1) {
            $client->update(['country_id' => $client->company->settings->country_id]);
        }

        $this->uploadLogo($request->file('company_logo'), $client->company, $client);

        event(new ClientWasCreated($client, $client->company, Ninja::eventVars(auth()->user() ? $user->id : null)));

        return $this->itemResponse($client);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param DestroyClientRequest $request
     * @param Client $client
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @throws \Exception
     */
    public function destroy(DestroyClientRequest $request, Client $client)
    {
        $this->client_repo->delete($client);

        return $this->itemResponse($client->fresh());
    }

    /**
     * Perform bulk actions on the list view.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function bulk(BulkClientRequest $request)
    {
        $action = $request->action;

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $clients = Client::withTrashed()
                         ->company()
                         ->whereIn('id', $request->ids)
                         ->get();

        if ($action == 'template' && $user->can('view', $clients->first())) {

            $hash_or_response = $request->boolean('send_email') ? 'email sent' : \Illuminate\Support\Str::uuid();

            TemplateAction::dispatch(
                $clients->pluck('hashed_id')->toArray(),
                $request->template_id,
                Client::class,
                $user->id,
                $user->company(),
                $user->company()->db,
                $hash_or_response,
                $request->boolean('send_email')
            );

            return response()->json(['message' => $hash_or_response], 200);
        }

        if ($action == 'assign_group' && $user->can('edit', $clients->first())) {

            $this->client_repo->assignGroup($clients, $request->group_settings_id);

            return $this->listResponse(Client::query()->withTrashed()->company()->whereIn('id', $request->ids));

        }

        if ($action == 'bulk_update' && $user->can('edit', $clients->first())) {

            $clients = Client::withTrashed()
                    ->company()
                    ->whereIn('id', $request->ids);

            $this->client_repo->bulkUpdate($clients, $request->column, $request->new_value);

            return $this->listResponse(Client::query()->withTrashed()->company()->whereIn('id', $request->ids));

        }

        $clients->each(function ($client) use ($action, $user) {
            if ($user->can('edit', $client)) {
                $this->client_repo->{$action}($client);
            }
        });

        return $this->listResponse(Client::query()->withTrashed()->company()->whereIn('id', $request->ids));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UploadClientRequest $request
     * @param Client $client
     * @return Response| \Illuminate\Http\JsonResponse
     *
     */
    public function upload(UploadClientRequest $request, Client $client)
    {
        if (! $this->checkFeature(Account::FEATURE_DOCUMENTS)) {
            return $this->featureFailure();
        }

        if ($request->has('documents')) {
            $this->saveDocuments($request->file('documents'), $client, $request->input('is_public', true));
        }

        return $this->itemResponse($client->fresh());
    }

    /**
     * Update the specified resource in storage.
     *
     * @param PurgeClientRequest $request
     * @param Client $client
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     *
     */
    public function purge(PurgeClientRequest $request, Client $client)
    {
        //delete all documents
        $client->documents->each(function ($document) {
            try {
                Storage::disk(config('filesystems.default'))->delete($document->url);
            } catch (\Exception $e) {
                nlog($e->getMessage());
            }
        });

        //force delete the client
        $this->client_repo->purge($client);

        return response()->json(['message' => 'Success'], 200);

        //todo add an event here using the client name as reference for purge event
    }

    /**
         * Update the specified resource in storage.
         *
         * @param PurgeClientRequest $request
         * @param Client $client
         * @param string $mergeable_client
         * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
         *
         */

    public function merge(PurgeClientRequest $request, Client $client, string $mergeable_client)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $m_client = Client::withTrashed()
                            ->where('id', $this->decodePrimaryKey($mergeable_client))
                            ->where('company_id', $user->company()->id)
                            ->first();

        if (!$m_client) {
            return response()->json(['message' => "Client not found"], 400);
        }

        if ($m_client->id == $client->id) {
            return response()->json(['message' => "Attempting to merge the same client is not possible."], 400);
        }

        $merged_client = $client->service()->merge($m_client)->save();

        return $this->itemResponse($merged_client);
    }

    /**
     * Updates the client's tax data
     *
     * @param  PurgeClientRequest $request
     * @param  Client $client
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function updateTaxData(PurgeClientRequest $request, Client $client)
    {
        if ($client->company->account->isPaid()) {
            (new UpdateTaxData($client, $client->company))->handle();
        }

        return $this->itemResponse($client->fresh());
    }

    /**
     * Reactivate a client email
     *
     * @param  ReactivateClientEmailRequest $request
     * @param  string $bounce_id //could also be the invitationId
     * @return \Illuminate\Http\JsonResponse|\Illuminate\Http\Response
     */
    public function reactivateEmail(ReactivateClientEmailRequest $request, string $bounce_id)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (stripos($bounce_id, '-') !== false) {
            $log =
                SystemLog::query()
                ->where('company_id', $user->company()->id)
                ->where('type_id', SystemLog::TYPE_WEBHOOK_RESPONSE)
                ->where('category_id', SystemLog::CATEGORY_MAIL)
                ->whereJsonContains('log', ['MessageID' => $bounce_id])
                ->orderBy('id', 'desc')
                ->first();

            $resolved_bounce_id = false;

            if ($log && ($log?->log['ID'] ?? false)) {
                $resolved_bounce_id = $log->log['ID'] ?? false;
            }

            if (!$resolved_bounce_id) {
                $ppwebhook = new ProcessPostmarkWebhook([]);
                $resolved_bounce_id = $ppwebhook->getBounceId($bounce_id);
            }

            if (!$resolved_bounce_id) {
                return response()->json(['message' => 'Bounce ID not found'], 400);
            }

            $bounce_id = $resolved_bounce_id;

            $record = $log->log;
            $record['ID'] = '';

            //2025-01-15 15:00:00 - unset the bounce ID here.
            $events = $record['history']['events'];

            foreach($events as &$event)
            {
                $event['bounce_id'] = "";
            }
            unset($event);

            $record['history']['events'] = $events;

            $log->log = $record;
            $log->save();

        }

        $postmark = new PostmarkClient(config('services.postmark.token'));

        try {

            /** @var ?\Postmark\Models\DynamicResponseModel $response */
            $response = $postmark->activateBounce((int)$bounce_id);

            if ($response && $response?->Message == 'OK' && !$response->Bounce->Inactive && $response->Bounce->Email) { // @phpstan-ignore-line

                $email =  $response->Bounce->Email;
                //remove email from quarantine. //@TODO
            }

            return response()->json(['message' => 'Success'], 200);

        } catch (\Exception $e) {

            return response()->json(['message' => $e->getMessage(), 400]);

        }

    }

    public function documents(ClientDocumentsRequest $request, Client $client)
    {

        $this->entity_type = Document::class;

        $this->entity_transformer = DocumentTransformer::class;

        $documents = Document::query()
            ->company()
            ->whereHasMorph('documentable', [Invoice::class, Quote::class, Credit::class, Expense::class, Payment::class, Task::class, RecurringInvoice::class, RecurringExpense::class, Project::class], function ($query) use ($client) {
                $query->where('client_id', $client->id);
            })
            ->orWhereHasMorph('documentable', [Client::class], function ($query) use ($client) {
                $query->where('id', $client->id);
            });

        return $this->listResponse($documents);

    }
}

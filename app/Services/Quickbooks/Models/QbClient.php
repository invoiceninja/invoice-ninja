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

namespace App\Services\Quickbooks\Models;

use App\Models\Client;
use App\DataMapper\ClientSync;
use App\Factory\ClientFactory;
use App\Interfaces\SyncInterface;
use App\Factory\ClientContactFactory;
use App\Services\Quickbooks\QuickbooksService;
use App\Services\Quickbooks\Transformers\ClientTransformer;

class QbClient implements SyncInterface
{
    public function __construct(public QuickbooksService $service)
    {
    }

    public function find(string $id): mixed
    {
        return $this->service->sdk->FindById('Customer', $id);
    }

    public function syncToNinja(array $records): void
    {

        $transformer = new ClientTransformer($this->service->company);

        foreach ($records as $record) {

            $ninja_data = $transformer->qbToNinja($record);

            if ($ninja_data[0]['terms']) {

                $days =  $this->service->findEntityById('Term', $ninja_data[0]['terms']);

                nlog($days);

                if ($days) {
                    $ninja_data[0]['settings']->payment_terms = (string)$days->DueDays;
                }

            }

            if ($client = $this->findClient($ninja_data[0]['id'])) {

                $qbc = $this->find($ninja_data[0]['id']);

                $client->fill($ninja_data[0]);
                $client->service()->applyNumber()->save();

                $contact = $client->contacts()->where('email', $ninja_data[1]['email'])->first();

                if (!$contact) {
                    $contact = ClientContactFactory::create($this->service->company->id, $this->service->company->owner()->id);
                    $contact->client_id = $client->id;
                    $contact->send_email = true;
                    $contact->is_primary = true;
                    $contact->fill($ninja_data[1]);
                    $contact->saveQuietly();
                } elseif ($this->service->syncable('client', \App\Enum\SyncDirection::PULL)) {
                    $contact->fill($ninja_data[1]);
                    $contact->saveQuietly();
                }

            }
        }

    }

    public function syncToForeign(array $records): void
    {
    }

    public function sync(string $id, string $last_updated): void
    {

    }

    private function findClient(string $key): ?Client
    {
        $search = Client::query()
                         ->withTrashed()
                         ->where('company_id', $this->service->company->id)
                         ->where('sync->qb_id', $key);

        if ($search->count() == 0) {

            $client = ClientFactory::create($this->service->company->id, $this->service->company->owner()->id);

            $sync = new ClientSync();
            $sync->qb_id = $key;
            $client->sync = $sync;

            return $client;

        } elseif ($search->count() == 1) {
            return $this->service->syncable('client', \App\Enum\SyncDirection::PULL) ? $search->first() : null;
        }

        return null;


    }
}

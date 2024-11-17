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

namespace App\Services\Client;

use App\Factory\CompanyLedgerFactory;
use App\Models\Activity;
use App\Models\Client;
use App\Models\CompanyLedger;
use App\Services\AbstractService;

class Merge extends AbstractService
{
    public $client;

    public $mergable_client;

    public function __construct(Client $client, Client $mergable_client)
    {
        $this->client = $client;
        $this->mergable_client = $mergable_client;
    }

    public function run()
    {
        nlog("merging {$this->mergable_client->id} into {$this->client->id}");
        nlog("balance pre {$this->client->balance}");
        nlog("paid_to_date pre {$this->client->paid_to_date}");

        $this->client->balance += $this->mergable_client->balance;
        $this->client->paid_to_date += $this->mergable_client->paid_to_date;
        $this->client->save();

        nlog("balance post {$this->client->balance}");
        nlog("paid_to_date post {$this->client->paid_to_date}");

        $this->updateLedger($this->mergable_client->balance);

        $this->mergable_client->activities()->update(['client_id' => $this->client->id]);
        $this->mergable_client->contacts()->update(['client_id' => $this->client->id]);
        $this->mergable_client->gateway_tokens()->update(['client_id' => $this->client->id]);
        $this->mergable_client->credits()->update(['client_id' => $this->client->id]);
        $this->mergable_client->expenses()->update(['client_id' => $this->client->id]);
        $this->mergable_client->invoices()->update(['client_id' => $this->client->id]);
        $this->mergable_client->payments()->update(['client_id' => $this->client->id]);
        $this->mergable_client->projects()->update(['client_id' => $this->client->id]);
        $this->mergable_client->quotes()->update(['client_id' => $this->client->id]);
        $this->mergable_client->recurring_invoices()->update(['client_id' => $this->client->id]);
        $this->mergable_client->tasks()->update(['client_id' => $this->client->id]);
        $this->mergable_client->documents()->update(['documentable_id' => $this->client->id]);

        /* Loop through contacts an only merge distinct contacts by email */
        $this->mergable_client->contacts->each(function ($contact) {
            $exist = $this->client->contacts->contains(function ($client_contact) use ($contact) {
                return $client_contact->email == $contact->email || empty($contact->email) || $contact->email == ' ';
            });

            if ($exist) {
                $contact->delete();
                $contact->save();
            }
        });

        $this->mergable_client->forceDelete();

        $this->client->credit_balance = $this->client->service()->getCreditBalance();
        $this->client->saveQuietly();

        return $this->client;
    }

    private function updateLedger($adjustment)
    {
        $balance = 0;

        $company_ledger = CompanyLedger::query()->whereClientId($this->client->id)
                                ->orderBy('id', 'DESC')
                                ->first();

        if ($company_ledger) {
            $balance = $company_ledger->balance;
        }

        $company_ledger = CompanyLedgerFactory::create($this->client->company_id, $this->client->user_id);
        $company_ledger->client_id = $this->client->id;
        $company_ledger->adjustment = $adjustment;
        $company_ledger->notes = 'Balance update after merging '.$this->mergable_client->present()->name();
        $company_ledger->balance = $balance + $adjustment;
        $company_ledger->activity_id = Activity::UPDATE_CLIENT;
        $company_ledger->save();
    }
}

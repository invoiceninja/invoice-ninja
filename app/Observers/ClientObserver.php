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

namespace App\Observers;

use App\Jobs\Client\CheckVat;
use App\Jobs\Client\UpdateTaxData;
use App\Jobs\Util\WebhookHandler;
use App\Models\Client;
use App\Models\Webhook;

class ClientObserver
{
    public $afterCommit = true;

    private $eu_country_codes = [
        'AT' => '40',
        'BE' => '56',
        'BG' => '100',
        'CY' => '196',
        'CZ' => '203',
        'DE' => '276',
        'DK' => '208',
        'EE' => '233',
        'ES' => '724',
        'FI' => '246',
        'FR' => '250',
        'GR' => '300',
        'HR' => '191',
        'HU' => '348',
        'IE' => '372',
        'IT' => '380',
        'LT' => '440',
        'LU' => '442',
        'LV' => '428',
        'MT' => '470',
        'NL' => '528',
        'PL' => '616',
        'PT' => '620',
        'RO' => '642',
        'SE' => '752',
        'SI' => '705',
        'SK' => '703',
    ];

    /**
     * Handle the client "created" event.
     *
     * @param Client $client
     * @return void
     */
    public function created(Client $client)
    {
        /** Fix Tax Data for Clients */
        if ($client->country_id == 840 && $client->company->calculate_taxes && !$client->company->account->isFreeHostedClient()) {
            UpdateTaxData::dispatch($client, $client->company);
        }

        /** Check VAT records for client */
        if (in_array($client->country_id, $this->eu_country_codes) && $client->company->calculate_taxes) {
            CheckVat::dispatch($client, $client->company);
        }

        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', Webhook::EVENT_CREATE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_CREATE_CLIENT, $client, $client->company)->delay(0);
        }
    }

    /**
     * Handle the client "updated" event.
     *
     * @param Client $client
     * @return void
     */
    public function updated(Client $client)
    {

        /** Monitor postal code changes for US based clients for tax calculations */
        if (($client->getOriginal('shipping_postal_code') != $client->shipping_postal_code || $client->getOriginal('postal_code') != $client->postal_code) && $client->country_id == 840 && $client->company->calculate_taxes && !$client->company->account->isFreeHostedClient()) {
            UpdateTaxData::dispatch($client, $client->company);
        }

        /** Monitor vat numbers for EU based clients for tax calculations */
        if ($client->getOriginal('vat_number') != $client->vat_number && in_array($client->country_id, $this->eu_country_codes) && $client->company->calculate_taxes) {
            CheckVat::dispatch($client, $client->company);
        }

        $event = Webhook::EVENT_UPDATE_CLIENT;

        if ($client->getOriginal('deleted_at') && !$client->deleted_at) {
            $event = Webhook::EVENT_RESTORE_CLIENT;
        }

        if ($client->is_deleted) {
            $event = Webhook::EVENT_DELETE_CLIENT;
        }

        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', $event)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch($event, $client, $client->company, 'client')->delay(0);
        }
    }

    /**
     * Handle the client "archived" event.
     *
     * @param Client $client
     * @return void
     */
    public function deleted(Client $client)
    {
        if ($client->is_deleted) {
            return;
        }

        $subscriptions = Webhook::where('company_id', $client->company_id)
                                    ->where('event_id', Webhook::EVENT_ARCHIVE_CLIENT)
                                    ->exists();

        if ($subscriptions) {
            WebhookHandler::dispatch(Webhook::EVENT_ARCHIVE_CLIENT, $client, $client->company)->delay(0);
        }
    }
}

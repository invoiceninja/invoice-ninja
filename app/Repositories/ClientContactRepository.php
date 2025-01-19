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

namespace App\Repositories;

use App\Factory\ClientContactFactory;
use App\Models\Client;
use App\Models\ClientContact;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ClientContactRepository.
 */
class ClientContactRepository extends BaseRepository
{
    private bool $is_primary = true;

    private bool $set_send_email_on_contact = false;

    public function save(array $data, Client $client): void
    {

        if (isset($data['contacts']) && (count($data['contacts']) !== count($data['contacts'], COUNT_RECURSIVE))) {
            $contacts = collect($data['contacts']);
        } elseif (isset($data['contacts'])) {
            $temp_array[] = $data['contacts'];
            $contacts = collect($temp_array);
        } else {
            $contacts = collect();
        }

        $client->contacts->pluck('id')->diff($contacts->pluck('id'))->each(function ($contact) {
            ClientContact::destroy($contact);
        });

        /* Ensure send_email always exists in at least one contact */
        if (! $contacts->contains('send_email', true)) {
            $this->set_send_email_on_contact = true;
        }

        /* Set first record to primary - always */
        $contacts = $contacts->sortByDesc('is_primary')->filter(function ($contact){
            return is_array($contact);
        })->map(function ($contact) {
            $contact['is_primary'] = $this->is_primary;
            $this->is_primary = false;

            if ($this->set_send_email_on_contact) {
                $contact['send_email'] = true;
                $this->set_send_email_on_contact = false;
            }

            return $contact;
        });

        //loop and update/create contacts
        $contacts->each(function ($contact) use ($client) {
            $update_contact = null;

            if (isset($contact['id'])) {
                $update_contact = ClientContact::find($contact['id']);
            }

            if (! $update_contact) {
                $update_contact = ClientContactFactory::create($client->company_id, $client->user_id);
            }

            //10-09-2021 - enforce the client->id and remove client_id from fillables
            $update_contact->client_id = $client->id;

            /* We need to set NULL email addresses to blank strings to pass authentication*/
            if (array_key_exists('email', $contact) && is_null($contact['email'])) {
                $contact['email'] = '';
            }

            $update_contact->fill($contact);

            if (array_key_exists('password', $contact) && strlen($contact['password']) > 1 && strlen($update_contact->email) > 3) { //updating on a blank contact email will cause large table scanning
                $update_contact->password = Hash::make($contact['password']);

                ClientContact::withTrashed()
                            ->where('company_id', $client->company_id)
                            ->where('client_id', $client->id)
                            ->where('email', $update_contact->email)->cursor()
                                    ->each(function ($saveable_contact) use ($update_contact) {
                                        $saveable_contact->password = $update_contact->password;
                                        $saveable_contact->save();
                                    });
            }

            if (array_key_exists('email', $contact)) {
                $update_contact->email = trim($contact['email']);
            }

            $update_contact->save();
        });

        //need to reload here to shake off stale contacts
        $client->fresh();

        //always made sure we have one blank contact to maintain state
        if ($client->contacts()->count() == 0) {
            $new_contact = ClientContactFactory::create($client->company_id, $client->user_id);
            $new_contact->client_id = $client->id;
            $new_contact->contact_key = Str::random(40);
            $new_contact->is_primary = true;
            $new_contact->confirmed = true;
            $new_contact->email = ' ';
            $new_contact->save();
        }

        $client = null;
    }
}

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

namespace App\Jobs\Util;

use App\Factory\ClientContactFactory;
use App\Factory\VendorContactFactory;
use App\Models\Account;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Vendor;
use App\Utils\Ninja;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Str;

class VersionCheck implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $version_file = trim(@file_get_contents(config('ninja.version_url')));

        if (Ninja::isSelfHost() && $version_file) {
            Account::query()->whereNotNull('id')->update(['latest_version' => $version_file]);
        }

        if (Ninja::isSelfHost()) {
            nlog("latest version = {$version_file}");

            /** @var \App\Models\Account $account **/
            $account = Account::first();

            if (! $account) {
                return;
            }

            if ($account->plan == 'white_label' && $account->plan_expires && Carbon::parse($account->plan_expires)->lt(now())) {
                $account->plan = null;
                $account->plan_expires = null;
                $account->saveQuietly();
            }

            Client::query()->whereNull('country_id')->cursor()->each(function ($client) {
                $client->country_id = $client->company->settings->country_id;
                $client->saveQuietly();
            });

            Vendor::query()->whereNull('currency_id')->orWhere('currency_id', '')->cursor()->each(function ($vendor) {
                $vendor->currency_id = $vendor->company->settings->currency_id;
                $vendor->saveQuietly();
            });

            ClientContact::whereNull('email')
                            ->where('send_email', true)
                            ->cursor()
                            ->each(function ($c) {

                                $c->send_email = false;
                                $c->saveQuietly();

                            });

            ClientContact::query()
                            ->whereNull('contact_key')
                            ->update([
                                'contact_key' => Str::random(config('ninja.key_length')),
                            ]);

            Client::doesntHave('contacts')
                            ->cursor()
                            ->each(function (Client $client) { //@phpstan-ignore-line

                                $new_contact = ClientContactFactory::create($client->company_id, $client->user_id);
                                $new_contact->client_id = $client->id;
                                $new_contact->contact_key = Str::random(40);
                                $new_contact->is_primary = true;
                                $new_contact->save();

                            });


            Vendor::doesntHave('contacts')
                            ->cursor()
                            ->each(function (Vendor $vendor) { //@phpstan-ignore-line

                                $new_contact = VendorContactFactory::create($vendor->company_id, $vendor->user_id);
                                $new_contact->vendor_id = $vendor->id;
                                $new_contact->contact_key = Str::random(40);
                                $new_contact->is_primary = true;
                                $new_contact->save();
                            });


        }
    }
}

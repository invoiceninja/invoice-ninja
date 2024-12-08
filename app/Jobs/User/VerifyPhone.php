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

namespace App\Jobs\User;

use App\Libraries\MultiDB;
use App\Models\User;
use App\Utils\Traits\MakesHash;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class VerifyPhone implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;
    use MakesHash;


    /**
     * Create a new job instance.
     *
     * @param User $user
     */
    public function __construct(private User $user)
    {
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle(): void
    {
        MultiDB::checkUserEmailExists($this->user->email);

        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        if (!$sid) {
            return;
        } // no twilio api credentials provided, bail.

        $twilio = new \Twilio\Rest\Client($sid, $token);

        $country = $this->user->account?->companies()?->first()?->country(); //@phpstan-ignore-line

        if (!$country || strlen($this->user->phone) < 2) {
            return;
        }

        $countryCode = $country->iso_3166_2;

        try {
            $phone_number = $twilio->lookups->v1->phoneNumbers($this->user->phone)
                                                ->fetch(["countryCode" => $countryCode]);
        } catch (\Exception $e) {
            $this->user->verified_phone_number = false;
            $this->user->save();
            return;
        }

        if ($phone_number && strlen($phone_number->phoneNumber) > 1) { //@phpstan-ignore-line
            $this->user->phone = $phone_number->phoneNumber;
            $this->user->verified_phone_number = true;
            $this->user->save();
        }
    }
}

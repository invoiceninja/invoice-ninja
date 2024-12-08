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

namespace App\Http\ValidationRules\User;

use Illuminate\Contracts\Validation\Rule;

/**
 * Class HasValidPhoneNumber.
 */
class HasValidPhoneNumber implements Rule
{
    public $message;

    public function __construct()
    {
    }

    public function message()
    {
        return [
            'phone' => ctrans('texts.phone_validation_error'),
        ];
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $sid = config('ninja.twilio_account_sid');
        $token = config('ninja.twilio_auth_token');

        if (!$sid) {
            return true;
        }

        if (is_null($value)) {
            return false;
        }

        $twilio = new \Twilio\Rest\Client($sid, $token);

        $country = auth()->user()->account?->companies()?->first()?->country(); //@phpstan-ignore-line

        if (!$country || strlen(auth()->user()->phone) < 2) {
            return true;
        }

        $countryCode = $country->iso_3166_2;

        try {
            $phone_number = $twilio->lookups->v1->phoneNumbers($value)
                                                ->fetch(["countryCode" => $countryCode]);

            $user = auth()->user();

            request()->merge(['validated_phone' => $phone_number->phoneNumber ]);

            $user->verified_phone_number = false;
            $user->save();

            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}

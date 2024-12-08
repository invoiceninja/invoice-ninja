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

namespace App\Models\Presenters;

use App\Models\Country;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Class CompanyPresenter.
 * @property \App\DataMapper\CompanySettings $settings
 */
class CompanyPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function name(): string
    {
        return $this->settings->name ?: ctrans('texts.untitled_account');
    }


    public function logo($settings = null)
    {
        if (! $settings) {
            $settings = $this->entity->settings;
        }

        if (strlen($settings->company_logo) >= 1 && (strpos($settings->company_logo, 'http') !== false)) {
            return $settings->company_logo;
        } elseif (strlen($settings->company_logo) >= 1) {
            return url('') . $settings->company_logo;
        } else {
            return asset('images/blank.png');
        }
    }

    public function logoDocker($settings = null)
    {
        if (! $settings) {
            $settings = $this->entity->settings;
        }

        $basename = basename($this->settings->company_logo);

        $logo = Storage::get("{$this->company_key}/{$basename}");

        if (!$logo) {
            return $this->logo($settings);
        }

        return "data:image/png;base64, ". base64_encode($logo);
    }

    /**
     * Test for using base64 encoding
     */
    public function logo_base64($settings = null)
    {
        if (! $settings) {
            $settings = $this->entity->settings;
        }

        if (config('ninja.is_docker') || config('ninja.local_download')) {
            return $this->logoDocker($settings);
        }

        $context_options = [
            "ssl" => [
               "verify_peer" => false,
               "verify_peer_name" => false,
            ],
        ];

        if (strlen($settings->company_logo) >= 1 && (strpos($settings->company_logo, 'http') !== false)) {
            return "data:image/png;base64,". base64_encode(@file_get_contents($settings->company_logo, false, stream_context_create($context_options)));
        } elseif (strlen($settings->company_logo) >= 1) {
            return "data:image/png;base64,". base64_encode(@file_get_contents(url('') . $settings->company_logo, false, stream_context_create($context_options)));
        } else {
            return "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=";
        }
    }

    public function email()
    {
        /** @var \App\Models\Company $this */
        if (str_contains($this->settings->email, "@")) {
            return $this->settings->email;
        }

        return $this->owner()->email;

    }

    public function address($settings = null)
    {
        $str = '';
        $company = $this->entity;

        if (! $settings) {
            $settings = $this->entity->settings;
        }

        if ($address1 = $settings->address1) {
            $str .= e($address1).'<br/>';
        }
        if ($address2 = $settings->address2) {
            $str .= e($address2).'<br/>';
        }
        if ($cityState = $this->getCompanyCityState($settings)) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = Country::find($settings->country_id)) {
            $str .= e($country->name).'<br/>';
        }
        if ($settings->phone) {
            $str .= ctrans('texts.phone').': '.e($settings->phone).'<br/>';
        }
        if ($settings->email) {
            $str .= ctrans('texts.work_email').': '.e($settings->email).'<br/>';
        }

        return $str;
    }

    public function getCompanyCityState($settings = null)
    {
        if (! $settings) {
            $settings = $this->entity->settings;
        }

        $country = Country::find($settings->country_id);

        $swap = $country && $country->swap_postal_code;

        $city = e($settings->city);
        $state = e($settings->state);
        $postalCode = e($settings->postal_code);

        if ($city || $state || $postalCode) {
            return $this->cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return false;
        }
    }

    public function phone()
    {
        return $this->entity->settings->phone ?? ' ';
    }

    public function address1()
    {
        return $this->entity->settings->address1;
    }

    public function address2()
    {
        return $this->entity->settings->address2;
    }

    public function qr_iban()
    {
        return $this->entity->getSetting('qr_iban');
    }

    public function besr_id()
    {
        return $this->entity->getSetting('besr_id');
    }

    public function getSpcQrCode($client_currency, $invoice_number, $balance_due_raw, $user_iban)
    {
        $settings = $this->entity->settings;

        return

        "SPC\n0200\n1\n{$user_iban}\nK\n{$this->name}\n{$settings->address1}\n{$settings->postal_code} {$settings->city}\n\n\nCH\n\n\n\n\n\n\n\n{$balance_due_raw}\n{$client_currency}\n\n\n\n\n\n\n\nNON\n\n{$invoice_number}\nEPD\n";
    }

    public function size()
    {
        return $this->entity->size ? $this->entity->size->name : '';
    }

    /**
     * Return company website URL.
     *
     * @return string
     */
    public function website(): string
    {
        $website = $this->entity->getSetting('website');

        if (empty($website)) {
            return $website;
        }

        if (Str::contains($website, ['http', 'https'])) {
            return $website;
        }

        return \sprintf('http://%s', $website);
    }
}

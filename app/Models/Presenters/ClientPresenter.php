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

/**
 * Class ClientPresenter.
 */
class ClientPresenter extends EntityPresenter
{
    /**
     * @return string
     */
    public function name()
    {
        if (strlen($this->entity->name) > 1) {
            return $this->entity->name;
        }

        $contact = $this->entity->contacts->whereNotNull('email')->first();

        $contact_name = 'No Contact Set';

        if ($contact && ((is_string($contact->first_name) && strlen($contact->first_name) >= 1) || (is_string($contact->last_name) && strlen($contact->last_name) >= 1))) {
            $contact_name = $contact->first_name.' '.$contact->last_name;
        } elseif ($contact && (strlen($contact->email))) {
            $contact_name = $contact->email;
        }

        return $contact_name;
    }

    public function first_name()
    {
        return $this->entity->primary_contact()->first()?->first_name ?: ($this->entity->contacts()->first()->first_name ?: '');
    }

    public function last_name()
    {
        return $this->entity->primary_contact()->first()?->last_name ?: ($this->entity->contacts()->first()->last_name ?: '');
    }

    public function primary_contact_name()
    {
        return $this->entity?->primary_contact()?->first() ? $this->entity->primary_contact()->first()->first_name.' '.$this->entity->primary_contact()->first()->last_name : 'No primary contact set';
    }

    public function email()
    {
        $primary_contact = $this->entity->primary_contact->first();

        if ($primary_contact && filter_var($primary_contact->email, FILTER_VALIDATE_EMAIL)) {
            return $primary_contact->email;
        }

        $contact = $this->entity->contacts->where('email', '!=', '')->first();

        return $contact ? $contact->email : 'No Email Set';
    }

    public function address()
    {
        $str = '';
        $client = $this->entity;

        if ($address1 = $client->address1) {
            $str .= e($address1).'<br/>';
        }
        if ($address2 = $client->address2) {
            $str .= e($address2).'<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = $client->country) {
            $str .= e($country->name).'<br/>';
        }

        return $str;
    }

    public function shipping_address()
    {
        $str = '';
        $client = $this->entity;

        if ($address1 = $client->shipping_address1) {
            $str .= e($address1).'<br/>';
        }
        if ($address2 = $client->shipping_address2) {
            $str .= e($address2).'<br/>';
        }
        if ($cityState = $this->getShippingCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = $client->shipping_country ?? $client->country) {
            $str .= e($country->name).'<br/>';
        }

        return $str;
    }

    public function shipping_country_code(): string
    {
        return $this->entity->shipping_country ? $this->entity->shipping_country->iso_3166_2 : $this->entity->country->iso_3166_2;
    }

    public function phone()
    {
        return $this->entity->phone ?: '';
    }

    public function website()
    {
        return $this->entity->website ?: '';
    }

    /**
     * Calculated company data fields
     * using settings.
     */
    public function company_name()
    {
        $settings = $this->entity->getMergedSettings();

        return $settings->name ?: ctrans('texts.untitled_account');
    }

    public function company_address()
    {
        $settings = $this->entity->getMergedSettings();

        $str = '';

        if ($settings->address1) {
            $str .= e($settings->address1).'<br/>';
        }
        if ($settings->address2) {
            $str .= e($settings->address2).'<br/>';
        }
        if ($cityState = $this->getCityState()) {
            $str .= e($cityState).'<br/>';
        }
        if ($country = Country::find($settings->country_id)) {
            $str .= e($country->name).'<br/>';
        }

        return $str;
    }

    // public function getCityState()
    // {
    //     $settings = $this->entity->getMergedSettings();

    //     $country = false;

    //     if ($settings->country_id) {
    //         $country = Country::find($settings->country_id);
    //     }

    //     $swap = $country && $country->swap_postal_code;

    //     $city = e($settings->city ?: '');
    //     $state = e($settings->state ?: '');
    //     $postalCode = e($settings->postal_code ?: '');

    //     if ($city || $state || $postalCode) {
    //         return $this->cityStateZip($city, $state, $postalCode, $swap);
    //     } else {
    //         return false;
    //     }
    // }

    public function getCityState()
    {
        $client = $this->entity;

        $country = $client->country ?: false;

        $swap = $country && $country->swap_postal_code;

        $city = e($client->city ?: '');
        $state = e($client->state ?: '');
        $postalCode = e($client->postal_code ?: '');

        if ($city || $state || $postalCode) {
            return $this->cityStateZip($city, $state, $postalCode, $swap);
        } else {
            return false;
        }
    }


}

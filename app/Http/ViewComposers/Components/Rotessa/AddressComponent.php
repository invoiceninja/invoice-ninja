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

namespace App\Http\ViewComposers\Components\Rotessa;

use App\DataProviders\CAProvinces;
use App\DataProviders\USStates;
use Illuminate\View\Component;
use App\Models\ClientContact;
use Illuminate\Support\Arr;
use Illuminate\View\View;

// Address Component
class AddressComponent extends Component
{
    private $fields = [
        'address_1',
        'address_2',
        'city',
        'postal_code',
        'province_code',
        'country'
    ];

    private $defaults = [
        'country' => 'US'
    ];

    public function __construct(public array $address)
    {
        if (strlen($this->address['state']) > 2) {
            $this->address['state'] = $this->address['country'] == 'US' ? array_search($this->address['state'], USStates::$states) : CAProvinces::getAbbreviation($this->address['state']);
        }

        $this->attributes = $this->newAttributeBag(
            Arr::only(
                Arr::mapWithKeys($this->address, function ($item, $key) {
                    return in_array($key, ['address1','address2','state']) ? [ (['address1' => 'address_1','address2' => 'address_2','state' => 'province_code'])[$key] => $item ] : [ $key => $item ];
                }),
                $this->fields
            )
        );
    }


    public function render()
    {
        return render('gateways.rotessa.components.address', $this->attributes->getAttributes() + $this->defaults);
    }
}

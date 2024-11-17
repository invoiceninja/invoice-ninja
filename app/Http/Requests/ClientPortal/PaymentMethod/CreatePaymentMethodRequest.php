<?php

namespace App\Http\Requests\ClientPortal\PaymentMethod;

use App\Http\Requests\Request;
use App\Models\Client;
use Illuminate\Foundation\Http\FormRequest;

use function auth;
use function collect;

class CreatePaymentMethodRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        auth()->guard('contact')->user()->loadMissing(['client' => function ($query) {
            $query->without('gateway_tokens', 'documents', 'contacts.company', 'contacts'); // Exclude 'grandchildren' relation of 'client'
        }]);

        /** @var Client $client */
        $client = auth()->guard('contact')->user()->client;

        $available_methods = collect($client->service()->getPaymentMethods(-1))
            ->pluck('gateway_type_id')
            ->toArray();

        return in_array($this->query('method'), $available_methods);

    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            //
        ];
    }
}

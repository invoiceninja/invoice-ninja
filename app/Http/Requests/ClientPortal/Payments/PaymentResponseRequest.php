<?php

namespace App\Http\Requests\ClientPortal\Payments;

use App\Models\PaymentHash;
use Illuminate\Foundation\Http\FormRequest;

class PaymentResponseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'company_gateway_id' => 'required',
            'payment_hash' => 'required',
        ];
    }

    public function getPaymentHash()
    {
        $input = $this->all();

        return PaymentHash::where('hash', $input['payment_hash'])->first();
    }

    public function shouldStoreToken(): bool
    {
        return (bool) $this->store_card;
    }

    public function prepareForValidation()
    {
        if ($this->has('store_card')) {
            $this->merge([
                'store_card' => ($this->store_card === 'true' || $this->store_card === true) ? true : false,
            ]);
        }

        if ($this->has('pay_with_token')) {
            $this->merge([
                'pay_with_token' => ($this->pay_with_token === 'true' || $this->pay_with_token === true) ? true : false,
            ]);
        }

        if ($this->has('payment_method_id')) {

            $this->merge([
                'payment_method_id' => preg_replace('~\D~', '', $this->payment_method_id),
            ]);

        }
    }

    public function shouldUseToken(): bool
    {
        return (bool) $this->token;
    }
}

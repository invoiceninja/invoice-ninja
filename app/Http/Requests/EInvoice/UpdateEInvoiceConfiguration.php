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

namespace App\Http\Requests\EInvoice;

use App\Utils\Ninja;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use App\Http\Requests\Request;
use App\Services\EDocument\Adapters\CII\PaymentMeans;
use Illuminate\Validation\Rule;

class UpdateEInvoiceConfiguration extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->isAdmin();
    }

    public function rules()
    {
        return [
           'entity' => 'required|bail|in:invoice,client,company',
           'payment_means' => 'sometimes|bail|array',
           'payment_means.*.code' => ['required_with:payment_means', 'bail', Rule::in(PaymentMeans::getPaymentMeansCodelist())],
           'payment_means.*.bic_swift' => Rule::forEach(function (string|null $value, string $attribute) {
               $index = explode('.', $attribute)[1];
               $code = $this->input("payment_means.{$index}.code");
               $requirements = PaymentMeans::$payment_means_requirements_codes[$code] ?? [];
               $rules = ['bail', 'string', 'min:8', 'max:11'];

               if (in_array('bic_swift', $requirements)) {
                   return [...$rules, 'required'];
               }

               return [...$rules, 'nullable'];
           }),
           'payment_means.*.iban' => Rule::forEach(function (string|null $value, string $attribute) {
               $index = explode('.', $attribute)[1];
               $code = $this->input("payment_means.{$index}.code");
               $requirements = PaymentMeans::$payment_means_requirements_codes[$code] ?? [];
               $rules = ['bail', 'sometimes', 'string', 'min:15', 'max:34'];

               if (in_array('iban', $requirements)) {
                   return [...$rules, 'required'];
               }

               return [...$rules, 'nullable'];
           }),
           'payment_means.*.payer_bank_account' => Rule::forEach(function (string|null $value, string $attribute) {
               $index = explode('.', $attribute)[1];
               $code = $this->input("payment_means.{$index}.code");
               $requirements = PaymentMeans::$payment_means_requirements_codes[$code] ?? [];
               $rules = ['bail', 'sometimes', 'string', 'max:255'];

               if (in_array('payer_bank_account', $requirements)) {
                   return [...$rules, 'required'];
               }

               return [...$rules, 'nullable'];
           }),
           'payment_means.*.account_holder' => Rule::forEach(function (string|null $value, string $attribute) {
               $index = explode('.', $attribute)[1];
               $code = $this->input("payment_means.{$index}.code");
               $requirements = PaymentMeans::$payment_means_requirements_codes[$code] ?? [];
               $rules = ['bail', 'sometimes', 'string', 'max:255'];

               if (in_array('account_holder', $requirements)) {
                   return [...$rules, 'required'];
               }

               return [...$rules, 'nullable'];
           }),
           'payment_means.*.information' => ['bail', 'sometimes', 'nullable', 'string'],
           'payment_means.*.card_type' => Rule::forEach(function (string|null $value, string $attribute) {
               $index = explode('.', $attribute)[1];
               $code = $this->input("payment_means.{$index}.code");
               $requirements = PaymentMeans::$payment_means_requirements_codes[$code] ?? [];
               $rules = ['bail', 'sometimes', 'nullable', 'string', 'min:4'];

               if (in_array('card_type', $requirements)) {
                   return [...$rules, 'required'];
               }

               return [...$rules, 'nullable'];
           }),
           'payment_means.*.card_holder' => Rule::forEach(function (string|null $value, string $attribute) {
               $index = explode('.', $attribute)[1];
               $code = $this->input("payment_means.{$index}.code");
               $requirements = PaymentMeans::$payment_means_requirements_codes[$code] ?? [];
               $rules = ['bail', 'sometimes', 'nullable', 'string', 'min:4'];

               if (in_array('card_holder', $requirements)) {
                   return [...$rules, 'required'];
               }

               return [...$rules, 'nullable'];
           }),
       ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        $this->replace($input);
    }

    public function getLevel()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return match($this->entity) {
            'company' => $user->company(),
            'invoice' => Invoice::class,
            'client' => Client::class,
            default => $user->company(),
        };
    }
}

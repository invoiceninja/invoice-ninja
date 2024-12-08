<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2024. Invoice Ninja LLC (https://invoiceninja.com)
 *1`
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\ValidationRules\EInvoice;

use App\Services\EDocument\Standards\Validation\Peppol\ClientLevel;
use Closure;
use InvoiceNinja\EInvoice\EInvoice;
use Illuminate\Validation\Validator;
use InvoiceNinja\EInvoice\Models\Peppol\Invoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\ValidatorAwareRule;

/**
 * Class ValidClientScheme.
 */
class ValidClientScheme implements ValidationRule, ValidatorAwareRule
{
    /**
     * The validator instance.
     *
     * @var Validator
     */
    protected $validator;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {

        if (isset($value['Invoice'])) {
            $r = new EInvoice();
            $errors = $r->validateRequest($value['Invoice'], ClientLevel::class);

            foreach ($errors as $key => $msg) {

                $this->validator->errors()->add(
                    "e_invoice.{$key}",
                    "{$key} - {$msg}"
                );

            }
        }
    }

    /**
     * Set the current validator.
     */
    public function setValidator(Validator $validator): static
    {
        $this->validator = $validator;
        return $this;
    }


}

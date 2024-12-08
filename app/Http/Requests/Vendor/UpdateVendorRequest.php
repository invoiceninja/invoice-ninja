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

namespace App\Http\Requests\Vendor;

use App\Http\Requests\Request;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;

class UpdateVendorRequest extends Request
{
    use MakesHash;
    use ChecksEntityStatus;

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->vendor);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['country_id'] = 'integer';

        if ($this->number) {
            $rules['number'] = Rule::unique('vendors')->where('company_id', $user->company()->id)->ignore($this->vendor->id);
        }

        $rules['contacts'] = 'bail|array';
        $rules['contacts.*.email'] = 'bail|nullable|distinct|sometimes|email';
        $rules['contacts.*.password'] = [
            'bail',
            'nullable',
            'sometimes',
            'string',
            'min:7',             // must be at least 10 characters in length
            'regex:/[a-z]/',      // must contain at least one lowercase letter
            'regex:/[A-Z]/',      // must contain at least one uppercase letter
            'regex:/[0-9]/',      // must contain at least one digit
            //'regex:/[@$!%*#?&.]/', // must contain a special character
        ];

        $rules['currency_id'] = 'bail|sometimes|exists:currencies,id';

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        } else {
            $rules['documents'] = 'bail|sometimes|array';
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        $rules['language_id'] = 'bail|nullable|sometimes|exists:languages,id';
        $rules['classification'] = 'bail|sometimes|nullable|in:individual,business,company,partnership,trust,charity,government,other';

        return $rules;
    }

    public function messages()
    {
        return [
            'email' => ctrans('validation.email', ['attribute' => 'email']),
            'name.required' => ctrans('validation.required', ['attribute' => 'name']),
            'required' => ctrans('validation.required', ['attribute' => 'email']),
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['name'])) {
            $input['name'] = strip_tags($input['name']);
        }

        if (array_key_exists('country_id', $input) && is_null($input['country_id'])) {
            unset($input['country_id']);
        } elseif (!$this->vendor->country_id) {

            /** @var \App\Models\User $user */
            $user = auth()->user();

            $input['country_id'] = $user->company()->country()->id;
        }

        $input = $this->decodePrimaryKeys($input);

        $this->replace($input);
    }
}

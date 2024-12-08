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
use Illuminate\Validation\Rule;

class ValidateEInvoiceRequest extends Request
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

        $entity = $this->getEntity();

        if ($entity instanceof Company) {
            return $entity->id == $user->company()->id;
        }

        return $user->can('view', $entity);

    }

    public function rules()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'entity' => 'required|bail|in:invoices,clients,companies',
            'entity_id' => ['required','bail', Rule::exists($this->entity, 'id')
                                                                ->when($this->entity != 'companies', function ($q) use ($user) {
                                                                    $q->where('company_id', $user->company()->id);
                                                                })
                                                            ],
        ];
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['entity_id']) && $input['entity_id'] != null) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }


        $this->replace($input);
    }

    public function getEntity()
    {
        if (!$this->entity) {
            return false;
        }


        $class = Invoice::class;

        match ($this->entity) {
            'invoices' => $class = Invoice::class,
            'clients' => $class = Client::class,
            'companies' => $class = Company::class,
            default => $class = Invoice::class,
        };

        if ($this->entity == 'companies') {
            return auth()->user()->company();
        }

        return $class::withTrashed()->find(is_string($this->entity_id) ? $this->decodePrimaryKey($this->entity_id) : $this->entity_id);

    }
}

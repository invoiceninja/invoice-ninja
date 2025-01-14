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

namespace App\Http\Requests\ProductAllocation;

use App\Http\Requests\Request;
use App\Models\Product;
use App\Models\ProductAllocation;

class CreateProductAllocationRequest extends Request
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return auth()->user()->can('create', ProductAllocation::class);
    }

    public function rules(): array
    {
        return [
            'product_key' => 'required|string',
        ];
    }

    public function prepareForValidation(): void
    {
        $input = $this->all();

        if (array_key_exists('product_key', $input) && is_string($input['product_key'])) {
            $input['product_id'] = Product::where('product_key', $input['product_key'])->first()->id;
        }

        $this->replace($input);
    }
}

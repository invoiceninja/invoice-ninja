<?php
/**
 * Project Ninja (https://paymentninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Project Ninja LLC (https://paymentninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Http\Requests\Project;

use App\Http\Requests\Request;

class UploadProjectRequest extends Request
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

        return $user->can('edit', $this->project);
    }

    public function rules()
    {
        $rules = [];

        if ($this->file('documents') && is_array($this->file('documents'))) {
            $rules['documents.*'] = $this->fileValidation();
        } elseif ($this->file('documents')) {
            $rules['documents'] = $this->fileValidation();
        }

        if ($this->file('file') && is_array($this->file('file'))) {
            $rules['file.*'] = $this->fileValidation();
        } elseif ($this->file('file')) {
            $rules['file'] = $this->fileValidation();
        }

        $rules['is_public'] = 'sometimes|boolean';

        return $rules;
    }

    public function prepareForValidation()
    {
        $input = $this->all();

        if (isset($input['is_public'])) {
            $input['is_public'] = $this->toBoolean($input['is_public']);
        }

        $this->replace($input);

    }
}

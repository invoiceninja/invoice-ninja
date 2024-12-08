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

namespace App\Http\Requests\Task;

use App\Http\Requests\Request;
use App\Models\Project;
use App\Utils\Traits\ChecksEntityStatus;
use App\Utils\Traits\MakesHash;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\Rule;

class UpdateTaskRequest extends Request
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
        //prevent locked tasks from updating
        if ($this->task->invoice_id && $this->task->company->invoice_task_lock) {
            return false;
        }

        /** @var \App\Models\User $user */
        $user = auth()->user();

        return $user->can('edit', $this->task);
    }

    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules = [];

        if (isset($this->number)) {
            $rules['number'] = Rule::unique('tasks')->where('company_id', $user->company()->id)->ignore($this->task->id);
        }

        if (isset($this->client_id)) {
            $rules['client_id'] = 'bail|required|exists:clients,id,company_id,'.$user->company()->id.',is_deleted,0';
        }

        if (isset($this->project_id)) {
            $rules['project_id'] = 'bail|required|exists:projects,id,company_id,'.$user->company()->id.',is_deleted,0';
        }

        $rules['hash'] = 'bail|sometimes|string|nullable';

        $rules['time_log'] = ['bail', function ($attribute, $values, $fail) {

            if (is_string($values)) {
                $values = json_decode($values, true);
            }

            if (!is_array($values)) {
                $fail('The '.$attribute.' must be a valid array.');
                return;
            }

            foreach ($values as $k) {
                if (!is_int($k[0]) || !is_int($k[1])) {
                    return $fail('The '.$attribute.' - '.print_r($k, true).' is invalid. Unix timestamps only.');
                }
            }

            if (!$this->checkTimeLog($values)) {
                return $fail('Please correct overlapping values');
            }
        }];

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

        return $this->globalRules($rules);
    }

    public function prepareForValidation()
    {
        $input = $this->decodePrimaryKeys($this->all());

        if (array_key_exists('status_id', $input) && is_string($input['status_id'])) {
            $input['status_id'] = $this->decodePrimaryKey($input['status_id']);
        }

        /* Ensure the project is related */
        if (array_key_exists('project_id', $input) && isset($input['project_id'])) {
            $project = Project::withTrashed()->where('id', $input['project_id'])->company()->first();

            if ($project) {
                $input['client_id'] = $project->client_id;
            } else {
                unset($input['project_id']);
            }
        }

        if (array_key_exists('color', $input) && is_null($input['color'])) {
            $input['color'] = '';
        }

        if (isset($input['project_id']) && isset($input['client_id'])) {
            $search_project_with_client = Project::withTrashed()->where('id', $input['project_id'])->where('client_id', $input['client_id'])->company()->doesntExist();

            if ($search_project_with_client) {
                unset($input['project_id']);
            }

        }

        if (!isset($input['time_log']) || empty($input['time_log']) || $input['time_log'] == '{}' || $input['time_log'] == '[""]') {
            $input['time_log'] = json_encode([]);
        }

        $this->replace($input);
    }


    protected function failedAuthorization()
    {
        throw new AuthorizationException(ctrans('texts.task_update_authorization_error'));
    }
}

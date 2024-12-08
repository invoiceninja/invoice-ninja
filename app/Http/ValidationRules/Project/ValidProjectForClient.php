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

namespace App\Http\ValidationRules\Project;

use App\Models\Project;
use App\Utils\Traits\MakesHash;
use Illuminate\Contracts\Validation\Rule;

/**
 * Class ValidProjectForClient.
 */
class ValidProjectForClient implements Rule
{
    use MakesHash;

    public $input;

    public $message;

    public function __construct($input)
    {
        $this->input = $input;
    }

    /**
     * @param string $attribute
     * @param mixed $value
     * @return bool
     */
    public function passes($attribute, $value)
    {
        $this->message = ctrans('texts.project_client_do_not_match');

        if (empty($this->input['project_id'])) {
            return true;
        }

        $project = Project::withTrashed()->find($this->input['project_id']);

        if (! $project) {
            $this->message = 'Project not found';
            return false;
        }

        if (!isset($this->input['client_id'])) {
            $this->message = 'No Client ID provided.';
            return false;
        }

        return $project->client_id == $this->input['client_id'];
    }

    /**
     * @return string
     */
    public function message()
    {
        return $this->message;
    }
}

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

namespace App\Http\Requests;

use App\Http\ValidationRules\User\RelatedUserRule;
use App\Utils\Traits\MakesHash;
use Illuminate\Foundation\Http\FormRequest;

class Request extends FormRequest
{
    use MakesHash;
    use RuntimeFormRequest;

    protected $file_validation = 'sometimes|file|max:100000|mimes:png,ai,jpeg,tiff,pdf,gif,psd,txt,doc,xls,ppt,xlsx,docx,pptx,webp,xml,zip,csv,ods,odt,odp';
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [];
    }

    public function fileValidation()
    {
        if (config('ninja.upload_extensions')) {
            return $this->file_validation. ",".config('ninja.upload_extensions');
        }

        return $this->file_validation;

    }

    public function globalRules($rules)
    {
        $merge_rules = [];

        foreach ($this->all() as $key => $value) {

            if ($key == 'user') {
                continue;
            }

            if (method_exists($this, $key)) {
                $merge_rules = $this->{$key}($rules);
            }
        }

        //01-02-2022 needed for CSV Imports
        if (! $merge_rules) {
            return $rules;
        }

        return array_merge($merge_rules, $rules);
    }

    private function assigned_user_id($rules)
    {
        $rules['assigned_user_id'] = [
            'bail',
            'sometimes',
            'nullable',
            new RelatedUserRule($this->all()),
        ];

        return $rules;
    }

    private function invoice_id($rules)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['invoice_id'] = 'bail|nullable|sometimes|exists:invoices,id,company_id,'.$user->company()->id.',client_id,'.$this['client_id'];

        return $rules;
    }

    private function vendor_id($rules)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $rules['vendor_id'] = 'bail|nullable|sometimes|exists:vendors,id,company_id,'.$user->company()->id;

        return $rules;
    }

    public function decodePrimaryKeys($input)
    {
        if (array_key_exists('group_settings_id', $input) && is_string($input['group_settings_id'])) {
            $input['group_settings_id'] = $this->decodePrimaryKey($input['group_settings_id']);
        }

        if (array_key_exists('group_id', $input) && is_string($input['group_id'])) {
            $input['group_id'] = $this->decodePrimaryKey($input['group_id']);
        }

        if (array_key_exists('subscription_id', $input) && is_string($input['subscription_id'])) {
            $input['subscription_id'] = $this->decodePrimaryKey($input['subscription_id']);
        }

        if (array_key_exists('assigned_user_id', $input) && is_string($input['assigned_user_id'])) {
            $input['assigned_user_id'] = $this->decodePrimaryKey($input['assigned_user_id']);
        }

        if (array_key_exists('user_id', $input) && is_string($input['user_id'])) {
            $input['user_id'] = $this->decodePrimaryKey($input['user_id']);
        }

        if (array_key_exists('vendor_id', $input) && is_string($input['vendor_id'])) {
            $input['vendor_id'] = $this->decodePrimaryKey($input['vendor_id']);
        }

        if (array_key_exists('client_id', $input) && is_string($input['client_id'])) {
            $input['client_id'] = $this->decodePrimaryKey($input['client_id']);
        }

        if (array_key_exists('invoice_id', $input) && is_string($input['invoice_id'])) {
            $input['invoice_id'] = $this->decodePrimaryKey($input['invoice_id']);
        }

        if (array_key_exists('expense_id', $input) && is_string($input['expense_id'])) {
            $input['expense_id'] = $this->decodePrimaryKey($input['expense_id']);
        }

        if (array_key_exists('design_id', $input) && is_string($input['design_id'])) {
            $input['design_id'] = $this->decodePrimaryKey($input['design_id']);
        }

        if (array_key_exists('project_id', $input) && is_string($input['project_id'])) {
            $input['project_id'] = $this->decodePrimaryKey($input['project_id']);
        }

        if (array_key_exists('company_gateway_id', $input) && is_string($input['company_gateway_id'])) {
            $input['company_gateway_id'] = $this->decodePrimaryKey($input['company_gateway_id']);
        }

        if (array_key_exists('transaction_id', $input) && is_string($input['transaction_id'])) {
            $input['transaction_id'] = $this->decodePrimaryKey($input['transaction_id']);
        }

        if (array_key_exists('category_id', $input) && is_string($input['category_id'])) {
            $input['category_id'] = $this->decodePrimaryKey($input['category_id']);
        }

        if (isset($input['client_contacts'])) {
            foreach ($input['client_contacts'] as $key => $contact) {
                if (! array_key_exists('send_email', $contact) || ! array_key_exists('id', $contact)) {
                    unset($input['client_contacts'][$key]);
                }
            }
        }

        if (isset($input['invitations']) && is_array($input['invitations'])) {
            foreach ($input['invitations'] as $key => $value) {
                if (isset($input['invitations'][$key]['id']) && is_numeric($input['invitations'][$key]['id'])) {
                    unset($input['invitations'][$key]['id']);
                }

                if (isset($input['invitations'][$key]['id']) && is_string($input['invitations'][$key]['id'])) {
                    $input['invitations'][$key]['id'] = $this->decodePrimaryKey($input['invitations'][$key]['id']);
                }

                if (array_key_exists('client_contact_id', $input['invitations'][$key]) && is_string($input['invitations'][$key]['client_contact_id'])) {
                    $input['invitations'][$key]['client_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['client_contact_id']);
                }

                if (array_key_exists('vendor_contact_id', $input['invitations'][$key]) && is_string($input['invitations'][$key]['vendor_contact_id'])) {
                    $input['invitations'][$key]['vendor_contact_id'] = $this->decodePrimaryKey($input['invitations'][$key]['vendor_contact_id']);
                }
            }
        }

        if (isset($input['contacts']) && is_array($input['contacts'])) {
            foreach ($input['contacts'] as $key => $contact) {
                if (! is_array($contact)) {
                    continue;
                }

                if (array_key_exists('id', $contact) && is_numeric($contact['id'])) {
                    unset($input['contacts'][$key]['id']);
                } elseif (array_key_exists('id', $contact) && is_string($contact['id'])) {
                    $input['contacts'][$key]['id'] = $this->decodePrimaryKey($contact['id']);
                }

                //Filter the client contact password - if it is sent with ***** we should ignore it!
                if (isset($contact['password']) && is_string($contact['password'])) {
                    if (strlen($contact['password']) == 0) {
                        $input['contacts'][$key]['password'] = '';
                    } else {
                        $contact['password'] = str_replace('*', '', $contact['password']);

                        if (strlen($contact['password']) == 0) {
                            unset($input['contacts'][$key]['password']);
                        }
                    }
                }

                if (array_key_exists('email', $contact)) {
                    $input['contacts'][$key]['email'] = trim($contact['email']);
                }
            }
        }

        if (isset($input['public_notes'])) {
            $input['public_notes'] = str_replace("</sc", "<-", $input['public_notes']);
        }

        if (isset($input['footer'])) {
            $input['footer'] = str_replace("</sc", "<-", $input['footer']);
        }

        if (isset($input['terms'])) {
            $input['terms'] = str_replace("</sc", "<-", $input['terms']);
        }

        if (isset($input['private_notes'])) {
            $input['private_notes'] = str_replace("</sc", "<-", $input['private_notes']);
        }

        return $input;
    }

    public function prepareForValidation()
    {
    }

    /**
     * Convert to boolean
     *
     * @param $bool
     * @return bool
     */
    public function toBoolean($bool): bool
    {
        return filter_var($bool, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    }

    public function checkTimeLog(array $log): bool
    {
        if (count($log) == 0) {
            return true;
        }

        /*Get first value of all arrays*/
        $result = array_column($log, 0);

        /*Sort the array in ascending order*/
        asort($result);

        $new_array = [];

        /*Rebuild the array in order*/
        foreach ($result as $key => $value) {
            $new_array[] = $log[$key];
        }

        /*Iterate through the array and perform checks*/
        foreach ($new_array as $key => $array) {
            /*Flag which helps us know if there is a NEXT timelog*/
            $next = false;
            /* If there are more than 1 time log in the array, ensure the last timestamp is not zero*/
            if (count($new_array) > 1 && $array[1] == 0) {
                return false;
            }

            /* Check if the start time is greater than the end time */
            /* Ignore the last value for now, we'll do a separate check for this */
            if ($array[0] > $array[1] && $array[1] != 0) {
                return false;
            }

            /* Find the next time log value - if it exists */
            if (array_key_exists($key + 1, $new_array)) {
                $next = $new_array[$key + 1];
            }

            /* check the next time log and ensure the start time is GREATER than the end time of the previous record */
            if ($next && $next[0] < $array[1]) {
                return false;
            }

            /* Get the last row of the timelog*/
            $last_row = end($new_array);

            /*If the last value is NOT zero, ensure start time is not GREATER than the endtime */
            if ($last_row[1] != 0 && $last_row[0] > $last_row[1]) {
                return false;
            }

            return true;
        }
    }
}

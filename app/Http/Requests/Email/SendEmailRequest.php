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

namespace App\Http\Requests\Email;

use App\Utils\Ninja;
use Illuminate\Support\Str;
use App\Http\Requests\Request;
use App\Utils\Traits\MakesHash;
use Illuminate\Validation\Rule;
use Illuminate\Auth\Access\AuthorizationException;

class SendEmailRequest extends Request
{
    use MakesHash;

    private string $entity_plural = 'invoices';
    private string $error_message = '';

    public array $templates = [
        'email_template_invoice',
        'email_template_quote',
        'email_template_credit',
        'email_template_payment',
        'email_template_payment_partial',
        'email_template_statement',
        'email_template_reminder1',
        'email_template_reminder2',
        'email_template_reminder3',
        'email_template_reminder_endless',
        'email_template_custom1',
        'email_template_custom2',
        'email_template_custom3',
        'email_template_purchase_order',
        'email_quote_template_reminder1',
    ];

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return $this->checkUserAbleToSend();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        return [
            'template' => 'bail|required|string|in:'.implode(',', $this->templates),
            'entity' => 'bail|required|in:App\Models\Invoice,App\Models\Quote,App\Models\Credit,App\Models\RecurringInvoice,App\Models\PurchaseOrder,App\Models\Payment',
            'entity_id' => ['bail', 'required', Rule::exists($this->entity_plural, 'id')->where('company_id', $user->company()->id)],
            'cc_email.*' => 'bail|sometimes|email',
        ];

    }

    public function prepareForValidation()
    {
        $input = $this->all();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $settings = $user->company()->settings;

        if (empty($input['template'])) {
            $input['template'] = '';
        }

        if (is_string($input['template']) && ! property_exists($settings, $input['template'])) {
            unset($input['template']);
        }

        if (array_key_exists('entity_id', $input)) {
            $input['entity_id'] = $this->decodePrimaryKey($input['entity_id']);
        }

        if (isset($input['entity']) && in_array($input['entity'], ['invoice','quote','credit','recurring_invoice','purchase_order','payment','purchaseOrder'])) {
            $this->entity_plural = Str::plural($input['entity']) ?? '';
            $input['entity'] = "App\Models\\".ucfirst(Str::camel($input['entity']));
        }

        if (isset($input['cc_email'])) {
            $input['cc_email'] = collect(explode(",", $input['cc_email']))->map(function ($email) {
                return trim($email);
            })->filter(function ($email) {
                return filter_var($email, FILTER_VALIDATE_EMAIL);
            })->slice(0, 4)->toArray();
        }


        $this->replace($input);
    }

    public function messages()
    {
        return [
            'template.in' => 'Template :input is not a valid template.',
            'entity.in' => 'Entity :input is not a valid entity.'
        ];
    }

    private function checkUserAbleToSend()
    {
        $input = $this->all();

        /** @var \App\Models\User $user */
        $user = auth()->user();

        if (Ninja::isHosted() && !$user->account->account_sms_verified) {
            $this->error_message = ctrans('texts.authorization_sms_failure');
            return false;
        }

        if (Ninja::isHosted() && $user->account->emailQuotaExceeded()) {
            $this->error_message = ctrans('texts.email_quota_exceeded_subject');
            return false;
        }

        /*Make sure we have all the require ingredients to send a template*/
        if (isset($input['entity']) && array_key_exists('entity_id', $input) && in_array($input['entity'], ['App\Models\Invoice','App\Models\Quote','App\Models\Credit','App\Models\RecurringInvoice','App\Models\PurchaseOrder','App\Models\Payment'])) {

            $company = $user->company();

            $entity = $input['entity'];

            /* Harvest the entity*/
            $entity_obj = $entity::whereId($input['entity_id'])->withTrashed()->company()->first();

            /* Check object, check user and company id is same as users, and check user can edit the object */
            if ($entity_obj && ($company->id == $entity_obj->company_id) && $user->can('edit', $entity_obj)) {
                return true;
            } else {
                $this->error_message = "Invalid entity or entity_id";
            }
        }

        return true;
    }

    protected function failedAuthorization()
    {
        throw new AuthorizationException($this->error_message);
    }
}

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

namespace App\Mail\Admin;

use App\Models\Company;
use App\Models\User;
use App\Utils\Ninja;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;

class VerifyUserObject
{
    use MakesHash;

    public function __construct(public User $user, public Company $company, private bool $is_react = false)
    {
    }

    public function build()
    {
        App::forgetInstance('translator');
        /* Init a new copy of the translator*/
        $t = app('translator');
        /* Set the locale*/
        App::setLocale($this->company->getLocale());
        /* Set customized translations _NOW_ */
        $t->replace(Ninja::transformTranslations($this->company->settings));

        $this->user->confirmation_code = $this->createDbHash($this->company->db);
        $this->user->save();

        $react_redirect = '';

        if ($this->is_react) {
            $react_redirect = '?react=true';
        }

        $data = [
            'title' => ctrans('texts.confirmation_subject'),
            'content' => ctrans('texts.confirmation_message'),
            'url' => url("/user/confirm/{$this->user->confirmation_code}".$react_redirect),
            'button' => ctrans('texts.button_confirmation_message'),
            'settings' => $this->company->settings,
            'logo' => $this->company->present()->logo(),
            'signature' => $this->company->settings->email_signature,
            'text_body' => ctrans('texts.confirmation_message'),
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        $mail_obj = new \stdClass();
        $mail_obj->subject = ctrans('texts.confirmation_subject');
        $mail_obj->data = $data;
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.admin.verify_user_text';

        return $mail_obj;
    }
}

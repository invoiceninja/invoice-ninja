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

use App\Models\Client;
use App\Models\Company;
use App\Models\PaymentHash;
use App\Utils\Ninja;
use App\Utils\Number;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Facades\App;
use stdClass;

class PaymentFailureObject
{
    use MakesHash;

    /**
     * Create a new job instance.
     *
     * @param $client
     * @param $message
     * @param $company
     * @param $amount
     */
    public function __construct(public Client $client, public string $error, public Company $company, public float $amount, public ?PaymentHash $payment_hash, protected bool $use_react_url)
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

        $mail_obj = new stdClass();
        $mail_obj->amount = $this->getAmount();
        $mail_obj->subject = $this->getSubject();
        $mail_obj->data = $this->getData();
        $mail_obj->markdown = 'email.admin.generic';
        $mail_obj->tag = $this->company->company_key;
        $mail_obj->text_view = 'email.template.text';


        $bccs = $this->client->getSetting('bcc_email');

        if (strlen($bccs) > 1) {
            if (\App\Utils\Ninja::isHosted() && $this->company->account->isPaid()) {
                $mail_obj->bcc = explode(',', str_replace(' ', '', $bccs));
            } 
            
            if(Ninja::isSelfHost()){
                $mail_obj->bcc = explode(',', str_replace(' ', '', $bccs));
            }
        }


        return $mail_obj;
    }

    private function getAmount()
    {
        return $this->amount;
    }

    private function getSubject()
    {
        return
            ctrans(
                'texts.payment_failed_subject',
                ['client' => $this->client->present()->name()]
            );
    }

    private function getData()
    {
        $signature = $this->client->getSetting('email_signature');
        $content = ctrans(
            'texts.notification_invoice_payment_failed',
            [
                    'client' => $this->client->present()->name(),
                    'invoice' => $this->getDescription(),
                    'amount' => Number::formatMoney($this->amount, $this->client),
                ]
        );

        $data = [
            'title' => ctrans(
                'texts.payment_failed_subject',
                [
                    'client' => $this->client->present()->name(),
                ]
            ),
            'content' => $content,
            'signature' => $signature,
            'logo' => $this->company->present()->logo(),
            'settings' => $this->client->getMergedSettings(),
            'whitelabel' => $this->company->account->isPaid() ? true : false,
            'url' => $this->client->portalUrl($this->use_react_url),
            'button' => $this->use_react_url ? ctrans('texts.view_client') : ctrans('texts.login'),
            'additional_info' => $this->error,
            'text_body' => $content,
            'template' => $this->company->account->isPremium() ? 'email.template.admin_premium' : 'email.template.admin',
        ];

        return $data;
    }

    public function getDescription(bool $abbreviated = false)
    {
        if (! $this->payment_hash) {
            return '';
        }

        return \implode(', ', collect($this->payment_hash->invoices())->pluck('invoice_number')->toArray());
    }
}

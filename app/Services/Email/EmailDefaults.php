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

namespace App\Services\Email;

use App\Models\Task;
use App\Utils\Ninja;
use App\Models\Quote;
use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\PurchaseOrder;
use App\Jobs\Invoice\CreateUbl;
use App\Utils\Traits\MakesHash;
use App\Jobs\Entity\CreateRawPdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Mail\Mailables\Address;
use App\DataMapper\EmailTemplateDefaults;
use League\CommonMark\CommonMarkConverter;

class EmailDefaults
{
    use MakesHash;
    /**
     * The settings object for this email
     * @var \App\DataMapper\CompanySettings $settings
     */
    protected $settings;

    /**
     * The HTML / Template to use for this email
     * @var string $template
     */
    private string $template;

    /**
     * The locale to use for
     * translations for this email
     */
    private string $locale;

    /**
     * @param Email $email job class
     */
    public function __construct(protected Email $email)
    {
    }

    /**
     * Entry point for generating
     * the defaults for the email object
     *
     * @return EmailObject $email_object The email object
     */
    public function run()
    {
        $this->settings = $this->email->email_object->settings;

        $this->setLocale()
             ->setFrom()
             ->setTo()
             ->setCc()
             ->setTemplate()
             ->setBody()
             ->setSubject()
             ->setReplyTo()
             ->setBcc()
             ->setAttachments()
             ->setVariables()
             ->setHeaders();
        return $this->email->email_object;
    }

    /**
     * Sets the locale
     */
    private function setLocale(): self
    {
        if ($this->email->email_object->client) {
            $this->locale = $this->email->email_object->client->locale();
        } elseif ($this->email->email_object->vendor) {
            $this->locale = $this->email->email_object->vendor->locale();
        } else {
            $this->locale = $this->email->company->locale();
        }

        App::setLocale($this->locale);
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->settings));

        return $this;
    }

    /**
     * Sets the template
     */
    private function setTemplate(): self
    {
        $this->template = $this->email->email_object->settings->email_style;

        match ($this->email->email_object->settings->email_style) {
            'plain' => $this->template = 'email.template.plain',
            'light' => $this->template = 'email.template.client',
            'dark' => $this->template = 'email.template.client',
            'custom' => $this->template = 'email.template.custom',
            default => $this->template = 'email.template.client',
        };

        $this->email->email_object->html_template = $this->template;

        return $this;
    }

    /**
     * Sets the FROM address
     */
    private function setFrom(): self
    {
        if (Ninja::isHosted() && in_array($this->email->email_object->settings->email_sending_method, ['default', 'mailgun'])) {
            if ($this->email->company->account->isPaid() && property_exists($this->email->email_object->settings, 'email_from_name') && strlen($this->email->email_object->settings->email_from_name) > 1) {
                $email_from_name = $this->email->email_object->settings->email_from_name;
            } else {
                $email_from_name = $this->email->company->present()->name();
            }

            $this->email->email_object->from = new Address(config('mail.from.address'), $email_from_name);

            return $this;
        }

        if ($this->email->email_object->from) {
            return $this;
        }

        $this->email->email_object->from = new Address(config('mail.from.address'), config('mail.from.name'));

        return $this;
    }

    /**
     * Sets the To address
     */
    private function setTo(): self
    {
        if ($this->email->email_object->to) {
            return $this;
        }

        $this->email->email_object->to = [new Address($this->email->email_object->contact->email, $this->email->email_object->contact->present()->name())];

        return $this;
    }

    /**
     * Sets the body of the email
     */
    private function setBody(): self
    {

        if (strlen($this->email->email_object->body ?? '') > 3) {
            // A Custom Message has been set in the email screen.
        } elseif (strlen($this->email->email_object->settings?->{$this->email->email_object->email_template_body} ?? '') > 3) {
            // A body has been saved in the settings.
            $this->email->email_object->body = $this->email->email_object->settings?->{$this->email->email_object->email_template_body};
        } else {
            // Default template to be used
            $this->email->email_object->body = EmailTemplateDefaults::getDefaultTemplate($this->email->email_object->email_template_body, $this->locale);
        }

        $breaks = ["<br />","<br>","<br/>"];
        $this->email->email_object->text_body = str_ireplace($breaks, "\r\n", $this->email->email_object->body);
        $this->email->email_object->text_body = strip_tags($this->email->email_object->text_body);
        $this->email->email_object->text_body = str_replace(['$view_button','$viewButton'], "\r\n\r\n".'$view_url'."\r\n", $this->email->email_object->text_body);

        if ($this->template == 'email.template.custom') {
            $this->email->email_object->body = (str_replace('$body', $this->email->email_object->body, str_replace(["\r","\n"], "", $this->email->email_object->settings->email_style_custom)));
        }

        return $this;

    }

    /**
     * Sets the subject of the email
     */
    private function setSubject(): self
    {
        if ($this->email->email_object->subject) { //where the user updates the subject from the UI
            return $this;
        } elseif (strlen($this->email->email_object->settings?->{$this->email->email_object->email_template_subject}) > 3) {
            $this->email->email_object->subject = $this->email->email_object->settings?->{$this->email->email_object->email_template_subject};
        } else {
            $this->email->email_object->subject = EmailTemplateDefaults::getDefaultTemplate($this->email->email_object->email_template_subject, $this->locale);
        }

        return $this;
    }

    /**
     * Sets the reply to of the email
     */
    private function setReplyTo(): self
    {
        $reply_to_email = $this->email->company->owner()->email;
        $reply_to_name = $this->email->company->owner()->present()->name();

        if (str_contains($this->email->email_object->settings->reply_to_email, "@")) {
            $reply_to_email = $this->email->email_object->settings->reply_to_email;
        } elseif (isset($this->email->email_object->invitation->user)) {
            $reply_to_email = $this->email->email_object->invitation->user->email;
        }

        if (strlen($this->email->email_object->settings->reply_to_name) > 3) {
            $reply_to_name = $this->email->email_object->settings->reply_to_name;
        } elseif (isset($this->email->email_object->invitation->user)) {
            $reply_to_name = $this->email->email_object->invitation->user->present()->name();
        }

        $this->email->email_object->reply_to = array_merge($this->email->email_object->reply_to, [new Address($reply_to_email, $reply_to_name)]);

        return $this;
    }

    /**
     * Replaces the template placeholders
     * with variable values.
     */
    public function setVariables(): self
    {

        $this->email->email_object->body = strtr($this->email->email_object->body, $this->email->email_object->variables);

        $this->email->email_object->text_body = strtr($this->email->email_object->text_body, $this->email->email_object->variables);

        $this->email->email_object->subject = strtr($this->email->email_object->subject, $this->email->email_object->variables);


        //06-06-2023 ensure we do not parse markdown in custom templates
        if ($this->template != 'custom' && $this->template != 'email.template.custom') {
            $this->email->email_object->body = $this->parseMarkdownToHtml($this->email->email_object->body);
        }

        return $this;
    }

    /**
     * Sets the BCC of the email
     */
    private function setBcc(): self
    {
        $bccs = [];
        $bcc_array = [];

        if (strlen($this->email->email_object->settings->bcc_email) > 1) {
            if (Ninja::isHosted() && $this->email->company->account->isPaid()) {
                $bccs = array_slice(explode(',', str_replace(' ', '', $this->email->email_object->settings->bcc_email)), 0, 5);
            } else {
                $bccs = (explode(',', str_replace(' ', '', $this->email->email_object->settings->bcc_email)));
            }
        }

        foreach ($bccs as $bcc) {
            $bcc_array[] = new Address($bcc);
        }

        $this->email->email_object->bcc = array_merge($this->email->email_object->bcc, $bcc_array);

        return $this;
    }

    /**
     * Sets the CC of the email
     */
    private function setCc(): self
    {
        return $this;
        // return $this->email->email_object->cc;
        // return [
        // ];
    }

    /**
     * Sets the attachments for the email
     *
     * Note that we base64 encode these, as they
     * sometimes may not survive serialization.
     *
     * We decode these in the Mailable later
     */
    private function setAttachments(): self
    {
        $documents = [];

        /* Return early if the user cannot attach documents */
        if (!$this->email->email_object->invitation || !$this->email->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT) || $this->email->email_object->email_template_subject == 'email_subject_statement') {
            return $this;
        }

        /** Purchase Order / Invoice / Credit / Quote PDF  */
        if ($this->email->email_object->settings->pdf_email_attachment) {
            $pdf = ((new CreateRawPdf($this->email->email_object->invitation))->handle());

            if ($this->email->email_object->settings->embed_documents && ($this->email->email_object->entity->documents()->where('is_public', true)->count() > 0 || $this->email->email_object->entity->company->documents()->where('is_public', true)->count() > 0)) {
                $pdf = $this->email->email_object->entity->documentMerge($pdf);
            }

            $this->email->email_object->attachments = array_merge($this->email->email_object->attachments, [['file' => base64_encode($pdf), 'name' => $this->email->email_object->entity->numberFormatter().'.pdf']]);
        }

        /** UBL xml file */
        if ($this->email->email_object->settings->ubl_email_attachment && !$this->email->email_object->settings->enable_e_invoice && $this->email->email_object->entity instanceof Invoice) {
            $ubl_string = (new CreateUbl($this->email->email_object->entity))->handle();

            if ($ubl_string) {
                $this->email->email_object->attachments = array_merge($this->email->email_object->attachments, [['file' => base64_encode($ubl_string), 'name' => $this->email->email_object->entity->getFileName('xml')]]);
            }
        }
        /** E-Invoice xml file */
        if ($this->email->email_object->settings->enable_e_invoice && $this->email->email_object->settings->enable_e_invoice) {

            $xml_string = false;

            try {
                $xml_string = $this->email->email_object->entity->service()->getEDocument();
            } catch (\Throwable $th) {
                nlog("could not generate e invoice for:: ".$this->email->email_object->entity->id);
            }

            if ($xml_string) {
                $this->email->email_object->attachments = array_merge($this->email->email_object->attachments, [['file' => base64_encode($xml_string), 'name' => explode(".", $this->email->email_object->entity->getFileName('xml'))[0]."-e_invoice.xml"]]);
            }

        }

        if (!$this->email->email_object->settings->document_email_attachment || !$this->email->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            return $this;
        }

        /* Company Documents */
        $this->email->email_object->documents = array_merge($this->email->email_object->documents, $this->email->company->documents()->where('is_public', true)->pluck('id')->toArray());

        /** Entity Documents */
        if ($this->email->email_object->entity?->documents) {
            $this->email->email_object->documents = array_merge($this->email->email_object->documents, $this->email->email_object->entity->documents()->where('is_public', true)->pluck('id')->toArray());
        }

        /** Recurring Invoice Documents */
        if ($this->email->email_object->entity instanceof Invoice && $this->email->email_object->entity->recurring_id != null) {
            $this->email->email_object->documents = array_merge($this->email->email_object->documents, $this->email->email_object->entity->recurring_invoice->documents()->where('is_public', true)->pluck('id')->toArray());
        }

        /** Task / Expense Documents */
        if ($this->email->email_object->entity instanceof Invoice) {
            $expense_ids = [];
            $task_ids = [];

            foreach ($this->email->email_object->entity->line_items as $item) {
                if (property_exists($item, 'expense_id')) {
                    $expense_ids[] = $item->expense_id;
                }

                if (property_exists($item, 'task_id')) {
                    $task_ids[] = $item->task_id;
                }
            }

            if (count($expense_ids) > 0) {
                Expense::query()->whereIn('id', $this->transformKeys($expense_ids))
                        ->where('invoice_documents', 1)
                        ->cursor()
                        ->each(function ($expense) {
                            $this->email->email_object->documents = array_merge($this->email->email_object->documents, $expense->documents()->where('is_public', true)->pluck('id')->toArray());
                        });
            }

            if (count($task_ids) > 0 && $this->email->company->invoice_task_documents) {
                Task::query()->whereIn('id', $this->transformKeys($task_ids))
                    ->cursor()
                    ->each(function ($task) {
                        $this->email->email_object->documents = array_merge($this->email->email_object->documents, $task->documents()->where('is_public', true)->pluck('id')->toArray());
                    });
            }
        }

        return $this;
    }

    /**
     * Sets the headers for the email
     */
    private function setHeaders(): self
    {
        if ($this->email->email_object->invitation_key) {
            $this->email->email_object->headers = array_merge($this->email->email_object->headers, ['x-invitation' => $this->email->email_object->invitation_key]);
            // $this->email->email_object->headers = array_merge($this->email->email_object->headers, ['x-invitation' => $this->email->email_object->invitation_key,'List-Unsubscribe' =>  URL::signedRoute('client.email_preferences', ['entity' => $this->email->email_object->invitation->getEntityString(), 'invitation_key' => $this->email->email_object->invitation->key])]);
        }

        return $this;
    }

    /**
     * Converts any markdown to HTML in the email
     *
     * @param  string $markdown The body to convert
     * @return string           The parsed markdown response
     */
    private function parseMarkdownToHtml(string $markdown): string
    {
        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($markdown);
    }
}

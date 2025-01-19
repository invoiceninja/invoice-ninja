<?php
/**
 * Quote Ninja (https://quoteninja.com).
 *
 * @link https://github.com/quoteninja/quoteninja source repository
 *
 * @copyright Copyright (c) 2022. Quote Ninja LLC (https://quoteninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Mail\Engine;

use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Account;
use App\Utils\HtmlEngine;
use Illuminate\Support\Str;
use App\Jobs\Entity\CreateRawPdf;
use App\Services\PdfMaker\PdfMerge;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;

class QuoteEmailEngine extends BaseEmailEngine
{
    public $invitation;

    public $client;

    public $quote;

    public $contact;

    public $reminder_template;

    public $template_data;

    public function __construct($invitation, $reminder_template, $template_data)
    {
        $this->invitation = $invitation;
        $this->reminder_template = $reminder_template;
        $this->client = $invitation->contact->client;
        $this->quote = $invitation->quote;
        $this->contact = $invitation->contact;
        $this->template_data = $template_data;
    }

    public function build()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->client->getMergedSettings()));

        if ($this->reminder_template == 'endless_reminder') {
            $this->reminder_template = 'reminder_endless';
        }

        if (is_array($this->template_data) && array_key_exists('body', $this->template_data) && strlen($this->template_data['body']) > 0) {
            $body_template = $this->template_data['body'];
        } elseif($this->reminder_template == 'reminder1' && strlen($this->client->getSetting('email_quote_template_reminder1')) > 0){
            $body_template = $this->client->getSetting('email_quote_template_reminder1');
        } elseif($this->reminder_template == 'reminder1'){
            $body_template = \App\DataMapper\EmailTemplateDefaults::getDefaultTemplate('email_quote_template_reminder1', $this->client->locale());
        } else {
            $body_template = $this->client->getSetting('email_template_'.$this->reminder_template);
        }

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.quote_message',
                [
                    'quote' => $this->quote->number,
                    'company' => $this->quote->company->present()->name(),
                    'amount' => Number::formatMoney($this->quote->amount, $this->client),
                ],
                $this->client->locale()
            );

            $body_template .= '<div class="center">$view_button</div>';
        }

        if (is_array($this->template_data) && array_key_exists('subject', $this->template_data) && strlen($this->template_data['subject']) > 0) {
            $subject_template = $this->template_data['subject'];
        } elseif($this->reminder_template == 'reminder1' && strlen($this->client->getSetting('email_quote_subject_reminder1')) > 0){
            $subject_template = $this->client->getSetting('email_quote_subject_reminder1');
        } elseif($this->reminder_template == 'reminder1'){
            $subject_template = \App\DataMapper\EmailTemplateDefaults::getDefaultTemplate('email_quote_subject_reminder1', $this->client->locale());
        } else {
            $subject_template = $this->client->getSetting('email_subject_'.$this->reminder_template);
        }

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans(
                'texts.quote_subject',
                [
                    'number' => $this->quote->number,
                    'account' => $this->quote->company->present()->name(),
                ],
                $this->client->locale()
            );
        }

        $text_body = trans(
            'texts.quote_message',
            [
                'quote' => $this->quote->number,
                'company' => $this->quote->company->present()->name(),
                'amount' => Number::formatMoney($this->quote->amount, $this->client),
            ],
            $this->client->locale()
        )."\n\n".$this->invitation->getLink();

        $this->setTemplate($this->client->getSetting('email_style'))
            ->setContact($this->contact)
            ->setVariables((new HtmlEngine($this->invitation))->makeValues())//move make values into the htmlengine
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$this->invitation->getLink()}'>".ctrans('texts.view_quote').'</a>')
            ->setViewLink($this->invitation->getLink())
            ->setViewText(ctrans('texts.view_quote'))
            ->setInvitation($this->invitation)
            ->setTextBody($text_body);

        if ($this->client->getSetting('pdf_email_attachment') !== false && $this->quote->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
            $pdf = ((new CreateRawPdf($this->invitation))->handle());

            if ($this->client->getSetting('embed_documents') && ($this->quote->documents()->where('is_public', true)->count() > 0 || $this->quote->company->documents()->where('is_public', true)->count() > 0)) {
                $pdf = $this->quote->documentMerge($pdf);
            }

            $this->setAttachments([['file' => base64_encode($pdf), 'name' => $this->quote->numberFormatter().'.pdf']]);
        }

        //attach third party documents
        if ($this->client->getSetting('document_email_attachment') !== false && $this->quote->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            // Storage::url
            $this->quote->documents()->where('is_public', true)->cursor()->each(function ($document) {
                if ($document->size > $this->max_attachment_size) {

                    $hash = Str::random(64);
                    Cache::put($hash, ['db' => $this->quote->company->db, 'doc_hash' => $document->hash], now()->addDays(7));

                    $this->setAttachmentLinks(["<a class='doc_links' href='" . URL::signedRoute('documents.hashed_download', ['hash' => $hash]) ."'>". $document->name ."</a>"]);
                } else {
                    $this->setAttachments([['file' => base64_encode($document->getFile()), 'path' => $document->filePath(), 'name' => $document->name, 'mime' => null, ]]);
                }
            });

            $this->quote->company->documents()->where('is_public', true)->cursor()->each(function ($document) {
                if ($document->size > $this->max_attachment_size) {

                    $hash = Str::random(64);
                    Cache::put($hash, ['db' => $this->quote->company->db, 'doc_hash' => $document->hash], now()->addDays(7));

                    $this->setAttachmentLinks(["<a class='doc_links' href='" . URL::signedRoute('documents.hashed_download', ['hash' => $hash]) ."'>". $document->name ."</a>"]);
                } else {
                    $this->setAttachments([['file' => base64_encode($document->getFile()), 'path' => $document->filePath(), 'name' => $document->name, 'mime' => null, ]]);
                }
            });
        }

        return $this;
    }
}

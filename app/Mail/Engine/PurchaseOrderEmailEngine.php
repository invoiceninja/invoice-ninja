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

namespace App\Mail\Engine;

use App\Utils\Ninja;
use App\Utils\Number;
use App\Models\Vendor;
use App\Models\Account;
use Illuminate\Support\Str;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use App\Jobs\Entity\CreateRawPdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use App\DataMapper\EmailTemplateDefaults;

class PurchaseOrderEmailEngine extends BaseEmailEngine
{
    use MakesHash;

    public $invitation;

    public Vendor $vendor;

    public PurchaseOrder $purchase_order;

    public $contact;

    public $reminder_template;

    public $template_data;

    public function __construct($invitation, $reminder_template, $template_data)
    {
        $this->invitation = $invitation;
        $this->reminder_template = $reminder_template; //'purchase_order'
        $this->vendor = $invitation->contact->vendor;
        $this->purchase_order = $invitation->purchase_order;
        $this->contact = $invitation->contact;
        $this->template_data = $template_data;
    }

    public function build()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->vendor->company->settings));

        if (is_array($this->template_data) && array_key_exists('body', $this->template_data) && strlen($this->template_data['body']) > 0) {
            $body_template = $this->template_data['body'];
        } elseif (strlen($this->vendor->getSetting('email_template_'.$this->reminder_template)) > 0) {
            $body_template = $this->vendor->getSetting('email_template_'.$this->reminder_template);
        } else {
            $body_template = EmailTemplateDefaults::getDefaultTemplate('email_template_'.$this->reminder_template, $this->vendor->company->locale());
        }

        /* Use default translations if a custom message has not been set*/
        if (iconv_strlen($body_template) == 0) {
            $body_template = trans(
                'texts.invoice_message',
                [
                    'invoice' => $this->purchase_order->number,
                    'company' => $this->purchase_order->company->present()->name(),
                    'amount' => Number::formatMoney($this->purchase_order->balance, $this->vendor),
                ],
                $this->vendor->company->locale()
            );

            $body_template .= '<div class="center">$view_button</div>';
        }
        $text_body = trans(
            'texts.purchase_order_message',
            [
                'purchase_order' => $this->purchase_order->number,
                'company' => $this->purchase_order->company->present()->name(),
                'amount' => Number::formatMoney($this->purchase_order->balance, $this->vendor),
            ],
            $this->vendor->company->locale()
        )."\n\n".$this->invitation->getLink();

        if (is_array($this->template_data) && array_key_exists('subject', $this->template_data) && strlen($this->template_data['subject']) > 0) {
            $subject_template = $this->template_data['subject'];
        } elseif (strlen($this->vendor->getSetting('email_subject_'.$this->reminder_template)) > 0) {
            $subject_template = $this->vendor->getSetting('email_subject_'.$this->reminder_template);
        } else {
            $subject_template = EmailTemplateDefaults::getDefaultTemplate('email_subject_'.$this->reminder_template, $this->vendor->company->locale());
        }

        if (iconv_strlen($subject_template) == 0) {
            $subject_template = trans(
                'texts.purchase_order_subject',
                [
                    'number' => $this->purchase_order->number,
                    'account' => $this->purchase_order->company->present()->name(),
                ],
                $this->vendor->company->locale()
            );
        }

        $this->setTemplate($this->vendor->getSetting('email_style'))
            ->setContact($this->contact)
            ->setVariables((new VendorHtmlEngine($this->invitation))->makeValues())//move make values into the htmlengine
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setFooter("<a href='{$this->invitation->getLink()}'>".ctrans('texts.view_purchase_order').'</a>')
            ->setViewLink($this->invitation->getLink())
            ->setViewText(ctrans('texts.view_purchase_order'))
            ->setInvitation($this->invitation)
            ->setTextBody($text_body);

        if ($this->vendor->getSetting('pdf_email_attachment') !== false && $this->purchase_order->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {

            $pdf = (new CreateRawPdf($this->invitation))->handle();

            if ($this->vendor->getSetting('embed_documents') && ($this->purchase_order->documents()->where('is_public', true)->count() > 0 || $this->purchase_order->company->documents()->where('is_public', true)->count() > 0)) {
                $pdf = $this->purchase_order->documentMerge($pdf);
            }

            $this->setAttachments([['file' => base64_encode($pdf), 'name' => $this->purchase_order->numberFormatter().'.pdf']]);
        }

        //attach third party documents
        if ($this->vendor->getSetting('document_email_attachment') !== false && $this->purchase_order->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            // Storage::url
            $this->purchase_order->documents()->where('is_public', true)->cursor()->each(function ($document) {
                if ($document->size > $this->max_attachment_size) {

                    $hash = Str::random(64);
                    Cache::put($hash, ['db' => $this->purchase_order->company->db, 'doc_hash' => $document->hash], now()->addDays(7));


                    $this->setAttachmentLinks(["<a class='doc_links' href='" . URL::signedRoute('documents.hashed_download', ['hash' => $hash]) ."'>". $document->name ."</a>"]);
                } else {
                    $this->setAttachments([['path' => $document->filePath(), 'name' => $document->name, 'mime' => null]]);
                }
            });

            $this->purchase_order->company->documents()->where('is_public', true)->cursor()->each(function ($document) {
                if ($document->size > $this->max_attachment_size) {

                    $hash = Str::random(64);
                    Cache::put($hash, ['db' => $this->purchase_order->company->db, 'doc_hash' => $document->hash], now()->addDays(7));

                    $this->setAttachmentLinks(["<a class='doc_links' href='" . URL::signedRoute('documents.hashed_download', ['hash' => $hash]) ."'>". $document->name ."</a>"]);
                } else {
                    $this->setAttachments([['path' => $document->filePath(), 'name' => $document->name, 'mime' => null]]);
                }
            });
        }

        return $this;
    }
}

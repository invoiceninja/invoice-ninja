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

namespace App\Mail;

use App\Models\VendorContact;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Ninja;
use App\Utils\VendorHtmlEngine;
use Illuminate\Mail\Mailable;

class VendorTemplateEmail extends Mailable
{
    private $build_email;

    private $vendor;

    private $contact;

    private $company;

    private $invitation;

    public function __construct($build_email, VendorContact $contact, $invitation = null)
    {
        $this->build_email = $build_email;

        $this->contact = $contact;

        $this->vendor = $contact->vendor;

        $this->company = $contact->company;

        $this->invitation = $invitation;
    }

    /**
     * Supports inline attachments for large
     * attachments in custom designs
     *
     * @return string
     */
    private function buildLinksForCustomDesign(): string
    {
        $links = $this->build_email->getAttachmentLinks();

        if (count($links) == 0) {
            return '';
        }

        $link_string = '<ul>';

        foreach ($this->build_email->getAttachmentLinks() as $link) {
            $link_string .= "<li>{$link}</li>";
        }

        $link_string .= '</ul>';

        return $link_string;
    }

    public function build()
    {
        $template_name = 'email.template.'.$this->build_email->getTemplate();

        if (in_array($this->build_email->getTemplate(), ['light', 'dark'])) {
            $template_name = 'email.template.client';
        }

        if ($this->build_email->getTemplate() == 'premium') {
            $template_name = 'email.template.client_premium';
        }

        if ($this->build_email->getTemplate() == 'custom') {
            $this->build_email->setBody(str_replace('$body', $this->build_email->getBody().$this->buildLinksForCustomDesign(), $this->company->getSetting('email_style_custom')));
        }

        $settings = $this->company->settings;

        if ($this->build_email->getTemplate() !== 'custom') {
            $this->build_email->setBody(
                DesignHelpers::parseMarkdownToHtml($this->build_email->getBody())
            );
        }

        if ($this->invitation) {
            $html_variables = (new VendorHtmlEngine($this->invitation))->makeValues();
            $signature = str_replace(array_keys($html_variables), array_values($html_variables), $settings->email_signature);
        } else {
            $signature = $settings->email_signature;
        }

        if (property_exists($settings, 'email_from_name') && strlen($settings->email_from_name) > 1) {
            $email_from_name = $settings->email_from_name;
        } else {
            $email_from_name = $this->company->present()->name();
        }

        $this->from(config('mail.from.address'), $email_from_name);

        if (strlen($settings->bcc_email) > 1) {

            if (Ninja::isHosted()) {

                if ($this->company->account->isPaid()) {
                    $bccs = explode(',', str_replace(' ', '', $settings->bcc_email));
                    $this->bcc(array_slice($bccs, 0, 5));
                }

            } else {
                $this->bcc(explode(',', str_replace(' ', '', $settings->bcc_email)));
            }

        }

        $this->subject($this->build_email->getSubject())
            ->text('email.template.text', [
                'text_body' => $this->build_email->getTextBody(),
                'whitelabel' => $this->vendor->user->account->isPaid() ? true : false,
                'settings' => $settings,
            ])
            ->view($template_name, [
                'greeting' => ctrans('texts.email_salutation', ['name' => $this->contact->present()->name()]),
                'body' => $this->build_email->getBody(),
                'footer' => $this->build_email->getFooter(),
                'view_link' => $this->build_email->getViewLink(),
                'view_text' => $this->build_email->getViewText(),
                'title' => '',
                'signature' => $signature,
                'settings' => $settings,
                'company' => $this->company,
                'whitelabel' => $this->vendor->user->account->isPaid() ? true : false,
                'logo' => $this->company->present()->logo($settings),
                'links' => $this->build_email->getAttachmentLinks(),
            ]);


        foreach ($this->build_email->getAttachments() as $file) {
            if (array_key_exists('file', $file)) {
                $this->attachData(base64_decode($file['file']), $file['name']);
            } else {
                $this->attach($file['path'], ['as' => $file['name'], 'mime' => null]);
            }
        }

        return $this;
    }
}

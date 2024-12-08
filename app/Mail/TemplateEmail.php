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

use App\Jobs\Invoice\CreateUbl;
use App\Models\Account;
use App\Models\ClientContact;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\HtmlEngine;
use App\Utils\Ninja;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\URL;

class TemplateEmail extends Mailable
{
    private $build_email;


    /** @var \App\Models\Client $client */
    private $client;

    /** @var \App\Models\ClientContact | \App\Models\VendorContact $contact */
    private $contact;

    /** @var \App\Models\Company $company */
    private $company;

    /** @var \App\Models\InvoiceInvitation | \App\Models\QuoteInvitation | \App\Models\CreditInvitation | \App\Models\PurchaseOrderInvitation | \App\Models\RecurringInvoiceInvitation | null $invitation */
    private $invitation;

    public function __construct($build_email, ClientContact $contact, $invitation = null)
    {
        $this->build_email = $build_email;

        $this->contact = $contact;

        $this->client = $contact->client;

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
        $link_string .= "<li>{ctrans('texts.download_files')}</li>";
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
            $this->build_email->setBody(str_replace('$body', $this->build_email->getBody().$this->buildLinksForCustomDesign(), $this->client->getSetting('email_style_custom')));
        }

        $settings = $this->client->getMergedSettings();

        if ($this->build_email->getTemplate() !== 'custom') {
            $this->build_email->setBody(
                DesignHelpers::parseMarkdownToHtml($this->build_email->getBody())
            );
        }

        $company = $this->client->company;

        if ($this->invitation) {
            $html_variables = (new HtmlEngine($this->invitation))->makeValues();
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

                if ($company->account->isPaid()) {
                    $bccs = explode(',', str_replace(' ', '', $settings->bcc_email));
                    $this->bcc(array_slice($bccs, 0, 5));
                }

            } else {
                $this->bcc(explode(',', str_replace(' ', '', $settings->bcc_email)));
            }
        }

        $this->subject(str_replace("<br>", "", $this->build_email->getSubject()))
            ->text('email.template.text', [
                'text_body' => $this->build_email->getTextBody(),
                'whitelabel' => $this->client->user->account->isPaid() ? true : false,
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
                'company' => $company,
                'whitelabel' => $this->client->user->account->isPaid() ? true : false,
                'logo' => $this->company->present()->logo($settings),
                'links' => $this->build_email->getAttachmentLinks(),
                'email_preferences' => (Ninja::isHosted() && $this->invitation && in_array($settings->email_sending_method, ['default', 'mailgun'])) ? $this->company->domain() . URL::signedRoute('client.email_preferences', ['entity' => $this->invitation->getEntityString(), 'invitation_key' => $this->invitation->key], absolute: false) : false,
            ]);

        foreach ($this->build_email->getAttachments() as $file) {
            if (array_key_exists('file', $file)) {
                $this->attachData(base64_decode($file['file']), $file['name']);
            } else {
                $this->attach($file['path'], ['as' => $file['name'], 'mime' => null]);
            }
        }

        if (!$this->invitation) {
            return $this;
        }

        if ($this->invitation->invoice && $settings->ubl_email_attachment && !$this->invitation->invoice->client->getSetting('enable_e_invoice') && $this->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
            $ubl_string = (new CreateUbl($this->invitation->invoice))->handle();

            if ($ubl_string) {
                $this->attachData($ubl_string, $this->invitation->invoice->getFileName('xml'));
            }

        }

        if ($this->invitation->invoice) { //@phpstan-ignore-line
            if ($this->invitation->invoice->client->getSetting('enable_e_invoice') && $this->invitation->invoice->client->getSetting('ubl_email_attachment') && $this->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
                $xml_string = $this->invitation->invoice->service()->getEInvoice($this->invitation->contact);

                if ($xml_string) {
                    $this->attachData($xml_string, $this->invitation->invoice->getEFileName("xml"));
                }

            }
        } elseif ($this->invitation->credit) {//@phpstan-ignore-line
            if ($this->invitation->credit->client->getSetting('enable_e_invoice') && $this->invitation->invoice->client->getSetting('ubl_email_attachment') && $this->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
                $xml_string = $this->invitation->credit->service()->getECredit($this->invitation->contact);

                if ($xml_string) {
                    $this->attachData($xml_string, $this->invitation->credit->getEFileName("xml"));
                }

            }
        } elseif ($this->invitation->quote) {//@phpstan-ignore-line
            if ($this->invitation->quote->client->getSetting('enable_e_invoice') && $this->invitation->quote->client->getSetting('ubl_email_attachment') && $this->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
                $xml_string = $this->invitation->quote->service()->getEQuote($this->invitation->contact);

                if ($xml_string) {
                    $this->attachData($xml_string, $this->invitation->quote->getEFileName("xml"));
                }

            }
        } elseif ($this->invitation->purchase_order) {
            if ($this->invitation->purchase_order->vendor->getSetting('enable_e_invoice') && $this->invitation->purchase_order->vendor->getSetting('ubl_email_attachment') && $this->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {
                $xml_string = $this->invitation->purchase_order->service()->getEPurchaseOrder($this->invitation->contact);

                if ($xml_string) {
                    $this->attachData($xml_string, $this->invitation->purchase_order->getEFileName("xml"));
                }

            }
        }
        return $this;
    }
}

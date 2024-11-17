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
use App\Utils\Helpers;
use App\Models\Account;
use App\Models\Payment;
use Illuminate\Support\Str;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesDates;
use App\Jobs\Entity\CreateRawPdf;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Cache;
use App\DataMapper\EmailTemplateDefaults;
use App\Services\Template\TemplateAction;

class PaymentEmailEngine extends BaseEmailEngine
{
    use MakesDates;
    use MakesHash;

    public $client;

    /** @var \App\Models\Payment $payment */
    public $payment;

    public $template_data;

    public $settings;

    public $company;

    public $contact;

    private $helpers;

    private $payment_template_body;

    private $payment_template_subject;

    public bool $is_refund = false;

    public function __construct($payment, $contact, $template_data = null)
    {
        $this->payment = $payment;
        $this->company = $payment->company;
        $this->client = $payment->client;
        $this->contact = $contact ?: $this->client->contacts()->first();
        $this->contact->load('client.company');
        $this->settings = $this->client->getMergedSettings();
        $this->template_data = $template_data;
        $this->helpers = new Helpers();
    }

    public function build()
    {
        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->client->locale());
        $t->replace(Ninja::transformTranslations($this->client->getMergedSettings()));

        $this->resolvePaymentTemplate();

        if (is_array($this->template_data) && array_key_exists('body', $this->template_data) && strlen($this->template_data['body']) > 0) {
            $body_template = $this->template_data['body'];
        } elseif (strlen($this->client->getSetting($this->payment_template_body)) > 0) {
            $body_template = $this->client->getSetting($this->payment_template_body);
        } else {
            $body_template = EmailTemplateDefaults::getDefaultTemplate($this->payment_template_body, $this->client->locale());
        }

        if (is_array($this->template_data) && array_key_exists('subject', $this->template_data) && strlen($this->template_data['subject']) > 0) {
            $subject_template = $this->template_data['subject'];
        } elseif (strlen($this->client->getSetting($this->payment_template_subject)) > 0) {
            $subject_template = $this->client->getSetting($this->payment_template_subject);
        } else {
            $subject_template = EmailTemplateDefaults::getDefaultTemplate($this->payment_template_subject, $this->client->locale());
        }

        $this->setTemplate($this->client->getSetting('email_style'))
            ->setContact($this->contact)
            ->setVariables($this->makeValues())
            ->setSubject($subject_template)
            ->setBody($body_template)
            ->setTextBody($body_template)
            ->setFooter('')
            ->setViewLink('')
            ->setViewText('');

        if ($this->client->getSetting('pdf_email_attachment') !== false && $this->company->account->hasFeature(Account::FEATURE_PDF_ATTACHMENT)) {

            $template_in_use = false;

            if($this->is_refund && \App\Models\Design::where('id', $this->decodePrimaryKey($this->payment->client->getSetting('payment_refund_design_id')))->where('is_template', true)->exists()) {
                $pdf = (new TemplateAction(
                    [$this->payment->hashed_id],
                    $this->payment->client->getSetting('payment_refund_design_id'),
                    Payment::class,
                    $this->payment->user_id,
                    $this->payment->company,
                    $this->payment->company->db,
                    'nohash',
                    false
                ))->handle();

                $file_name = ctrans('texts.payment_refund_receipt', ['number' => $this->payment->number ]) . '.pdf';
                $file_name = str_replace(' ', '_', $file_name);
                $this->setAttachments([['file' => base64_encode($pdf), 'name' => $file_name]]);
                $template_in_use = true;

            } elseif(!$this->is_refund && \App\Models\Design::where('id', $this->decodePrimaryKey($this->payment->client->getSetting('payment_receipt_design_id')))->where('is_template', true)->exists()) {
                $pdf = (new TemplateAction(
                    [$this->payment->hashed_id],
                    $this->payment->client->getSetting('payment_receipt_design_id'),
                    Payment::class,
                    $this->payment->user_id,
                    $this->payment->company,
                    $this->payment->company->db,
                    'nohash',
                    false
                ))->handle();

                $file_name = ctrans('texts.payment_receipt', ['number' => $this->payment->number ]) . '.pdf';
                $file_name = str_replace(' ', '_', $file_name);
                $this->setAttachments([['file' => base64_encode($pdf), 'name' => $file_name]]);
                $template_in_use = true;

            }

            $this->payment->invoices->each(function ($invoice) use ($template_in_use) {

                if(!$template_in_use) {
                    $pdf = ((new CreateRawPdf($invoice->invitations->first()))->handle());
                    $file_name = $invoice->numberFormatter().'.pdf';
                    $this->setAttachments([['file' => base64_encode($pdf), 'name' => $file_name]]);
                }

                //attach invoice documents also to payments
                if ($this->client->getSetting('document_email_attachment') !== false) {
                    $invoice->documents()->where('is_public', true)->cursor()->each(function ($document) {
                        if ($document->size > $this->max_attachment_size) {

                            $hash = Str::random(64);
                            Cache::put($hash, ['db' => $this->payment->company->db, 'doc_hash' => $document->hash], now()->addDays(7));

                            $this->setAttachmentLinks(["<a class='doc_links' href='" . URL::signedRoute('documents.hashed_download', ['hash' => $hash]) ."'>". $document->name ."</a>"]);
                        } else {
                            $this->setAttachments([['path' => $document->filePath(), 'name' => $document->name, 'mime' => null, ]]);
                        }
                    });
                }

            });
        }

        return $this;
    }

    /**
     * Helper method to resolve which payment template
     * to use. We need to check the invoice balances to
     * determine if this is a partial payment, or full payment.
     *
     * @return $this
     */
    private function resolvePaymentTemplate()
    {
        $partial = $this->payment->invoices->contains(function ($invoice) {
            return $invoice->balance > 0;
        });

        if ($partial) {
            $this->payment_template_body = 'email_template_payment_partial';
            $this->payment_template_subject = 'email_subject_payment_partial';
        } else {
            $this->payment_template_body = 'email_template_payment';
            $this->payment_template_subject = 'email_subject_payment';
        }

        return $this;
    }

    public function makePaymentVariables()
    {
        $data = [];

        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$amount_paid'] = ['value' => '', 'label' => ctrans('texts.amount_paid')];
        $data['$refund'] = ['value' => '', 'label' => ctrans('texts.refund')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$number'] = ['value' => $this->payment->number ?: '&nbsp;', 'label' => ctrans('texts.payment_number')];
        $data['$payment.number'] = &$data['$number'];
        $data['$entity'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$payment.amount'] = ['value' => Number::formatMoney($this->payment->amount, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.amount')];
        $data['$payment.refunded'] = ['value' => Number::formatMoney($this->payment->refunded, $this->client) ?: '&nbsp;', 'label' => ctrans('texts.refund')];
        $data['$payment.unapplied'] = ['value' => Number::formatMoney(($this->payment->amount - $this->payment->refunded - $this->payment->applied), $this->client) ?: '&nbsp;', 'label' => ctrans('texts.refund')];
        $data['$amount'] = &$data['$payment.amount'];
        $data['$payment.date'] = ['value' => $this->translateDate($this->payment->date, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.payment_date')];
        $data['$transaction_reference'] = ['value' => $this->payment->transaction_reference, 'label' => ctrans('texts.transaction_reference')];
        $data['$reference'] = ['value' => '', 'label' => ctrans('texts.reference')];
        $data['$public_notes'] = ['value' => '', 'label' => ctrans('texts.notes')];

        $data['$payment1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment1', $this->payment->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment1')];
        $data['$payment2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment2', $this->payment->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment2')];
        $data['$payment3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment3', $this->payment->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment3')];
        $data['$payment4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'payment4', $this->payment->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment4')];

        $data['$custom1'] = &$data['$payment1'];
        $data['$custom2'] = &$data['$payment2'];
        $data['$custom3'] = &$data['$payment3'];
        $data['$custom4'] = &$data['$payment4'];

        $data['$client1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client1', $this->client->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client1')];
        $data['$client2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client2', $this->client->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client2')];
        $data['$client3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client3', $this->client->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client3')];
        $data['$client4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client4', $this->client->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client4')];
        $data['$address1'] = ['value' => $this->client->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$address2'] = ['value' => $this->client->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$id_number'] = ['value' => $this->client->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$client.number'] = ['value' => $this->client->number ?: '&nbsp;', 'label' => ctrans('texts.number')];
        $data['$vat_number'] = ['value' => $this->client->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$website'] = ['value' => $this->client->present()->website() ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$phone'] = ['value' => $this->client->present()->phone() ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$country'] = ['value' => isset($this->client->country->name) ? $this->client->country->name : '', 'label' => ctrans('texts.country')];
        $data['$email'] = ['value' => isset($this->contact) ? $this->contact->email : 'no contact email on record', 'label' => ctrans('texts.email')];
        $data['$client_name'] = ['value' => $this->client->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.client_name')];
        $data['$client.name'] = &$data['$client_name'];
        $data['$client'] = &$data['$client_name'];
        $data['$client.address1'] = &$data['$address1'];
        $data['$client.address2'] = &$data['$address2'];
        $data['$client_address'] = ['value' => $this->client->present()->address() ?: '&nbsp;', 'label' => ctrans('texts.address')];
        $data['$client.address'] = &$data['$client_address'];
        $data['$client.id_number'] = &$data['$id_number'];
        $data['$client.vat_number'] = &$data['$vat_number'];
        $data['$client.website'] = &$data['$website'];
        $data['$client.phone'] = &$data['$phone'];
        $data['$city'] = ['value' => $this->client->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$client.city'] = &$data['$city'];
        $data['$state'] = ['value' => $this->client->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$client.state'] = &$data['$state'];
        $data['$postal_code'] = ['value' => $this->client->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        $data['$client.postal_code'] = &$data['$postal_code'];
        $data['$city_state_postal'] = ['value' => $this->client->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = ['value' => $this->client->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.postal_city_state'] = &$data['$postal_city_state'];
        $data['$postal_city'] = ['value' => $this->client->present()->cityStateZip($this->client->city, null, $this->client->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city')];
        $data['$client.postal_city'] = &$data['$postal_city'];
        $data['$client.country'] = &$data['$country'];
        $data['$client.email'] = &$data['$email'];

        $data['$client.balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$client.payment_balance'] = ['value' => Number::formatMoney($this->client->payment_balance, $this->client), 'label' => ctrans('texts.payment_balance_on_file')];
        $data['$outstanding'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$client_balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$paid_to_date'] = ['value' => Number::formatMoney($this->client->paid_to_date, $this->client), 'label' => ctrans('texts.paid_to_date')];

        $data['$contact.full_name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : '', 'label' => ctrans('texts.name')];
        $data['$contact.email'] = ['value' => isset($this->contact) ? $this->contact->email : '', 'label' => ctrans('texts.email')];
        $data['$contact.phone'] = ['value' => isset($this->contact) ? $this->contact->phone : '', 'label' => ctrans('texts.phone')];

        $data['$contact.name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : 'no contact name on record', 'label' => ctrans('texts.contact_name')];
        $data['$contact.first_name'] = ['value' => isset($this->contact) ? $this->contact->first_name : '', 'label' => ctrans('texts.first_name')];
        $data['$contact.last_name'] = ['value' => isset($this->contact) ? $this->contact->last_name : '', 'label' => ctrans('texts.last_name')];
        $data['$contact.custom1'] = ['value' => isset($this->contact) ? $this->contact->custom_value1 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom2'] = ['value' => isset($this->contact) ? $this->contact->custom_value2 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom3'] = ['value' => isset($this->contact) ? $this->contact->custom_value3 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom4'] = ['value' => isset($this->contact) ? $this->contact->custom_value4 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$firstName'] = &$data['$contact.first_name'];

        $data['$company.city_state_postal'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.postal_city'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, null, $this->settings->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city')];
        $data['$company.name'] = ['value' => $this->company->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.company_name')];
        $data['$company.address1'] = ['value' => $this->settings->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$company.address2'] = ['value' => $this->settings->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$company.city'] = ['value' => $this->settings->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$company.state'] = ['value' => $this->settings->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$company.postal_code'] = ['value' => $this->settings->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        //$data['$company.country'] = ['value' => $this->getCountryName(), 'label' => ctrans('texts.country')];
        $data['$company.phone'] = ['value' => $this->settings->phone ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$company.email'] = ['value' => $this->settings->email ?: '&nbsp;', 'label' => ctrans('texts.email')];
        $data['$company.vat_number'] = ['value' => $this->settings->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number'] = ['value' => $this->settings->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$company.website'] = ['value' => $this->settings->website ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$company.address'] = ['value' => $this->company->present()->address($this->settings) ?: '&nbsp;', 'label' => ctrans('texts.address')];

        $logo = $this->company->present()->logo($this->settings);

        $data['$company.logo'] = ['value' => $logo ?: '&nbsp;', 'label' => ctrans('texts.logo')];
        $data['$company_logo'] = &$data['$company.logo'];
        $data['$company1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];
        $data['$company2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company2', $this->settings->custom_value2, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company2')];
        $data['$company3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company3', $this->settings->custom_value3, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company3')];
        $data['$company4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company4', $this->settings->custom_value4, $this->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company4')];

        $data['$view_link'] = ['value' => $this->buildViewButton($this->payment->getLink(), ctrans('texts.view_payment')), 'label' => ctrans('texts.view_payment')];
        $data['$view_button'] = &$data['$view_link'];
        $data['$viewButton'] = &$data['$view_link'];
        $data['$viewLink'] = &$data['$view_link'];
        $data['$paymentLink'] = &$data['$view_link'];
        $data['$portalButton'] = ['value' =>  $this->buildViewButton($this->payment->getPortalLink(), ctrans('texts.login')), 'label' => ''];
        $data['$portal_url'] = &$data['$portalButton'];

        $data['$view_url'] = ['value' => $this->payment->getLink(), 'label' => ctrans('texts.view_payment')];
        $data['$signature'] = ['value' => $this->settings->email_signature ?: '&nbsp;', 'label' => ''];
        $data['$emailSignature'] = &$data['$signature'];

        $data['$invoices'] = ['value' => $this->formatInvoices(), 'label' => ctrans('texts.invoices')];
        $data['$invoice_references_subject'] = ['value' => $this->formatInvoiceReferencesSubject(), 'label' => ctrans('texts.invoices')];
        $data['$invoice_references'] = ['value' => $this->formatInvoiceReferences(), 'label' => ctrans('texts.invoices')];
        $data['$invoice'] = ['value' => $this->formatInvoice(), 'label' => ctrans('texts.invoices')];
        $data['$invoice.po_number'] = ['value' => $this->formatPoNumber(), 'label' => ctrans('texts.po_number')];
        $data['$poNumber'] = &$data['$invoice.po_number'];
        $data['$payment.status'] = ['value' => $this->payment->stringStatus($this->payment->status_id), 'label' => ctrans('texts.payment_status')];
        $data['$invoices.amount'] = ['value' => $this->formatInvoiceField('amount'), 'label' => ctrans('texts.invoices')];
        $data['$invoices.balance'] = ['value' => $this->formatInvoiceField('balance'), 'label' => ctrans('texts.invoices')];
        $data['$invoices.due_date'] = ['value' => $this->formatInvoiceField('due_date'), 'label' => ctrans('texts.invoices')];
        $data['$invoices.po_number'] = ['value' => $this->formatInvoiceField('po_number'), 'label' => ctrans('texts.invoices')];
        $data['$invoice_numbers'] = ['value' => $this->formatInvoiceNumbersRaw(), 'label' => ctrans('texts.invoices')];

        if ($this->payment->status_id == 4) {
            $data['$status_logo'] = ['value' => '<div class="stamp is-paid"> ' . ctrans('texts.paid') .'</div>', 'label' => ''];
        } else {
            $data['$status_logo'] = ['value' => '', 'label' => ''];
        }


        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

        return $data;
    }

    private function formatInvoiceField($field)
    {
        $invoicex = '';

        foreach ($this->payment->invoices as $invoice) {
            $invoice_field = $invoice->{$field};

            if (in_array($field, ['amount', 'balance'])) {
                $invoice_field = Number::formatMoney($invoice_field, $this->client);
            }

            if ($field == 'due_date') {
                $invoice_field = $this->translateDate($invoice_field, $this->client->date_format(), $this->client->locale());
            }

            $invoicex .= ctrans('texts.invoice_number_short') . "{$invoice->number} {$invoice_field}";
        }

        return $invoicex;
    }

    private function formatInvoice()
    {
        $invoice = '';

        if ($this->payment->invoices()->exists()) {
            $invoice = ctrans('texts.invoice_number_short').implode(',', $this->payment->invoices->pluck('number')->toArray());
        }

        return $invoice;
    }

    private function formatPoNumber()
    {
        $invoice = '';

        if ($this->payment->invoices()->exists()) {
            $invoice = ctrans('texts.po_number_short').implode(',', $this->payment->invoices->pluck('po_number')->toArray());
        }

        return $invoice;
    }

    private function formatInvoices()
    {
        $invoice_list = '<br><br>';

        foreach ($this->payment->invoices as $invoice) {
            $invoice_list .= ctrans('texts.invoice_number_short')." {$invoice->number} ".Number::formatMoney($invoice->pivot->amount, $this->client).'<br>';
        }

        return $invoice_list;
    }

    private function formatInvoiceReferencesSubject()
    {
        $invoice_list = '';

        foreach ($this->payment->invoices as $invoice) {
            if (strlen($invoice->po_number) > 1) {
                $invoice_list .= ctrans('texts.po_number')." {$invoice->po_number} <br>";
            }

            $invoice_list .= ctrans('texts.invoice_number_short')." {$invoice->number} " . Number::formatMoney($invoice->pivot->amount, $this->client).', ';

        }

        if(strlen($invoice_list) < 4) {
            $invoice_list = Number::formatMoney($this->payment->amount, $this->client) ?: '&nbsp;';
        }


        return $invoice_list;

    }

    private function formatInvoiceNumbersRaw()
    {

        return collect($this->payment->invoices->pluck('number')->toArray())->implode(', ');

    }

    private function formatInvoiceReferences()
    {
        $invoice_list = '<br><br>';

        foreach ($this->payment->invoices as $invoice) {
            if (strlen($invoice->po_number) > 1) {
                $invoice_list .= ctrans('texts.po_number')." {$invoice->po_number} <br>";
            }

            $invoice_list .= ctrans('texts.invoice_number_short')." {$invoice->number} <br>";
            $invoice_list .= ctrans('texts.invoice_amount').' '.Number::formatMoney($invoice->pivot->amount, $this->client).'<br>';
            $invoice_list .= ctrans('texts.invoice_balance').' '.Number::formatMoney($invoice->fresh()->balance, $this->client).'<br>';
            $invoice_list .= '-----<br>';
        }

        return $invoice_list;
    }

    public function makeValues(): array
    {
        $data = [];

        $values = $this->makePaymentVariables();

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }

    /**
     * generateLabelsAndValues
     *
     * @return array
     */
    public function generateLabelsAndValues(): array
    {
        $data = [];

        $values = $this->makePaymentVariables();

        foreach ($values as $key => $value) {
            $data['values'][$key] = $value['value'];
            $data['labels'][$key.'_label'] = $value['label'];
        }

        return $data;
    }

    /**
     * buildViewButton
     *
     * @param  string $link
     * @param  string $text
     * @return string
     */
    private function buildViewButton(string $link, string $text): string
    {
        if ($this->settings->email_style == 'plain') {
            return '<a href="'. $link .'" target="_blank">'. $text .'</a>';
        }


        return '
<div>
<!--[if (gte mso 9)|(IE)]>
<table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
    <tr>
    <td align="center" valign="top">
        <![endif]-->        
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
        <tbody><tr>
        <td align="center" class="new_button" style="border-radius: 2px; background-color: '.$this->settings->primary_color.'">
            <a href="'. $link . '" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid '.$this->settings->primary_color.'; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
            <singleline label="cta button">'. $text .'</singleline>
            </a>
        </td>
        </tr>
        </tbody>
        </table>
<!--[if (gte mso 9)|(IE)]>
    </td>
    </tr>
</table>
<![endif]-->
</div>
        ';


    }
}

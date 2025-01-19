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

namespace App\Utils;

use App\Helpers\Epc\EpcQrGenerator;
use App\Helpers\SwissQr\SwissQrGenerator;
use App\Models\Account;
use App\Models\Country;
use App\Models\CreditInvitation;
use App\Models\GatewayType;
use App\Models\InvoiceInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\DesignCalculator;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class HtmlEngine
{
    use MakesDates;
    use AppSetup;
    use MakesHash;
    use DesignCalculator;

    /** @var  \App\Models\Invoice | \App\Models\Credit | \App\Models\RecurringInvoice | \App\Models\Quote $entity **/
    public $entity;

    /** @var  \App\Models\CreditInvitation | CreditInvitation | \App\Models\RecurringInvoiceInvitation | \App\Models\QuoteInvitation $invitation **/
    public $invitation;

    /** @var \App\Models\Client $client */
    public $client;

    /** @var ?\App\Models\ClientContact $contact */
    public $contact;

    /** @var \App\Models\Company $company */
    public $company;

    /** @var \App\DataMapper\CompanySettings|\stdClass $settings **/
    public $settings;

    public $entity_calc;

    public $entity_string;

    private $helpers;


    /**
     * __construct
     *
     * @param  InvoiceInvitation | CreditInvitation | RecurringInvoiceInvitation | QuoteInvitation $invitation
     * @return void
     */
    public function __construct($invitation)
    {
        $this->invitation = $invitation;

        $this->entity_string = $this->resolveEntityString();

        $this->entity = $invitation->{$this->entity_string};

        $this->company = $invitation->company;

        $this->contact = $invitation->contact->load('client');

        $this->client = $this->contact->client->load('company', 'country');

        $this->entity->load('client');

        $this->settings = $this->client->getMergedSettings();

        $this->entity_calc = $this->entity->calc();

        $this->helpers = new Helpers();
    }

    public function setSettings($settings): self
    {
        $this->settings = $settings;

        return $this;
    }

    ///////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    private function resolveEntityString()
    {
        switch ($this->invitation) {
            case ($this->invitation instanceof InvoiceInvitation):
                return 'invoice';
            case ($this->invitation instanceof CreditInvitation):
                return 'credit';
            case ($this->invitation instanceof QuoteInvitation):
                return 'quote';
            case ($this->invitation instanceof RecurringInvoiceInvitation):
                return 'recurring_invoice';
            default:
                # code...
                break;
        }
    }

    public function buildEntityDataArray(): array
    {
        if (! $this->client->currency()) {
            throw new Exception(debug_backtrace()[1]['function'], 1);
            // exit;
        }

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->contact->preferredLocale());
        $t->replace(Ninja::transformTranslations($this->settings));

        $data = [];

        $data['$date_client_now'] = ['value' => now()->setTimezone($this->client->timezone()->name)->addSeconds($this->client->utc_offset())->format($this->client->date_format()), 'label' => ''];
        $data['$date_company_now'] = ['value' => now()->setTimezone($this->company->timezone()->name)->addSeconds($this->company->utc_offset())->format($this->company->date_format()), 'label' => ''];
        $data['$global_margin'] = ['value' => '6.35mm', 'label' => ''];
        $data['$company_logo_size'] = ['value' => $this->resolveCompanyLogoSize(), 'label' => ''];
        $data['$show_shipping_address'] = ['value' => strlen($this->client->shipping_address1 ?? '') > 0 && $this->settings->show_shipping_address ? 'flex' : 'none', 'label' => ''];
        $data['$show_shipping_address_block'] = ['value' => strlen($this->client->shipping_address1 ?? '') > 0 && $this->settings->show_shipping_address ? 'block' : 'none', 'label' => ''];
        // $data['$show_shipping_address_visibility'] = ['value' => $this->settings?->show_shipping_address ? 'visible' : 'hidden', 'label' => ''];
        $data['$show_shipping_address_visibility'] = ['value' => strlen($this->client->shipping_address1 ?? '') > 0 && $this->settings->show_shipping_address ? 1 : 0, 'label' => ''];

        $data['$order_number'] = ['value' => '', 'label' => ctrans('texts.order_number')];
        $data['$tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$quantity'] = ['value' => '', 'label' => ctrans('texts.quantity')];
        $data['$amount_paid'] = ['value' => '', 'label' => ctrans('texts.amount_paid')];
        $data['$app_url'] = ['value' => $this->generateAppUrl(), 'label' => ''];
        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$shipping'] = ['value' => '', 'label' => ctrans('texts.ship_to')];
        $data['$ship_to'] = &$data['$shipping'];
        $data['$total_tax_labels'] = ['value' => $this->totalTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$total_tax_values'] = ['value' => $this->totalTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_labels'] = ['value' => $this->lineTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_values'] = ['value' => $this->lineTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.date')];
        $data['$status_logo'] = ['value' => ' ', 'label' => ' '];
        $data['$delivery_note'] = ['value' => ' ', 'label' => ctrans('texts.delivery_note')];
        $data['$receipt'] = ['value' => ' ', 'label' => ctrans('texts.receipt')];

        $data['$invoice.date'] = &$data['$date'];
        $data['$invoiceDate'] = &$data['$date'];
        $data['$due_date'] = ['value' => $this->translateDate($this->entity->due_date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];

        $data['$partial_due_date'] = ['value' => $this->translateDate($this->entity->partial_due_date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];

        $data['$dueDate'] = &$data['$due_date'];

        $data['$payment_due'] = ['value' => $this->translateDate($this->entity->due_date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.payment_due')];
        $data['$invoice.due_date'] = &$data['$due_date'];
        $data['$invoice.number'] = ['value' => $this->entity->number ?: ' ', 'label' => ctrans('texts.invoice_number')];
        $data['$invoice.po_number'] = ['value' => $this->entity->po_number ?: ' ', 'label' => ctrans('texts.po_number')];
        $data['$poNumber'] = &$data['$invoice.po_number'];
        $data['$po_number'] = &$data['$invoice.po_number'];

        $data['$project.name'] = ['value' => $this->entity->project?->name ?: '', 'label' => ctrans('texts.project')];

        $data['$entity.datetime'] = ['value' => $this->formatDatetime($this->entity->created_at, $this->client->date_format()), 'label' => ctrans('texts.date')];
        $data['$invoice.datetime'] = &$data['$entity.datetime'];
        $data['$quote.datetime'] = &$data['$entity.datetime'];
        $data['$credit.datetime'] = &$data['$entity.datetime'];
        $data['$payment_button'] = ['value' => $this->buildViewButton($this->invitation->getPaymentLink(), ctrans('texts.pay_now')), 'label' => ctrans('texts.pay_now')];
        $data['$payment_link'] = ['value' => $this->invitation->getPaymentLink(), 'label' => ctrans('texts.pay_now')];

        $data['$payment_qrcode'] = ['value' => $this->invitation->getPaymentQrCode(), 'label' => ctrans('texts.pay_now')];
        $data['$payment_qrcode_raw'] = ['value' => $this->invitation->getPaymentQrCodeRaw(), 'label' => ctrans('texts.pay_now')];

        $data['$exchange_rate'] = ['value' => $this->entity->exchange_rate ?: ' ', 'label' => ctrans('texts.exchange_rate')];
        $data['$triangular_tax'] = ['value' => ctrans('texts.triangular_tax'), 'label' => ''];
        $data['$tax_info'] = ['value' => $this->taxLabel(), 'label' => ''];
        $data['$net'] = ['value' => '', 'label' => ctrans('texts.net')];

        if ($this->entity_string == 'invoice' || $this->entity_string == 'recurring_invoice') {
            $data['$entity'] = ['value' => ctrans('texts.invoice'), 'label' => ctrans('texts.invoice')];
            $data['$number'] = ['value' => $this->entity->number ?: ' ', 'label' => ctrans('texts.invoice_number')];
            $data['$invoice'] = ['value' => $this->entity->number ?: ' ', 'label' => ctrans('texts.invoice_number')];
            $data['$number_short'] = ['value' => $this->entity->number ?: ' ', 'label' => ctrans('texts.invoice_number_short')];
            $data['$entity.terms'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->terms ?: ''), $this->client) ?: '', 'label' => ctrans('texts.invoice_terms')];
            $data['$terms'] = &$data['$entity.terms'];
            $data['$view_link'] = ['value' => $this->buildViewButton($this->invitation->getLink(), ctrans('texts.view_invoice')), 'label' => ctrans('texts.view_invoice')];
            $data['$viewLink'] = &$data['$view_link'];
            $data['$viewButton'] = &$data['$view_link'];
            $data['$view_button'] = &$data['$view_link'];
            $data['$paymentButton'] = &$data['$payment_button'];
            $data['$view_url'] = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_invoice')];
            $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.invoice_date')];

            $data['$invoice.custom1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice1', $this->entity->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice1')];
            $data['$invoice.custom2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice2', $this->entity->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice2')];
            $data['$invoice.custom3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice3', $this->entity->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice3')];
            $data['$invoice.custom4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice4', $this->entity->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice4')];

            $data['$custom1'] = &$data['$invoice.custom1'];
            $data['$custom2'] = &$data['$invoice.custom2'];
            $data['$custom3'] = &$data['$invoice.custom3'];
            $data['$custom4'] = &$data['$invoice.custom4'];

            $data['$quote.custom1'] = &$data['$invoice.custom1'];
            $data['$quote.custom2'] = &$data['$invoice.custom2'];
            $data['$quote.custom3'] = &$data['$invoice.custom3'];
            $data['$quote.custom4'] = &$data['$invoice.custom4'];

            $data['$credit.custom1'] = &$data['$invoice.custom1'];
            $data['$credit.custom2'] = &$data['$invoice.custom2'];
            $data['$credit.custom3'] = &$data['$invoice.custom3'];
            $data['$credit.custom4'] = &$data['$invoice.custom4'];

            $data['$invoice.project'] = &$data['$project.name'];
            $data['$quote.project'] = &$data['$project.name'];

            $data['$status_logo'] = ['value' => '<div class="stamp is-paid"> ' . ctrans('texts.paid') .'</div>', 'label' => ''];

            if ($this->entity->status_id == 5) {
                $data['$status_logo'] = ['value' => '<div class="stamp is-paid"> ' . ctrans('texts.cancelled') .'</div>', 'label' => ''];
            }

            $data['$show_paid_stamp'] = ['value' => in_array($this->entity->status_id, [4,5]) && $this->settings->show_paid_stamp ? 'flex' : 'none', 'label' => ''];

            $data['$invoice.vendor'] = ['value' => $this->entity->vendor?->present()->name() ?: '', 'label' => ctrans('texts.vendor_name')];

            if (strlen($this->company->getSetting('qr_iban')) > 5) {
                try {
                    $data['$swiss_qr'] = ['value' => (new SwissQrGenerator($this->entity, $this->company))->run(), 'label' => ''];
                    $data['$swiss_qr_raw'] = ['value' => html_entity_decode($data['$swiss_qr']['value']), 'label' => '']; 
                } catch (\Exception $e) {
                    $data['$swiss_qr'] = ['value' => '', 'label' => ''];
                    $data['$swiss_qr_raw'] = ['value' => '', 'label' => ''];
                }
            }
        }

        if ($this->entity_string == 'quote') {
            $data['$entity'] = ['value' => ctrans('texts.quote'), 'label' => ctrans('texts.quote')];
            $data['$number'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.quote_number')];
            $data['$number_short'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.quote_number_short')];
            $data['$entity.terms'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->terms ?: ''), $this->client) ?: '', 'label' => ctrans('texts.quote_terms')];
            $data['$terms'] = &$data['$entity.terms'];
            $data['$view_link'] = ['value' => $this->buildViewButton($this->invitation->getLink(), ctrans('texts.view_quote')), 'label' => ctrans('texts.view_quote')];
            $data['$viewLink'] = &$data['$view_link'];
            $data['$viewButton'] = &$data['$view_link'];
            $data['$view_button'] = &$data['$view_link'];
            $data['$approveButton'] = ['value' => $this->buildViewButton($this->invitation->getLink(), ctrans('texts.view_quote')), 'label' => ctrans('texts.approve')];
            $data['$view_url'] = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_quote')];
            $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.quote_date')];

            $data['$quote.custom1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'quote1', $this->entity->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'quote1')];
            $data['$quote.custom2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'quote2', $this->entity->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'quote2')];
            $data['$quote.custom3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'quote3', $this->entity->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'quote3')];
            $data['$quote.custom4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'quote4', $this->entity->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'quote4')];

            $data['$custom1'] = &$data['$quote.custom1'];
            $data['$custom2'] = &$data['$quote.custom2'];
            $data['$custom3'] = &$data['$quote.custom3'];
            $data['$custom4'] = &$data['$quote.custom4'];

            $data['$invoice.custom1'] = &$data['$quote.custom1'];
            $data['$invoice.custom2'] = &$data['$quote.custom2'];
            $data['$invoice.custom3'] = &$data['$quote.custom3'];
            $data['$invoice.custom4'] = &$data['$quote.custom4'];

            $data['$credit.custom1'] = &$data['$quote.custom1'];
            $data['$credit.custom2'] = &$data['$quote.custom2'];
            $data['$credit.custom3'] = &$data['$quote.custom3'];
            $data['$credit.custom4'] = &$data['$quote.custom4'];

            $data['$invoice.project'] = &$data['$project.name'];
            $data['$quote.project'] = &$data['$project.name'];

            $data['$invoice.vendor'] = ['value' => $this->entity->vendor?->present()->name() ?: '', 'label' => ctrans('texts.vendor_name')];
        }

        if ($this->entity_string == 'credit') {
            $data['$entity'] = ['value' => ctrans('texts.credit'), 'label' => ctrans('texts.credit')];
            $data['$number'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.credit_number')];
            $data['$number_short'] = ['value' => $this->entity->number ?: '', 'label' => ctrans('texts.credit_number_short')];
            $data['$entity.terms'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->terms ?: ''), $this->client) ?: '', 'label' => ctrans('texts.credit_terms')];
            $data['$terms'] = &$data['$entity.terms'];
            $data['$view_link'] = ['value' => $this->buildViewButton($this->invitation->getLink(), ctrans('texts.view_credit')), 'label' => ctrans('texts.view_credit')];
            $data['$viewButton'] = &$data['$view_link'];
            $data['$view_button'] = &$data['$view_link'];
            $data['$viewLink'] = &$data['$view_link'];
            $data['$view_url'] = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_credit')];
            // $data['$view_link']          = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_credit')];
            $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.credit_date')];

            $data['$credit.custom1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'credit1', $this->entity->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'credit1')];
            $data['$credit.custom2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'credit2', $this->entity->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'credit2')];
            $data['$credit.custom3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'credit3', $this->entity->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'credit3')];
            $data['$credit.custom4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'credit4', $this->entity->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'credit4')];

            $data['$custom1'] = &$data['$credit.custom1'];
            $data['$custom2'] = &$data['$credit.custom2'];
            $data['$custom3'] = &$data['$credit.custom3'];
            $data['$custom4'] = &$data['$credit.custom4'];

            $data['$quote.custom1'] = &$data['$credit.custom1'];
            $data['$quote.custom2'] = &$data['$credit.custom2'];
            $data['$quote.custom3'] = &$data['$credit.custom3'];
            $data['$quote.custom4'] = &$data['$credit.custom4'];

            $data['$invoice.custom1'] = &$data['$credit.custom1'];
            $data['$invoice.custom2'] = &$data['$credit.custom2'];
            $data['$invoice.custom3'] = &$data['$credit.custom3'];
            $data['$invoice.custom4'] = &$data['$credit.custom4'];
        }

        $data['$portal_url'] = ['value' => $this->invitation->getPortalLink(), 'label' => ''];

        $data['$entity_number'] = &$data['$number'];
        $data['$invoice.discount'] = ['value' => Number::formatMoney($this->entity_calc->getTotalDiscount(), $this->client) ?: ' ', 'label' => ($this->entity->is_amount_discount) ? ctrans('texts.discount') : ctrans('texts.discount').' '.(float)$this->entity->discount.'%'];
        $data['$discount'] = &$data['$invoice.discount'];
        $data['$subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getSubTotal(), $this->client) ?: ' ', 'label' => ctrans('texts.subtotal')];
        $data['$gross_subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getGrossSubTotal(), $this->client) ?: ' ', 'label' => ctrans('texts.subtotal')];

        if ($this->entity->uses_inclusive_taxes) {
            $data['$net_subtotal'] = ['value' => Number::formatMoney(($this->entity_calc->getSubTotal() - $this->entity->total_taxes - $this->entity_calc->getTotalDiscount()), $this->client) ?: ' ', 'label' => ctrans('texts.net_subtotal')];
        } else {
            $data['$net_subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getSubTotal() - $this->entity_calc->getTotalDiscount(), $this->client) ?: ' ', 'label' => ctrans('texts.net_subtotal')];
        }

        $data['$invoice.subtotal'] = &$data['$subtotal'];

        /* Do not change the order of these */
        if ($this->entity->partial > 0) {
            $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->partial, $this->client) ?: ' ', 'label' => ctrans('texts.partial_due')];
            $data['$balance_due_dec'] = ['value' => sprintf("%01.2f", $this->entity->partial), 'label' => ctrans('texts.partial_due')];
            $data['$balance_due_raw'] = ['value' => $this->entity->partial, 'label' => ctrans('texts.partial_due')];
            $data['$amount_raw'] = ['value' => $this->entity->partial, 'label' => ctrans('texts.partial_due')];
            $data['$due_date'] = ['value' => $this->translateDate($this->entity->partial_due_date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];
        } else {
            if ($this->entity->status_id == 1 || $this->entity_string == 'recurring_invoice') {
                $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->amount, $this->client) ?: ' ', 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_dec'] = ['value' => sprintf("%01.2f", $this->entity->amount), 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.balance_due')];
                $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
            } else {
                $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->balance, $this->client) ?: ' ', 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_dec'] = ['value' => sprintf("%01.2f", $this->entity->balance), 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_raw'] = ['value' => $this->entity->balance, 'label' => ctrans('texts.balance_due')];
                $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
            }
        }

        $data['$quote.balance_due'] = &$data['$balance_due'];
        $data['$invoice.balance_due'] = &$data['$balance_due'];


        if ($this->entity_string == 'credit') {
            $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->balance, $this->client) ?: ' ', 'label' => ctrans('texts.credit_balance')];
            $data['$balance_due_dec'] = ['value' => sprintf("%01.2f", $this->entity->balance), 'label' => ctrans('texts.credit_balance')];
            $data['$balance_due_raw'] = ['value' => $this->entity->balance, 'label' => ctrans('texts.credit_balance')];
            $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
        }

        if ($this->entity_string == 'credit' && $this->entity->status_id == 1) {
            $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->amount, $this->client) ?: ' ', 'label' => ctrans('texts.credit_balance')];
            $data['$balance_due_dec'] = ['value' => sprintf("%01.2f", $this->entity->amount), 'label' => ctrans('texts.credit_balance')];
            $data['$balance_due_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.credit_balance')];
            $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
        }

        /* Do not change the order of these */

        $data['$outstanding'] = &$data['$balance_due'];
        $data['$partial_due'] = ['value' => Number::formatMoney($this->entity->partial, $this->client) ?: ' ', 'label' => ctrans('texts.partial_due')];
        $data['$partial'] = &$data['$partial_due'];

        $data['$total'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: ' ', 'label' => ctrans('texts.total')];
        $data['$amount'] = &$data['$total'];
        $data['$amount_due'] = ['value' => &$data['$balance_due']['value'], 'label' => ctrans('texts.amount_due')];
        $data['$quote.total'] = &$data['$total'];
        $data['$invoice.total'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: ' ', 'label' => ctrans('texts.invoice_total')];
        $data['$invoice_total_raw'] = ['value' => $this->entity_calc->getTotal(), 'label' => ctrans('texts.invoice_total')];
        $data['$invoice.amount'] = &$data['$total'];
        $data['$quote.amount'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: ' ', 'label' => ctrans('texts.quote_total')];
        $data['$credit.total'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: ' ', 'label' => ctrans('texts.credit_total')];
        $data['$credit.number'] = ['value' => $this->entity->number ?: ' ', 'label' => ctrans('texts.credit_number')];
        $data['$credit.total'] = &$data['$credit.total'];
        $data['$credit.po_number'] = &$data['$invoice.po_number'];
        $data['$credit.date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.credit_date')];
        $data['$credit.valid_until'] = ['value' => $this->translateDate($this->entity->due_date, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.valid_until')];

        $data['$balance'] = ['value' => Number::formatMoney($this->getBalance(), $this->client) ?: ' ', 'label' => ctrans('texts.balance')];
        $data['$credit.balance'] = ['value' => Number::formatMoney($this->entity_calc->getBalance(), $this->client) ?: ' ', 'label' => ctrans('texts.credit_balance')];
        $data['$client.credit_balance'] = &$data['$credit.balance'];

        $data['$invoice.balance'] = &$data['$balance'];
        $data['$taxes'] = ['value' => Number::formatMoney($this->entity_calc->getItemTotalTaxes(), $this->client) ?: ' ', 'label' => ctrans('texts.taxes')];
        $data['$invoice.taxes'] = &$data['$taxes'];


        $data['$user.name'] = ['value' => $this->entity->user->present()->name(), 'label' => ctrans('texts.name')];

        $data['$user.signature'] = ['value' => $this->entity->user->signature ?? '', 'label' => ctrans('texts.signature')];
        $data['$user.first_name'] = ['value' => $this->entity->user->first_name, 'label' => ctrans('texts.first_name')];
        $data['$user.last_name'] = ['value' => $this->entity->user->last_name, 'label' => ctrans('texts.last_name')];
        $data['$created_by_user'] = &$data['$user.name'];

        $data['$assigned_to_user'] = ['value' => $this->entity->assigned_user ? $this->entity->assigned_user->present()->name() : '', 'label' => ctrans('texts.name')];
        $data['$assigned_user.signature'] = ['value' => $this->entity->assigned_user ? $this->entity->assigned_user->signature : '', 'label' => ctrans('texts.signature')];

        $data['$assigned_user.first_name'] = ['value' => $this->entity->assigned_user ? $this->entity->assigned_user->first_name : '', 'label' => ctrans('texts.first_name')];
        $data['$assigned_user.last_name'] = ['value' => $this->entity->assigned_user ? $this->entity->assigned_user->last_name : '', 'label' => ctrans('texts.last_name')];

        $data['$user_iban'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];

        $data['$invoice.public_notes'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->public_notes ?: ''), $this->client) ?: '', 'label' => ctrans('texts.public_notes')];
        $data['$entity.public_notes'] = &$data['$invoice.public_notes'];
        $data['$public_notes'] = &$data['$invoice.public_notes'];
        $data['$notes'] = &$data['$public_notes'];

        $data['$entity_issued_to'] = ['value' => '', 'label' => ctrans("texts.{$this->entity_string}_issued_to")];
        $data['$your_entity'] = ['value' => '', 'label' => ctrans("texts.your_{$this->entity_string}")];

        $data['$quote.date'] = ['value' => $this->translateDate($this->entity->date, $this->client->date_format(), $this->client->locale()) ?: ' ', 'label' => ctrans('texts.quote_date')];
        $data['$quote.number'] = ['value' => $this->entity->number ?: ' ', 'label' => ctrans('texts.quote_number')];
        $data['$quote.po_number'] = &$data['$invoice.po_number'];
        $data['$quote.quote_number'] = &$data['$quote.number'];
        $data['$quote_no'] = &$data['$quote.number'];
        $data['$quote.quote_no'] = &$data['$quote.number'];
        $data['$quote.valid_until'] = ['value' => $this->translateDate($this->entity->due_date, $this->client->date_format(), $this->client->locale()), 'label' => ctrans('texts.valid_until')];
        $data['$valid_until'] = &$data['$quote.valid_until'];
        $data['$credit_amount'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->client) ?: ' ', 'label' => ctrans('texts.credit_amount')];
        $data['$credit_balance'] = ['value' => Number::formatMoney($this->entity->balance, $this->client) ?: ' ', 'label' => ctrans('texts.credit_balance')];


        $data['$credit_number'] = &$data['$number'];
        $data['$credit_no'] = &$data['$number'];
        $data['$credit.credit_no'] = &$data['$number'];

        $data['$invoice_no'] = &$data['$number'];
        $data['$invoice.invoice_no'] = &$data['$number'];
        $data['$client1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client1', $this->client->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client1')];
        $data['$client2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client2', $this->client->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client2')];
        $data['$client3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client3', $this->client->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client3')];
        $data['$client4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client4', $this->client->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client4')];
        $data['$client.custom1'] = &$data['$client1'];
        $data['$client.custom2'] = &$data['$client2'];
        $data['$client.custom3'] = &$data['$client3'];
        $data['$client.custom4'] = &$data['$client4'];
        $data['$address1'] = ['value' => $this->client->address1 ?: ' ', 'label' => ctrans('texts.address1')];
        $data['$address2'] = ['value' => $this->client->address2 ?: ' ', 'label' => ctrans('texts.address2')];
        $data['$id_number'] = ['value' => $this->client->id_number ?: ' ', 'label' => ctrans('texts.id_number')];
        $data['$client.number'] = ['value' => $this->client->number ?: ' ', 'label' => ctrans('texts.number')];
        $data['$vat_number'] = ['value' => $this->client->vat_number ?: ' ', 'label' => ctrans('texts.vat_number')];
        $data['$website'] = ['value' => $this->client->present()->website() ?: ' ', 'label' => ctrans('texts.website')];
        $data['$phone'] = ['value' => $this->client->present()->phone() ?: ' ', 'label' => ctrans('texts.phone')];
        $data['$country'] = ['value' => isset($this->client->country->name) ? ctrans('texts.country_' . $this->client->country->name) : '', 'label' => ctrans('texts.country')];
        $data['$country_2'] = ['value' => isset($this->client->country) ? $this->client->country->iso_3166_2 : '', 'label' => ctrans('texts.country')];
        $data['$email'] = ['value' => isset($this->contact) ? $this->contact->email : 'no contact email on record', 'label' => ctrans('texts.email')];

        if (str_contains($data['$email']['value'], 'example.com')) {
            $data['$email'] = ['value' => '', 'label' => ctrans('texts.email')];
        }

        $data['$client_name'] = ['value' => $this->entity->present()->clientName() ?: ' ', 'label' => ctrans('texts.client_name')];
        $data['$client.name'] = &$data['$client_name'];
        $data['$client'] = &$data['$client_name'];

        $data['$client.address1'] = &$data['$address1'];
        $data['$client.address2'] = &$data['$address2'];
        $data['$client_address'] = ['value' => $this->client->present()->address() ?: ' ', 'label' => ctrans('texts.address')];
        $data['$client.address'] = &$data['$client_address'];
        $data['$client.postal_code'] = ['value' => $this->client->postal_code ?: ' ', 'label' => ctrans('texts.postal_code')];
        $data['$client.public_notes'] = ['value' => $this->client->public_notes ?: ' ', 'label' => ctrans('texts.notes')];
        $data['$client.city'] = ['value' => $this->client->city ?: ' ', 'label' => ctrans('texts.city')];
        $data['$client.state'] = ['value' => $this->client->state ?: ' ', 'label' => ctrans('texts.state')];
        $data['$client.id_number'] = &$data['$id_number'];
        $data['$client.vat_number'] = &$data['$vat_number'];
        $data['$client.website'] = &$data['$website'];
        $data['$client.phone'] = &$data['$phone'];
        $data['$city_state_postal'] = ['value' => $this->entity->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, false) ?: ' ', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = ['value' => $this->entity->present()->cityStateZip($this->client->city, $this->client->state, $this->client->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.postal_city_state'] = &$data['$postal_city_state'];
        $data['$postal_city'] = ['value' => $this->entity->present()->cityStateZip($this->client->city, null, $this->client->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city')];
        $data['$client.postal_city'] = &$data['$postal_city'];
        $data['$client.country'] = &$data['$country'];
        $data['$client.email'] = &$data['$email'];
        $data['$client.classification'] = ['value' => isset($this->client->classification) ? ctrans("texts.{$this->client->classification}") : ' ', 'label' => ctrans('texts.classification')];
        $data['$client.billing_address'] = &$data['$client_address'];
        $data['$client.billing_address1'] = &$data['$client.address1'];
        $data['$client.billing_address2'] = &$data['$client.address2'];
        $data['$client.billing_city'] = &$data['$client.city'];
        $data['$client.billing_state'] = &$data['$client.state'];
        $data['$client.billing_postal_code'] = &$data['$client.postal_code'];
        $data['$client.billing_country'] = &$data['$client.country'];

        $data['$client.shipping_address'] = ['value' => $this->client->present()->shipping_address() ?: ' ', 'label' => ctrans('texts.shipping_address')];
        $data['$client.shipping_address1'] = ['value' => $this->client->shipping_address1 ?: ' ', 'label' => ctrans('texts.shipping_address1')];
        $data['$client.shipping_address2'] = ['value' => $this->client->shipping_address2 ?: ' ', 'label' => ctrans('texts.shipping_address2')];
        $data['$client.shipping_city'] = ['value' => $this->client->shipping_city ?: ' ', 'label' => ctrans('texts.shipping_city')];
        $data['$client.shipping_state'] = ['value' => $this->client->shipping_state ?: ' ', 'label' => ctrans('texts.shipping_state')];
        $data['$client.shipping_postal_code'] = ['value' => $this->client->shipping_postal_code ?: ' ', 'label' => ctrans('texts.shipping_postal_code')];
        $data['$client.shipping_country'] = ['value' => isset($this->client->shipping_country->name) ? ctrans('texts.country_' . $this->client->shipping_country->name) : '', 'label' => ctrans('texts.shipping_country')];
        $data['$shipping_postal_city_state'] = ['value' => $this->entity->present()->cityStateZip($this->client->shipping_city, $this->client->shipping_state, $this->client->shipping_postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city_state')];
        $data['$client.shipping_postal_city_state'] = &$data['$shipping_postal_city_state'];
        $data['$shipping_postal_city'] = ['value' => $this->entity->present()->cityStateZip($this->client->shipping_city, null, $this->client->shipping_postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city')];
        $data['$client.shipping_postal_city'] = &$data['$shipping_postal_city'];
        $data['$shipping_city_state_postal'] = ['value' => $this->entity->present()->cityStateZip($this->client->shipping_city, $this->client->shipping_state, $this->client->shipping_postal_code, false) ?: ' ', 'label' => ctrans('texts.city_state_postal')];
        $data['$client.shipping_city_state_postal'] = &$data['$shipping_city_state_postal'];

        $data['$client.currency'] = ['value' => $this->client->currency()->code, 'label' => ''];

        $data['$client.lang_2'] = ['value' => optional($this->client->language())->locale, 'label' => ''];


        $data['$client.balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$client.payment_balance'] = ['value' => Number::formatMoney($this->client->payment_balance, $this->client), 'label' => ctrans('texts.payment_balance_on_file')];
        $data['$client_balance'] = ['value' => Number::formatMoney($this->client->balance, $this->client), 'label' => ctrans('texts.account_balance')];
        $data['$paid_to_date'] = ['value' => Number::formatMoney($this->entity->paid_to_date, $this->client), 'label' => ctrans('texts.paid_to_date')];

        $data['$contact.full_name'] = ['value' => $this->contact->present()->name(), 'label' => ctrans('texts.name')];
        $data['$contact'] = &$data['$contact.full_name'];

        $data['$contact.email'] = &$data['$email'];
        $data['$contact.phone'] = ['value' => $this->contact->phone, 'label' => ctrans('texts.phone')];

        $data['$contact.name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : $this->client->present()->name(), 'label' => ctrans('texts.contact_name')];
        $data['$contact.first_name'] = ['value' => isset($this->contact) ? $this->contact->first_name : '', 'label' => ctrans('texts.first_name')];
        $data['$firstName'] = &$data['$contact.first_name'];

        $data['$contact.last_name'] = ['value' => isset($this->contact) ? $this->contact->last_name : '', 'label' => ctrans('texts.last_name')];

        $data['$portal_button'] = ['value' => $this->buildViewButton($this->contact->getLoginLink().'?client_hash='.$this->client->client_hash, ctrans('texts.view_client_portal')), 'label' => ctrans('view_client_portal')];
        $data['$contact.portal_button'] = &$data['$portal_button'];
        $data['$portalButton'] = &$data['$portal_button'];

        $data['$contact.custom1'] = ['value' => isset($this->contact) ? $this->contact->custom_value1 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom2'] = ['value' => isset($this->contact) ? $this->contact->custom_value2 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact2')];
        $data['$contact.custom3'] = ['value' => isset($this->contact) ? $this->contact->custom_value3 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact3')];
        $data['$contact.custom4'] = ['value' => isset($this->contact) ? $this->contact->custom_value4 : ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact4')];

        $data['$company.city_state_postal'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, false) ?: ' ', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.postal_city'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, null, $this->settings->postal_code, true) ?: ' ', 'label' => ctrans('texts.postal_city')];
        $data['$company.name'] = ['value' => $this->settings->name ?: ctrans('texts.untitled_account'), 'label' => ctrans('texts.company_name')];
        $data['$company.classification'] = ['value' => ($this->settings->classification ?? false) ? ctrans("texts.{$this->settings->classification}") : ' ', 'label' => ctrans('texts.classification')];
        $data['$account'] = &$data['$company.name'];

        $data['$company.address1'] = ['value' => $this->settings->address1 ?: ' ', 'label' => ctrans('texts.address1')];
        $data['$company.address2'] = ['value' => $this->settings->address2 ?: ' ', 'label' => ctrans('texts.address2')];
        $data['$company.city'] = ['value' => $this->settings->city ?: ' ', 'label' => ctrans('texts.city')];
        $data['$company.state'] = ['value' => $this->settings->state ?: ' ', 'label' => ctrans('texts.state')];
        $data['$company.postal_code'] = ['value' => $this->settings->postal_code ?: ' ', 'label' => ctrans('texts.postal_code')];
        $data['$company.country'] = ['value' => $this->getCountryName(), 'label' => ctrans('texts.country')];
        $data['$company.country_2'] = ['value' => $this->getCountryCode(), 'label' => ctrans('texts.country')];
        $data['$company.phone'] = ['value' => $this->settings->phone ?: ' ', 'label' => ctrans('texts.phone')];
        $data['$company.email'] = ['value' => $this->settings->email ?: ' ', 'label' => ctrans('texts.email')];
        $data['$company.vat_number'] = ['value' => $this->settings->vat_number ?: ' ', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number'] = ['value' => $this->settings->id_number ?: ' ', 'label' => ctrans('texts.id_number')];
        $data['$company.website'] = ['value' => $this->settings->website ?: ' ', 'label' => ctrans('texts.website')];
        $data['$company.address'] = ['value' => $this->company->present()->address($this->settings) ?: ' ', 'label' => ctrans('texts.address')];

        $data['$signature'] = ['value' => $this->settings->email_signature ?: ' ', 'label' => ''];
        $data['$emailSignature'] = &$data['$signature'];

        $data['$spc_qr_code'] = ['value' => $this->company->present()->getSpcQrCode($this->client->currency()->code, $this->entity->number, $this->entity->balance, $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client)), 'label' => ''];

        if (Ninja::isHosted()) {
            $logo = $this->company->present()->logo($this->settings);
        } else {
            $logo = $this->company->present()->logo_base64($this->settings);
        }

        $logo_url = $this->company->present()->logo($this->settings);


        $data['$company.logo'] = ['value' => $logo ?: ' ', 'label' => ctrans('texts.logo')];
        $data['$company_logo'] = &$data['$company.logo'];

        $data['$company.logo_url'] = ['value' => $logo_url ?: ' ', 'label' => ctrans('texts.logo')];

        $data['$company1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];
        $data['$company2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company2', $this->settings->custom_value2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company2')];
        $data['$company3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company3', $this->settings->custom_value3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company3')];
        $data['$company4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company4', $this->settings->custom_value4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company4')];

        $data['$company.custom1'] = &$data['$company1'];
        $data['$company.custom2'] = &$data['$company2'];
        $data['$company.custom3'] = &$data['$company3'];
        $data['$company.custom4'] = &$data['$company4'];

        $data['$custom_surcharge1'] = ['value' => Number::formatMoney($this->entity->custom_surcharge1, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge1')];
        $data['$custom_surcharge2'] = ['value' => Number::formatMoney($this->entity->custom_surcharge2, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge2')];
        $data['$custom_surcharge3'] = ['value' => Number::formatMoney($this->entity->custom_surcharge3, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge3')];
        $data['$custom_surcharge4'] = ['value' => Number::formatMoney($this->entity->custom_surcharge4, $this->client) ?: ' ', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge4')];

        $data['$product.item'] = ['value' => '', 'label' => ctrans('texts.item')];
        $data['$product.date'] = ['value' => '', 'label' => ctrans('texts.date')];
        $data['$product.discount'] = ['value' => '', 'label' => ctrans('texts.discount')];
        $data['$product.product_key'] = ['value' => '', 'label' => ctrans('texts.product_key')];
        $data['$product.description'] = ['value' => '', 'label' => ctrans('texts.description')];
        $data['$product.unit_cost'] = ['value' => '', 'label' => ctrans('texts.unit_cost')];
        $data['$product.quantity'] = ['value' => '', 'label' => ctrans('texts.quantity')];
        $data['$product.tax_name1'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name2'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.tax_name3'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.line_total'] = ['value' => '', 'label' => ctrans('texts.line_total')];
        $data['$product.gross_line_total'] = ['value' => '', 'label' => ctrans('texts.gross_line_total')];
        $data['$product.tax_amount'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$product.description'] = ['value' => '', 'label' => ctrans('texts.description')];
        $data['$product.unit_cost'] = ['value' => '', 'label' => ctrans('texts.unit_cost')];
        $data['$product.product1'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product1')];
        $data['$product.product2'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product2')];
        $data['$product.product3'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product3')];
        $data['$product.product4'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'product4')];

        $data['$task.date'] = ['value' => '', 'label' => ctrans('texts.date')];
        $data['$task.discount'] = ['value' => '', 'label' => ctrans('texts.discount')];
        $data['$task.service'] = ['value' => '', 'label' => ctrans('texts.service')];
        $data['$task.description'] = ['value' => '', 'label' => ctrans('texts.description')];
        $data['$task.rate'] = ['value' => '', 'label' => ctrans('texts.rate')];
        $data['$task.cost'] = ['value' => '', 'label' => ctrans('texts.rate')];
        $data['$task.hours'] = ['value' => '', 'label' => ctrans('texts.hours')];
        $data['$task.tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name1'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name2'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.tax_name3'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.line_total'] = ['value' => '', 'label' => ctrans('texts.line_total')];
        $data['$task.tax_amount'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$task.gross_line_total'] = ['value' => '', 'label' => ctrans('texts.gross_line_total')];
        $data['$task.service'] = ['value' => '', 'label' => ctrans('texts.service')];
        $data['$task.task1'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task1')];
        $data['$task.task2'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task2')];
        $data['$task.task3'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task3')];
        $data['$task.task4'] = ['value' => '', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'task4')];


        if ($this->entity->vendor) {

            $data['$vendor_name'] = ['value' => $this->entity->vendor->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.vendor_name')];
            $data['$vendor.name'] = &$data['$vendor_name'];
            $data['$vendor'] = &$data['$vendor_name'];
            $data['$vendor.address1'] = ['value' => $this->entity->vendor->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
            $data['$vendor.address2'] = ['value' => $this->entity->vendor->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
            $data['$vendor.id_number'] = ['value' => $this->entity->vendor->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
            $data['$vendor.number'] = ['value' => $this->entity->vendor->number ?: '&nbsp;', 'label' => ctrans('texts.number')];
            $data['$vendor.vat_number'] = ['value' => $this->entity->vendor->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
            $data['$vendor.website'] = ['value' => $this->entity->vendor->present()->website() ?: '&nbsp;', 'label' => ctrans('texts.website')];
            $data['$vendor.phone'] = ['value' => $this->entity->vendor->present()->phone() ?: '&nbsp;', 'label' => ctrans('texts.phone')];
            $data['$vendor.country'] = ['value' => isset($this->entity->vendor->country->name) ? ctrans('texts.country_' . $this->entity->vendor->country->name) : '', 'label' => ctrans('texts.country')];
            $data['$vendor.country_2'] = ['value' => isset($this->entity->vendor->country) ? $this->entity->vendor->country->iso_3166_2 : '', 'label' => ctrans('texts.country')];
            $data['$vendor_address'] = ['value' => $this->entity->vendor->present()->address() ?: '&nbsp;', 'label' => ctrans('texts.address')];
            $data['$vendor.address'] = &$data['$vendor_address'];
            $data['$vendor.postal_code'] = ['value' => $this->entity->vendor->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
            $data['$vendor.public_notes'] = ['value' => $this->entity->vendor->public_notes ?: '&nbsp;', 'label' => ctrans('texts.notes')];
            $data['$vendor.city'] = ['value' => $this->entity->vendor->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
            $data['$vendor.state'] = ['value' => $this->entity->vendor->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
            $data['$vendor.city_state_postal'] = ['value' => $this->entity->vendor->present()->cityStateZip($this->entity->vendor->city, $this->entity->vendor->state, $this->entity->vendor->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
            $data['$vendor.postal_city_state'] = ['value' => $this->entity->vendor->present()->cityStateZip($this->entity->vendor->city, $this->entity->vendor->state, $this->entity->vendor->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
            $data['$vendor.postal_city'] = ['value' => $this->entity->vendor->present()->cityStateZip($this->entity->vendor->city, null, $this->entity->vendor->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city')];

        }

        if ($this->settings->signature_on_pdf) {
            $data['$contact.signature'] = ['value' => $this->invitation->signature_base64, 'label' => ctrans('texts.signature')];
        } else {
            $data['$contact.signature'] = ['value' => '', 'label' => ''];
        }

        if($this->entity->quote){
            $data['$quote.reference'] = ['value' => $this->entity->quote->number ?: '&nbsp;', 'label' => ctrans('texts.quote_number')];
        }

        $data['$contact.signature_raw'] = ['value' => $this->invitation->signature_base64, 'label' => ctrans('texts.signature')];
        $data['$contact.signature_date'] = ['value' => $this->invitation->signature_date ? $this->translateDate($this->invitation->signature_date, $this->client->date_format(), $this->client->locale()) : ' ', 'label' => ctrans('texts.date')];
        $data['$contact.signature_ip'] = ['value' => $this->invitation->signature_ip ?? '', 'label' => ctrans('texts.address')];

        $data['$thanks'] = ['value' => '', 'label' => ctrans('texts.thanks')];
        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];

        $data['$details'] = ['value' => '', 'label' => ctrans('texts.details')];

        $data['_rate1'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['_rate2'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['_rate3'] = ['value' => '', 'label' => ctrans('texts.tax')];

        $data['$font_size'] = ['value' => $this->settings->font_size . 'px !important;', 'label' => ''];
        $data['$font_name'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['name'], 'label' => ''];
        $data['$font_url'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['url'], 'label' => ''];

        $data['$secondary_font_name'] = ['value' => Helpers::resolveFont($this->settings->secondary_font)['name'], 'label' => ''];
        $data['$secondary_font_url'] = ['value' => Helpers::resolveFont($this->settings->secondary_font)['url'], 'label' => ''];

        $data['$invoiceninja.whitelabel'] = ['value' => 'https://invoicing.co/images/new_logo.png', 'label' => ''];

        $data['$primary_color'] = ['value' => $this->settings->primary_color, 'label' => ''];
        $data['$secondary_color'] = ['value' => $this->settings->secondary_color, 'label' => ''];

        $data['$item'] = ['value' => '', 'label' => ctrans('texts.item')];
        $data['$description'] = ['value' => '', 'label' => ctrans('texts.description')];

        //$data['$entity_footer'] = ['value' => $this->client->getSetting("{$this->entity_string}_footer"), 'label' => ''];
        $data['$entity_footer'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->footer ?: ''), $this->client), 'label' => ''];
        $data['$footer'] = &$data['$entity_footer'];

        $data['$page_size'] = ['value' => $this->settings->page_size, 'label' => ''];
        $data['$page_layout'] = ['value' => property_exists($this->settings, 'page_layout') ? $this->settings->page_layout : 'Portrait', 'label' => ''];

        $data['$tech_hero_image'] = ['value' => asset('images/pdf-designs/tech-hero-image.jpg'), 'label' => ''];
        $data['$autoBill'] = ['value' => ctrans('texts.auto_bill_notification_placeholder'), 'label' => ''];
        $data['$auto_bill'] = &$data['$autoBill'];

        /*Payment Aliases*/
        $data['$paymentLink'] = &$data['$payment_link'];
        $data['$payment_url'] = &$data['$payment_link'];

        $data['$dir'] = ['value' => in_array(optional($this->client->language())->locale, ['ar', 'he']) ? 'rtl' : 'ltr', 'label' => ''];
        $data['$dir_text_align'] = ['value' => in_array(optional($this->client->language())->locale, ['ar', 'he']) ? 'right' : 'left', 'label' => ''];

        $data['$payment.date'] = ['value' => ' ', 'label' => ctrans('texts.payment_date')];
        $data['$method'] = ['value' => ' ', 'label' => ctrans('texts.method')];

        $data['$statement_amount'] = ['value' => '', 'label' => ctrans('texts.amount')];
        $data['$statement'] = ['value' => '', 'label' => ctrans('texts.statement')];

        $data['$entity_images'] = ['value' => $this->generateEntityImagesMarkup(), 'label' => ''];

        $data['$payments'] = ['value' => '', 'label' => ctrans('texts.payments')];
        $data['$payment.custom1'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$payment.custom2'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$payment.custom3'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$payment.custom4'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$refund'] = ['value' => '', 'label' => ctrans('texts.refund')];
        $data['$refunded'] = ['value' => '', 'label' => ctrans('texts.refunded')];

        $data['$payment.payment_balance'] = ['value' => '', 'label' => ctrans('texts.payment_balance')];
        $data['$payment.amount'] = ['value' => '', 'label' => ctrans('texts.payment')];
        $data['$payment.date'] = ['value' => '', 'label' => ctrans('texts.payment_date')];
        $data['$payment.number'] = ['value' => '', 'label' => ctrans('texts.payment_number')];
        $data['$payment.transaction_reference'] = ['value' => '', 'label' => ctrans('texts.transaction_reference')];
        $data['$payment.refunded'] = ['value' => '', 'label' => ctrans('texts.refund')];
        $data['$payment_error'] = ['value' => '', 'label' => ctrans('texts.error')];

        if ($this->entity_string == 'invoice' && $this->entity->net_payments()->exists()) {
            $payment_list = '<br><br>';

            foreach ($this->entity->net_payments as $payment) {
                $payment_list .= ctrans('texts.payment_subject') . ": " . $this->formatDate($payment->date, $this->client->date_format()) . " :: " . Number::formatMoney($payment->amount, $this->client) ." :: ". $payment->translatedType() . "<br>";
            }

            $data['$payments'] = ['value' => $payment_list, 'label' => ctrans('texts.payments')];

            $payment = $this->entity->net_payments()->first();

            if ($payment) {
                $data['$payment.custom1'] = ['value' => $payment->custom_value1, 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment1')];
                $data['$payment.custom2'] = ['value' => $payment->custom_value2, 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment2')];
                $data['$payment.custom3'] = ['value' => $payment->custom_value3, 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment3')];
                $data['$payment.custom4'] = ['value' => $payment->custom_value4, 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'payment4')];

                $data['$payment.amount'] = ['value' => Number::formatMoney($payment->amount, $this->client), 'label' => ctrans('texts.payment')];
                $data['$payment.date'] = ['value' => $this->formatDate($payment->date, $this->client->date_format()), 'label' => ctrans('texts.payment_date')];
                $data['$payment.number'] = ['value' => $payment->number, 'label' => ctrans('texts.payment_number')];
                $data['$payment.transaction_reference'] = ['value' => $payment->transaction_reference, 'label' => ctrans('texts.transaction_reference')];
                $data['$payment.refunded'] = ['value' => $this->getPaymentMeta($payment), 'label' => ctrans('texts.refund')];
            }
        }

        if (($this->entity_string == 'invoice' || $this->entity_string == 'recurring_invoice') && isset($this->company?->custom_fields?->company1)) {
            $data['$sepa_qr_code'] = ['value' => (new EpcQrGenerator($this->company, $this->entity, $data['$amount_raw']['value']))->getQrCode(), 'label' => ''];
        }

        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

        return $data;
    }

    private function getPaymentMeta(\App\Models\Payment $payment)
    {

        if (!is_array($payment->refund_meta)) {
            return '';
        }

        return collect($payment->refund_meta)
                ->map(function ($refund) use ($payment) {

                    $date = \Carbon\Carbon::parse($refund['date'] ?? $payment->date)->addSeconds($payment->client->timezone_offset());
                    $date = $this->translateDate($date, $payment->client->date_format(), $payment->client->locale());
                    $entity = ctrans('texts.invoice');

                    $map = [];

                    foreach ($refund['invoices'] as $refunded_invoice) {
                        $invoice = \App\Models\Invoice::withTrashed()->find($refunded_invoice['invoice_id']);
                        $amount = Number::formatMoney($refunded_invoice['amount'], $payment->client);
                        $notes = ctrans('texts.status_partially_refunded_amount', ['amount' => $amount]);

                        if ($invoice) {
                            array_push($map, "{$date} {$entity} #{$invoice->number} {$notes}\n");
                        }

                    }

                    return $map;

                })->flatten()->implode("\n");

    }
    /**
     * Returns a localized string for tax compliance purposes
     *
     * @return string
     */
    private function taxLabel(): string
    {
        $tax_label = '';

        if (collect($this->entity->line_items)->contains('tax_id', \App\Models\Product::PRODUCT_TYPE_REVERSE_TAX)) {
            $tax_label .= ctrans('texts.reverse_tax_info') . "<br>";
        }

        if ((int)$this->client->country_id !== (int)$this->company->settings->country_id) {
            $tax_label .= ctrans('texts.intracommunity_tax_info') . "<br>";

            if ($this->entity_calc->getTotalTaxes() > 0) {
                $tax_label = '';
            }
        }

        if (isset($this->entity->company->tax_data->regions->EU->has_sales_above_threshold) && !$this->entity->company->tax_data->regions->EU->has_sales_above_threshold) {
            $tax_label .= ctrans('text.small_company_info') ."<br>";
        }

        return $tax_label;
    }

    private function getBalance()
    {
        if ($this->entity->status_id == 1) {
            return $this->entity->amount;
        }

        return $this->entity->balance;
    }

    public function makeValues(): array
    {
        $data = [];

        $values = $this->buildEntityDataArray();

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
        }

        return $data;
    }

    public function makeValuesNoPrefix(): array
    {
        $data = [];

        $values = $this->buildEntityDataArray();

        foreach ($values as $key => $value) {
            $data[str_replace(["$","."], ["_","_"], $key)] = $value['value'];
        }

        return $data;
    }


    public function generateLabelsAndValues()
    {
        $data = [];

        $values = $this->buildEntityDataArray();

        foreach ($values as $key => $value) {
            $data['values'][$key] = $value['value'];
            $data['labels'][$key.'_label'] = $value['label'];
        }

        return $data;
    }

    private function totalTaxLabels(): string
    {
        $data = '';

        if (! $this->entity_calc->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
            $data .= '<span>'.$tax['name'].'</span>';
        }

        return $data;
    }

    private function totalTaxValues(): string
    {
        $data = '';

        if (! $this->entity_calc->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $this->client).'</span>';
        }

        return $data;
    }

    private function lineTaxLabels(): string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.$tax['name'].'</span>';
        }

        return $data;
    }

    private function getCountryName(): string
    {

        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        $country = $countries->first(function ($item) {
            return $item->id == $this->settings->country_id;
        });

        return $country ? ctrans('texts.country_' . $country->name) : ctrans('texts.country_' . $countries->first()->name);
    }


    private function getCountryCode(): string
    {


        /** @var \Illuminate\Support\Collection<\App\Models\Country> */
        $countries = app('countries');

        $country = $countries->first(function ($item) {
            return $item->id == $this->settings->country_id;
        });

        return $country ? $country->iso_3166_2 : ' ';
    }
    /**
     * Due to the way we are compiling the blade template we
     * have no ability to iterate, so in the case
     * of line taxes where there are multiple rows,
     * we use this function to format a section of rows.
     *
     * @return string a collection of <tr> rows with line item
     * aggregate data
     */
    // private function makeLineTaxes(): string
    // {
    //     $tax_map = $this->entity_calc->getTaxMap();

    //     $data = '';

    //     foreach ($tax_map as $tax) {
    //         $data .= '<tr class="line_taxes">';
    //         $data .= '<td>'.$tax['name'].'</td>';
    //         $data .= '<td>'.Number::formatMoney($tax['total'], $this->client).'</td></tr>';
    //     }

    //     return $data;
    // }

    private function lineTaxValues(): string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $this->client).'</span>';
        }

        return $data;
    }

    // private function makeTotalTaxes(): string
    // {
    //     $data = '';

    //     if (! $this->entity_calc->getTotalTaxMap()) {
    //         return $data;
    //     }

    //     foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
    //         $data .= '<tr>';
    //         $data .= '<td colspan="{ count($this->entity->company->settings->pdf_variables->total_columns) - 2 }"></td>';
    //         $data .= '<td>'.$tax['name'].'</td>';
    //         $data .= '<td>'.Number::formatMoney($tax['total'], $this->client).'</td></tr>';
    //     }

    //     return $data;
    // }

    // private function parseLabelsAndValues($labels, $values, $section): string
    // {
    //     $section = strtr($section, $labels);

    //     return strtr($section, $values);
    // }

    /*
    | Ensures the URL doesn't have duplicated trailing slash
    */
    public function generateAppUrl()
    {
        //return rtrim(config('ninja.app_url'), "/");
        return config('ninja.app_url');
    }

    /**
     * Builds CSS to assist with the generation
     * of Repeating headers and footers on the PDF.
     * @return string The css string
     */
    //     private function generateCustomCSS(): string
    //     {
    //         $header_and_footer = '
    // .header, .header-space {
    //   height: 160px;
    // }

    // .footer, .footer-space {
    //   height: 160px;
    // }

    // .footer {
    //   position: fixed;
    //   bottom: 0;
    //   width: 100%;
    // }

    // .header {
    //   position: fixed;
    //   top: 0mm;
    //   width: 100%;
    // }

    // @media print {
    //    thead {display: table-header-group;}
    //    tfoot {display: table-footer-group;}
    //    button {display: none;}
    //    body {margin: 0;}
    // }';

    //         $header = '
    // .header, .header-space {
    //   height: 160px;
    // }

    // .header {
    //   position: fixed;
    //   top: 0mm;
    //   width: 100%;
    // }

    // @media print {
    //    thead {display: table-header-group;}
    //    button {display: none;}
    //    body {margin: 0;}
    // }';

    //         $footer = '

    // .footer, .footer-space {
    //   height: 160px;
    // }

    // .footer {
    //   position: fixed;
    //   bottom: 0;
    //   width: 100%;
    // }

    // @media print {
    //    tfoot {display: table-footer-group;}
    //    button {display: none;}
    //    body {margin: 0;}
    // }';
    //         $css = '';

    //         if ($this->settings->all_pages_header && $this->settings->all_pages_footer) {
    //             $css .= $header_and_footer;
    //         } elseif ($this->settings->all_pages_header && ! $this->settings->all_pages_footer) {
    //             $css .= $header;
    //         } elseif (! $this->settings->all_pages_header && $this->settings->all_pages_footer) {
    //             $css .= $footer;
    //         }

    //         $css .= '
    // .page {
    //   page-break-after: always;
    // }

    // @page {
    //   margin: 0mm
    // }

    // html {
    //         ';

    //         $css .= 'font-size:'.$this->settings->font_size.'px;';

    //         $css .= '}';

    //         return $css;
    //     }

    /**
     * Generate markup for HTML images on entity.
     *
     * @return string|void
     */
    protected function generateEntityImagesMarkup()
    {
        if (!$this->client->getSetting('embed_documents') || !$this->company->account->hasFeature(Account::FEATURE_DOCUMENTS)) {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $container =  $dom->createElement('div');
        $container->setAttribute('style', 'display:grid; grid-auto-flow: row; grid-template-columns: repeat(2, 1fr); grid-template-rows: repeat(2, 1fr);justify-items: center;');

        /** @var \App\Models\Document $document */
        foreach ($this->entity->documents()->where('is_public', true)->get() as $document) {
            if (!$document->isImage()) {
                continue;
            }

            $image = $dom->createElement('img');

            $image->setAttribute('src', "data:image/png;base64,".base64_encode($document->compress()));
            $image->setAttribute('style', 'max-width: 50%; margin-top: 20px;');

            $container->appendChild($image);
        }

        $dom->appendChild($container);

        $html = $dom->saveHTML();

        $dom = null;

        return $html;
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
<div class=\"center\">
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


        // return '
        //     <table border="0" cellspacing="0" cellpadding="0" align="center">
        //         <tr style="border: 0 !important; ">
        //             <td class="new_button" style="padding: 12px 18px 12px 18px; border-radius:5px;" align="center">
        //             <a href="'. $link .'" target="_blank" style="border: 0 !important;font-size: 18px; font-family: Helvetica, Arial, sans-serif; color: #ffffff; text-decoration: none; display: inline-block;">'. $text .'</a>
        //             </td>
        //         </tr>
        //     </table>
        // ';
    }
}

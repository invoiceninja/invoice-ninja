<?php

/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2023. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Utils;

use App\Models\Country;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrderInvitation;
use App\Models\QuoteInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\DesignCalculator;
use App\Utils\Traits\MakesDates;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

/**
 * Note the premise used here is that any currencies will be formatted back to the company currency and not
 * use the vendor currency, if we continue to extend on vendor, we will need to relook at this
 */

class VendorHtmlEngine
{
    use MakesDates;
    use AppSetup;
    use DesignCalculator;
    
    public $entity;

    public $invitation;

    public $vendor;

    public $contact;

    public $company;

    public $settings;

    public $entity_calc;

    public $entity_string;

    private $helpers;

    public function __construct($invitation)
    {
        $this->invitation = $invitation;

        $this->entity_string = $this->resolveEntityString();

        $this->entity = $invitation->purchase_order;

        $this->company = $invitation->company;

        $this->contact = $invitation->contact->load('vendor');
        
        $this->vendor = $this->contact->vendor->load('company', 'country');
        
        if (!$this->vendor->currency_id) {
            $this->vendor->currency_id = $this->company->settings->currency_id;
            $this->vendor->save();
        }

        $this->entity->load('vendor');

        $this->settings = $this->company->settings;

        $this->entity_calc = $this->entity->calc();

        $this->helpers = new Helpers();
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
            case ($this->invitation instanceof PurchaseOrderInvitation):
                return 'purchase_order';
            default:
                # code...
                break;
        }
    }

    public function buildEntityDataArray() :array
    {
        if (! $this->vendor->currency()) {
            throw new Exception(debug_backtrace()[1]['function'], 1);
        }

        App::forgetInstance('translator');
        $t = app('translator');
        App::setLocale($this->vendor->locale());
        $t->replace(Ninja::transformTranslations($this->settings));

        $data = [];
        $data['$global_margin'] = ['value' => '6.35mm', 'label' => ''];
        $data['$tax'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['$company_logo_size'] = ['value' => $this->resolveCompanyLogoSize(), 'label' => ''];
        $data['$app_url'] = ['value' => $this->generateAppUrl(), 'label' => ''];
        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$total_tax_labels'] = ['value' => $this->totalTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$total_tax_values'] = ['value' => $this->totalTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_labels'] = ['value' => $this->lineTaxLabels(), 'label' => ctrans('texts.taxes')];
        $data['$line_tax_values'] = ['value' => $this->lineTaxValues(), 'label' => ctrans('texts.taxes')];
        $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->company->date_format(), $this->vendor->locale()) ?: '&nbsp;', 'label' => ctrans('texts.date')];

        $data['$due_date'] = ['value' => $this->translateDate($this->entity->due_date, $this->company->date_format(), $this->vendor->locale()) ?: '&nbsp;', 'label' => ctrans('texts.due_date')];

        $data['$partial_due_date'] = ['value' => $this->translateDate($this->entity->partial_due_date, $this->company->date_format(), $this->vendor->locale()) ?: '&nbsp;', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];
        
        $data['$dueDate'] = &$data['$due_date'];
        $data['$purchase_order.due_date'] = &$data['$due_date'];

        $data['$payment_due'] = ['value' => $this->translateDate($this->entity->due_date, $this->company->date_format(), $this->vendor->locale()) ?: '&nbsp;', 'label' => ctrans('texts.payment_due')];
        $data['$purchase_order.po_number'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.po_number')];

        $data['$poNumber'] = &$data['$purchase_order.po_number'];

        $data['$entity.datetime'] = ['value' => $this->formatDatetime($this->entity->created_at, $this->company->date_format()), 'label' => ctrans('texts.date')];

        $data['$status_logo'] = ['value' => ' ', 'label' => ' '];
        $data['$entity'] = ['value' => '', 'label' => ctrans('texts.purchase_order')];
        $data['$number'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.number')];
        $data['$number_short'] = ['value' => $this->entity->number ?: '&nbsp;', 'label' => ctrans('texts.purchase_order_number_short')];
        $data['$entity.terms'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->terms), $this->company) ?: '', 'label' => ctrans('texts.invoice_terms')];
        $data['$terms'] = &$data['$entity.terms'];
        $data['$view_link'] = ['value' => $this->buildViewButton($this->invitation->getLink(), ctrans('texts.view_purchase_order')), 'label' => ctrans('texts.view_purchase_order')];
        $data['$viewLink'] = &$data['$view_link'];
        $data['$viewButton'] = &$data['$view_link'];
        $data['$view_button'] = &$data['$view_link'];
        $data['$view_url'] = ['value' => $this->invitation->getLink(), 'label' => ctrans('texts.view_invoice')];
        $data['$date'] = ['value' => $this->translateDate($this->entity->date, $this->company->date_format(), $this->vendor->locale()) ?: '&nbsp;', 'label' => ctrans('texts.date')];

        $data['$purchase_order.number'] = &$data['$number'];
        $data['$purchase_order.date'] = &$data['$date'];
        $data['$purchase_order.due_date'] = &$data['$due_date'];
        $data['$entity_issued_to'] = ['value' => '', 'label' => ctrans("texts.purchase_order_issued_to")];

        $data['$portal_url'] = ['value' => $this->invitation->getPortalLink(), 'label' =>''];

        $data['$entity_number'] = &$data['$number'];
        $data['$discount'] = ['value' => $this->entity->discount, 'label' => ctrans('texts.discount')];
        $data['$subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getSubTotal(), $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.subtotal')];
        $data['$gross_subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getGrossSubTotal(), $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.subtotal')];

        if ($this->entity->uses_inclusive_taxes) {
            $data['$net_subtotal'] = ['value' => Number::formatMoney(($this->entity_calc->getSubTotal() - $this->entity->total_taxes - $this->entity_calc->getTotalDiscount()), $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.net_subtotal')];
        } else {
            $data['$net_subtotal'] = ['value' => Number::formatMoney($this->entity_calc->getSubTotal() - $this->entity_calc->getTotalDiscount(), $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.net_subtotal')];
        }

        if ($this->entity->partial > 0) {
            $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->partial, $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.partial_due')];
            $data['$balance_due_raw'] = ['value' => $this->entity->partial, 'label' => ctrans('texts.partial_due')];
            $data['$amount_raw'] = ['value' => $this->entity->partial, 'label' => ctrans('texts.partial_due')];
            $data['$due_date'] = ['value' => $this->translateDate($this->entity->partial_due_date, $this->company->date_format(), $this->vendor->locale()) ?: '&nbsp;', 'label' => ctrans('texts.'.$this->entity_string.'_due_date')];
        } else {
            if ($this->entity->status_id == 1) {
                $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->amount, $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.balance_due')];
                $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
            } else {
                $data['$balance_due'] = ['value' => Number::formatMoney($this->entity->balance, $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.balance_due')];
                $data['$balance_due_raw'] = ['value' => $this->entity->balance, 'label' => ctrans('texts.balance_due')];
                $data['$amount_raw'] = ['value' => $this->entity->amount, 'label' => ctrans('texts.amount')];
            }
        }

        $data['$outstanding'] = &$data['$balance_due'];
        $data['$purchase_order.balance_due'] = &$data['$balance_due'];
        
        $data['$partial_due'] = ['value' => Number::formatMoney($this->entity->partial, $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.partial_due')];
        $data['$partial'] = &$data['$partial_due'];

        $data['$total'] = ['value' => Number::formatMoney($this->entity_calc->getTotal(), $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.total')];

        $data['$purchase_order.total'] = &$data['$total'];

        $data['$amount'] = &$data['$total'];
        $data['$amount_due'] = ['value' => &$data['$total']['value'], 'label' => ctrans('texts.amount_due')];
        $data['$balance'] = ['value' => Number::formatMoney($this->entity_calc->getBalance(), $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.balance')];

        $data['$taxes'] = ['value' => Number::formatMoney($this->entity_calc->getItemTotalTaxes(), $this->vendor) ?: '&nbsp;', 'label' => ctrans('texts.taxes')];

        $data['$user.name'] = ['value' => $this->entity->user->present()->name(), 'label' => ctrans('texts.name')];
        $data['$user.first_name'] = ['value' => $this->entity->user->first_name, 'label' => ctrans('texts.first_name')];
        $data['$user.last_name'] = ['value' => $this->entity->user->last_name, 'label' => ctrans('texts.last_name')];
        $data['$created_by_user'] = &$data['$user.name'];
        $data['$assigned_to_user'] = ['value' => $this->entity->assigned_user ? $this->entity->assigned_user->present()->name() : '', 'label' => ctrans('texts.name')];

        $data['$public_notes'] = ['value' => $this->entity->public_notes, 'label' => ctrans("texts.public_notes")];
        $data['$entity.public_notes'] = &$data['$public_notes'];
        $data['$notes'] = &$data['$public_notes'];

        $data['$purchase_order.custom1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice1', $this->entity->custom_value1, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice1')];
        $data['$purchase_order.custom2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice2', $this->entity->custom_value2, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice2')];
        $data['$purchase_order.custom3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice3', $this->entity->custom_value3, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice3')];
        $data['$purchase_order.custom4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'invoice4', $this->entity->custom_value4, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'invoice4')];

        $data['$custom1'] = &$data['$purchase_order.custom1'];
        $data['$custom2'] = &$data['$purchase_order.custom2'];
        $data['$custom3'] = &$data['$purchase_order.custom3'];
        $data['$custom4'] = &$data['$purchase_order.custom4'];

        $data['$vendor1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'vendor1', $this->vendor->custom_value1, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'vendor1')];
        $data['$vendor2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'vendor2', $this->vendor->custom_value2, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'vendor2')];
        $data['$vendor3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'vendor3', $this->vendor->custom_value3, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'vendor3')];
        $data['$vendor4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'vendor4', $this->vendor->custom_value4, $this->company) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'vendor4')];
        $data['$vendor.custom1'] = &$data['$vendor1'];
        $data['$vendor.custom2'] = &$data['$vendor2'];
        $data['$vendor.custom3'] = &$data['$vendor3'];
        $data['$vendor.custom4'] = &$data['$vendor4'];
        $data['$address1'] = ['value' => $this->vendor->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$address2'] = ['value' => $this->vendor->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$id_number'] = ['value' => $this->vendor->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$vendor.number'] = ['value' => $this->vendor->number ?: '&nbsp;', 'label' => ctrans('texts.number')];
        $data['$vat_number'] = ['value' => $this->vendor->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$website'] = ['value' => $this->vendor->present()->website() ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$phone'] = ['value' => $this->vendor->present()->phone() ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$country'] = ['value' => isset($this->vendor->country->name) ? ctrans('texts.country_' . $this->vendor->country->name) : '', 'label' => ctrans('texts.country')];
        $data['$country_2'] = ['value' => isset($this->vendor->country) ? $this->vendor->country->iso_3166_2 : '', 'label' => ctrans('texts.country')];
        $data['$email'] = ['value' => isset($this->contact) ? $this->contact->email : 'no contact email on record', 'label' => ctrans('texts.email')];
        
        if (str_contains($data['$email']['value'], 'example.com')) {
            $data['$email'] = ['value' => '', 'label' => ctrans('texts.email')];
        }

        $data['$vendor_name'] = ['value' => $this->vendor->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.vendor_name')];
        $data['$vendor.name'] = &$data['$vendor_name'];
        $data['$vendor'] = &$data['$vendor_name'];

        $data['$vendor.address1'] = &$data['$address1'];
        $data['$vendor.address2'] = &$data['$address2'];
        $data['$vendor_address'] = ['value' => $this->vendor->present()->address() ?: '&nbsp;', 'label' => ctrans('texts.address')];
        $data['$vendor.address'] = &$data['$vendor_address'];
        $data['$vendor.postal_code'] = ['value' => $this->vendor->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        $data['$vendor.public_notes'] = ['value' => $this->vendor->public_notes ?: '&nbsp;', 'label' => ctrans('texts.notes')];
        $data['$vendor.city'] = ['value' => $this->vendor->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$vendor.state'] = ['value' => $this->vendor->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$vendor.id_number'] = &$data['$id_number'];
        $data['$vendor.vat_number'] = &$data['$vat_number'];
        $data['$vendor.website'] = &$data['$website'];
        $data['$vendor.phone'] = &$data['$phone'];
        $data['$city_state_postal'] = ['value' => $this->vendor->present()->cityStateZip($this->vendor->city, $this->vendor->state, $this->vendor->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$vendor.city_state_postal'] = &$data['$city_state_postal'];
        $data['$postal_city_state'] = ['value' => $this->vendor->present()->cityStateZip($this->vendor->city, $this->vendor->state, $this->vendor->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$vendor.postal_city_state'] = &$data['$postal_city_state'];
        $data['$postal_city'] = ['value' => $this->vendor->present()->cityStateZip($this->vendor->city, null, $this->vendor->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city')];
        $data['$vendor.postal_city'] = &$data['$postal_city'];
        $data['$vendor.country'] = &$data['$country'];
        $data['$vendor.email'] = &$data['$email'];
        
        $data['$vendor.billing_address'] = &$data['$vendor_address'];
        $data['$vendor.billing_address1'] = &$data['$vendor.address1'];
        $data['$vendor.billing_address2'] = &$data['$vendor.address2'];
        $data['$vendor.billing_city'] = &$data['$vendor.city'];
        $data['$vendor.billing_state'] = &$data['$vendor.state'];
        $data['$vendor.billing_postal_code'] = &$data['$vendor.postal_code'];
        $data['$vendor.billing_country'] = &$data['$vendor.country'];

        $data['$vendor.currency'] = ['value' => $this->vendor->currency()->code, 'label' => ''];

        $data['$paid_to_date'] = ['value' => Number::formatMoney($this->entity->paid_to_date, $this->vendor), 'label' => ctrans('texts.paid_to_date')];

        $data['$contact.full_name'] = ['value' => $this->contact->present()->name(), 'label' => ctrans('texts.name')];
        $data['$contact'] = &$data['$contact.full_name'];

        $data['$contact.email'] = &$data['$email'];
        $data['$contact.phone'] = ['value' => $this->contact->phone, 'label' => ctrans('texts.phone')];

        $data['$contact.name'] = ['value' => isset($this->contact) ? $this->contact->present()->name() : $this->vendor->present()->name(), 'label' => ctrans('texts.contact_name')];
        $data['$contact.first_name'] = ['value' => isset($this->contact) ? $this->contact->first_name : '', 'label' => ctrans('texts.first_name')];
        $data['$firstName'] = &$data['$contact.first_name'];

        $data['$contact.last_name'] = ['value' => isset($this->contact) ? $this->contact->last_name : '', 'label' => ctrans('texts.last_name')];

        $data['$contact.custom1'] = ['value' => isset($this->contact) ? $this->contact->custom_value1 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact1')];
        $data['$contact.custom2'] = ['value' => isset($this->contact) ? $this->contact->custom_value2 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact2')];
        $data['$contact.custom3'] = ['value' => isset($this->contact) ? $this->contact->custom_value3 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact3')];
        $data['$contact.custom4'] = ['value' => isset($this->contact) ? $this->contact->custom_value4 : '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'contact4')];

        $data['$company.city_state_postal'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
        $data['$company.postal_city_state'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, $this->settings->state, $this->settings->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
        $data['$company.postal_city'] = ['value' => $this->company->present()->cityStateZip($this->settings->city, null, $this->settings->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city')];
        $data['$company.name'] = ['value' => $this->settings->name ?: ctrans('texts.untitled_account'), 'label' => ctrans('texts.company_name')];
        $data['$account'] = &$data['$company.name'];

        $data['$company.address1'] = ['value' => $this->settings->address1 ?: '&nbsp;', 'label' => ctrans('texts.address1')];
        $data['$company.address2'] = ['value' => $this->settings->address2 ?: '&nbsp;', 'label' => ctrans('texts.address2')];
        $data['$company.city'] = ['value' => $this->settings->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
        $data['$company.state'] = ['value' => $this->settings->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
        $data['$company.postal_code'] = ['value' => $this->settings->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
        $data['$company.country'] = ['value' => $this->getCountryName(), 'label' => ctrans('texts.country')];
        $data['$company.country_2'] = ['value' => $this->getCountryCode(), 'label' => ctrans('texts.country')];
        $data['$company.phone'] = ['value' => $this->settings->phone ?: '&nbsp;', 'label' => ctrans('texts.phone')];
        $data['$company.email'] = ['value' => $this->settings->email ?: '&nbsp;', 'label' => ctrans('texts.email')];
        $data['$company.vat_number'] = ['value' => $this->settings->vat_number ?: '&nbsp;', 'label' => ctrans('texts.vat_number')];
        $data['$company.id_number'] = ['value' => $this->settings->id_number ?: '&nbsp;', 'label' => ctrans('texts.id_number')];
        $data['$company.website'] = ['value' => $this->settings->website ?: '&nbsp;', 'label' => ctrans('texts.website')];
        $data['$company.address'] = ['value' => $this->company->present()->address($this->settings) ?: '&nbsp;', 'label' => ctrans('texts.address')];

        $data['$signature'] = ['value' => $this->settings->email_signature ?: '&nbsp;', 'label' => ''];
        $data['$emailSignature'] = &$data['$signature'];

        $logo = $this->company->present()->logo_base64($this->settings);

        $data['$company.logo'] = ['value' => $logo ?: '&nbsp;', 'label' => ctrans('texts.logo')];
        $data['$company_logo'] = &$data['$company.logo'];
        $data['$company1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company1', $this->settings->custom_value1, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company1')];
        $data['$company2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company2', $this->settings->custom_value2, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company2')];
        $data['$company3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company3', $this->settings->custom_value3, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company3')];
        $data['$company4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'company4', $this->settings->custom_value4, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'company4')];

        $data['$company.custom1'] = &$data['$company1'];
        $data['$company.custom2'] = &$data['$company2'];
        $data['$company.custom3'] = &$data['$company3'];
        $data['$company.custom4'] = &$data['$company4'];

        $data['$custom_surcharge1'] = ['value' => Number::formatMoney($this->entity->custom_surcharge1, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge1')];
        $data['$custom_surcharge2'] = ['value' => Number::formatMoney($this->entity->custom_surcharge2, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge2')];
        $data['$custom_surcharge3'] = ['value' => Number::formatMoney($this->entity->custom_surcharge3, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge3')];
        $data['$custom_surcharge4'] = ['value' => Number::formatMoney($this->entity->custom_surcharge4, $this->vendor) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'surcharge4')];

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

        if ($this->settings->signature_on_pdf) {
            $data['$contact.signature'] = ['value' => $this->invitation->signature_base64, 'label' => ctrans('texts.signature')];
        } else {
            $data['$contact.signature'] = ['value' => '', 'label' => ''];
        }

        $data['$thanks'] = ['value' => '', 'label' => ctrans('texts.thanks')];
        $data['$from'] = ['value' => '', 'label' => ctrans('texts.from')];
        $data['$to'] = ['value' => '', 'label' => ctrans('texts.to')];
        $data['$details'] = ['value' => '', 'label' => ctrans('texts.details')];

        $data['_rate1'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['_rate2'] = ['value' => '', 'label' => ctrans('texts.tax')];
        $data['_rate3'] = ['value' => '', 'label' => ctrans('texts.tax')];

        $data['$font_size'] = ['value' => $this->settings->font_size . 'px', 'label' => ''];
        $data['$font_name'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['name'], 'label' => ''];
        $data['$font_url'] = ['value' => Helpers::resolveFont($this->settings->primary_font)['url'], 'label' => ''];

        $data['$invoiceninja.whitelabel'] = ['value' => 'https://i.imgur.com/HDfTPYV.png', 'label' => ''];

        $data['$primary_color'] = ['value' => $this->settings->primary_color, 'label' => ''];
        $data['$secondary_color'] = ['value' => $this->settings->secondary_color, 'label' => ''];

        $data['$item'] = ['value' => '', 'label' => ctrans('texts.item')];
        $data['$description'] = ['value' => '', 'label' => ctrans('texts.description')];

        $data['$entity_footer'] = ['value' => Helpers::processReservedKeywords(\nl2br($this->entity->footer), $this->company), 'label' => ''];
        $data['$footer'] = &$data['$entity_footer'];
        
        $data['$page_size'] = ['value' => $this->settings->page_size, 'label' => ''];
        $data['$page_layout'] = ['value' => property_exists($this->settings, 'page_layout') ? $this->settings->page_layout : 'Portrait', 'label' => ''];

        $data['$tech_hero_image'] = ['value' => asset('images/pdf-designs/tech-hero-image.jpg'), 'label' => ''];
        $data['$autoBill'] = ['value' => ctrans('texts.auto_bill_notification_placeholder'), 'label' => ''];
        $data['$auto_bill'] = &$data['$autoBill'];

        $data['$dir'] = ['value' => in_array(optional($this->company->language())->locale, ['ar', 'he']) ? 'rtl' : 'ltr', 'label' => ''];
        $data['$dir_text_align'] = ['value' => in_array(optional($this->company->language())->locale, ['ar', 'he']) ? 'right' : 'left', 'label' => ''];


        $data['$payment.date'] = ['value' => '&nbsp;', 'label' => ctrans('texts.payment_date')];
        $data['$method'] = ['value' => '&nbsp;', 'label' => ctrans('texts.method')];

        $data['$statement_amount'] = ['value' => '', 'label' => ctrans('texts.amount')];
        $data['$statement'] = ['value' => '', 'label' => ctrans('texts.statement')];

        $data['$entity_images'] = ['value' => $this->generateEntityImagesMarkup(), 'label' => ''];

        $data['$payments'] = ['value' => '', 'label' => ctrans('texts.payments')];

        if ($this->entity->client()->exists()) {
            $data['$client1'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client1', $this->entity->client->custom_value1, $this->entity->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client1')];
            $data['$client2'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client2', $this->entity->client->custom_value2, $this->entity->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client2')];
            $data['$client3'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client3', $this->entity->client->custom_value3, $this->entity->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client3')];
            $data['$client4'] = ['value' => $this->helpers->formatCustomFieldValue($this->company->custom_fields, 'client4', $this->entity->client->custom_value4, $this->entity->client) ?: '&nbsp;', 'label' => $this->helpers->makeCustomField($this->company->custom_fields, 'client4')];
            $data['$client.custom1'] = &$data['$client1'];
            $data['$client.custom2'] = &$data['$client2'];
            $data['$client.custom3'] = &$data['$client3'];
            $data['$client.custom4'] = &$data['$client4'];
            $data['$client.number'] = ['value' => $this->entity->client->number ?: '&nbsp;', 'label' => ctrans('texts.number')];

            $data['$client_name'] = ['value' => $this->entity->client->present()->name() ?: '&nbsp;', 'label' => ctrans('texts.client_name')];
            $data['$client.name'] = &$data['$client_name'];
            $data['$client'] = &$data['$client_name'];

            $data['$client.address1'] = &$data['$address1'];
            $data['$client.address2'] = &$data['$address2'];
            $data['$client_address'] = ['value' => $this->entity->client->present()->address() ?: '&nbsp;', 'label' => ctrans('texts.address')];
            $data['$client.address'] = &$data['$client_address'];
            $data['$client.postal_code'] = ['value' => $this->entity->client->postal_code ?: '&nbsp;', 'label' => ctrans('texts.postal_code')];
            $data['$client.public_notes'] = ['value' => $this->entity->client->public_notes ?: '&nbsp;', 'label' => ctrans('texts.notes')];
            $data['$client.city'] = ['value' => $this->entity->client->city ?: '&nbsp;', 'label' => ctrans('texts.city')];
            $data['$client.state'] = ['value' => $this->entity->client->state ?: '&nbsp;', 'label' => ctrans('texts.state')];
            $data['$client.id_number'] = &$data['$id_number'];
            $data['$client.vat_number'] = &$data['$vat_number'];
            $data['$client.website'] = &$data['$website'];
            $data['$client.phone'] = &$data['$phone'];
            $data['$city_state_postal'] = ['value' => $this->entity->client->present()->cityStateZip($this->entity->client->city, $this->entity->client->state, $this->entity->client->postal_code, false) ?: '&nbsp;', 'label' => ctrans('texts.city_state_postal')];
            $data['$client.city_state_postal'] = &$data['$city_state_postal'];
            $data['$postal_city_state'] = ['value' => $this->entity->client->present()->cityStateZip($this->entity->client->city, $this->entity->client->state, $this->entity->client->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city_state')];
            $data['$client.postal_city_state'] = &$data['$postal_city_state'];
            $data['$postal_city'] = ['value' => $this->entity->client->present()->cityStateZip($this->entity->client->city, null, $this->entity->client->postal_code, true) ?: '&nbsp;', 'label' => ctrans('texts.postal_city')];
            $data['$client.postal_city'] = &$data['$postal_city'];
            $data['$client.country'] = &$data['$country'];
            $data['$client.email'] = &$data['$email'];
            
            $data['$client.billing_address'] = &$data['$client_address'];
            $data['$client.billing_address1'] = &$data['$client.address1'];
            $data['$client.billing_address2'] = &$data['$client.address2'];
            $data['$client.billing_city'] = &$data['$client.city'];
            $data['$client.billing_state'] = &$data['$client.state'];
            $data['$client.billing_postal_code'] = &$data['$client.postal_code'];
            $data['$client.billing_country'] = &$data['$client.country'];

            $data['$client.shipping_address'] = ['value' => $this->entity->client->present()->shipping_address() ?: '&nbsp;', 'label' => ctrans('texts.shipping_address')];
            $data['$client.shipping_address1'] = ['value' => $this->entity->client->shipping_address1 ?: '&nbsp;', 'label' => ctrans('texts.shipping_address1')];
            $data['$client.shipping_address2'] = ['value' => $this->entity->client->shipping_address2 ?: '&nbsp;', 'label' => ctrans('texts.shipping_address2')];
            $data['$client.shipping_city'] = ['value' => $this->entity->client->shipping_city ?: '&nbsp;', 'label' => ctrans('texts.shipping_city')];
            $data['$client.shipping_state'] = ['value' => $this->entity->client->shipping_state ?: '&nbsp;', 'label' => ctrans('texts.shipping_state')];
            $data['$client.shipping_postal_code'] = ['value' => $this->entity->client->shipping_postal_code ?: '&nbsp;', 'label' => ctrans('texts.shipping_postal_code')];
            $data['$client.shipping_country'] = ['value' => isset($this->entity->client->shipping_country->name) ? ctrans('texts.country_' . $this->entity->client->shipping_country->name) : '', 'label' => ctrans('texts.shipping_country')];

            $data['$client.currency'] = ['value' => $this->entity->client->currency()->code, 'label' => ''];

            $data['$client.lang_2'] = ['value' => optional($this->entity->client->language())->locale, 'label' => ''];

            $data['$client.balance'] = ['value' => Number::formatMoney($this->entity->client->balance, $this->entity->client), 'label' => ctrans('texts.account_balance')];
            $data['$client_balance'] = ['value' => Number::formatMoney($this->entity->client->balance, $this->entity->client), 'label' => ctrans('texts.account_balance')];
        }


        $arrKeysLength = array_map('strlen', array_keys($data));
        array_multisort($arrKeysLength, SORT_DESC, $data);

        return $data;
    }

    public function makeValues() :array
    {
        $data = [];

        $values = $this->buildEntityDataArray();

        foreach ($values as $key => $value) {
            $data[$key] = $value['value'];
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

    private function totalTaxLabels() :string
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

    private function totalTaxValues() :string
    {
        $data = '';

        if (! $this->entity_calc->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $this->vendor).'</span>';
        }

        return $data;
    }

    private function lineTaxLabels() :string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.$tax['name'].'</span>';
        }

        return $data;
    }

    private function getCountryName() :string
    {
        $countries = Cache::get('countries');

        if (! $countries) {
            $this->buildCache(true);

            $countries = Cache::get('countries');
        }

        if ($countries) {
            $country = $countries->filter(function ($item) {
                return $item->id == $this->settings->country_id;
            })->first();
        } else {
            $country = Country::find($this->settings->country_id);
        }

        if ($country) {
            return ctrans('texts.country_' . $country->name);
        }

        return '&nbsp;';
    }


    private function getCountryCode() :string
    {
        $country = Country::find($this->settings->country_id);

        if ($country) {
            return $country->iso_3166_2;
        }
        // if ($country) {
        //     return ctrans('texts.country_' . $country->iso_3166_2);
        // }

        return '&nbsp;';
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

     /*
    private function makeLineTaxes() :string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<tr class="line_taxes">';
            $data .= '<td>'.$tax['name'].'</td>';
            $data .= '<td>'.Number::formatMoney($tax['total'], $this->company).'</td></tr>';
        }

        return $data;
    }


    private function makeTotalTaxes() :string
    {
        $data = '';

        if (! $this->entity_calc->getTotalTaxMap()) {
            return $data;
        }

        foreach ($this->entity_calc->getTotalTaxMap() as $tax) {
            $data .= '<tr>';
            $data .= '<td colspan="{ count($this->entity->company->settings->pdf_variables->total_columns) - 2 }"></td>';
            $data .= '<td>'.$tax['name'].'</td>';
            $data .= '<td>'.Number::formatMoney($tax['total'], $this->company).'</td></tr>';
        }

        return $data;
    }

    private function parseLabelsAndValues($labels, $values, $section) :string
    {
        $section = strtr($section, $labels);

        return strtr($section, $values);
    }
        */

    /**
     * Builds CSS to assist with the generation
     * of Repeating headers and footers on the PDF.
     * @return string The css string
     
    private function generateCustomCSS() :string
    {
        $header_and_footer = '
.header, .header-space {
  height: 160px;
}

.footer, .footer-space {
  height: 160px;
}

.footer {
  position: fixed;
  bottom: 0;
  width: 100%;
}

.header {
  position: fixed;
  top: 0mm;
  width: 100%;
}

@media print {
   thead {display: table-header-group;}
   tfoot {display: table-footer-group;}
   button {display: none;}
   body {margin: 0;}
}';

        $header = '
.header, .header-space {
  height: 160px;
}

.header {
  position: fixed;
  top: 0mm;
  width: 100%;
}

@media print {
   thead {display: table-header-group;}
   button {display: none;}
   body {margin: 0;}
}';

        $footer = '

.footer, .footer-space {
  height: 160px;
}

.footer {
  position: fixed;
  bottom: 0;
  width: 100%;
}

@media print {
   tfoot {display: table-footer-group;}
   button {display: none;}
   body {margin: 0;}
}';
        $css = '';

        if ($this->settings->all_pages_header && $this->settings->all_pages_footer) {
            $css .= $header_and_footer;
        } elseif ($this->settings->all_pages_header && ! $this->settings->all_pages_footer) {
            $css .= $header;
        } elseif (! $this->settings->all_pages_header && $this->settings->all_pages_footer) {
            $css .= $footer;
        }

        $css .= '
.page {
  page-break-after: always;
}

@page {
  margin: 0mm
}

html {
        ';

        $css .= 'font-size:'.$this->settings->font_size.'px;';
        $css .= '}';

        return $css;
    }
*/

    private function lineTaxValues() :string
    {
        $tax_map = $this->entity_calc->getTaxMap();

        $data = '';

        foreach ($tax_map as $tax) {
            $data .= '<span>'.Number::formatMoney($tax['total'], $this->company).'</span>';
        }

        return $data;
    }

    /*
    | Ensures the URL doesn't have duplicated trailing slash
    */
    public function generateAppUrl()
    {
        //return rtrim(config('ninja.app_url'), "/");
        return config('ninja.app_url');
    }



    /**
     * Generate markup for HTML images on entity.
     *
     * @return string|void
     */
    protected function generateEntityImagesMarkup()
    {
        if ($this->company->getSetting('embed_documents') === false) {
            return '';
        }

        $dom = new \DOMDocument('1.0', 'UTF-8');

        $container =  $dom->createElement('div');
        $container->setAttribute('style', 'display:grid; grid-auto-flow: row; grid-template-columns: repeat(4, 1fr); grid-template-rows: repeat(2, 1fr);');

        foreach ($this->entity->documents as $document) {
            if (!$document->isImage()) {
                continue;
            }

            $image = $dom->createElement('img');

            $image->setAttribute('src', $document->generateUrl());
            $image->setAttribute('style', 'max-height: 100px; margin-top: 20px;');

            $container->appendChild($image);
        }

        $dom->appendChild($container);

        return $dom->saveHTML();
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

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

use App\DataMapper\EmailTemplateDefaults;
use App\Mail\Engine\PaymentEmailEngine;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\MakesInvoiceHtml;
use App\Utils\Traits\MakesTemplateData;
use DB;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use League\CommonMark\CommonMarkConverter;

class TemplateEngine
{
    use MakesHash;
    use MakesTemplateData;
    use MakesInvoiceHtml;

    public $body;

    public $subject;

    public $entity;

    public $entity_id;

    public $template;

    /** @var \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder | \App\Models\RecurringInvoice | \App\Models\Payment $entity_obj **/
    private \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder | \App\Models\RecurringInvoice | \App\Models\Payment $entity_obj;

    /** @var \App\Models\Company | \App\Models\Client | null $settings_entity **/
    private $settings_entity;

    private $settings;

    private $raw_body;

    private $raw_subject;

    /**
     * @var array
     */
    private $labels_and_values;

    public function __construct($body, $subject, $entity, $entity_id, $template)
    {
        $this->body = $body;

        $this->subject = $subject;

        $this->entity = $entity;

        $this->entity_id = $entity_id;

        $this->template = $template;

        // $this->entity_obj = null;

        $this->settings_entity = null;
    }

    public function build()
    {
        return $this->setEntity()
            ->setSettingsObject()
            ->setTemplates()
            ->replaceValues()
            ->renderTemplate();
    }

    private function setEntity()
    {
        if (strlen($this->entity) > 1 && strlen($this->entity_id) > 1) {
            $class = 'App\Models\\' . ucfirst(Str::camel($this->entity));
            $this->entity_obj = $class::query()->withTrashed()->where('id', $this->decodePrimaryKey($this->entity_id))->company()->first();
        } elseif (stripos($this->template, 'quote') !== false && $quote = Quote::query()->whereHas('invitations')->withTrashed()->company()->first()) {
            $this->entity = 'quote';
            $this->entity_obj = $quote;
        } elseif (stripos($this->template, 'purchase') !== false && $purchase_order = PurchaseOrder::query()->whereHas('invitations')->withTrashed()->company()->first()) {
            $this->entity = 'purchase_order';
            $this->entity_obj = $purchase_order;
        } elseif (stripos($this->template, 'payment') !== false && $payment = Payment::query()->withTrashed()->company()->first()) {
            $this->entity = 'payment';
            $this->entity_obj = $payment;
        } elseif ($invoice = Invoice::query()->whereHas('invitations')->withTrashed()->company()->first()) {
            /** @var \App\Models\Invoice $invoice */
            $this->entity_obj = $invoice;
        } else {
            $this->mockEntity();
        }

        return $this;
    }

    private function setSettingsObject()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        if ($this->entity == 'purchaseOrder' || $this->entity == 'purchase_order') {
            $this->settings_entity = $user->company();
            $this->settings = $this->settings_entity->settings;
        } elseif ($this->entity_obj->client()->exists()) {
            $this->settings_entity = $this->entity_obj->client;
            $this->settings = $this->settings_entity->getMergedSettings();
        } else {
            $this->settings_entity = $user->company();
            $this->settings = $this->settings_entity->settings;
        }

        App::forgetInstance('translator');
        $t = app('translator');
        $t->replace(Ninja::transformTranslations($this->settings));

        return $this;
    }

    /* If the body / subject are not populated we need to get the defaults */
    private function setTemplates()
    {
        if (strlen($this->subject) == 0 && strlen($this->template) > 1) {
            $subject_template = str_replace('template', 'subject', $this->template);

            if (strlen($this->settings_entity->getSetting($subject_template)) > 1) {
                $this->subject = $this->settings_entity->getSetting($subject_template);
            } else {
                $this->subject = EmailTemplateDefaults::getDefaultTemplate($subject_template, $this->settings_entity->locale());
            }
        }

        if (strlen($this->body) == 0 && strlen($this->template) > 1) {
            if (strlen($this->settings_entity->getSetting($this->template)) > 1) {
                $this->body = $this->settings_entity->getSetting($this->template);
            } else {
                $this->body = EmailTemplateDefaults::getDefaultTemplate($this->template, $this->settings_entity->locale());
            }
        }

        return $this;
    }

    private function replaceValues()
    {
        $this->raw_body = $this->body;
        $this->raw_subject = $this->subject;

        if ($this->entity_obj->client()->exists()) {
            $this->entityValues($this->entity_obj->client->primary_contact()->first());
        } elseif ($this->entity_obj->vendor()->exists()) {
            $this->entityValues($this->entity_obj->vendor->primary_contact()->first());
        } else {
            $this->fakerValues();
        }

        return $this;
    }

    private function fakerValues()
    {
        $labels = $this->makeFakerLabels();
        $values = $this->makeFakerValues();

        $this->body = strtr($this->body, $labels);
        $this->body = strtr($this->body, $values);

        $this->subject = strtr($this->subject, $labels);
        $this->subject = strtr($this->subject, $values);

        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        $this->body = $converter->convert($this->body)->getContent();
    }

    private function entityValues($contact)
    {
        if (in_array($this->entity, ['purchaseOrder', 'purchase_order'])) {
            $this->labels_and_values = (new VendorHtmlEngine($this->entity_obj->invitations->first()))->generateLabelsAndValues();
        } elseif ($this->entity == 'payment') {
            $this->labels_and_values = (new PaymentEmailEngine($this->entity_obj, $this->entity_obj->client->contacts->first()))->generateLabelsAndValues();
        } else {
            $this->labels_and_values = (new HtmlEngine($this->entity_obj->invitations->first()))->generateLabelsAndValues();
        }


        $this->body = strtr($this->body, $this->labels_and_values['labels']);
        $this->body = strtr($this->body, $this->labels_and_values['values']);

        $this->subject = strtr($this->subject, $this->labels_and_values['labels']);
        $this->subject = strtr($this->subject, $this->labels_and_values['values']);

        $email_style = $this->settings_entity->getSetting('email_style');

        if ($email_style !== 'custom') {
            $this->body = DesignHelpers::parseMarkdownToHtml($this->body);
        }
    }

    private function renderTemplate()
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        /* wrapper */
        $email_style = $this->settings_entity->getSetting('email_style');

        $data['title'] = '';
        $data['body'] = '$body';
        $data['footer'] = '';
        $data['logo'] = $user->company()->present()->logo();

        if ($this->entity_obj->client()->exists()) {
            $data = array_merge($data, Helpers::sharedEmailVariables($this->entity_obj->client));
        } else {
            $data['signature'] = $this->settings->email_signature;
            $data['settings'] = $this->settings;
            // $data['whitelabel'] = $this->entity_obj ? $this->entity_obj->company->account->isPaid() : true;
            // $data['company'] = $this->entity_obj ? $this->entity_obj->company : '';
            $data['whitelabel'] = $this->entity_obj->company->account->isPaid();
            $data['company'] = $this->entity_obj->company;
            $data['settings'] = $this->settings;
        }


        if ($email_style == 'custom') {
            $wrapper = $this->settings_entity->getSetting('email_style_custom');

            // In order to parse variables such as $signature in the body,
            // we need to replace strings with the values from HTMLEngine.
            $wrapper = strtr($wrapper, $this->labels_and_values['values']);

            /*If no custom design exists, send back a blank!*/
            if (strlen($wrapper) > 1) {
                // $wrapper = $this->renderView($wrapper, $data);
            } else {
                $wrapper = '';
            }
        } elseif ($email_style == 'plain') {
            $wrapper = view($this->getTemplatePath($email_style), $data)->render();
            $injection = '';
            $wrapper = str_replace('<head>', $injection, $wrapper);
        } else {
            $wrapper = view($this->getTemplatePath('client'), $data)->render();
            $injection = '';
            $wrapper = str_replace('<head>', $injection, $wrapper);
        }

        $data = [
            'subject' => $this->subject,
            'body' => $this->body,
            'wrapper' => $wrapper,
            'raw_body' => $this->raw_body,
            'raw_subject' => $this->raw_subject,
        ];

        $this->tearDown();

        return $data;
    }

    private function mockEntity()
    {
        $invitation = false;

        if (!$this->entity && $this->template && str_contains($this->template, 'purchase_order')) {
            $this->entity = 'purchaseOrder';
        } elseif (str_contains($this->template, 'payment')) {
            $this->entity = 'payment';
        }

        try {
            DB::connection(config('database.default'))->beginTransaction();

            /** @var \App\Models\User $user */
            $user = auth()->user();

            $vendor = false;
            /** @var \App\Models\Client $client */
            $client = Client::factory()->create([
                'user_id' => $user->id,
                'company_id' => $user->company()->id,
            ]);

            /** @var \App\Models\ClientContact $contact */
            $contact = ClientContact::factory()->create([
                'user_id' => $user->id,
                'company_id' => $user->company()->id,
                'client_id' => $client->id,
                'is_primary' => 1,
                'send_email' => true,
            ]);

            if ($this->entity == 'payment') {
                /** @var \App\Models\Payment $payment */
                $payment = Payment::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'client_id' => $client->id,
                    'amount' => 10,
                    'applied' => 10,
                    'refunded' => 5,
                ]);

                $this->entity_obj = $payment;

                /** @var \App\Models\Invoice $invoice */
                $invoice = Invoice::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'client_id' => $client->id,
                    'amount' => 10,
                    'balance' => 10,
                    'number' => rand(1, 10000)
                ]);

                /** @var \App\Models\InvoiceInvitation $invitation */
                $invitation = InvoiceInvitation::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'invoice_id' => $invoice->id,
                    'client_contact_id' => $contact->id,
                ]);

                /** @var \App\Models\Invoice $invoice */
                $this->entity_obj->invoices()->attach($invoice->id, [
                    'amount' => 10,
                ]);
            }

            if (!$this->entity || $this->entity == 'invoice') {
                /** @var \App\Models\Invoice $invoice */
                $invoice = Invoice::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'client_id' => $client->id,
                    'amount' => '10',
                    'balance' => '10',
                ]);

                $this->entity_obj = $invoice;

                $invitation = InvoiceInvitation::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'invoice_id' => $this->entity_obj->id,
                    'client_contact_id' => $contact->id,
                ]);
            }

            if ($this->entity == 'quote') {
                /** @var \App\Models\Quote $quote */
                $quote = Quote::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'client_id' => $client->id,
                ]);

                $this->entity_obj = $quote;

                $invitation = QuoteInvitation::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'quote_id' => $this->entity_obj->id,
                    'client_contact_id' => $contact->id,
                ]);
            }

            if ($this->entity == 'purchaseOrder') {
                /** @var \App\Models\Vendor $vendor **/
                $vendor = Vendor::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                ]);

                /** @var \App\Models\VendorContact $contact **/
                $contact = VendorContact::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'vendor_id' => $vendor->id,
                    'is_primary' => 1,
                    'send_email' => true,
                ]);

                /** @var \App\Models\PurchaseOrder $purchase_order **/
                $purchase_order = PurchaseOrder::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'vendor_id' => $vendor->id,
                ]);

                $this->entity_obj = $purchase_order;

                /** @var \App\Models\PurchaseOrderInvitation $invitation **/
                $invitation = PurchaseOrderInvitation::factory()->create([
                    'user_id' => $user->id,
                    'company_id' => $user->company()->id,
                    'purchase_order_id' => $this->entity_obj->id,
                    'vendor_contact_id' => $contact->id,
                ]);
            }

            if ($vendor) {
                $this->entity_obj->setRelation('invitations', $invitation);
                $this->entity_obj->setRelation('vendor', $vendor);
                $this->entity_obj->setRelation('company', $user->company());
                $this->entity_obj->load('vendor');
                $vendor->setRelation('company', $user->company());
                $vendor->load('company');
            } else {
                $this->entity_obj->setRelation('invitations', $invitation);
                $this->entity_obj->setRelation('client', $client);
                $this->entity_obj->setRelation('company', $user->company());
                $this->entity_obj->load('client');
                $client->setRelation('company', $user->company());
                $client->load('company');
            }

        } catch (\Throwable $th) {
            nlog("Throwable:: transaction:: TemplateEngine MockEntity");

            DB::connection(config('database.default'))->rollBack();

        }

    }

    private function tearDown()
    {
        DB::connection(config('database.default'))->rollBack();
    }
}

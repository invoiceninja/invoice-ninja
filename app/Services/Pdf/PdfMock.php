<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2022. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace App\Services\Pdf;

use App\DataMapper\ClientSettings;
use App\DataMapper\CompanySettings;
use App\Models\Client;
use App\Models\Company;
use App\Models\Country;
use App\Models\Credit;
use App\Models\CreditInvitation;
use App\Models\Currency;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\InvoiceInvitation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderInvitation;
use App\Models\Quote;
use App\Models\QuoteInvitation;
use App\Models\Vendor;
use App\Utils\Traits\GeneratesCounter;
use App\Utils\Traits\MakesHash;

class PdfMock
{
    use MakesHash;
    use GeneratesCounter;

    private mixed $mock;

    public object $settings;

    private string $entity_string = 'invoice';

    public function __construct(public array $request, public Company $company)
    {
    }

    public function getPdf(): mixed
    {
        //need to resolve the pdf type here, ie product / purchase order
        $document_type = $this->request['entity_type'] == 'purchase_order' ? 'purchase_order' : 'product';

        $pdf_service = new PdfService($this->mock->invitation, $document_type);

        $pdf_config = (new PdfConfiguration($pdf_service));
        $pdf_config->entity = $this->mock;
        $pdf_config->entity_string = $this->request['entity_type'];
        $this->entity_string = $this->request['entity_type'];
        $pdf_config->setTaxMap($this->mock->tax_map);
        $pdf_config->setTotalTaxMap($this->mock->total_tax_map);
        $pdf_config->client = $this->mock->client;
        $pdf_config->settings_object = $this->mock->client;
        $pdf_config->settings = $this->getMergedSettings();
        $this->settings = $pdf_config->settings;
        $pdf_config->entity_design_id = $pdf_config->settings->{"{$pdf_config->entity_string}_design_id"};
        $pdf_config->setPdfVariables();
        $pdf_config->setCurrency(Currency::find($this->settings->currency_id));
        $pdf_config->setCountry(Country::find($this->settings->country_id ?: 840));
        $pdf_config->currency_entity = $this->mock->client ?? $this->mock->vendor;

        if (isset($this->request['design_id']) && $design  = Design::withTrashed()->find($this->request['design_id'])) {
            $pdf_config->design = $design;
            $pdf_config->entity_design_id = $design->hashed_id;
        } else {
            $pdf_config->design = Design::withTrashed()->find($this->decodePrimaryKey($pdf_config->entity_design_id));
        }

        $pdf_service->config = $pdf_config;

        if (isset($this->request['design'])) {
            $pdf_designer = (new PdfDesigner($pdf_service))->buildFromPartials($this->request['design']);
        } else {
            $pdf_designer = (new PdfDesigner($pdf_service))->build();
        }

        $pdf_service->designer = $pdf_designer;

        $pdf_service->html_variables = $document_type == 'purchase_order' ? $this->getVendorStubVariables() : $this->getStubVariables();

        $pdf_builder = (new PdfBuilder($pdf_service))->build();
        $pdf_service->builder = $pdf_builder;

        $html = $pdf_service->getHtml();

        return $pdf_service->resolvePdfEngine($html);
    }

    public function build(): self
    {
        $this->mock = $this->initEntity();

        return $this;
    }

    public function initEntity(): mixed
    {
        $settings = new \stdClass();
        $settings->entity = Client::class;
        $settings->currency_id = '1';
        $settings->industry_id = '';
        $settings->size_id = '';

        switch ($this->request['entity_type']) {
            case 'invoice':
                /** @var \App\Models\Invoice | \App\Models\Credit | \App\Models\Quote $entity */
                $entity = Invoice::factory()->make();
                $entity->client = Client::factory()->make(['settings' => $settings]); //@phpstan-ignore-line
                $entity->client->setRelation('company', $this->company);
                $entity->invitation = InvoiceInvitation::factory()->make(); //@phpstan-ignore-line
                break;
            case 'quote':
                /** @var \App\Models\Invoice | \App\Models\Credit | \App\Models\Quote $entity */
                $entity = Quote::factory()->make();
                $entity->client = Client::factory()->make(['settings' => $settings]); //@phpstan-ignore-line
                $entity->client->setRelation('company', $this->company);
                $entity->invitation = QuoteInvitation::factory()->make(); //@phpstan-ignore-line
                break;
            case 'credit':
                /** @var \App\Models\Invoice | \App\Models\Credit | \App\Models\Quote $entity */
                $entity = Credit::factory()->make();
                $entity->client = Client::factory()->make(['settings' => $settings]); //@phpstan-ignore-line
                $entity->client->setRelation('company', $this->company);
                $entity->invitation = CreditInvitation::factory()->make(); //@phpstan-ignore-line
                break;
            case 'purchase_order':

                /** @var \App\Models\PurchaseOrder $entity */
                $entity = PurchaseOrder::factory()->make();
                // $entity->client = Client::factory()->make(['settings' => $settings]);
                $entity->vendor = Vendor::factory()->make(); /** @phpstan-ignore-line */
                $entity->vendor->setRelation('company', $this->company);
                $entity->invitation = PurchaseOrderInvitation::factory()->make();/** @phpstan-ignore-line */

                break;
            case PurchaseOrder::class:
                /** @var \App\Models\PurchaseOrder $entity */
                $entity = PurchaseOrder::factory()->make();
                $entity->invitation = PurchaseOrderInvitation::factory()->make();
                $entity->vendor = Vendor::factory()->make(); /** @phpstan-ignore-line */

                $entity->invitation->setRelation('company', $this->company);
                break;
            default:
                $entity = false;
                break;
        }

        $entity->tax_map = $this->getTaxMap();
        $entity->total_tax_map = $this->getTotalTaxMap();
        $entity->invitation->company = $this->company;

        return $entity;
    }

    /**
     * getMergedSettings
     *
     * @return object
     */
    public function getMergedSettings(): object
    {
        $settings = $this->company->settings;

        match ($this->request['settings_type'] ?? '') {
            'group' => $settings = ClientSettings::buildClientSettings($this->company->settings, $this->request['settings']),
            'client' => $settings = ClientSettings::buildClientSettings($this->company->settings, $this->request['settings']),
            'company' => $settings = (object)$this->request['settings'],
            default => $settings = (object)$this->company->settings,
        };

        $settings = CompanySettings::setProperties($settings);

        return $settings;
    }


    /**
     * getTaxMap
     *
     * @return  \Illuminate\Support\Collection
     */
    private function getTaxMap(): \Illuminate\Support\Collection
    {
        return collect([['name' => 'GST', 'total' => 10]]);
    }

    /**
     * getTotalTaxMap
     *
     * @return array
     */
    private function getTotalTaxMap(): array
    {
        return [['name' => 'GST', 'total' => 10]];
    }

    /**
     * getStubVariables
     *
     * @return array
     */
    public function getStubVariables(): array
    {
        $entity_pattern = $this->entity_string.'_number_pattern';
        $entity_number = '0029';

        if (!empty($this->settings->{$entity_pattern})) {
            // Although $this->mock is the Invoice/etc entity,
            // we need the invitation to get company details.
            $entity_number = $this->getFormattedEntityNumber(
                $this->mock->invitation,
                (int) $entity_number,
                $this->settings->counter_padding,
                $this->settings->{$entity_pattern},
            );
        }

        return ['values' =>
         [
    '$client.shipping_postal_code' => '46420',
    '$client.billing_postal_code' => '11243',
    '$company.city_state_postal' => "{$this->settings->city}, {$this->settings->state}, {$this->settings->postal_code}",
    '$company.postal_city_state' => "{$this->settings->postal_code}, {$this->settings->city}, {$this->settings->state}",
    '$company.postal_city' => "{$this->settings->postal_code}, {$this->settings->state}",
    '$product.gross_line_total' => '100',
    '$client.classification' => 'Individual',
    '$company.classification' => 'Business',
    '$client.postal_city_state' => '11243 Aufderharchester, North Carolina',
    '$client.postal_city' => '11243 Aufderharchester, North Carolina',
    '$client.shipping_address1' => '453',
    '$client.shipping_address2' => '66327 Waters Trail',
    '$client.city_state_postal' => 'Aufderharchester, North Carolina 11243',
    '$client.shipping_address' => '453<br/>66327 Waters Trail<br/>Aufderharchester, North Carolina 11243<br/>United States<br/>',
    '$client.billing_address2' => '63993 Aiyana View',
    '$client.billing_address1' => '8447',
    '$client.shipping_country' => 'USA',
    '$invoiceninja.whitelabel' => config('ninja.app_logo'),
    '$client.billing_address' => '8447<br/>63993 Aiyana View<br/>Aufderharchester, North Carolina 11243<br/>United States<br/>',
    '$client.billing_country' => 'USA',
    '$task.gross_line_total' => '100',
    '$contact.portal_button' => '<a class="button" href="http://ninja.test:8000/client/key_login/zJJEjlUtXPiNnnnyO2tcYia64PSwauidy61eDnMU?client_hash=nzikYQITs1kyUK61GScTNW67JwhTRkOBVdvsHzIv">View client portal</a>',
    '$client.shipping_state' => 'Delaware',
    '$invoice.public_notes' => 'These are some public notes for your document',
    '$client.shipping_city' => 'Kesslerport',
    '$client.billing_state' => 'North Carolina',
    '$product.description' => 'A Product Description',
    '$product.product_key' => 'A Product Key',
    '$entity.public_notes' => 'Entity Public notes',
    '$invoice.balance_due' => '$0.00',
    '$client.public_notes' => '&nbsp;',
    '$company.postal_code' => $this->settings->postal_code,
    '$client.billing_city' => 'Aufderharchester',
    '$secondary_font_name' => isset($this->settings?->secondary_font) ? $this->settings->secondary_font : 'Roboto',
    '$secondary_font_url' => isset($this->settings?->secondary_font) ? \App\Utils\Helpers::resolveFont($this->settings->secondary_font)['url'] : 'https://fonts.googleapis.com/css2?family=Roboto&display=swap',
    '$credit.valid_until' => '2024-12-11',
    '$product.line_total' => '',
    '$product.tax_amount' => '',
    '$company.vat_number' => $this->settings->vat_number,
    '$invoice.invoice_no' => '0029',
    '$quote.quote_number' => '0029',
    '$client.postal_code' => '11243',
    '$contact.first_name' => 'Benedict',
    '$contact.signature' => '',
    '$company_logo_size' => $this->settings->company_logo_size ?: '65%',
    '$product.tax_rate1' => ctrans('texts.tax'),
    '$product.tax_rate2' => ctrans('texts.tax'),
    '$product.tax_rate3' => ctrans('texts.tax'),
    '$product.tax_name1' => '',
    '$product.tax_name2' => '',
    '$product.tax_name3' => '',
    '$product.unit_cost' => '',
    '$quote.valid_until' => '2023-10-24',
    '$custom_surcharge1' => '$0.00',
    '$custom_surcharge2' => '$0.00',
    '$custom_surcharge3' => '$0.00',
    '$custom_surcharge4' => '$0.00',
    '$quote.balance_due' => '$0.00',
    '$company.id_number' => $this->settings->id_number,
    '$invoice.po_number' => 'PO12345',
    '$invoice_total_raw' => 0.0,
    '$postal_city_state' => "{$this->settings->postal_code}, {$this->settings->city}, {$this->settings->state}",
    '$client.vat_number' => '975977515',
    '$city_state_postal' => "{$this->settings->city}, {$this->settings->state}, {$this->settings->postal_code}",
    '$contact.full_name' => 'Benedict Eichmann',
    '$contact.last_name' => 'Eichmann',
    '$company.country_2' => 'US',
    '$product.product1' => '',
    '$product.product2' => '',
    '$product.product3' => '',
    '$product.product4' => '',
    '$statement_amount' => '',
    '$task.description' => '',
    '$product.discount' => '',
    '$entity_issued_to' => 'Bob Jones',
    '$assigned_to_user' => '',
    '$product.quantity' => '',
    '$total_tax_labels' => '',
    '$total_tax_values' => '',
    '$invoice.discount' => '$5.00',
    '$invoice.subtotal' => '$100.00',
    '$company.address2' => $this->settings->address2,
    '$partial_due_date' => '&nbsp;',
    '$invoice.due_date' => '2023-10-24',
    '$client.id_number' => 'CLI-2023-1234',
    '$credit.po_number' => 'PO12345',
    '$company.address1' => $this->settings->address1,
    '$credit.credit_no' => '0029',
    '$invoice.datetime' => '25/Feb/2023 1:10 am',
    '$contact.custom1' => null,
    '$contact.custom2' => null,
    '$contact.custom3' => null,
    '$contact.custom4' => null,
    '$task.line_total' => '',
    '$line_tax_labels' => '',
    '$line_tax_values' => '',
    '$secondary_color' => isset($this->settings->secondary_color) ? $this->settings->secondary_color : '#3d3d3d;',
    '$invoice.balance' => '$0.00',
    '$invoice.custom1' => 'custom value',
    '$invoice.custom2' => 'custom value',
    '$invoice.custom3' => 'custom value',
    '$invoice.custom4' => 'custom value',
    '$company.custom1' => $this->company->settings->custom_value1,
    '$company.custom2' => $this->company->settings->custom_value2,
    '$company.custom3' => $this->company->settings->custom_value3,
    '$company.custom4' => $this->company->settings->custom_value4,
    '$quote.po_number' => 'PO12345',
    '$company.website' => $this->settings->website,
    '$balance_due_raw' => '0.00',
    '$entity.datetime' => '25/Feb/2023 1:10 am',
    '$credit.datetime' => '25/Feb/2023 1:10 am',
    '$client.address2' => '63993 Aiyana View',
    '$client.address1' => '8447',
    '$user.first_name' => 'Derrick Monahan DDS',
    '$created_by_user' => 'Derrick Monahan DDS Erna Wunsch',
    '$client.currency' => 'USD',
    '$company.country' => $this->company->country()?->name ?? 'USA',
    '$company.address' => $this->company->present()->address(),
    '$tech_hero_image' => 'https://invoicing.co/images/pdf-designs/tech-hero-image.jpg',
    '$task.tax_name1' => '',
    '$task.tax_name2' => '',
    '$task.tax_name3' => '',
    '$client.balance' => '$0.00',
    '$client_balance' => '$0.00',
    '$credit.balance' => '$0.00',
    '$credit_balance' => '$0.00',
    '$gross_subtotal' => '$0.00',
    '$invoice.amount' => '$0.00',
    '$client.custom1' => 'custom value',
    '$client.custom2' => 'custom value',
    '$client.custom3' => 'custom value',
    '$client.custom4' => 'custom value',
    '$emailSignature' => 'A email signature.',
    '$invoice.number' => '0029',
    '$quote.quote_no' => '0029',
    '$quote.datetime' => '25/Feb/2023 1:10 am',
    '$client_address' => '8447<br/>63993 Aiyana View<br/>Aufderharchester, North Carolina 11243<br/>United States<br/>',
    '$client.address' => '8447<br/>63993 Aiyana View<br/>Aufderharchester, North Carolina 11243<br/>United States<br/>',
    '$payment_button' => '<a class="button" href="http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">Pay Now</a>',
    '$payment_qrcode' => '<svg class=\'pqrcode\' viewBox=\'0 0 200 200\' width=\'200\' height=\'200\' x=\'0\' y=\'0\' xmlns=\'http://www.w3.org/2000/svg\'>
          <rect x=\'0\' y=\'0\' width=\'100%\'\' height=\'100%\' /><?xml version="1.0" encoding="UTF-8"?>
<svg xmlns="http://www.w3.org/2000/svg" version="1.1" width="200" height="200" viewBox="0 0 200 200"><rect x="0" y="0" width="200" height="200" fill="#fefefe"/><g transform="scale(4.878)"><g transform="translate(4,4)"><path fill-rule="evenodd" d="M9 0L9 1L8 1L8 2L9 2L9 3L8 3L8 4L10 4L10 7L11 7L11 4L12 4L12 5L13 5L13 4L12 4L12 2L14 2L14 7L15 7L15 6L16 6L16 8L15 8L15 10L14 10L14 11L16 11L16 12L14 12L14 13L15 13L15 14L14 14L14 15L15 15L15 14L17 14L17 15L16 15L16 16L14 16L14 17L15 17L15 18L14 18L14 19L13 19L13 18L11 18L11 15L8 15L8 12L9 12L9 13L10 13L10 14L11 14L11 13L12 13L12 14L13 14L13 13L12 13L12 11L13 11L13 10L11 10L11 11L10 11L10 9L11 9L11 8L6 8L6 9L5 9L5 8L0 8L0 10L1 10L1 12L2 12L2 11L3 11L3 10L4 10L4 11L5 11L5 12L3 12L3 13L7 13L7 14L6 14L6 15L5 15L5 14L1 14L1 15L0 15L0 19L1 19L1 20L0 20L0 25L1 25L1 20L2 20L2 19L3 19L3 20L4 20L4 21L5 21L5 20L6 20L6 21L8 21L8 23L7 23L7 22L5 22L5 24L4 24L4 25L8 25L8 27L10 27L10 28L11 28L11 29L9 29L9 28L8 28L8 33L9 33L9 30L11 30L11 29L12 29L12 32L13 32L13 33L14 33L14 32L15 32L15 33L17 33L17 32L19 32L19 31L18 31L18 30L16 30L16 28L17 28L17 29L18 29L18 28L19 28L19 27L18 27L18 26L17 26L17 27L16 27L16 26L15 26L15 25L16 25L16 24L18 24L18 25L19 25L19 23L18 23L18 22L19 22L19 20L17 20L17 19L20 19L20 25L21 25L21 26L22 26L22 28L21 28L21 27L20 27L20 33L21 33L21 30L24 30L24 32L25 32L25 33L27 33L27 32L29 32L29 33L32 33L32 32L33 32L33 31L31 31L31 32L29 32L29 30L32 30L32 29L33 29L33 27L32 27L32 26L31 26L31 25L32 25L32 24L31 24L31 25L30 25L30 23L29 23L29 21L30 21L30 22L31 22L31 21L32 21L32 22L33 22L33 21L32 21L32 20L33 20L33 18L32 18L32 20L31 20L31 21L30 21L30 19L29 19L29 18L28 18L28 17L25 17L25 16L28 16L28 15L30 15L30 14L31 14L31 17L30 17L30 18L31 18L31 17L32 17L32 16L33 16L33 15L32 15L32 14L31 14L31 13L32 13L32 12L33 12L33 11L32 11L32 10L31 10L31 9L32 9L32 8L31 8L31 9L30 9L30 8L29 8L29 10L28 10L28 11L30 11L30 14L29 14L29 12L27 12L27 11L26 11L26 10L25 10L25 9L26 9L26 8L25 8L25 9L23 9L23 8L24 8L24 7L25 7L25 5L23 5L23 3L24 3L24 4L25 4L25 3L24 3L24 2L25 2L25 0L24 0L24 1L23 1L23 0L21 0L21 1L20 1L20 4L21 4L21 5L22 5L22 7L23 7L23 8L22 8L22 9L18 9L18 8L19 8L19 6L20 6L20 8L21 8L21 6L20 6L20 5L19 5L19 6L18 6L18 5L17 5L17 2L18 2L18 1L19 1L19 0L18 0L18 1L17 1L17 0L16 0L16 1L17 1L17 2L16 2L16 3L15 3L15 2L14 2L14 1L15 1L15 0L14 0L14 1L11 1L11 2L10 2L10 0ZM21 1L21 2L22 2L22 3L23 3L23 2L22 2L22 1ZM10 3L10 4L11 4L11 3ZM15 4L15 5L16 5L16 4ZM8 5L8 7L9 7L9 5ZM12 6L12 9L14 9L14 8L13 8L13 6ZM17 6L17 7L18 7L18 6ZM23 6L23 7L24 7L24 6ZM16 8L16 9L17 9L17 10L16 10L16 11L17 11L17 10L18 10L18 11L20 11L20 10L18 10L18 9L17 9L17 8ZM27 8L27 9L28 9L28 8ZM1 9L1 10L2 10L2 9ZM4 9L4 10L5 10L5 11L6 11L6 12L7 12L7 11L9 11L9 10L8 10L8 9L6 9L6 10L5 10L5 9ZM22 9L22 10L21 10L21 11L22 11L22 10L23 10L23 11L24 11L24 12L23 12L23 13L22 13L22 14L21 14L21 12L18 12L18 13L17 13L17 12L16 12L16 13L17 13L17 14L21 14L21 15L20 15L20 16L19 16L19 15L17 15L17 16L16 16L16 18L21 18L21 19L22 19L22 18L21 18L21 17L22 17L22 16L23 16L23 19L25 19L25 18L24 18L24 16L23 16L23 13L24 13L24 14L25 14L25 12L26 12L26 15L27 15L27 14L28 14L28 13L27 13L27 12L26 12L26 11L24 11L24 10L23 10L23 9ZM6 10L6 11L7 11L7 10ZM30 10L30 11L31 11L31 10ZM10 12L10 13L11 13L11 12ZM1 15L1 17L2 17L2 18L1 18L1 19L2 19L2 18L3 18L3 19L4 19L4 20L5 20L5 19L6 19L6 20L8 20L8 21L10 21L10 23L8 23L8 24L10 24L10 27L11 27L11 26L14 26L14 25L15 25L15 24L16 24L16 23L17 23L17 22L18 22L18 21L17 21L17 20L16 20L16 19L14 19L14 21L13 21L13 19L12 19L12 21L10 21L10 20L11 20L11 18L10 18L10 17L8 17L8 15L6 15L6 16L7 16L7 17L5 17L5 16L4 16L4 15ZM12 15L12 17L13 17L13 15ZM3 16L3 18L4 18L4 19L5 19L5 17L4 17L4 16ZM17 16L17 17L18 17L18 16ZM20 16L20 17L21 17L21 16ZM6 18L6 19L7 19L7 18ZM8 18L8 20L9 20L9 19L10 19L10 18ZM26 18L26 19L27 19L27 20L26 20L26 21L25 21L25 22L24 22L24 20L22 20L22 22L21 22L21 23L22 23L22 25L23 25L23 28L22 28L22 29L24 29L24 30L25 30L25 32L27 32L27 31L28 31L28 30L27 30L27 31L26 31L26 29L24 29L24 24L23 24L23 23L27 23L27 24L29 24L29 23L27 23L27 20L29 20L29 19L27 19L27 18ZM15 20L15 21L14 21L14 23L12 23L12 25L13 25L13 24L14 24L14 23L16 23L16 22L15 22L15 21L16 21L16 20ZM2 21L2 22L3 22L3 23L4 23L4 22L3 22L3 21ZM12 21L12 22L13 22L13 21ZM22 22L22 23L23 23L23 22ZM6 23L6 24L7 24L7 23ZM10 23L10 24L11 24L11 23ZM2 24L2 25L3 25L3 24ZM25 25L25 28L28 28L28 25ZM26 26L26 27L27 27L27 26ZM29 26L29 27L30 27L30 28L29 28L29 29L32 29L32 27L31 27L31 26ZM12 27L12 28L13 28L13 30L14 30L14 29L15 29L15 28L16 28L16 27L15 27L15 28L14 28L14 27ZM17 27L17 28L18 28L18 27ZM15 30L15 31L16 31L16 30ZM10 31L10 32L11 32L11 31ZM13 31L13 32L14 32L14 31ZM22 32L22 33L23 33L23 32ZM0 0L0 7L7 7L7 0ZM1 1L1 6L6 6L6 1ZM2 2L2 5L5 5L5 2ZM26 0L26 7L33 7L33 0ZM27 1L27 6L32 6L32 1ZM28 2L28 5L31 5L31 2ZM0 26L0 33L7 33L7 26ZM1 27L1 32L6 32L6 27ZM2 28L2 31L5 31L5 28Z" fill="#000000"/></g></g></svg>
</svg>',
    '$client.country' => 'United States',
    '$user.last_name' => 'Erna Wunsch',
    '$client.website' => 'http://www.parisian.org/',
    '$dir_text_align' => 'left',
    '$entity_images' => '',
    '$task.discount' => '',
    '$contact.email' => 'bob@gmail.com',
    '$primary_color' => isset($this->settings->primary_color) ? $this->settings->primary_color : '#4e4e4e',
    '$credit_amount' => '$40.00',
    '$invoice.total' => '$330.00',
    '$invoice.taxes' => '$10.00',
    '$quote.custom1' => 'custom value',
    '$quote.custom2' => 'custom value',
    '$quote.custom3' => 'custom value',
    '$quote.custom4' => 'custom value',
    '$company.email' => $this->settings->email,
    '$client.number' => '12345',
    '$company.phone' => $this->settings->phone,
    '$company.state' => $this->settings->state,
    '$credit.number' => '0029',
    '$entity_number' => $entity_number,
    '$credit_number' => '0029',
    '$global_margin' => '6.35mm',
    '$contact.phone' => '681-480-9828',
    '$portal_button' => '<a class="button" href="http://ninja.test:8000/client/key_login/zJJEjlUtXPiNnnnyO2tcYia64PSwauidy61eDnMU?client_hash=nzikYQITs1kyUK61GScTNW67JwhTRkOBVdvsHzIv">View client portal</a>',
    '$paymentButton' => '<a class="button" href="http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">Pay Now</a>',
    '$entity_footer' => 'Default invoice footer',
    '$client.lang_2' => 'en',
    '$product.date' => '',
    '$client.email' => 'client@gmail.com',
    '$product.item' => '',
    '$public_notes' => 'These are very public notes',
    '$task.service' => '',
    '$credit.total' => '$0.00',
    '$net_subtotal' => '$0.00',
    '$paid_to_date' => '$0.00',
    '$quote.amount' => '$0.00',
    '$company.city' => $this->settings->city,
    '$payment.date' => '2022-10-10',
    '$client.phone' => '555-123-3212',
    '$number_short' => '0029',
    '$quote.number' => '0029',
    '$invoice.date' => '25/Feb/2023',
    '$company.name' => $this->settings->name,
    '$portalButton' => '<a class="button" href="http://ninja.test:8000/client/key_login/zJJEjlUtXPiNnnnyO2tcYia64PSwauidy61eDnMU?client_hash=nzikYQITs1kyUK61GScTNW67JwhTRkOBVdvsHzIv">View client portal</a>',
    '$contact.name' => 'Benedict Eichmann',
    '$entity.terms' => 'Default company invoice terms',
    '$client.state' => 'North Carolina',
    '$company.logo' => $this->settings->company_logo,
    '$company_logo' => $this->settings->company_logo,
    '$payment_link' => 'http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$status_logo' => '',
    '$description' => '',
    '$product.tax' => '',
    '$valid_until' => '',
    '$your_entity' => '',
    '$shipping' => ctrans('texts.shipping_address'),
    '$balance_due' => '$1110.00',
    '$outstanding' => '$440.00',
    '$partial_due' => '$50.00',
    '$quote.total' => '$10.00',
    '$payment_due' => '&nbsp;',
    '$credit.date' => '25/Feb/2023',
    '$invoiceDate' => '25/Feb/2023',
    '$view_button' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$client.city' => 'Aufderharchester',
    '$spc_qr_code' => '',
    '$client_name' => 'A Client Called Bob',
    '$client.name' => 'A Client Called Bob',
    '$paymentLink' => 'http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$payment_url' => 'http://ninja.test:8000/client/pay/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$page_layout' => $this->settings->page_layout,
    '$task.task1' => '',
    '$task.task2' => '',
    '$task.task3' => '',
    '$task.task4' => '',
    '$task.hours' => '',
    '$amount_due' => '$0.00',
    '$amount_raw' => '0.00',
    '$invoice_no' => '0029',
    '$quote.date' => '25/Feb/2023',
    '$vat_number' => '975977515',
    '$viewButton' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$portal_url' => 'http://ninja.test:8000/client/',
    '$task.date' => '',
    '$task.rate' => '',
    '$task.cost' => '',
    '$statement' => '',
    '$user_iban' => '&nbsp;',
    '$signature' => '&nbsp;',
    '$id_number' => 'ID Number',
    '$credit_no' => '0029',
    '$font_size' => $this->settings->font_size,
    '$view_link' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$reference' => '',
    '$po_number' => 'PO12345',
    '$page_size' => $this->settings->page_size,
    '$country_2' => 'AF',
    '$firstName' => 'Benedict',
    '$user.name' => 'Derrick Monahan DDS Erna Wunsch',
    '$font_name' => isset($this->settings?->primary_font) ? $this->settings?->primary_font : 'Roboto', //@phpstan-ignore-line
    '$auto_bill' => 'This invoice will automatically be billed to your credit card on file on the due date.',
    '$payments' => '',
    '$task.tax' => '',
    '$discount' => '$0.00',
    '$subtotal' => '$0.00',
    '$company1' => $this->company->settings->custom_value1,
    '$company2' => $this->company->settings->custom_value2,
    '$company3' => $this->company->settings->custom_value3,
    '$company4' => $this->company->settings->custom_value4,
    '$due_date' => '2022-01-01',
    '$poNumber' => 'PO-123456',
    '$quote_no' => '0029',
    '$address2' => $this->settings->address2,
    '$address1' => $this->settings->address1,
    '$viewLink' => '<a class="button" href="http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs">View Invoice</a>',
    '$autoBill' => 'This invoice will automatically be billed to your credit card on file on the due date.',
    '$view_url' => 'http://ninja.test:8000/client/invoice/UAUY8vIPuno72igmXbbpldwo5BDDKIqs',
    '$font_url' => isset($this->settings?->primary_font) ? \App\Utils\Helpers::resolveFont($this->settings->primary_font)['url'] : 'https://fonts.googleapis.com/css2?family=Roboto&display=swap',
    '$details' => '',
    '$balance' => '$40.00',
    '$partial' => '$30.00',
    '$client1' => 'custom value',
    '$client2' => 'custom value',
    '$client3' => 'custom value',
    '$client4' => 'custom value',
    '$dueDate' => '2022-01-01',
    '$invoice' => '0029',
    '$invoices' => '0029',
    '$account' => '434343',
    '$country' => 'United States',
    '$contact' => 'Benedict Eichmann',
    '$app_url' => 'http://ninja.test:8000',
    '$website' => $this->settings->website,
    '$entity' => '',
    '$thanks' => 'Thanks!',
    '$amount' => '$30.00',
    '$method' => '&nbsp;',
    '$number' => '0029',
    '$footer' => 'Default invoice footer',
    '$client' => 'The Client Name',
    '$email' => 'email@invoiceninja.net',
    '$notes' => '',
    '_rate1' => '',
    '_rate2' => '',
    '_rate3' => '',
    '$taxes' => '$40.00',
    '$total' => '$10.00',
    '$refund' => '',
    '$refunded' => '',
    '$phone' => '&nbsp;',
    '$terms' => 'Default company invoice terms',
    '$from' => 'Bob Jones',
    '$item' => '',
    '$date' => '25/Feb/2023',
    '$tax' => '',
    '$net' => 'Net',
    '$dir' => 'ltr',
    '$to' => 'Jimmy Giggles',
    '$show_paid_stamp' => $this->settings->show_paid_stamp ? 'flex' : 'none',
    '$status_logo' => '<div class="stamp is-paid"> ' . ctrans('texts.paid') .'</div>',
    '$show_shipping_address' => $this->settings->show_shipping_address ? 'flex' : 'none',
    '$show_shipping_address_block' => $this->settings->show_shipping_address ? 'block' : 'none',
    '$show_shipping_address_visibility' => $this->settings->show_shipping_address ? '1' : '0',
    '$start_date' => '31/01/2023',
    '$end_date' => '31/12/2023',
    '$history' => '',
    '$amount_paid' => '',
    '$receipt' => '',
    '$ship_to' => '',
    '$delivery_note' => '',
    '$quantity' => '',
    '$order_number' => '',
  ],
  'labels' => $this->mockTranslatedLabels(),
];
    }


    private function mockTranslatedLabels()
    {
        return [
            '$show_shipping_address_visibility_label' => ctrans('texts.shipping_address'),
            '$client.shipping_postal_code_label' => ctrans('texts.shipping_postal_code'),
            '$show_shipping_address_block_label' => ctrans('texts.shipping_address'),
            '$client.billing_postal_code_label' => ctrans('texts.billing_postal_code'),
            '$company.postal_city_state_label' => ctrans('texts.postal_city_state'),
            '$company.city_state_postal_label' => ctrans('texts.city_state_postal'),
            '$client.classification_label' => ctrans('texts.classification'),
            '$company.classification_label' => ctrans('texts.classification'),
            '$product.gross_line_total_label' => ctrans('texts.gross_line_total'),
            '$client.shipping_address1_label' => ctrans('texts.shipping_address1'),
            '$client.postal_city_state_label' => ctrans('texts.postal_city_state'),
            '$client.shipping_address2_label' => ctrans('texts.shipping_address2'),
            '$client.city_state_postal_label' => ctrans('texts.city_state_postal'),
            '$client.billing_address2_label' => ctrans('texts.billing_address2'),
            '$client.shipping_address_label' => ctrans('texts.shipping_address'),
            '$client.billing_address1_label' => ctrans('texts.billing_address1'),
            '$client.shipping_country_label' => ctrans('texts.shipping_country'),
            '$invoiceninja.whitelabel_label' => ctrans('texts.white_label_link'),
            '$client.billing_country_label' => ctrans('texts.billing_country'),
            '$client.billing_address_label' => ctrans('texts.billing_address'),
            '$task.gross_line_total_label' => ctrans('texts.gross_line_total'),
            '$contact.portal_button_label' => ctrans('texts.button'),
            '$client.shipping_state_label' => ctrans('texts.shipping_state'),
            '$show_shipping_address_label' => ctrans('texts.show_shipping_address'),
            '$invoice.public_notes_label' => ctrans('texts.public_notes'),
            '$client.billing_state_label' => ctrans('texts.billing_state'),
            '$client.shipping_city_label' => ctrans('texts.shipping_city'),
            '$product.description_label' => ctrans('texts.description'),
            '$product.product_key_label' => ctrans('texts.product_key'),
            '$entity.public_notes_label' => ctrans('texts.public_notes'),
            '$client.public_notes_label' => ctrans('texts.public_notes'),
            '$company.postal_code_label' => ctrans('texts.postal_code'),
            '$company.postal_city_label' => ctrans('texts.postal_city'),
            '$secondary_font_name_label' => ctrans('texts.secondary_font'),
            '$client.billing_city_label' => ctrans('texts.billing_city'),
            '$invoice.balance_due_label' => ctrans('texts.balance_due'),
            '$product.line_total_label' => ctrans('texts.line_total'),
            '$product.tax_amount_label' => ctrans('texts.tax_amount'),
            '$client.postal_code_label' => ctrans('texts.postal_code'),
            '$company.vat_number_label' => ctrans('texts.vat_number'),
            '$client.postal_city_label' => ctrans('texts.postal_city'),
            '$quote.quote_number_label' => ctrans('texts.quote_number'),
            '$invoice.invoice_no_label' => ctrans('texts.invoice_no'),
            '$contact.first_name_label' => ctrans('texts.first_name'),
            '$secondary_font_url_label' => ctrans('texts.secondary_font'),
            '$credit.valid_until_label' => ctrans('texts.valid_until'),
            '$contact.signature_label' => ctrans('texts.signature'),
            '$product.tax_name1_label' => ctrans('texts.tax_name1'),
            '$product.tax_name2_label' => ctrans('texts.tax_name2'),
            '$product.tax_name3_label' => ctrans('texts.tax_name3'),
            '$product.unit_cost_label' => ctrans('texts.unit_cost'),
            '$company.id_number_label' => ctrans('texts.id_number'),
            '$quote.valid_until_label' => ctrans('texts.valid_until'),
            '$invoice_total_raw_label' => ctrans('texts.invoice_total'),
            '$client.vat_number_label' => ctrans('texts.vat_number'),
            '$company_logo_size_label' => ctrans('texts.logo'),
            '$postal_city_state_label' => ctrans('texts.postal_city_state'),
            '$invoice.po_number_label' => ctrans('texts.po_number'),
            '$contact.last_name_label' => ctrans('texts.last_name'),
            '$contact.full_name_label' => ctrans('texts.full_name'),
            '$city_state_postal_label' => ctrans('texts.city_state_postal'),
            '$company.country_2_label' => ctrans('texts.country'),
            '$custom_surcharge1_label' => ctrans('texts.custom_surcharge1'),
            '$custom_surcharge2_label' => ctrans('texts.custom_surcharge2'),
            '$custom_surcharge3_label' => ctrans('texts.custom_surcharge3'),
            '$custom_surcharge4_label' => ctrans('texts.custom_surcharge4'),
            '$quote.balance_due_label' => ctrans('texts.balance_due'),
            '$product.product1_label' => ctrans('texts.product1'),
            '$product.product2_label' => ctrans('texts.product2'),
            '$product.product3_label' => ctrans('texts.product3'),
            '$product.product4_label' => ctrans('texts.product4'),
            '$statement_amount_label' => ctrans('texts.amount'),
            '$task.description_label' => ctrans('texts.description'),
            '$product.discount_label' => ctrans('texts.discount'),
            '$product.quantity_label' => ctrans('texts.quantity'),
            '$entity_issued_to_label' => ctrans("texts.{$this->entity_string}_issued_to") ?: ctrans('texts.quote_issued_to'),
            '$partial_due_date_label' => ctrans('texts.partial_due_date'),
            '$invoice.datetime_label' => ctrans('texts.datetime_format_id'),
            '$invoice.due_date_label' => ctrans('texts.invoice_due_date'),
            '$company.address1_label' => ctrans('texts.address1'),
            '$company.address2_label' => ctrans('texts.address2'),
            '$total_tax_labels_label' => ctrans('texts.total_taxes'),
            '$total_tax_values_label' => ctrans('texts.total_taxes'),
            '$credit.po_number_label' => ctrans('texts.po_number'),
            '$client.id_number_label' => ctrans('texts.id_number'),
            '$credit.credit_note_label' => ctrans('texts.credit_note'),
            '$assigned_to_user_label' => ctrans('texts.assigned_to'),
            '$invoice.discount_label' => ctrans('texts.discount'),
            '$invoice.subtotal_label' => ctrans('texts.subtotal'),
            '$contact.custom1_label' => ctrans('texts.custom1'),
            '$contact.custom2_label' => ctrans('texts.custom2'),
            '$contact.custom3_label' => ctrans('texts.custom3'),
            '$contact.custom4_label' => ctrans('texts.custom4'),
            '$task.line_total_label' => ctrans('texts.line_total'),
            '$task.tax_amount_label' => ctrans('texts.tax_amount'),
            '$line_tax_labels_label' => ctrans('texts.line_taxes'),
            '$line_tax_values_label' => ctrans('texts.line_taxes'),
            '$invoice.custom1_label' => ctrans('texts.custom1'),
            '$invoice.custom2_label' => ctrans('texts.custom2'),
            '$invoice.custom3_label' => ctrans('texts.custom3'),
            '$invoice.custom4_label' => ctrans('texts.custom4'),
            '$company.custom1_label' => ctrans('texts.custom1'),
            '$company.custom2_label' => ctrans('texts.custom2'),
            '$company.custom3_label' => ctrans('texts.custom3'),
            '$company.custom4_label' => ctrans('texts.custom4'),
            '$secondary_color_label' => ctrans('texts.secondary_color'),
            '$balance_due_raw_label' => ctrans('texts.balance_due'),
            '$entity.datetime_label' => ctrans('texts.datetime_format_id'),
            '$credit.datetime_label' => ctrans('texts.datetime_format_id'),
            '$client.address2_label' => ctrans('texts.address2'),
            '$company.address_label' => ctrans('texts.address'),
            '$client.address1_label' => ctrans('texts.address1'),
            '$quote.po_number_label' => ctrans('texts.po_number'),
            '$client.currency_label' => ctrans('texts.currency'),
            '$user.first_name_label' => ctrans('texts.first_name'),
            '$created_by_user_label' => ctrans('texts.created_by'),
            '$company.country_label' => ctrans('texts.country'),
            '$tech_hero_image_label' => '',
            '$company.website_label' => ctrans('texts.website'),
            '$invoice.balance_label' => ctrans('texts.balance'),
            '$client.country_label' => ctrans('texts.country'),
            '$task.tax_name1_label' => ctrans('texts.tax_name1'),
            '$task.tax_name2_label' => ctrans('texts.tax_name2'),
            '$task.tax_name3_label' => ctrans('texts.tax_name3'),
            '$payment_button_label' => '',
            '$credit.custom1_label' => ctrans('texts.custom1'),
            '$credit.custom2_label' => ctrans('texts.custom2'),
            '$credit.custom3_label' => ctrans('texts.custom3'),
            '$credit.custom4_label' => ctrans('texts.custom4'),
            '$emailSignature_label' => ctrans('texts.email_signature'),
            '$quote.datetime_label' => ctrans('texts.datetime_format_id'),
            '$client.custom1_label' => ctrans('texts.custom1'),
            '$client_address_label' => ctrans('texts.address'),
            '$client.address_label' => ctrans('texts.address'),
            '$payment_qrcode_label' => '',
            '$client.custom2_label' => ctrans('texts.custom2'),
            '$invoice.number_label' => ctrans('texts.number'),
            '$quote.quote_no_label' => ctrans('texts.quote_no'),
            '$user.last_name_label' => ctrans('texts.last_name'),
            '$client.custom3_label' => ctrans('texts.custom3'),
            '$client.website_label' => ctrans('texts.website'),
            '$dir_text_align_label' => '',
            '$client.custom4_label' => ctrans('texts.custom4'),
            '$client.balance_label' => ctrans('texts.balance'),
            '$client_balance_label' => ctrans('texts.client_balance'),
            '$credit.balance_label' => ctrans('texts.balance'),
            '$credit_balance_label' => ctrans('texts.credit_balance'),
            '$gross_subtotal_label' => ctrans('texts.subtotal'),
            '$invoice.amount_label' => ctrans('texts.amount'),
            '$entity_footer_label' => ctrans('texts.footer'),
            '$entity_images_label' => '',
            '$task.discount_label' => ctrans('texts.discount'),
            '$portal_button_label' => '',
            '$approveButton_label' => ctrans('texts.approve'),
            '$quote.custom1_label' => ctrans('texts.custom1'),
            '$quote.custom2_label' => ctrans('texts.custom2'),
            '$quote.custom3_label' => ctrans('texts.custom3'),
            '$quote.custom4_label' => ctrans('texts.custom4'),
            '$company.email_label' => ctrans('texts.email'),
            '$primary_color_label' => ctrans('texts.primary_color'),
            '$company.phone_label' => ctrans('texts.phone'),
            '$exchange_rate_label' => ctrans('texts.exchange_rate'),
            '$client.number_label' => ctrans('texts.number'),
            '$global_margin_label' => '',
            '$contact.phone_label' => ctrans('texts.phone'),
            '$company.state_label' => ctrans('texts.state'),
            '$credit.number_label' => ctrans('texts.number'),
            '$entity_number_label' => ctrans('texts.quote_number'),
            '$credit_number_label' => ctrans('texts.credit_number'),
            '$client.lang_2_label' => ctrans('texts.language'),
            '$contact.email_label' => ctrans('texts.email'),
            '$invoice.taxes_label' => ctrans('texts.taxes'),
            '$credit_amount_label' => ctrans('texts.credit_amount'),
            '$invoice.total_label' => ctrans('texts.invoice_total'),
            '$product.date_label' => ctrans('texts.date'),
            '$product.item_label' => ctrans('texts.item'),
            '$public_notes_label' => ctrans('texts.public_notes'),
            '$entity.terms_label' => ctrans('texts.terms'),
            '$task.service_label' => ctrans('texts.service'),
            '$portalButton_label' => '',
            '$payment.date_label' => ctrans('texts.payment_date'),
            '$client.phone_label' => ctrans('texts.phone'),
            '$invoice.date_label' => ctrans('texts.invoice_date'),
            '$client.state_label' => ctrans('texts.state'),
            '$number_short_label' => '',
            '$quote.number_label' => ctrans('texts.number'),
            '$contact.name_label' => ctrans('texts.name'),
            '$company.city_label' => ctrans('texts.city'),
            '$company.name_label' => ctrans('texts.name'),
            '$company.logo_label' => ctrans('texts.logo'),
            '$company_logo_label' => ctrans('texts.logo'),
            '$payment_link_label' => ctrans('texts.link'),
            '$client.email_label' => ctrans('texts.email'),
            '$paid_to_date_label' => ctrans('texts.paid_to_date'),
            '$net_subtotal_label' => ctrans('texts.net_subtotal'),
            '$credit.total_label' => ctrans('texts.total'),
            '$quote.amount_label' => ctrans('texts.amount'),
            '$product.tax_rate1_label' => ctrans('texts.tax'),
            '$product.tax_rate2_label' => ctrans('texts.tax'),
            '$product.tax_rate3_label' => ctrans('texts.tax'),
            '$product.tax_label' => ctrans('texts.tax'),
            '$description_label' => ctrans('texts.description'),
            '$your_entity_label' => ctrans("texts.your_{$this->entity_string}"),
            '$view_button_label' => ctrans('texts.view'),
            '$status_logo_label' => ctrans('texts.logo'),
            '$credit.date_label' => ctrans('texts.date'),
            '$payment_due_label' => ctrans('texts.payment_due'),
            '$invoiceDate_label' => ctrans('texts.date'),
            '$valid_until_label' => ctrans('texts.valid_until'),
            '$postal_city_label' => ctrans('texts.postal_city'),
            '$client_name_label' => ctrans('texts.client_name'),
            '$client.name_label' => ctrans('texts.name'),
            '$spc_qr_code_label' => '',
            '$client.city_label' => ctrans('texts.city'),
            '$paymentLink_label' => ctrans('texts.link'),
            '$payment_url_label' => ctrans('texts.url'),
            '$page_layout_label' => ctrans('texts.page_layout'),
            '$balance_due_label' => ctrans('texts.balance_due'),
            '$outstanding_label' => ctrans('texts.outstanding'),
            '$partial_due_label' => ctrans('texts.partial_due'),
            '$quote.total_label' => ctrans('texts.total'),
            '$task.task1_label' => ctrans('texts.task1'),
            '$task.task2_label' => ctrans('texts.task2'),
            '$task.task3_label' => ctrans('texts.task3'),
            '$task.task4_label' => ctrans('texts.task4'),
            '$task.hours_label' => ctrans('texts.hours'),
            '$viewButton_label' => ctrans('texts.view'),
            '$quote.date_label' => ctrans('texts.date'),
            '$amount_raw_label' => ctrans('texts.amount'),
            '$vat_number_label' => ctrans('texts.vat_number'),
            '$invoice_no_label' => ctrans('texts.invoice_no'),
            '$portal_url_label' => ctrans('texts.url'),
            '$amount_due_label' => ctrans('texts.amount_due'),
            '$country_2_label' => ctrans('texts.country'),
            '$task.date_label' => ctrans('texts.date'),
            '$task.rate_label' => ctrans('texts.rate'),
            '$task.cost_label' => ctrans('texts.cost'),
            '$statement_label' => ctrans('texts.statement'),
            '$view_link_label' => ctrans('texts.link'),
            '$user_iban_label' => ctrans('texts.iban'),
            '$signature_label' => ctrans('texts.signature'),
            '$font_size_label' => ctrans('texts.font_size'),
            '$reference_label' => ctrans('texts.reference'),
            '$po_number_label' => ctrans('texts.po_number'),
            '$page_size_label' => ctrans('texts.page_size'),
            '$user.name_label' => ctrans('texts.name'),
            '$id_number_label' => ctrans('texts.id_number'),
            '$credit_no_label' => ctrans('texts.credit_note'),
            '$firstName_label' => ctrans('texts.first_name'),
            '$font_name_label' => '',
            '$auto_bill_label' => ctrans('texts.auto_bill'),
            '$payments_label' => ctrans('texts.payments'),
            '$shipping_label' => ctrans('texts.shipping_address'),
            '$task.tax_label' => ctrans('texts.tax'),
            '$viewLink_label' => ctrans('texts.link'),
            '$company1_label' => ctrans('texts.company1'),
            '$company2_label' => ctrans('texts.company2'),
            '$company3_label' => ctrans('texts.company3'),
            '$company4_label' => ctrans('texts.company4'),
            '$due_date_label' => ctrans('texts.due_date'),
            '$address2_label' => ctrans('texts.address2'),
            '$address1_label' => ctrans('texts.address1'),
            '$poNumber_label' => ctrans('texts.po_number'),
            '$quote_no_label' => ctrans('texts.quote_no'),
            '$autoBill_label' => ctrans('texts.auto_bill'),
            '$view_url_label' => ctrans('texts.url'),
            '$font_url_label' => '',
            '$discount_label' => ctrans('texts.discount'),
            '$subtotal_label' => ctrans('texts.subtotal'),
            '$country_label' => ctrans('texts.country'),
            '$details_label' => ctrans('texts.details'),
            '$custom1_label' => ctrans('texts.custom1'),
            '$custom2_label' => ctrans('texts.custom2'),
            '$custom3_label' => ctrans('texts.custom3'),
            '$custom4_label' => ctrans('texts.custom4'),
            '$dueDate_label' => ctrans('texts.due_date'),
            '$client1_label' => ctrans('texts.client1'),
            '$client2_label' => ctrans('texts.client2'),
            '$contact_label' => ctrans('texts.contact'),
            '$account_label' => '',
            '$client3_label' => ctrans('texts.client3'),
            '$app_url_label' => ctrans('texts.url'),
            '$website_label' => ctrans('texts.website'),
            '$client4_label' => ctrans('texts.client4'),
            '$balance_label' => ctrans('texts.balance'),
            '$partial_label' => ctrans('texts.partial'),
            '$footer_label' => ctrans('texts.footer'),
            '$entity_label' => ctrans("texts.{$this->entity_string}"),
            '$thanks_label' => ctrans('texts.thanks'),
            '$method_label' => ctrans('texts.method'),
            '$client_label' => ctrans('texts.client'),
            '$number_label' => ctrans('texts.number'),
            '$amount_label' => ctrans('texts.amount'),
            '$notes_label' => ctrans('texts.notes'),
            '$terms_label' => ctrans('texts.terms'),
            '$tax_rate1_label' => ctrans('texts.tax_rate1'),
            '$tax_rate2_label' => ctrans('texts.tax_rate2'),
            '$tax_rate3_label' => ctrans('texts.tax_rate3'),
            '$refund_label' => ctrans('texts.refund'),
            '$refunded_label' => ctrans('texts.refunded'),
            '$phone_label' => ctrans('texts.phone'),
            '$email_label' => ctrans('texts.email'),
            '$taxes_label' => ctrans('texts.taxes'),
            '$total_label' => ctrans('texts.total'),
            '$from_label' => ctrans('texts.from'),
            '$item_label' => ctrans('texts.item'),
            '$date_label' => ctrans('texts.date'),
            '$tax_label' => ctrans('texts.tax'),
            '$net_label' => ctrans('texts.net'),
            '$dir_label' => '',
            '$to_label' => ctrans('texts.to'),
            '$start_date_label' => ctrans('texts.start_date'),
            '$end_date_label' => ctrans('texts.end_date'),
            '$invoice_label' => ctrans('texts.invoice'),
            '$invoices_label' => ctrans('texts.invoices'),
            '$history_label' => ctrans('texts.history'),
            '$amount_paid_label' => ctrans('texts.amount_paid'),
            '$receipt_label' => ctrans('texts.receipt'),
            '$ship_to_label' => ctrans('texts.ship_to'),
            '$delivery_note_label' => ctrans('texts.delivery_note'),
            '$quantity_label' => ctrans('texts.quantity'),
            '$order_number_label' => ctrans('texts.order_number'),
        ];
    }

    private function getVendorStubVariables()
    {
        return ['values' => [
            '$vendor.billing_postal_code' => '06270-5526',
            '$company.postal_city_state' => '29359 New Loy, Delaware',
            '$company.city_state_postal' => 'New Loy, Delaware 29359',
            '$product.gross_line_total' => '',
            '$purchase_order.po_number' => 'PO12345',
            '$vendor.postal_city_state' => '06270-5526 Jameyhaven, West Virginia',
            '$vendor.city_state_postal' => 'Jameyhaven, West Virginia 06270-5526',
            '$purchase_order.due_date' => '02-12-2021',
            '$vendor.billing_address1' => '589',
          '$vendor.billing_address2' => '761 Odessa Centers Suite 673',
          '$invoiceninja.whitelabel' => config('ninja.app_logo'),
          '$purchase_order.custom1' => 'Custom 1',
          '$purchase_order.custom2' => 'Custom 2',
          '$purchase_order.custom3' => 'Custom 3',
          '$purchase_order.custom4' => 'Custom 4',
          '$vendor.billing_address' => '589<br/>761 Odessa Centers Suite 673<br/>New Loy, Delaware 29359<br/>United States<br/>',
          '$vendor.billing_country' => 'United States',
          '$purchase_order.number' => 'Live Preview #790',
          '$purchase_order.total' => '$10,256.40',
          '$vendor.billing_state' => 'West Virginia',
          '$product.description' => '',
          '$product.product_key' => '',
          '$entity.public_notes' => 'These are public notes for the Vendor',
          '$purchase_order.date' => '14/Mar/2023',
          '$company.postal_code' => '29359',
          '$company.postal_city' => '29359 New Loy',
          '$vendor.billing_city' => 'Jameyhaven',
          '$vendor.public_notes' => 'Public notes',
          '$product.line_total' => '',
          '$product.tax_amount' => '',
          '$vendor.postal_code' => '06270-5526',
          '$vendor.postal_city' => '06270-5526 Jameyhaven',
          '$contact.first_name' => 'Geo',
          '$company.vat_number' => 'vat number',
          '$contact.signature' => '',
          '$product.tax_name1' => '',
          '$product.tax_name2' => '',
          '$product.tax_name3' => '',
          '$product.unit_cost' => '',
          '$custom_surcharge1' => '$0.00',
          '$custom_surcharge2' => '$0.00',
          '$custom_surcharge3' => '$0.00',
          '$custom_surcharge4' => '$0.00',
          '$postal_city_state' => '06270-5526 Jameyhaven, West Virginia',
          '$company_logo_size' => '65%',
          '$vendor.vat_number' => 'At qui autem iusto et.',
          '$contact.full_name' => 'Geo Maggio',
          '$city_state_postal' => 'Jameyhaven, West Virginia 06270-5526',
          '$contact.last_name' => 'Maggio',
          '$company.country_2' => 'US',
          '$company.id_number' => 'id number',
          '$product.product1' => '',
          '$product.product2' => '',
          '$product.product3' => '',
          '$product.product4' => '',
          '$statement_amount' => '',
          '$product.discount' => '',
          '$assigned_to_user' => '',
          '$entity_issued_to' => '',
          '$product.quantity' => '',
          '$total_tax_labels' => '',
          '$total_tax_values' => '',
          '$partial_due_date' => '&nbsp;',
          '$company.address2' => '70218 Lori Station Suite 529',
          '$company.address1' => 'Christiansen Garden',
          '$vendor.id_number' => 'Libero debitis.',
          '$contact.custom1' => null,
          '$contact.custom2' => null,
          '$contact.custom3' => null,
          '$contact.custom4' => null,
          '$secondary_color' => '#7081e0',
          '$company.custom1' => '&nbsp;',
          '$company.custom2' => '&nbsp;',
          '$company.custom3' => '&nbsp;',
          '$company.custom4' => '&nbsp;',
          '$balance_due_raw' => '10256.40',
          '$entity.datetime' => '14/Mar/2023 10:43 pm',
          '$vendor.address1' => '589',
          '$vendor.address2' => '761 Odessa Centers Suite 673',
          '$line_tax_values' => '<span>$488.40</span>',
          '$line_tax_labels' => '<span>Sales Tax 5%</span>',
          '$company.address' => 'Christiansen Garden<br/>70218 Lori Station Suite 529<br/>New Loy, Delaware 29359<br/>United States<br/>Phone: 1-240-886-2233<br/>Email: immanuel53@example.net<br/>',
          '$user.first_name' => 'Mr. Louvenia Armstrong',
          '$created_by_user' => 'Mr. Louvenia Armstrong Prof. Reyes Anderson',
          '$vendor.currency' => 'USD',
          '$company.country' => 'United States',
          '$tech_hero_image' => config('ninja.app_url').'/images/pdf-designs/tech-hero-image.jpg',
          '$company.website' => 'http://www.dare.com/vero-consequatur-eveniet-dolorum-exercitationem-alias-repellat.html',
          '$gross_subtotal' => '$10,256.40',
          '$emailSignature' => '&nbsp;',
          '$vendor_address' => '589<br/>761 Odessa Centers Suite 673<br/>New Loy, Delaware 29359<br/>United States<br/>',
          '$vendor.address' => '589<br/>761 Odessa Centers Suite 673<br/>New Loy, Delaware 29359<br/>United States<br/>',
          '$vendor.country' => 'United States',
          '$vendor.custom3' => 'Ea quia tempore.',
          '$vendor.custom1' => 'Necessitatibus aut.',
          '$vendor.custom4' => 'Nobis aut harum.',
          '$user.last_name' => 'Prof. Reyes Anderson',
          '$vendor.custom2' => 'Sit fuga quas sint.',
          '$vendor.website' => 'http://abernathy.com/consequatur-at-beatae-nesciunt',
          '$dir_text_align' => 'left',
          '$entity_footer' => '',
          '$entity_images' => '',
          '$contact.email' => '',
          '$primary_color' => '#298AAB',
          '$contact.phone' => '+1 (920) 735-1990',
          '$vendor.number' => '0001',
          '$company.phone' => '1-240-886-2233',
          '$global_margin' => '6.35mm',
          '$company.state' => 'Delaware',
          '$entity_number' => 'Live Preview #790',
          '$company.email' => 'immanuel53@example.net',
          '$product.date' => '',
          '$vendor.email' => '',
          '$entity.terms' => '',
          '$product.item' => '',
          '$public_notes' => null,
          '$paid_to_date' => '$0.00',
          '$net_subtotal' => '$9,768.00',
          '$payment.date' => '&nbsp;',
          '$vendor.phone' => '&nbsp;',
          '$contact.name' => 'Geo Maggio',
          '$number_short' => 'Live Preview #790',
          '$company.name' => 'Mrs. Kristina Powlowski',
          '$company.city' => 'New Loy',
          '$vendor.state' => 'West Virginia',
          '$company.logo' => 'data:image/png;base64, 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',
          '$company_logo' => 'data:image/png;base64, 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',
          '$description' => '',
          '$product.tax' => '',
          '$view_button' => '
<div>
<!--[if (gte mso 9)|(IE)]>
<table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
    <tr>
    <td align="center" valign="top">
        <![endif]-->
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
        <tbody><tr>
        <td align="center" class="new_button" style="border-radius: 2px; background-color: #298AAB">
            <a href="http://ninja.test:8000/vendor/purchase_order/OwH1Bkl0AP3EBQxJpGvEsU7YbTk5durD" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid #298AAB; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
            <singleline label="cta button">View Purchase Order</singleline>
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
        ',
          '$status_logo' => ' ',
          '$partial_due' => '$0.00',
          '$balance_due' => '$10,256.40',
          '$outstanding' => '$10,256.40',
          '$payment_due' => '&nbsp;',
          '$postal_city' => '06270-5526 Jameyhaven',
          '$vendor_name' => 'Claudie Nikolaus MD',
          '$vendor.name' => 'Claudie Nikolaus MD',
          '$vendor.city' => 'Jameyhaven',
          '$page_layout' => 'portrait',
          '$viewButton' => '
<div>
<!--[if (gte mso 9)|(IE)]>
<table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
    <tr>
    <td align="center" valign="top">
        <![endif]-->
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
        <tbody><tr>
        <td align="center" class="new_button" style="border-radius: 2px; background-color: #298AAB">
            <a href="http://ninja.test:8000/vendor/purchase_order/OwH1Bkl0AP3EBQxJpGvEsU7YbTk5durD" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid #298AAB; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
            <singleline label="cta button">View Purchase Order</singleline>
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
        ',
          '$amount_due' => '$10,256.40',
          '$amount_raw' => '10256.40',
          '$vat_number' => 'At qui autem iusto et.',
          '$portal_url' => 'http://ninja.test:8000/vendor/',
          '$reference' => '',
          '$po_number' => null,
          '$statement' => '',
          '$view_link' => '
<div>
<!--[if (gte mso 9)|(IE)]>
<table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
    <tr>
    <td align="center" valign="top">
        <![endif]-->
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
        <tbody><tr>
        <td align="center" class="new_button" style="border-radius: 2px; background-color: #298AAB">
            <a href="http://ninja.test:8000/vendor/purchase_order/OwH1Bkl0AP3EBQxJpGvEsU7YbTk5durD" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid #298AAB; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
            <singleline label="cta button">View Purchase Order</singleline>
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
        ',
          '$signature' => '&nbsp;',
          '$font_size' => '16px',
          '$page_size' => 'A4',
          '$country_2' => 'AF',
          '$firstName' => 'Geo',
          '$id_number' => 'Libero debitis.',
          '$user.name' => 'Mr. Louvenia Armstrong Prof. Reyes Anderson',
          '$font_name' => 'Roboto',
          '$auto_bill' => 'This invoice will automatically be billed to your credit card on file on the due date.',
          '$poNumber' => null,
          '$payments' => '',
          '$viewLink' => '
<div>
<!--[if (gte mso 9)|(IE)]>
<table align="center" cellspacing="0" cellpadding="0" style="width: 600px;">
    <tr>
    <td align="center" valign="top">
        <![endif]-->
        <table align="center" border="0" cellpadding="0" cellspacing="0" role="presentation" >
        <tbody><tr>
        <td align="center" class="new_button" style="border-radius: 2px; background-color: #298AAB">
            <a href="http://ninja.test:8000/vendor/purchase_order/OwH1Bkl0AP3EBQxJpGvEsU7YbTk5durD" target="_blank" class="new_button" style="text-decoration: none; border: 1px solid #298AAB; display: inline-block; border-radius: 2px; padding-top: 15px; padding-bottom: 15px; padding-left: 25px; padding-right: 25px; font-size: 20px; color: #fff">
            <singleline label="cta button">View Purchase Order</singleline>
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
        ',
          '$subtotal' => '$9,768.00',
          '$company1' => '&nbsp;',
          '$company2' => '&nbsp;',
          '$company3' => '&nbsp;',
          '$company4' => '&nbsp;',
          '$due_date' => '&nbsp;',
          '$discount' => 0.0,
          '$address1' => '589',
          '$address2' => '761 Odessa Centers Suite 673',
          '$autoBill' => 'This invoice will automatically be billed to your credit card on file on the due date.',
          '$view_url' => 'http://ninja.test:8000/vendor/purchase_order/OwH1Bkl0AP3EBQxJpGvEsU7YbTk5durD',
          '$font_url' => 'https://fonts.googleapis.com/css2?family=Roboto&display=swap',
          '$details' => '',
          '$balance' => '$0.00',
          '$partial' => '$0.00',
          '$custom1' => '&nbsp;',
          '$custom2' => '&nbsp;',
          '$custom3' => '&nbsp;',
          '$custom4' => '&nbsp;',
          '$dueDate' => '&nbsp;',
          '$country' => 'United States',
          '$vendor3' => 'Ea quia tempore.',
          '$contact' => 'Geo Maggio',
          '$account' => 'Mrs. Kristina Powlowski',
          '$vendor1' => 'Necessitatibus aut.',
          '$vendor4' => 'Nobis aut harum.',
          '$vendor2' => 'Sit fuga quas sint.',
          '$website' => 'http://abernathy.com/consequatur-at-beatae-nesciunt',
          '$app_url' => 'http://ninja.test:8000',
          '$footer' => '',
          '$entity' => '',
          '$thanks' => '',
          '$amount' => '$10,256.40',
          '$method' => '&nbsp;',
          '$vendor' => 'Claudie Nikolaus MD',
          '$number' => 'Live Preview #790',
          '$email' => '',
          '$terms' => '',
          '$notes' => null,
          '$tax_rate1' => '',
          '$tax_rate2' => '',
          '$tax_rate3' => '',
          '$refund' => 'Refund',
          '$refunded' => 'Refunded',
          '$total' => '$10,256.40',
          '$taxes' => '$488.40',
          '$phone' => '&nbsp;',
          '$from' => '',
          '$item' => '',
          '$date' => '14/Mar/2023',
          '$tax' => '',
          '$dir' => 'ltr',
          '$net' => 'Net',
          '$to' => '',
          '$amount_paid' => '',
          '$receipt' => '',
          '$ship_to' => '',
          '$delivery_note_label' => '',
          '$quantity_label' => '',
          '$order_number_label' => '',
        ],
        'labels' => $this->vendorLabels(),
];
    }

    private function vendorLabels()
    {
        return [
          '$vendor.billing_postal_code_label' => ctrans('texts.billing_postal_code'),
          '$company.postal_city_state_label' => ctrans('texts.postal_city_state'),
          '$company.city_state_postal_label' => ctrans('texts.city_state_postal'),
          '$product.gross_line_total_label' => ctrans('texts.gross_line_total'),
          '$purchase_order.po_number_label' => ctrans('texts.po_number'),
          '$vendor.postal_city_state_label' => ctrans('texts.postal_city_state'),
          '$vendor.city_state_postal_label' => ctrans('texts.city_state_postal'),
          '$purchase_order.due_date_label' => ctrans('texts.due_date'),
          '$vendor.billing_address1_label' => ctrans('texts.billing_address1'),
          '$vendor.billing_address2_label' => ctrans('texts.billing_address2'),
          '$invoiceninja.whitelabel_label' => ctrans('texts.white_label'),
          '$purchase_order.custom1_label' => ctrans('texts.custom1'),
          '$purchase_order.custom2_label' => ctrans('texts.custom2'),
          '$purchase_order.custom3_label' => ctrans('texts.custom3'),
          '$purchase_order.custom4_label' => ctrans('texts.custom4'),
          '$vendor.billing_address_label' => ctrans('texts.billing_address'),
          '$vendor.billing_country_label' => ctrans('texts.billing_country'),
          '$purchase_order.number_label' => ctrans('texts.number'),
          '$purchase_order.total_label' => ctrans('texts.total'),
          '$vendor.billing_state_label' => ctrans('texts.billing_state'),
          '$product.description_label' => ctrans('texts.description'),
          '$product.product_key_label' => ctrans('texts.product_key'),
          '$entity.public_notes_label' => ctrans('texts.public_notes'),
          '$purchase_order.date_label' => ctrans('texts.date'),
          '$company.postal_code_label' => ctrans('texts.postal_code'),
          '$company.postal_city_label' => ctrans('texts.postal_city'),
          '$vendor.billing_city_label' => ctrans('texts.billing_city'),
          '$vendor.public_notes_label' => ctrans('texts.public_notes'),
          '$product.line_total_label' => ctrans('texts.line_total'),
          '$product.tax_amount_label' => ctrans('texts.tax_amount'),
          '$vendor.postal_code_label' => ctrans('texts.postal_code'),
          '$vendor.postal_city_label' => ctrans('texts.postal_city'),
          '$contact.first_name_label' => ctrans('texts.first_name'),
          '$company.vat_number_label' => ctrans('texts.vat_number'),
          '$contact.signature_label' => ctrans('texts.signature'),
          '$product.tax_name1_label' => ctrans('texts.tax_name1'),
          '$product.tax_name2_label' => ctrans('texts.tax_name2'),
          '$product.tax_name3_label' => ctrans('texts.tax_name3'),
          '$product.unit_cost_label' => ctrans('texts.unit_cost'),
          '$custom_surcharge1_label' => ctrans('texts.custom_surcharge1'),
          '$custom_surcharge2_label' => ctrans('texts.custom_surcharge2'),
          '$custom_surcharge3_label' => ctrans('texts.custom_surcharge3'),
          '$custom_surcharge4_label' => ctrans('texts.custom_surcharge4'),
          '$postal_city_state_label' => ctrans('texts.postal_city_state'),
          '$company_logo_size_label' => ctrans('texts.logo'),
          '$vendor.vat_number_label' => ctrans('texts.vat_number'),
          '$contact.full_name_label' => ctrans('texts.full_name'),
          '$city_state_postal_label' => ctrans('texts.city_state_postal'),
          '$contact.last_name_label' => ctrans('texts.last_name'),
          '$company.country_2_label' => ctrans('texts.country'),
          '$company.id_number_label' => ctrans('texts.id_number'),
          '$product.product1_label' => ctrans('texts.product1'),
          '$product.product2_label' => ctrans('texts.product2'),
          '$product.product3_label' => ctrans('texts.product3'),
          '$product.product4_label' => ctrans('texts.product4'),
          '$statement_amount_label' => ctrans('texts.amount'),
          '$product.discount_label' => ctrans('texts.discount'),
          '$assigned_to_user_label' => ctrans('texts.assigned_to'),
          '$entity_issued_to_label' => ctrans('texts.purchase_order_issued_to'),
          '$product.quantity_label' => ctrans('texts.quantity'),
          '$total_tax_labels_label' => ctrans('texts.total_taxes'),
          '$total_tax_label' => ctrans('texts.total_taxes'),
          '$partial_due_date_label' => ctrans('texts.partial_due_date'),
          '$company.address2_label' => ctrans('texts.address2'),
          '$company.address1_label' => ctrans('texts.address1'),
          '$vendor.id_number_label' => ctrans('texts.id_number'),
          '$contact.custom1_label' => ctrans('texts.custom1'),
          '$contact.custom2_label' => ctrans('texts.custom2'),
          '$contact.custom3_label' => ctrans('texts.custom3'),
          '$contact.custom4_label' => ctrans('texts.custom4'),
          '$secondary_color_label' => ctrans('texts.secondary_color'),
          '$company.custom1_label' => ctrans('texts.custom1'),
          '$company.custom2_label' => ctrans('texts.custom2'),
          '$company.custom3_label' => ctrans('texts.custom3'),
          '$company.custom4_label' => ctrans('texts.custom4'),
          '$balance_due_raw_label' => ctrans('texts.balance_due'),
          '$entity.datetime_label' => ctrans('texts.datetime_format_id'),
          '$vendor.address1_label' => ctrans('texts.address1'),
          '$vendor.address2_label' => ctrans('texts.address2'),
          '$line_tax_label' => ctrans('texts.line_taxes'),
          '$line_tax_labels_label' => ctrans('texts.line_taxes'),
          '$company.address_label' => ctrans('texts.address'),
          '$user.first_name_label' => ctrans('texts.first_name'),
          '$created_by_user_label' => ctrans('texts.created_by', ['name' => 'Manuel']),
          '$vendor.currency_label' => ctrans('texts.currency'),
          '$company.country_label' => ctrans('texts.country'),
          '$tech_hero_image_label' => ctrans('texts.logo'),
          '$company.website_label' => ctrans('texts.website'),
          '$gross_subtotal_label' => ctrans('texts.subtotal'),
          '$emailSignature_label' => ctrans('texts.email_signature'),
          '$vendor_address_label' => ctrans('texts.address'),
          '$vendor.address_label' => ctrans('texts.address'),
          '$vendor.country_label' => ctrans('texts.country'),
          '$vendor.custom3_label' => ctrans('texts.custom3'),
          '$vendor.custom1_label' => ctrans('texts.custom1'),
          '$vendor.custom4_label' => ctrans('texts.custom4'),
          '$user.last_name_label' => ctrans('texts.last_name'),
          '$vendor.custom2_label' => ctrans('texts.custom2'),
          '$vendor.website_label' => ctrans('texts.website'),
          '$dir_text_align_label' => '',
          '$entity_footer_label' => ctrans('texts.footer'),
          '$entity_images_label' => ctrans('texts.logo'),
          '$contact.email_label' => ctrans('texts.email'),
          '$primary_color_label' => ctrans('texts.primary_color'),
          '$contact.phone_label' => ctrans('texts.phone'),
          '$vendor.number_label' => ctrans('texts.number'),
          '$company.phone_label' => ctrans('texts.phone'),
          '$global_margin_label' => '',
          '$company.state_label' => ctrans('texts.state'),
          '$entity_number_label' => ctrans('texts.purchase_order_number'),
          '$company.email_label' => ctrans('texts.email'),
          '$product.date_label' => ctrans('texts.date'),
          '$vendor.email_label' => ctrans('texts.email'),
          '$entity.terms_label' => ctrans('texts.terms'),
          '$product.item_label' => ctrans('texts.item'),
          '$public_notes_label' => ctrans('texts.public_notes'),
          '$paid_to_date_label' => ctrans('texts.paid_to_date'),
          '$net_subtotal_label' => ctrans('texts.net_subtotal'),
          '$payment.date_label' => ctrans('texts.date'),
          '$vendor.phone_label' => ctrans('texts.phone'),
          '$contact.name_label' => ctrans('texts.name'),
          '$number_short_label' => ctrans('texts.purchase_order_number_short'),
          '$company.name_label' => ctrans('texts.name'),
          '$company.city_label' => ctrans('texts.city'),
          '$vendor.state_label' => ctrans('texts.state'),
          '$company.logo_label' => ctrans('texts.logo'),
          '$company_logo_label' => ctrans('texts.logo'),
          '$description_label' => ctrans('texts.description'),
          '$product.tax_label' => ctrans('texts.tax'),
          '$view_button_label' => ctrans('texts.link'),
          '$status_logo_label' => ctrans('texts.logo'),
          '$partial_due_label' => ctrans('texts.partial_due'),
          '$balance_due_label' => ctrans('texts.balance_due'),
          '$outstanding_label' => ctrans('texts.outstanding'),
          '$payment_due_label' => ctrans('texts.payment_due'),
          '$postal_city_label' => ctrans('texts.postal_city'),
          '$vendor_name_label' => ctrans('texts.vendor_name'),
          '$vendor.name_label' => ctrans('texts.name'),
          '$vendor.city_label' => ctrans('texts.city'),
          '$page_layout_label' => ctrans('texts.page_layout'),
          '$viewButton_label' => ctrans('texts.view'),
          '$amount_due_label' => ctrans('texts.amount_due'),
          '$amount_raw_label' => ctrans('texts.amount'),
          '$vat_number_label' => ctrans('texts.vat_number'),
          '$portal_url_label' => ctrans('texts.link'),
          '$reference_label' => ctrans('texts.reference'),
          '$po_number_label' => ctrans('texts.po_number'),
          '$statement_label' => ctrans('texts.statement'),
          '$view_link_label' => ctrans('texts.link'),
          '$signature_label' => ctrans('texts.signature'),
          '$font_size_label' => ctrans('texts.font_size'),
          '$page_size_label' => ctrans('texts.page_size'),
          '$country_2_label' => ctrans('texts.country'),
          '$firstName_label' => ctrans('texts.name'),
          '$id_number_label' => ctrans('texts.id_number'),
          '$user.name_label' => ctrans('texts.name'),
          '$font_name_label' => ctrans('texts.name'),
          '$auto_bill_label' => ctrans('texts.auto_bill'),
          '$poNumber_label' => ctrans('texts.po_number'),
          '$payments_label' => ctrans('texts.payments'),
          '$viewLink_label' => ctrans('texts.link'),
          '$subtotal_label' => ctrans('texts.subtotal'),
          '$company1_label' => ctrans('texts.company1'),
          '$company2_label' => ctrans('texts.company2'),
          '$company3_label' => ctrans('texts.company3'),
          '$company4_label' => ctrans('texts.company4'),
          '$due_date_label' => ctrans('texts.due_date'),
          '$discount_label' => ctrans('texts.discount'),
          '$address1_label' => ctrans('texts.address1'),
          '$address2_label' => ctrans('texts.address2'),
          '$autoBill_label' => ctrans('texts.auto_bill'),
          '$view_url_label' => ctrans('texts.url'),
          '$font_url_label' => ctrans('texts.url'),
          '$details_label' => ctrans('texts.details'),
          '$balance_label' => ctrans('texts.balance'),
          '$partial_label' => ctrans('texts.partial'),
          '$custom1_label' => ctrans('texts.custom1'),
          '$custom2_label' => ctrans('texts.custom2'),
          '$custom3_label' => ctrans('texts.custom3'),
          '$custom4_label' => ctrans('texts.custom4'),
          '$dueDate_label' => ctrans('texts.due_date'),
          '$country_label' => ctrans('texts.country'),
          '$vendor3_label' => ctrans('texts.vendor3'),
          '$contact_label' => ctrans('texts.contact'),
          '$account_label' => ctrans('texts.company'),
          '$vendor1_label' => ctrans('texts.vendor1'),
          '$vendor4_label' => ctrans('texts.vendor4'),
          '$vendor2_label' => ctrans('texts.vendor2'),
          '$website_label' => ctrans('texts.website'),
          '$app_url_label' => ctrans('texts.url'),
          '$footer_label' => ctrans('texts.footer'),
          '$entity_label' => ctrans('texts.purchase_order'),
          '$thanks_label' => ctrans('texts.thanks'),
          '$amount_label' => ctrans('texts.amount'),
          '$method_label' => ctrans('texts.method'),
          '$vendor_label' => ctrans('texts.vendor'),
          '$number_label' => ctrans('texts.number'),
          '$email_label' => ctrans('texts.email'),
          '$terms_label' => ctrans('texts.terms'),
          '$notes_label' => ctrans('texts.notes'),
          '$tax_rate1_label' => ctrans('texts.tax_rate1'),
          '$tax_rate2_label' => ctrans('texts.tax_rate2'),
          '$tax_rate3_label' => ctrans('texts.tax_rate3'),
          '$refund_label' => ctrans('texts.refund'),
          '$refunded_label' => ctrans('texts.refunded'),
          '$total_label' => ctrans('texts.total'),
          '$taxes_label' => ctrans('texts.taxes'),
          '$phone_label' => ctrans('texts.phone'),
          '$from_label' => ctrans('texts.from'),
          '$item_label' => ctrans('texts.item'),
          '$date_label' => ctrans('texts.date'),
          '$tax_label' => ctrans('texts.tax'),
          '$net_label' => ctrans('texts.net'),
          '$dir_label' => '',
          '$to_label' => ctrans('texts.to'),
          '$amount_paid_label' => ctrans('texts.amount_paid'),
          '$receipt_label' => ctrans('texts.receipt'),
          '$ship_to_label' => ctrans('texts.ship_to'),
          '$delivery_note_label' => ctrans('texts.delivery_note'),
          '$quantity_label' => ctrans('texts.quantity'),
          '$order_number_label' => ctrans('texts.order_number'),
          '$shipping_label' => ctrans('texts.shipping_address'),
        ];
    }
}

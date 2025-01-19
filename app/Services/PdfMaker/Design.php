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

namespace App\Services\PdfMaker;

use App\Models\Credit;
use App\Models\Quote;
use App\Services\PdfMaker\Designs\Utilities\BaseDesign;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;
use App\Utils\Number;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesInvoiceValues;
use DOMDocument;
use Illuminate\Support\Str;

class Design extends BaseDesign
{
    use MakesInvoiceValues;
    use DesignHelpers;
    use MakesDates;

    /** @var \App\Models\Invoice | \App\Models\Quote | \App\Models\Credit | \App\Models\PurchaseOrder | \App\Models\RecurringInvoice */
    public $entity;

    /** @var \App\Models\Client */
    public $client;

    /** @var \App\Models\Vendor */
    public $vendor;

    /** Global state of the design, @var array */
    public $context;

    /** Type of entity => product||task */
    public $type;

    /** Design string */
    public $design;

    /** Construct options */
    public $options;

    public $invoices;

    public $credits;

    public $payments;

    public $unapplied_payments;

    public $settings_object;

    public $company;

    public float $payment_amount_total = 0;

    public float $unapplied_total = 0;

    /** @var array */
    public $aging = [];

    public const BOLD = 'bold';
    public const BUSINESS = 'business';
    public const CLEAN = 'clean';
    public const CREATIVE = 'creative';
    public const ELEGANT = 'elegant';
    public const HIPSTER = 'hipster';
    public const MODERN = 'modern';
    public const PLAIN = 'plain';
    public const PLAYFUL = 'playful';
    public const CUSTOM = 'custom';
    public const CALM = 'calm';

    public const DELIVERY_NOTE = 'delivery_note';
    public const STATEMENT = 'statement';
    public const PURCHASE_ORDER = 'purchase_order';


    public function __construct(string $design = null, array $options = [])
    {
        Str::endsWith('.html', $design) ? $this->design = $design : $this->design = "{$design}.html";

        $this->options = $options;
    }

    public function html(): ?string
    {
        if ($this->design == 'custom.html') {
            $design = $this->composeFromPartials(
                $this->options['custom_partials']
            );
            
            // Remove NULL bytes
            $design = str_replace("\0", '', $design);
            // Remove UTF-7 BOM
            $design = preg_replace('/^\\+ADw-/', '', $design);

            return $design;

        }

        $path = $this->options['custom_path'] ?? config('ninja.designs.base_path');

        $design = file_get_contents(
            $path . $this->design
        );

        
        // Remove NULL bytes
        $design = str_replace("\0", '', $design);
        // Remove UTF-7 BOM
        $design = preg_replace('/^\\+ADw-/', '', $design);

        return $design;

    }

    public function elements(array $context, string $type = 'product'): array
    {
        $this->context = $context;

        $this->type = $type;

        $this->setup();

        return [
            'company-details' => [
                'id' => 'company-details',
                'elements' => $this->companyDetails(),
            ],
            'company-address' => [
                'id' => 'company-address',
                'elements' => $this->companyAddress(),
            ],
            'client-details' => [
                'id' => 'client-details',
                'elements' => $this->clientDetails(),
            ],
            'shipping-details' => [
                'id' => 'shipping-details',
                'elements' => $this->shippingDetails(),
            ],
            'vendor-details' => [
                'id' => 'vendor-details',
                'elements' => $this->vendorDetails(),
            ],
            'entity-details' => [
                'id' => 'entity-details',
                'elements' => $this->entityDetails(),
            ],
            'delivery-note-table' => [
                'id' => 'delivery-note-table',
                'elements' => $this->deliveryNoteTable(),
            ],
            'product-table' => [
                'id' => 'product-table',
                'elements' => $this->productTable(),
            ],
            'task-table' => [
                'id' => 'task-table',
                'elements' => $this->taskTable(),
            ],
            'statement-credit-table' => [
                'id' => 'statement-credit-table',
                'elements' => $this->statementCreditTable(),
            ],
            'statement-credit-table-totals' => [
                'id' => 'statement-credit-table-totals',
                'elements' => $this->statementCreditTableTotals(),
            ],
            'statement-invoice-table' => [
                'id' => 'statement-invoice-table',
                'elements' => $this->statementInvoiceTable(),
            ],
            'statement-unapplied-payment-table' => [
                'id' => 'statement-unapplied-payment-table',
                'elements' => $this->statementUnappliedPaymentTable(),
            ],
            'statement-unapplied-payment-table-totals' => [
                'id' => 'statement-unapplied-payment-table-totals',
                'elements' => $this->statementUnappliedPaymentTableTotals(),
            ],
            'statement-invoice-table-totals' => [
                'id' => 'statement-invoice-table-totals',
                'elements' => $this->statementInvoiceTableTotals(),
            ],
            'statement-payment-table' => [
                'id' => 'statement-payment-table',
                'elements' => $this->statementPaymentTable(),
            ],
            'statement-payment-table-totals' => [
                'id' => 'statement-payment-table-totals',
                'elements' => $this->statementPaymentTableTotals(),
            ],
            'statement-aging-table' => [
                'id' => 'statement-aging-table',
                'elements' => $this->statementAgingTable(),
            ],
            'table-totals' => [
                'id' => 'table-totals',
                'elements' => $this->tableTotals(),
            ],
            'footer-elements' => [
                'id' => 'footer',
                'elements' => [
                    // $this->sharedFooterElements(),
                ],
            ],
        ];
    }


    public function swissQrCodeElement(): array
    {
        if ($this->type == self::DELIVERY_NOTE) {
            return [];
        }

        $elements = [];

        if (strlen($this->company->getSetting('qr_iban')) > 5 && strlen($this->company->getSetting('besr_id')) > 1) {
            $elements[] = ['element' => 'qr_code', 'content' => '$swiss_qr', 'show_empty' => false, 'properties' => ['data-ref' => 'swiss-qr-code']];
        }

        return $elements;
    }

    public function companyDetails(): array
    {
        $variables = $this->context['pdf_variables']['company_details'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function companyAddress(): array
    {
        $variables = $this->context['pdf_variables']['company_address'];

        $elements = [];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_address-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function vendorDetails(): array
    {
        $elements = [];

        if (!$this->vendor) {
            return $elements;
        }

        $variables = $this->context['pdf_variables']['vendor_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'vendor_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function shippingDetails(): array
    {
        $elements = [];

        if (!$this->client || $this->type == self::DELIVERY_NOTE) {
            return $elements;
        }

        $address_variables = [
            '$client.address1',
            '$client.address2',
            '$client.city_state_postal',
            '$client.country',
            '$client.postal_city_state',
            '$client.postal_city',
        ];

        $variables = $this->context['pdf_variables']['client_details'];

        $elements = collect($variables)->filter(function ($variable) use ($address_variables) {
            return in_array($variable, $address_variables);
        })->map(function ($variable) {

            $variable = str_replace('$client.', '$client.shipping_', $variable);
            return ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => "client_details-shipping-" . substr($variable, 1)]];

        })->toArray();

        $header = [];
        $header[] = ['element' => 'div', 'content' => ctrans('texts.shipping_address'), 'properties' => ['data-ref' => 'shipping_address-label', 'style' => 'font-weight: bold; text-transform: uppercase']];

        return array_merge($header, $elements);

    }

    public function clientDetails(): array
    {
        $elements = [];

        if (!$this->client) {//@phpstan-ignore-line
            return $elements;
        }

        if ($this->type == self::DELIVERY_NOTE) {
            $elements = [
                ['element' => 'div', 'content' => ctrans('texts.delivery_note'), 'properties' => ['data-ref' => 'delivery_note-label', 'style' => 'font-weight: bold; text-transform: uppercase']],
                ['element' => 'div', 'content' => $this->client->name, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.name']],
                ['element' => 'div', 'content' => $this->client->shipping_address1, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address1']],
                ['element' => 'div', 'content' => $this->client->shipping_address2, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-client.shipping_address2']],
                ['element' => 'div', 'show_empty' => false, 'elements' => [
                    ['element' => 'span', 'content' => "{$this->client->shipping_city} ", 'properties' => ['ref' => 'delivery_note-client.shipping_city']],
                    ['element' => 'span', 'content' => "{$this->client->shipping_state} ", 'properties' => ['ref' => 'delivery_note-client.shipping_state']],
                    ['element' => 'span', 'content' => "{$this->client->shipping_postal_code} ", 'properties' => ['ref' => 'delivery_note-client.shipping_postal_code']],
                ]],
                ['element' => 'div', 'content' => optional($this->client->shipping_country)->name, 'show_empty' => false],
            ];

            if (!is_null($this->context['contact'])) {
                $elements[] = ['element' => 'div', 'content' => $this->context['contact']->email, 'show_empty' => false, 'properties' => ['data-ref' => 'delivery_note-contact.email']];
            }

            return $elements;
        }

        $variables = $this->context['pdf_variables']['client_details'];

        foreach ($variables as $variable) {
            $elements[] = ['element' => 'div', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'client_details-' . substr($variable, 1)]];
        }

        return $elements;
    }

    public function entityDetails(): array
    {
        if ($this->type === 'statement') {

            $variables = $this->context['pdf_variables']['statement_details'] ?? [];

            $s_date = $this->translateDate($this->options['start_date'], $this->client->date_format(), $this->client->locale()) . " - " . $this->translateDate($this->options['end_date'], $this->client->date_format(), $this->client->locale());

            return [
                ['element' => 'tr', 'properties' => ['data-ref' => 'statement-label'], 'elements' => [
                    ['element' => 'th', 'properties' => [], 'content' => ""],
                    ['element' => 'th', 'properties' => [], 'content' => '<h2>'.ctrans('texts.statement').'</h2>'],
                ]],
                ['element' => 'tr', 'properties' => [], 'elements' => [
                    ['element' => 'th', 'properties' => [], 'content' => ctrans('texts.statement_date')],
                    ['element' => 'th', 'properties' => [], 'content' => $s_date ?? ''],
                ]],
                ['element' => 'tr', 'properties' => [], 'elements' => [
                    ['element' => 'th', 'properties' => [], 'content' => '$balance_due_label'],
                    ['element' => 'th', 'properties' => [], 'content' => Number::formatMoney($this->invoices->sum('balance'), $this->client)],
                ]],
            ];
        }

        $variables = $this->context['pdf_variables']['invoice_details'];

        if ($this->entity instanceof Quote) {
            $variables = $this->context['pdf_variables']['quote_details'];

            if ($this->entity->partial > 0) {
                $variables[] = '$quote.balance_due';
            }
        }

        if ($this->entity instanceof Credit) {
            $variables = $this->context['pdf_variables']['credit_details'];
        }

        if ($this->vendor) { //@phpstan-ignore-line
            $variables = $this->context['pdf_variables']['purchase_order_details'];
        }

        $elements = [];

        // We don't want to show account balance or invoice total on PDF.. or any amount with currency.
        if ($this->type == self::DELIVERY_NOTE) {
            $variables = array_filter($variables, function ($m) {
                return !in_array($m, ['$invoice.balance_due', '$invoice.total', '$invoice.amount']);
            });
        }

        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            /* 2/7/2022 don't show custom values if they are empty */
            $var = str_replace("custom", "custom_value", $_variable);

            if (in_array($_variable, $_customs) && !empty($this->entity->{$var})) {
                $elements[] = ['element' => 'tr', 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $this->entityVariableCheck($variable)], 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => 'entity_details-' . substr($variable, 1)]],
                ]];
            }
        }

        return $elements;
    }


    public function deliveryNoteTable(): array
    {
        if ($this->type !== self::DELIVERY_NOTE) {
            return [];
        }

        $thead = [
            ['element' => 'th', 'content' => '$item_label', 'properties' => ['data-ref' => 'delivery_note-item_label']],
            ['element' => 'th', 'content' => '$description_label', 'properties' => ['data-ref' => 'delivery_note-description_label']],
            ['element' => 'th', 'content' => '$product.quantity_label', 'properties' => ['data-ref' => 'delivery_note-product.quantity_label']],
        ];

        $items = $this->transformLineItems($this->entity->line_items, $this->type);

        $this->processNewLines($items);
        $product_customs = [false, false, false, false];

        foreach ($items as $row) {
            for ($i = 0; $i < count($product_customs); $i++) {
                if (!empty($row['delivery_note.delivery_note' . ($i + 1)])) {
                    $product_customs[$i] = true;
                }
            }
        }

        for ($i = 0; $i < count($product_customs); $i++) {
            if ($product_customs[$i]) {
                array_push($thead, ['element' => 'th', 'content' => '$product.product' . ($i + 1) . '_label', 'properties' => ['data-ref' => 'delivery_note-product.product' . ($i + 1) . '_label']]);
            }
        }

        return [
            ['element' => 'thead', 'elements' => $thead],
            ['element' => 'tbody', 'elements' => $this->buildTableBody(self::DELIVERY_NOTE)],
        ];
    }

    /**
     * Parent method for building products table.
     *
     * @return array
     */
    public function productTable(): array
    {
        $product_items = collect($this->entity->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6 || $item->type_id == 5 || $item->type_id == 4;
        });

        if (count($product_items) == 0) {
            return [];
        }

        if ($this->type === self::DELIVERY_NOTE || $this->type === self::STATEMENT) {
            return [];
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('product')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$product')],
        ];
    }

    /**
     * Parent method for building tasks table.
     *
     * @return array
     */
    public function taskTable(): array
    {
        $task_items = collect($this->entity->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        });

        if (count($task_items) == 0) {
            return [];
        }

        if ($this->type === self::DELIVERY_NOTE || $this->type === self::STATEMENT) {
            return [];
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('task')],
            ['element' => 'tbody', 'elements' => $this->buildTableBody('$task')],
        ];
    }

    /**
     * Parent method for building invoices table within statement.
     *
     * @return array
     */
    public function statementInvoiceTable(): array
    {
        if (is_null($this->invoices) || $this->type !== self::STATEMENT) {
            return [];
        }

        $tbody = [];

        foreach ($this->invoices as $invoice) {
            $element = ['element' => 'tr', 'elements' => []];

            $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->date, $this->client->date_format(), $this->client->locale()) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($invoice->due_date, $this->client->date_format(), $this->client->locale()) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($invoice->amount, $this->client) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($invoice->balance, $this->client) ?: ' '];

            $tbody[] = $element;
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_invoice')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    public function statementInvoiceTableTotals(): array
    {

        if ($this->type !== self::STATEMENT) {
            return [];
        }

        $outstanding = $this->invoices->sum('balance');

        return [
            ['element' => 'div', 'content' => '$outstanding_label: ' . Number::formatMoney($outstanding, $this->client)],
        ];
    }

    /**
     * Parent method for building payments table within statement.
     *
     * @return array
     */
    public function statementPaymentTable(): array
    {
        if (is_null($this->payments) && $this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->options) && $this->options['show_payments_table'] === false) {
            return [];
        }

        $tbody = [];

        //24-03-2022 show payments per invoice
        foreach ($this->invoices as $invoice) {
            foreach ($invoice->payments as $payment) {
                if ($payment->is_deleted) {
                    continue;
                }

                $element = ['element' => 'tr', 'elements' => []];

                $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
                $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($payment->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;'];
                $element['elements'][] = ['element' => 'td', 'content' => $payment->translatedType()];
                $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($payment->pivot->amount, $this->client) ?: '&nbsp;'];

                $tbody[] = $element;

                $this->payment_amount_total += $payment->pivot->amount;

                if ($payment->pivot->refunded > 0) {

                    $refund_date = $payment->date;

                    if ($payment->refund_meta && is_array($payment->refund_meta)) {

                        $refund_array = collect($payment->refund_meta)->first(function ($meta) use ($invoice) {
                            foreach ($meta['invoices'] as $refunded_invoice) {

                                if ($refunded_invoice['invoice_id'] == $invoice->id) {
                                    return true;
                                }

                            }
                        });

                        $refund_date = $refund_array['date'];
                    }

                    $element = ['element' => 'tr', 'elements' => []];
                    $element['elements'][] = ['element' => 'td', 'content' => $invoice->number];
                    $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($refund_date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;'];
                    $element['elements'][] = ['element' => 'td', 'content' => ctrans('texts.refund')];
                    $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($payment->pivot->refunded, $this->client) ?: '&nbsp;'];

                    $tbody[] = $element;

                    $this->payment_amount_total -= $payment->pivot->refunded;

                }

            }


        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_payment')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];
    }

    /**
     * Parent method for building payments table within statement.
     *
     * @return array
     */
    public function statementCreditTable(): array
    {
        if (is_null($this->credits) && $this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_credits_table', $this->options) && $this->options['show_credits_table'] === false) {
            return [];
        }

        $tbody = [];

        foreach ($this->credits as $credit) {
            $element = ['element' => 'tr', 'elements' => []];

            $element['elements'][] = ['element' => 'td', 'content' => $credit->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($credit->date, $this->client->date_format(), $this->client->locale()) ?: ' '];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($credit->amount, $this->client)];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($credit->balance, $this->client)];

            $tbody[] = $element;
        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_credit')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];

    }

    public function statementCreditTableTotals(): array
    {
        if ($this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_credits_table', $this->options) && $this->options['show_credits_table'] === false) {
            return [];
        }

        $outstanding = $this->credits->sum('balance');

        return [
            ['element' => 'div', 'content' => '$credit.balance_label: ' . Number::formatMoney($outstanding, $this->client)],
        ];
    }

    public function statementPaymentTableTotals(): array
    {
        if (is_null($this->payments) || !$this->payments->first() || $this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->options) && $this->options['show_payments_table'] === false) {
            return [];
        }

        $payment = $this->payments->first();

        return [
            // ['element' => 'div', 'content' => \sprintf('%s: %s', ctrans('texts.amount_paid'), Number::formatMoney($this->payments->sum('amount'), $this->client))],
            ['element' => 'div', 'content' => \sprintf('%s: %s', ctrans('texts.amount_paid'), Number::formatMoney($this->payment_amount_total, $this->client))],
        ];
    }

    public function statementUnappliedPaymentTableTotals(): array
    {

        if (is_null($this->unapplied_payments) || !$this->unapplied_payments->first() || $this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->options) && $this->options['show_payments_table'] === false) {
            return [];
        }

        return [
            ['element' => 'div', 'content' => \sprintf('%s: %s', ctrans('texts.payment_balance_on_file'), Number::formatMoney($this->unapplied_total, $this->client))],
        ];

    }


    /**
     * Generates the statement unapplied payments table
     *
     * @return array
     *
     */
    public function statementUnappliedPaymentTable(): array
    {

        if (is_null($this->unapplied_payments) && $this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_payments_table', $this->options) && $this->options['show_payments_table'] === false) {
            return [];
        }

        $tbody = [];

        //24-03-2022 show payments per invoice
        foreach ($this->unapplied_payments as $unapplied_payment) {
            if ($unapplied_payment->is_deleted) {
                continue;
            }

            $element = ['element' => 'tr', 'elements' => []];
            $element['elements'][] = ['element' => 'td', 'content' => $unapplied_payment->number];
            $element['elements'][] = ['element' => 'td', 'content' => $this->translateDate($unapplied_payment->date, $this->client->date_format(), $this->client->locale()) ?: '&nbsp;'];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($unapplied_payment->amount, $this->client) ?: '&nbsp;'];
            $element['elements'][] = ['element' => 'td', 'content' => Number::formatMoney($unapplied_payment->amount - $unapplied_payment->applied, $this->client) ?: '&nbsp;'];

            $tbody[] = $element;

            $this->unapplied_total += round($unapplied_payment->amount - $unapplied_payment->applied, 2);

        }

        return [
            ['element' => 'thead', 'elements' => $this->buildTableHeader('statement_unapplied')],
            ['element' => 'tbody', 'elements' => $tbody],
        ];

    }


    public function statementAgingTable(): array
    {
        if ($this->type !== self::STATEMENT) {
            return [];
        }

        if (\array_key_exists('show_aging_table', $this->options) && $this->options['show_aging_table'] === false) {
            return [];
        }

        $elements = [
            ['element' => 'thead', 'elements' => []],
            ['element' => 'tbody', 'elements' => [
                ['element' => 'tr', 'elements' => []],
            ]],
        ];

        foreach ($this->aging as $column => $value) {
            $elements[0]['elements'][] = ['element' => 'th', 'content' => $column];
            $elements[1]['elements'][] = ['element' => 'td', 'content' => $value];
        }

        return $elements;
    }

    /**
     * Generate the structure of table headers. (<thead/>)
     *
     * @param string $type "product" or "task"
     * @return array
     */
    public function buildTableHeader(string $type): array
    {

        $elements = [];

        // Some of column can be aliased. This is simple workaround for these.
        $aliases = [
            '$product.product_key' => '$product.item',
            '$task.product_key' => '$task.service',
            '$task.rate' => '$task.cost',
        ];

        $table_type = "{$type}_columns";

        $column_type = $type;

        if ($type == 'product' && $this->entity instanceof Quote && !$this->settings_object->getSetting('sync_invoice_quote_columns')) {
            $table_type = "product_quote_columns";
            $column_type = 'product_quote';
        }

        $this->processTaxColumns($column_type);

        $column_visibility = $this->getColumnVisibility($this->entity->line_items, $type);

        foreach ($this->context['pdf_variables'][$table_type] as $column) {
            if (array_key_exists($column, $aliases)) {
                $elements[] = ['element' => 'th', 'content' => $aliases[$column] . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($aliases[$column], 1) . '-th', 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$product.discount' && !$this->company->enable_product_discount) {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'style' => 'display: none;']];
            } elseif ($column == '$product.tax_rate1') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax1-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$product.tax_rate2') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax2-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } elseif ($column == '$product.tax_rate3') {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-product.tax3-th", 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            } else {
                $elements[] = ['element' => 'th', 'content' => $column . '_label', 'properties' => ['data-ref' => "{$type}_table-" . substr($column, 1) . '-th', 'visi' => $this->visibilityCheck($column_visibility, $column)]];
            }
        }
                
        $visible_elements = array_filter($elements, function ($element) {
            return $element['properties']['visi'] ?? true;
        });

        if (!empty($visible_elements)) {
            $first_visible = array_key_first($visible_elements);
            $last_visible = array_key_last($visible_elements);

            // Add class to first visible element
            if (!isset($elements[$first_visible]['properties']['class'])) {//@phpstan-ignore-line
                $elements[$first_visible]['properties']['class'] = 'left-radius';
            } else {
                $elements[$first_visible]['properties']['class'] .= 'left-radius';
            }

            // Add class to last visible element
            if (!isset($elements[$last_visible]['properties']['class'])) {
                $elements[$last_visible]['properties']['class'] = 'right-radius';
            } else {
                $elements[$last_visible]['properties']['class'] .= 'right-radius';
            }
        }

        $elements = array_map(function ($element) {
            if (isset($element['properties']['visi'])) {
                if ($element['properties']['visi'] === false) {
                    $element['properties']['style'] = 'display: none;';
                }
                unset($element['properties']['visi']);
            }
            return $element;
        }, $elements);

        return $elements;
    }

    private function visibilityCheck(array $column_visibility, string $column): bool
    {
        if(!$this->settings_object->getSetting('hide_empty_columns_on_pdf')){
            return true;
        }

        if(array_key_exists($column, $column_visibility)){
            return !$column_visibility[$column];
        }

        return true;
    }

    /**
     * Generate the structure of table body. (<tbody/>)
     *
     * @param string $type "$product" or "$task"
     * @return array
     */
    public function buildTableBody(string $type): array
    {
        $elements = [];

        $items = $this->transformLineItems($this->entity->line_items, $type);

        $this->processNewLines($items);

        if (count($items) == 0) {
            return [];
        }

        if ($type == self::DELIVERY_NOTE) {
            $product_customs = [false, false, false, false];

            foreach ($items as $row) {
                for ($i = 0; $i < count($product_customs); $i++) {
                    if (!empty($row['delivery_note.delivery_note' . ($i + 1)])) {
                        $product_customs[$i] = true;
                    }
                }
            }

            foreach ($items as $row) {
                $element = ['element' => 'tr', 'elements' => []];

                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.product_key'], 'properties' => ['data-ref' => 'delivery_note_table.product_key-td']];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.notes'], 'properties' => ['data-ref' => 'delivery_note_table.notes-td']];
                $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.quantity'], 'properties' => ['data-ref' => 'delivery_note_table.quantity-td']];

                for ($i = 0; $i < count($product_customs); $i++) {
                    if ($product_customs[$i]) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['delivery_note.delivery_note' . ($i + 1)], 'properties' => ['data-ref' => 'delivery_note_table.product' . ($i + 1) . '-td']];
                    }
                }

            $visible_elements = array_filter($element['elements'], function ($el) {
                if (isset($el['properties']['visi']) && $el['properties']['visi']) {
                    return true;
                }
                return false;
            });

            if (!empty($visible_elements)) {
                $first_visible = array_key_first($visible_elements);
                $last_visible = array_key_last($visible_elements);

                // Add class to first visible cell
                if (!isset($element['elements'][$first_visible]['properties']['class'])) { //@phpstan-ignore-line
                    $element['elements'][$first_visible]['properties']['class'] = 'left-radius';
                } else {
                    $element['elements'][$first_visible]['properties']['class'] .= ' left-radius';
                }

                // Add class to last visible cell
                if (!isset($element['elements'][$last_visible]['properties']['class'])) {
                    $element['elements'][$last_visible]['properties']['class'] = 'right-radius';
                } else {
                    $element['elements'][$last_visible]['properties']['class'] .= ' right-radius';
                }
            }

                // Then, filter the elements array
                $element['elements'] = array_map(function ($el) {
                    if (isset($el['properties']['visi'])) {
                        if ($el['properties']['visi'] === false) {
                            $el['properties']['style'] = 'display: none;';
                        }
                        unset($el['properties']['visi']);
                    }
                    return $el;
                }, $element['elements']);

                $elements[] = $element;

            }

            return $elements;
        }

        $_type = Str::startsWith($type, '$') ? ltrim($type, '$') : $type;
        $table_type = "{$_type}_columns";

        if ($_type == 'product' && $this->entity instanceof Quote && !$this->settings_object->getSetting('sync_invoice_quote_columns')) {
            $table_type = "product_quote_columns";
        }

        foreach ($items as $row) {
            $element = ['element' => 'tr', 'elements' => []];

            if (
                array_key_exists($type, $this->context) &&
                !empty($this->context[$type]) &&
                !is_null($this->context[$type])
            ) {
                $document = new DOMDocument();
                $document->loadHTML($this->context[$type], LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

                $td = $document->getElementsByTagName('tr')->item(0);

                if ($td) {
                    foreach ($td->childNodes as $child) {
                        if ($child->nodeType !== 1) {
                            continue;
                        }

                        if ($child->tagName !== 'td') {
                            continue;
                        }

                        $element['elements'][] = ['element' => 'td', 'content' => strtr($child->nodeValue, $row)];
                    }
                }
            } else {

                $column_visibility = $this->getColumnVisibility($this->entity->line_items, $_type);

                foreach ($this->context['pdf_variables'][$table_type] as $key => $cell) {
                    // We want to keep aliases like these:
                    // $task.cost => $task.rate
                    // $task.quantity => $task.hours

                    if ($cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.cost'], 'properties' => ['data-ref' => 'task_table-task.cost-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.discount' && !$this->company->enable_product_discount) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$product.discount'], 'properties' => ['data-ref' => 'product_table-product.discount-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$task.hours') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.quantity'], 'properties' => ['data-ref' => 'task_table-task.hours-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.tax_rate1') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax1-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.tax_rate2') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax2-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$product.tax_rate3') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'product_table-product.tax3-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$task.discount' && !$this->company->enable_product_discount) {
                        $element['elements'][] = ['element' => 'td', 'content' => $row['$task.discount'], 'properties' => ['data-ref' => 'task_table-task.discount-td', 'style' => 'display: none;']];
                    } elseif ($cell == '$task.tax_rate1') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'task_table-task.tax1-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$task.tax_rate2') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'task_table-task.tax2-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } elseif ($cell == '$task.tax_rate3') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => 'task_table-task.tax3-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    }elseif ($cell == '$product.unit_cost' || $cell == '$task.rate') {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['style' => 'white-space: nowrap;', 'data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    } else {
                        $element['elements'][] = ['element' => 'td', 'content' => $row[$cell], 'properties' => ['data-ref' => "{$_type}_table-" . substr($cell, 1) . '-td', 'visi' => $this->visibilityCheck($column_visibility, $cell)]];
                    }
                }
            }

            // Then, filter the elements array
            $element['elements'] = array_map(function ($el) {
                if (isset($el['properties']['visi'])) {
                    if ($el['properties']['visi'] === false) {
                        $el['properties']['style'] = 'display: none;';
                    }
                    unset($el['properties']['visi']);
                }
                return $el;
            }, $element['elements']);

            $elements[] = $element;

        }

        $document = null;

        return $elements;
    }

    private function getColumnVisibility(array $items, string $type_id): array
    {
        // Convert type_id to numeric
        $type_id = $type_id === 'product' ? '1' : '2';

        // Filter items by type_id
        $filtered_items = collect($items)->filter(function ($item) use ($type_id) {
            return (!isset($item->type_id) || $item->type_id == $type_id) ||
                ($type_id == '1' && ($item->type_id == '4' || $item->type_id == '5' || $item->type_id == '6'));
        });

        // Transform the items first
        $transformed_items = $this->transformLineItems(
            $filtered_items->toArray(),
            $type_id === '1' ? '$product' : '$task'
        );
        
        $columns = [];

        // Initialize all columns as empty
        if (!empty($transformed_items)) {
            $firstRow = reset($transformed_items);
            foreach (array_keys($firstRow) as $column) {
                $columns[$column] = true;
            }
        }

        // Check each column for non-empty values
        foreach ($transformed_items as $row) {
            foreach ($row as $key => $value) {
                if (!empty($value)) {
                    $columns[$key] = false;
                }
            }
        }

        return $columns;


    }

    public function tableTotals(): array
    {
        if ($this->type === self::STATEMENT) {
            return [
                ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                    ['element' => 'div', 'properties' => ['style' => 'display: block; align-items: flex-start; page-break-inside: avoid; visible !important;'], 'elements' => [
                        ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem; margin-top: 1.5rem;', 'hidden' => $this->entity->user->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                    ]],
                ]],
            ];
        }

        $_variables = array_key_exists('variables', $this->context)
            ? $this->context['variables']
            : ['values' => ['$entity.public_notes' => $this->entity->public_notes, '$entity.terms' => $this->entity->terms, '$entity_footer' => $this->entity->footer], 'labels' => []];

        $variables = $this->context['pdf_variables']['total_columns'];
        $show_terms_label = $this->entityVariableCheck('$entity.terms') ? 'display: none;' : '';

        
        $elements = [
            ['element' => 'div', 'properties' => ['style' => 'display: flex; flex-direction: column;'], 'elements' => [
                ['element' => 'div', 'properties' => ['data-ref' => 'total_table-public_notes', 'style' => 'text-align: left;'], 'elements' => [
                    ['element' => 'span', 'content' => strtr(str_replace(["labels", "values"], ["",""], $_variables['values']['$entity.public_notes']), $_variables)]
                ]],
                ['element' => 'div', 'content' => '', 'properties' => ['style' => 'text-align: left; display: flex; flex-direction: column; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'span', 'content' => '$entity.terms_label: ', 'properties' => ['data-ref' => 'total_table-terms-label', 'style' => "font-weight: bold; text-align: left; margin-top: 1rem; {$show_terms_label}"]],
                    ['element' => 'span', 'content' => strtr(str_replace("labels", "", $_variables['values']['$entity.terms']), $_variables['labels']), 'properties' => ['data-ref' => 'total_table-terms', 'style' => 'text-align: left;']],
                ]],
                ['element' => 'img', 'properties' => ['style' => 'max-width: 50%; height: auto;', 'src' => '$contact.signature', 'id' => 'contact-signature']],
                ['element' => 'div', 'properties' => ['style' => 'display: flex; align-items: flex-start; page-break-inside: auto;'], 'elements' => [
                    ['element' => 'img', 'properties' => ['src' => '$invoiceninja.whitelabel', 'style' => 'height: 2.5rem; margin-top: 1.5rem;', 'hidden' => $this->entity->user->account->isPaid() ? 'true' : 'false', 'id' => 'invoiceninja-whitelabel-logo']],
                ]],
            ]],
            ['element' => 'div', 'properties' => ['class' => 'totals-table-right-side', 'dir' => '$dir'], 'elements' => []],
        ];


        if ($this->type == self::DELIVERY_NOTE) {
            return $elements;
        }

        if ($this->entity instanceof Quote) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$outstanding', $variables)) {
                $variables = \array_diff($variables, ['$outstanding']);
            }

            if ($this->entity->partial > 0) {
                $variables[] = '$partial_due';
            }

            if (in_array('$paid_to_date', $variables)) {
                $variables = \array_diff($variables, ['$paid_to_date']);
            }
        }

        if ($this->entity instanceof Credit) {
            // We don't want to show Balanace due on the quotes.
            if (in_array('$paid_to_date', $variables)) {
                $variables = \array_diff($variables, ['$paid_to_date']);
            }
        }

        foreach (['discount'] as $property) {
            $variable = sprintf('%s%s', '$', $property);

            if (
                !is_null($this->entity->{$property}) &&
                !empty($this->entity->{$property}) &&
                $this->entity->{$property} != 0
            ) {
                continue;
            }

            $variables = array_filter($variables, function ($m) use ($variable) {
                return $m != $variable;
            });
        }

        foreach ($variables as $variable) {
            if ($variable == '$total_taxes') {
                $taxes = $this->entity->calc()->getTotalTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->entity instanceof \App\Models\PurchaseOrder ? $this->vendor : $this->client), 'properties' => ['data-ref' => 'totals-table-total_tax_' . $i]],
                    ]];
                }
            } elseif ($variable == '$line_taxes') {
                $taxes = $this->entity->calc()->getTaxMap();

                if (!$taxes) {
                    continue;
                }

                foreach ($taxes as $i => $tax) {
                    $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                        ['element' => 'span', 'content', 'content' => $tax['name'], 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i . '-label']],
                        ['element' => 'span', 'content', 'content' => Number::formatMoney($tax['total'], $this->entity instanceof \App\Models\PurchaseOrder ? $this->vendor : $this->client), 'properties' => ['data-ref' => 'totals-table-line_tax_' . $i]],
                    ]];
                }
            } elseif (Str::startsWith($variable, '$custom_surcharge')) {
                $_variable = ltrim($variable, '$'); // $custom_surcharge1 -> custom_surcharge1
                $visible = intval(str_replace(['0','.'], '', $this->entity->{$_variable})) != 0;

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } elseif (Str::startsWith($variable, '$custom')) {
                $field = explode('_', $variable);
                $visible = is_object($this->company->custom_fields) && property_exists($this->company->custom_fields, $field[1]) && !empty($this->company->custom_fields->{$field[1]});

                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['hidden' => !$visible, 'data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            } else {
                $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
                    ['element' => 'span', 'content' => $variable . '_label', 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1) . '-label']],
                    ['element' => 'span', 'content' => $variable, 'properties' => ['data-ref' => 'totals_table-' . substr($variable, 1)]],
                ]];
            }
        }

        $elements[1]['elements'][] = ['element' => 'div', 'elements' => [
            ['element' => 'span', 'content' => '',],
            ['element' => 'span', 'content' => ''],
        ]];

        return $elements;
    }
}

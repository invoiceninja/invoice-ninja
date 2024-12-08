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

namespace App\Services\PdfMaker\Designs\Utilities;

use App\Utils\Traits\MakesHash;
use DOMDocument;
use DOMXPath;
use Exception;
use League\CommonMark\CommonMarkConverter;

trait DesignHelpers
{
    use MakesHash;

    public $document;

    public $xpath;

    public function setup(): self
    {
        $this->syncPdfVariables();

        if (isset($this->context['vendor'])) {
            $this->vendor = $this->context['vendor'];
        }

        if (isset($this->context['client'])) {
            $this->client = $this->context['client'];
        }

        if (isset($this->context['entity'])) {
            $this->entity = $this->context['entity'];
        }

        if (isset($this->context['invoices'])) {
            $this->invoices = $this->context['invoices'];

            if ($this->invoices->count() >= 1) {
                $this->entity = $this->invoices->first();
            }
        }

        if (isset($this->context['payments'])) {
            $this->payments = $this->context['payments'];
        }

        if (isset($this->context['unapplied'])) {
            $this->unapplied_payments = $this->context['unapplied'];
        }

        if (isset($this->context['credits'])) {
            $this->credits = $this->context['credits'];
        }

        if (isset($this->context['aging'])) {
            $this->aging = $this->context['aging'];
        }

        $this->document();

        $this->settings_object = $this->vendor ? $this->vendor->company : $this->client; //@phpstan-ignore-line

        $this->company = $this->vendor ? $this->vendor->company : $this->client->company; //@phpstan-ignore-line

        return $this;
    }

    protected function syncPdfVariables(): void
    {
        $default = (array) \App\DataMapper\CompanySettings::getEntityVariableDefaults();
        $variables = $this->context['pdf_variables'];

        foreach ($default as $property => $value) {
            if (array_key_exists($property, $variables)) {
                continue;
            }

            $variables[$property] = $value;
        }

        $this->context['pdf_variables'] = $variables;
    }

    /**
     * Initialize local dom document instance. Used for getting raw HTML out of template.
     *
     * @return $this
     */
    public function document(): self
    {
        $document = new DOMDocument();

        $document->validateOnParse = true;
        @$document->loadHTML($this->html());

        $this->document = $document;
        $this->xpath = new DOMXPath($document);

        return $this;
    }

    /**
     * Get specific section HTML.
     *
     * @param string $section
     * @param bool $id
     * @return null|string
     */
    public function getSectionHTML(string $section, $id = true): ?string
    {
        if ($id) {
            $element = $this->document->getElementById($section);
        } else {
            $elements = $this->document->getElementsByTagName($section);
            $element = $elements[0];
        }

        $document = new DOMDocument();
        $document->preserveWhiteSpace = false;
        $document->formatOutput = true;

        if ($element) {
            $document->appendChild(
                $document->importNode($element, true)
            );

            $html = $document->saveHTML();

            return str_replace('%24', '$', $html);
        }

        return '';
    }

    /**
     * This method will help us decide either we show
     * one "tax rate" column in the table or 3 custom tax rates.
     *
     * Logic below will help us calculate that & inject the result in the
     * global state of the $context (design state).
     *
     * @param string $type "product" or "task"
     * @return void
     */
    public function processTaxColumns(string $type): void
    {
        $column_type = $type;

        if ($type == 'product') {
            $type_id = 1;
        }

        if ($type == 'task') {
            $type_id = 2;
        }

        /** 17-05-2023 need to explicity define product_quote here */
        if ($type == 'product_quote') {
            $type_id = 1;
            $column_type = 'product_quote';
            $type = 'product';
        }


        // At the moment we pass "task" or "product" as type.
        // However, "pdf_variables" contains "$task.tax" or "$product.tax" <-- Notice the dollar sign.
        // This sprintf() will help us convert "task" or "product" into "$task" or "$product" without
        // evaluating the variable.

        if (in_array(sprintf('%s%s.tax', '$', $type), (array) $this->context['pdf_variables']["{$column_type}_columns"])) {
            $line_items = collect($this->entity->line_items)->filter(function ($item) use ($type_id) { //@phpstan-ignore-line
                return $item->type_id = $type_id;
            });

            $tax1 = $line_items->where('tax_name1', '<>', '')->where('type_id', $type_id)->count();
            $tax2 = $line_items->where('tax_name2', '<>', '')->where('type_id', $type_id)->count();
            $tax3 = $line_items->where('tax_name3', '<>', '')->where('type_id', $type_id)->count();

            $taxes = [];

            if ($tax1 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate1', '$', $type));
            }

            if ($tax2 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate2', '$', $type));
            }

            if ($tax3 > 0) {
                array_push($taxes, sprintf('%s%s.tax_rate3', '$', $type));
            }

            $key = array_search(sprintf('%s%s.tax', '$', $type), $this->context['pdf_variables']["{$column_type}_columns"], true);

            if ($key !== false) {
                array_splice($this->context['pdf_variables']["{$column_type}_columns"], $key, 1, $taxes);
            }
        }
    }

    /**
     * Calculates the remaining colspans.
     *
     * @param int $taken
     * @return int
     */
    public function calculateColspan(int $taken): int
    {
        $total = (int) count($this->context['pdf_variables']['product_columns']);

        return (int) $total - $taken;
    }

    /**
     * Return "true" or "false" based on null or empty check.
     * We need to return false as string because of HTML parsing.
     *
     * @param mixed $property
     * @return string
     */
    public function toggleHiddenProperty($property): string
    {
        if (is_null($property)) {
            return 'false';
        }

        if (empty($property)) {
            return 'false';
        }

        return 'true';
    }

    public function sharedFooterElements()
    {
        // We want to show headers for statements, no exceptions.
        $statements = "
            document.querySelectorAll('#statement-credit-table > thead > tr > th, #statement-invoice-table > thead > tr > th, #statement-payment-table > thead > tr > th, #statement-aging-table > thead > tr > th').forEach(t => {
                t.hidden = false;
            });
        ";

        // $javascript = 'document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("#product-table > tbody > tr > td, #task-table > tbody > tr > td, #delivery-note-table > tbody > tr > td").forEach(t=>{if(""!==t.innerText){let e=t.getAttribute("data-ref").slice(0,-3);document.querySelector(`th[data-ref="${e}-th"]`).removeAttribute("hidden")}}),document.querySelectorAll("#product-table > tbody > tr > td, #task-table > tbody > tr > td, #delivery-note-table > tbody > tr > td").forEach(t=>{let e=t.getAttribute("data-ref").slice(0,-3);(e=document.querySelector(`th[data-ref="${e}-th"]`)).hasAttribute("hidden")&&""==t.innerText&&t.setAttribute("hidden","true")})},!1);';
        $javascript = 'document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll("#custom-table > tbody > tr >td, #product-table > tbody > tr > td, #task-table > tbody > tr > td, #delivery-note-table > tbody > tr > td").forEach(t=>{if(""!==t.innerText){let e=t.getAttribute("data-ref").slice(0,-3);document.querySelector(`th[data-ref="${e}-th"]`).removeAttribute("hidden")}}),document.querySelectorAll("#custom-table > tbody > tr > td, #product-table > tbody > tr > td, #task-table > tbody > tr > td, #delivery-note-table > tbody > tr > td").forEach(t=>{let e=t.getAttribute("data-ref").slice(0,-3);(e=document.querySelector(`th[data-ref="${e}-th"]`)).hasAttribute("hidden")&&""==t.innerText&&t.setAttribute("hidden","true")})},!1);';

        // Previously we've been decoding the HTML on the backend and XML parsing isn't good options because it requires,
        // strict & valid HTML to even output/decode. Decoding is now done on the frontend with this piece of Javascript.

        $html_decode = 'document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(`[data-state="encoded-html"]`).forEach(e=>e.innerHTML=e.innerText)},!1);';

        return ['element' => 'div', 'elements' => [
            ['element' => 'script', 'content' => $statements],
            ['element' => 'script', 'content' => $javascript],
            ['element' => 'script', 'content' => $html_decode],
        ]];
    }


    public function entityVariableCheck(string $variable): bool
    {
        // Extract $invoice.date => date
        // so we can append date as $entity->date and not $entity->$invoice.date;

        // When it comes to invoice balance, we'll always show it.
        if ($variable == '$invoice.total') {
            return false;
        }

        // Some variables don't map 1:1 to table columns. This gives us support for such cases.
        $aliases = [
            '$quote.balance_due' => 'partial',
            '$purchase_order.po_number' => 'number',
            '$purchase_order.total' => 'amount',
            '$purchase_order.due_date' => 'due_date',
            '$purchase_order.balance_due' => 'balance_due',
            '$credit.valid_until' => 'due_date',
        ];

        try {
            $_variable = explode('.', $variable)[1];
        } catch (Exception $e) {
            throw new Exception('Company settings seems to be broken. Missing $entity.variable type.');
        }

        if (\in_array($variable, \array_keys($aliases))) {
            $_variable = $aliases[$variable];
        }

        if (is_null($this->entity->{$_variable})) {
            return true;
        }

        if (empty($this->entity->{$_variable})) {
            return true;
        }

        return false;
    }

    public function entityVariableCheckx(string $variable): string
    {
        // Extract $invoice.date => date
        // so we can append date as $entity->date and not $entity->$invoice.date;

        // When it comes to invoice balance, we'll always show it.
        if ($variable == '$invoice.total') {
            return 'visible';
        }

        // Some variables don't map 1:1 to table columns. This gives us support for such cases.
        $aliases = [
            '$quote.balance_due' => 'partial',
            '$purchase_order.po_number' => 'number',
            '$purchase_order.total' => 'amount',
            '$purchase_order.due_date' => 'due_date',
            '$purchase_order.balance_due' => 'balance_due',
            '$credit.valid_until' => 'due_date',
        ];

        try {
            $_variable = explode('.', $variable)[1];
        } catch (Exception $e) {
            nlog("Company settings seems to be broken. Could not resolve {$variable} type.");
            return 'collapse';
        }

        if (\in_array($variable, \array_keys($aliases))) {
            $_variable = $aliases[$variable];
        }

        if (is_null($this->entity->{$_variable})) {
            return 'collapse';
        }

        if (empty($this->entity->{$_variable})) {
            return 'collapse';
        }

        return 'visible';
    }

    public function composeFromPartials(array $partials)
    {
        $html = '';

        $html .= $partials['includes'];
        $html .= $partials['header'];
        $html .= $partials['body'];
        $html .= $partials['footer'];

        return $html;
    }

    public function processCustomColumns(string $type): void
    {
        $custom_columns = [];

        foreach ((array) $this->client->company->custom_fields as $field => $value) {
            info($field);

            if (\Illuminate\Support\Str::startsWith($field, $type)) {
                $custom_columns[] = '$'.$type.'.'.$field;
            }
        }

        $key = array_search(sprintf('%s%s.description', '$', $type), $this->context['pdf_variables']["{$type}_columns"], true);

        if ($key !== false) {
            array_splice($this->context['pdf_variables']["{$type}_columns"], $key + 1, 0, $custom_columns);
        }
    }

    public function getCustomFieldValue(string $field): string
    {
        // In custom_fields column we store fields like: company1-4,
        // while in settings, they're stored in custom_value1-4 format.
        // That's why we need this mapping.

        $fields = [
            'company1' => 'custom_value1',
            'company2' => 'custom_value2',
            'company3' => 'custom_value3',
            'company4' => 'custom_value4',
        ];

        if (! array_key_exists($field, $fields)) {
            return '';
        }

        if ($this->client->company->custom_fields && ! property_exists($this->client->company->custom_fields, $field)) { //@phpstan-ignore-line
            return '';
        }

        $value = $this->client->company->getSetting($fields[$field]);

        return (new \App\Utils\Helpers())->formatCustomFieldValue(
            $this->client->company->custom_fields,
            $field,
            $value,
            $this->client
        );
    }

    /**
     * @todo - this is being called directl, - not through the calling class!!!!
     * @design_flaw
     */
    public static function parseMarkdownToHtml(string $markdown): ?string
    {
        // Use setting to determinate if parsing should be done.
        // 'parse_markdown_on_pdfs'

        $converter = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        return $converter->convert($markdown);
    }

    public function processNewLines(array &$items): void
    {
        foreach ($items as $key => $item) {
            foreach ($item as $variable => $value) {
                // $item[$variable] = nl2br($value, true);
                $item[$variable] = str_replace("\n", '<br>', $value);
            }

            $items[$key] = $item;
        }
    }
}

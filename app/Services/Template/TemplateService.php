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

namespace App\Services\Template;

use App\Models\Task;
use App\Models\User;
use App\Models\Quote;
use App\Utils\Number;
use Twig\Error\Error;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Vendor;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Utils\HtmlEngine;
use Twig\Error\LoaderError;
use Twig\Error\SyntaxError;
use Twig\Error\RuntimeError;
use App\Models\PurchaseOrder;
use App\Utils\Traits\MakesHash;
use App\Utils\VendorHtmlEngine;
use Twig\Sandbox\SecurityError;
use App\Models\RecurringInvoice;
use App\Utils\PaymentHtmlEngine;
use App\Utils\Traits\MakesDates;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\Traits\Pdf\PdfMaker;
use Twig\Extra\Intl\IntlExtension;
use League\CommonMark\CommonMarkConverter;

class TemplateService
{
    use MakesDates;
    use PdfMaker;
    use MakesHash;

    private \DomDocument $document;

    public \Twig\Environment $twig;

    private string $compiled_html = '';

    private array $data = [];

    private array $variables = [];

    private array $global_vars = [];

    public ?Company $company = null;

    private ?Client $client = null;

    private ?Vendor $vendor = null;

    private Invoice | Quote | Credit | PurchaseOrder | RecurringInvoice | Task | Project $entity;

    private Payment $payment;

    private CommonMarkConverter $commonmark;

    private ?object $settings = null;

    public function __construct(public ?Design $template = null)
    {
        $this->template = $template;
        $this->init();
    }

    /**
     * Boot Dom Document
     *
     * @return self
     */
    private function init(): self
    {

        $this->commonmark = new CommonMarkConverter([
            'allow_unsafe_links' => false,
        ]);

        $this->document = new \DOMDocument();
        $this->document->validateOnParse = true;

        $loader = new \Twig\Loader\FilesystemLoader(storage_path());
        $this->twig = new \Twig\Environment($loader, [
            'debug' => true,
        ]);

        $string_extension = new \Twig\Extension\StringLoaderExtension();
        $this->twig->addExtension($string_extension);
        $this->twig->addExtension(new IntlExtension());
        $this->twig->addExtension(new \Twig\Extension\DebugExtension());

        $function = new \Twig\TwigFunction('img', \Closure::fromCallable(function (string $image_src, string $image_style = '') {
            $html = '<img src="' . $image_src . '" style="' . $image_style . '"></img>';

            return new \Twig\Markup($html, 'UTF-8');

        }));
        $this->twig->addFunction($function);

        $function = new \Twig\TwigFunction('t', \Closure::fromCallable(function (string $text_key) {
            return ctrans("texts.{$text_key}");
        }));
        $this->twig->addFunction($function);

        $filter = new \Twig\TwigFilter('sum', \Closure::fromCallable(function (?array $array, ?string $column) {
            if (!is_array($array)) {
                return 0;
            }

            return array_sum(array_column($array, $column));
        }));
        $this->twig->addFilter($filter);

        $allowedTags = ['if', 'for', 'set', 'filter'];
        $allowedFilters = ['replace', 'escape', 'e', 'upper', 'lower', 'capitalize', 'filter', 'length', 'merge','format_currency', 'format_number','format_percent_number','map', 'join', 'first', 'date', 'sum', 'number_format','nl2br','striptags'];
        $allowedFunctions = ['range', 'cycle', 'constant', 'date','img','t'];
        $allowedProperties = ['type_id'];
        // $allowedMethods = ['img','t'];
        $allowedMethods = [
            'Illuminate\Support\Collection' => ['__toString'],
        ];

        $policy = new \Twig\Sandbox\SecurityPolicy($allowedTags, $allowedFilters, $allowedMethods, $allowedProperties, $allowedFunctions);
        $this->twig->addExtension(new \Twig\Extension\SandboxExtension($policy, true));

        return $this;
    }

    /**
     * Iterate through all of the
     * ninja nodes, and field stacks
     *
     * @param array $data - the payload to be passed into the template
     * @return self
     */
    public function build(array $data): self
    {
        $this->compose()
             ->processData($data)
             ->setGlobals()
             ->parseNinjaBlocks()
             ->processVariables($data)
             ->parseGlobalStacks()
             ->parseVariables();

        return $this;
    }

    /**
     * Initialized a set of HTMLEngine variables
     *
     * @param  array | \Illuminate\Support\Collection $data
     * @return self
     */
    private function processVariables($data): self
    {
        $this->variables = $this->resolveHtmlEngine($data);

        return $this;
    }

    public function setGlobals(): self
    {

        foreach ($this->global_vars as $key => $value) {
            $this->twig->addGlobal($key, $value);
        }

        $this->global_vars = [];

        return $this;
    }

    public function setSettings($settings): self
    {
        $this->settings = $settings;

        return $this;

    }

    private function getSettings(): object
    {
        if ($this->settings) {
            return $this->settings;
        }

        if ($this->client) {
            return $this->client->getMergedSettings();
        }

        return $this->company->settings;
    }

    public function addGlobal(array $var): self
    {
        $this->global_vars = array_merge($this->global_vars, $var);

        return $this;
    }

    /**
     * Returns a Mock Template
     *
     * @return self
     */
    public function mock(): self
    {
        $tm = new TemplateMock($this->company);
        $tm->setSettings($this->getSettings())->init();

        $this->entity = $this->company->invoices()->first() ?? $this->company->quotes()->first() ?? (new \App\Services\Pdf\PdfMock(['entity_type' => 'invoice'], $this->company))->initEntity();

        $this->data = $tm->engines;

        $this->variables = $tm->variables[0];
        $this->twig->addGlobal('currency_code', $this->company->currency()->code);
        $this->twig->addGlobal('show_credits', true);
        $this->twig->addGlobal('show_aging', true);
        $this->twig->addGlobal('show_payments', true);

        $this->parseNinjaBlocks()
             ->parseGlobalStacks()
             ->parseVariables();

        return $this;
    }

    /**
     * Returns the HTML as string
     *
     * @return string
     */
    public function getHtml(): string
    {
        return $this->compiled_html;
    }

    /**
     * Returns the PDF string
     *
     * @return string
     */
    public function getPdf(): string
    {

        if (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($this->compiled_html);
        } else {
            $pdf = $this->makePdf(null, null, $this->compiled_html);
        }

        return $pdf;

    }

    /**
     * Get the parsed data
     *
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    /**
     * Process data variables
     *
     * @param  array | \Illuminate\Support\Collection $data
     * @return self
     */
    public function processData($data): self
    {
        $this->data = $this->preProcessDataBlocks($data);

        return $this;
    }

    /**
     * Parses all Ninja tags in the document
     *
     * @return self
     */
    public function parseNinjaBlocks(): self
    {
        $replacements = [];

        $contents = $this->document->getElementsByTagName('ninja');

        foreach ($contents as $content) {

            $template = $content->ownerDocument->saveHTML($content);

            try {
                $template = $this->twig->createTemplate(html_entity_decode($template));
            } catch (SyntaxError $e) {
                nlog($e->getMessage());
                throw ($e);
            } catch (RuntimeError $e) {
                nlog("runtime = " . $e->getMessage());
                throw ($e);
            } catch (LoaderError $e) {
                nlog("loader = " . $e->getMessage());
                throw ($e);
            } catch (SecurityError $e) {
                nlog("security = " . $e->getMessage());
                throw ($e);
            } catch (Error $e) {
                nlog("error = " . $e->getMessage());
                throw ($e);
            }

            $template = $template->render($this->data);

            $f = $this->document->createDocumentFragment();

            $template = htmlspecialchars($template, ENT_XML1, 'UTF-8');

            $f->appendXML(html_entity_decode($template));

            $replacements[] = $f;

        }

        foreach ($contents as $key => $content) {
            $content->parentNode->replaceChild($replacements[$key], $content);
        }

        $this->save();

        return $this;

    }

    public function setEntity($entity): self
    {
        $this->entity = $entity;

        return $this;
    }

    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }
    /**
     * Parses all variables in the document
     *
     * @return self
     */
    public function parseVariables(): self
    {

        $html = $this->getHtml();

        foreach ($this->variables as $key => $variable) {
            if (isset($variable['labels']) && isset($variable['values'])) {
                $html = strtr($html, $variable['labels']);
                $html = strtr($html, $variable['values']);
            }
        }

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $this->save();

        return $this;
    }

    /**
     * Saves the document and updates the compiled string.
     *
     * @return self
     */
    public function save(): self
    {
        $this->compiled_html = str_replace('%24', '$', $this->document->saveHTML());

        return $this;
    }

    /**
     * compose
     *
     * @return self
     */
    public function compose(): self
    {
        if (!$this->template) {
            return $this;
        }

        $html = '';
        $html .= $this->template->design->includes;
        $html .= $this->template->design->header;
        $html .= $this->template->design->body;
        $html .= $this->template->design->footer;

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        return $this;

    }

    public function setRawTemplate(string $template): self
    {

        @$this->document->loadHTML(mb_convert_encoding($template, 'HTML-ENTITIES', 'UTF-8'));

        return $this;

    }
    /**
     * Inject the template components
     * manually
     *
     * @return self
     */
    public function setTemplate(array $partials): self
    {

        $html = '';
        $html .= $partials['design']['includes'];
        $html .= $partials['design']['header'];
        $html .= $partials['design']['body'];
        $html .= $partials['design']['footer'];

        @$this->document->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        return $this;

    }

    /**
     * Resolves the labels and values needed to replace the string
     * holders in the template.
     *
     * @param  array $data
     * @return array
     */
    private function resolveHtmlEngine(array $data): array
    {
        return collect($data)->map(function ($value, $key) {

            $processed = [];

            if (in_array($key, ['aging', 'unapplied']) || !$value->first() || (in_array($key, ['projects','tasks']) && !$value->first()->client)) {
                return $processed;
            }

            match ($key) {
                'variables' => $processed = $value->first() ?? [], //@phpstan-ignore-line
                'invoices' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->setSettings($this->getSettings())->generateLabelsAndValues() ?? [],
                'quotes' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->setSettings($this->getSettings())->generateLabelsAndValues() ?? [],
                'credits' => $processed = (new HtmlEngine($value->first()->invitations()->first()))->setSettings($this->getSettings())->generateLabelsAndValues() ?? [],
                'payments' => $processed = (new PaymentHtmlEngine($value->first(), $value->first()->client->contacts()->first()))->setSettings($this->getSettings())->generateLabelsAndValues() ?? [], //@phpstan-ignore-line
                'tasks' => $processed = (new HtmlEngine($value->first()->client->invoices()->first()->invitations()->first()))->setSettings($this->getSettings())->generateLabelsAndValues() ?? [],
                'projects' => $processed = (new HtmlEngine($value->first()->client->invoices()->first()->invitations()->first()))->setSettings($this->getSettings())->generateLabelsAndValues() ?? [],
                'purchase_orders' => (new VendorHtmlEngine($value->first()->invitations()->first()))->setSettings($this->getSettings())->generateLabelsAndValues() ?? [],
                'aging' => $processed = [],
                default => $processed = [],
            };

            return $processed;

        })->toArray();

    }

    /**
     * Pre Processes the Data Blocks into
     * Twig consumables
     *
     * @param  array | \Illuminate\Support\Collection $data
     * @return array
     */
    private function preProcessDataBlocks($data): array
    {

        return collect($data)->map(function ($value, $key) {

            $processed = [];

            match ($key) {
                'invoices' => $processed = $this->processInvoices($value),
                'quotes' => $processed = $this->processQuotes($value),
                'credits' => $processed = $this->processCredits($value),
                'payments' => $processed = $this->processPayments($value),
                'tasks' => $processed = $this->processTasks($value),
                'projects' => $processed = $this->processProjects($value),
                'purchase_orders' => $processed = $this->processPurchaseOrders($value),
                'aging' => $processed = $value,
                'unapplied' => $processed = $this->processPayments($value),
                default => $processed = [],
            };

            // nlog(json_encode($processed));

            return $processed;

        })->toArray();
    }

    /**
     * Process Invoices into consumable form for Twig templates
     *
     * @param  array | \Illuminate\Support\Collection $invoices
     * @return array
     */
    public function processInvoices($invoices): array
    {
        $invoices = collect($invoices)
                ->map(function ($invoice) {

                    $payments = [];
                    $this->entity = $invoice;

                    if ($invoice->payments ?? false) {
                        $payments = $invoice->payments->map(function ($payment) {
                            return $this->transformPayment($payment);
                        })->toArray();
                    }

                    return [
                        'amount' => Number::formatMoney($invoice->amount, $invoice->client),
                        'balance' => Number::formatMoney($invoice->balance, $invoice->client),
                        'status_id' => $invoice->status_id,
                        'status' => Invoice::stringStatus($invoice->status_id),
                        'amount_raw' => $invoice->amount ,
                        'balance_raw' => $invoice->balance,
                        'number' => $invoice->number ?: '',
                        'discount' => $invoice->discount,
                        'po_number' => $invoice->po_number ?: '',
                        'date' => $this->translateDate($invoice->date, $invoice->client->date_format(), $invoice->client->locale()),
                        'last_sent_date' => $this->translateDate($invoice->last_sent_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'next_send_date' => $this->translateDate($invoice->next_send_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'due_date' => $this->translateDate($invoice->due_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'terms' => $invoice->terms ?: '',
                        'public_notes' => $invoice->public_notes ?: '',
                        'private_notes' => $invoice->private_notes ?: '',
                        'uses_inclusive_taxes' => (bool) $invoice->uses_inclusive_taxes,
                        'tax_name1' => $invoice->tax_name1 ?? '',
                        'tax_rate1' => (float) $invoice->tax_rate1,
                        'tax_name2' => $invoice->tax_name2 ?? '',
                        'tax_rate2' => (float) $invoice->tax_rate2,
                        'tax_name3' => $invoice->tax_name3 ?? '',
                        'tax_rate3' => (float) $invoice->tax_rate3,
                        'total_taxes' => Number::formatMoney($invoice->total_taxes, $invoice->client),
                        'total_taxes_raw' => $invoice->total_taxes,
                        'is_amount_discount' => (bool) $invoice->is_amount_discount ?? false,//@phpstan-ignore-line
                        'footer' => $invoice->footer ?? '',
                        'partial' => $invoice->partial ?? 0,
                        'partial_due_date' => $this->translateDate($invoice->partial_due_date, $invoice->client->date_format(), $invoice->client->locale()),
                        'custom_value1' => (string) $invoice->custom_value1 ?: '',
                        'custom_value2' => (string) $invoice->custom_value2 ?: '',
                        'custom_value3' => (string) $invoice->custom_value3 ?: '',
                        'custom_value4' => (string) $invoice->custom_value4 ?: '',
                        'custom_surcharge1' => (float) $invoice->custom_surcharge1,
                        'custom_surcharge2' => (float) $invoice->custom_surcharge2,
                        'custom_surcharge3' => (float) $invoice->custom_surcharge3,
                        'custom_surcharge4' => (float) $invoice->custom_surcharge4,
                        'exchange_rate' => (float) $invoice->exchange_rate,
                        'custom_surcharge_tax1' => (bool) $invoice->custom_surcharge_tax1,
                        'custom_surcharge_tax2' => (bool) $invoice->custom_surcharge_tax2,
                        'custom_surcharge_tax3' => (bool) $invoice->custom_surcharge_tax3,
                        'custom_surcharge_tax4' => (bool) $invoice->custom_surcharge_tax4,
                        'line_items' => $invoice->line_items ? $this->padLineItems($invoice->line_items, $invoice->client) : (array) [],
                        'reminder1_sent' => $this->translateDate($invoice->reminder1_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'reminder2_sent' => $this->translateDate($invoice->reminder2_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'reminder3_sent' => $this->translateDate($invoice->reminder3_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'reminder_last_sent' => $this->translateDate($invoice->reminder_last_sent, $invoice->client->date_format(), $invoice->client->locale()),
                        'paid_to_date' => Number::formatMoney($invoice->paid_to_date, $invoice->client),
                        'auto_bill_enabled' => (bool) $invoice->auto_bill_enabled,
                        'client' => $this->getClient($invoice),
                        'payments' => $payments,
                        'total_tax_map' => $invoice->calc()->getTotalTaxMap(),
                        'line_tax_map' => $invoice->calc()->getTaxMap(),
                    ];

                });

        return $invoices->toArray();

    }

    /**
     * Pads Line Items with raw and formatted content
     *
     * @param  array $items
     * @param  Vendor | Client $client_or_vendor
     * @return array
     */
    public function padLineItems(array $items, Vendor | Client $client_or_vendor): array
    {
        return collect($items)->map(function ($item) use ($client_or_vendor) {

            $item->cost_raw = $item->cost ?? 0;
            $item->discount_raw = $item->discount ?? 0;
            $item->line_total_raw = $item->line_total ?? 0;
            $item->gross_line_total_raw = $item->gross_line_total ?? 0;
            $item->tax_amount_raw = $item->tax_amount ?? 0;
            $item->product_cost_raw = $item->product_cost ?? 0;

            $item->cost = Number::formatMoney($item->cost_raw, $client_or_vendor);

            if ($item->is_amount_discount) {
                $item->discount = Number::formatMoney($item->discount_raw, $client_or_vendor);
            }

            $item->line_total = Number::formatMoney($item->line_total_raw, $client_or_vendor);
            $item->gross_line_total = Number::formatMoney($item->gross_line_total_raw, $client_or_vendor);
            $item->tax_amount = Number::formatMoney($item->tax_amount_raw, $client_or_vendor);
            $item->product_cost = Number::formatMoney($item->product_cost_raw, $client_or_vendor);
            $item->task = strlen($item->task_id ?? '') > 1 ? $this->processInvoiceTask($item->task_id) : [];

            return (array)$item;

        })->toArray();
    }

    /**
     * Transforms a Payment into consumable for twig
     *
     * @param  Payment $payment
     * @return array
     */
    private function transformPayment(Payment $payment): array
    {

        $this->payment = $payment;

        $credits = $payment->credits->map(function ($credit) use ($payment) {
            return [
                'credit' => $credit->number,
                'amount_raw' => $credit->pivot->amount,
                'refunded_raw' => $credit->pivot->refunded,
                'net_raw' => $credit->pivot->amount - $credit->pivot->refunded,
                'amount' => Number::formatMoney($credit->pivot->amount, $payment->client),
                'refunded' => Number::formatMoney($credit->pivot->refunded, $payment->client),
                'net' => Number::formatMoney($credit->pivot->amount - $credit->pivot->refunded, $payment->client),
                'is_credit' => true,
                'date' => $this->translateDate($credit->date, $payment->client->date_format(), $payment->client->locale()),
                'created_at' => $this->translateDate($credit->pivot->created_at, $payment->client->date_format(), $payment->client->locale()),
                'updated_at' => $this->translateDate($credit->pivot->updated_at, $payment->client->date_format(), $payment->client->locale()),
                'timestamp' => $credit->pivot->created_at->timestamp,
            ];
        });

        $pivot = $payment->invoices->map(function ($invoice) use ($payment) {
            return [
                'invoice' => $invoice->number,
                'amount_raw' => $invoice->pivot->amount,
                'refunded_raw' => $invoice->pivot->refunded,
                'net_raw' => $invoice->pivot->amount - $invoice->pivot->refunded,
                'amount' => Number::formatMoney($invoice->pivot->amount, $payment->client),
                'refunded' => Number::formatMoney($invoice->pivot->refunded, $payment->client),
                'net' => Number::formatMoney($invoice->pivot->amount - $invoice->pivot->refunded, $payment->client),
                'is_credit' => false,
                'date' => $this->translateDate($invoice->date, $payment->client->date_format(), $payment->client->locale()),
                'created_at' => $this->translateDate($invoice->pivot->created_at, $payment->client->date_format(), $payment->client->locale()),
                'updated_at' => $this->translateDate($invoice->pivot->updated_at, $payment->client->date_format(), $payment->client->locale()),
                'timestamp' => $invoice->pivot->created_at->timestamp,
            ];
        })->concat($credits)->sortBy('timestamp')->toArray();

        return [
            'status' => $payment->stringStatus($payment->status_id),
            'badge' => $payment->badgeForStatus(),
            'amount' => Number::formatMoney($payment->amount, $payment->client),
            'applied' => Number::formatMoney($payment->applied, $payment->client),
            'balance' => Number::formatMoney(($payment->amount - $payment->refunded - $payment->applied), $payment->client),
            'refunded' => Number::formatMoney($payment->refunded, $payment->client),
            'amount_raw' => $payment->amount,
            'applied_raw' => $payment->applied,
            'refunded_raw' => $payment->refunded,
            'balance_raw' => ($payment->amount - $payment->applied),
            'date' => $this->translateDate($payment->date, $payment->client->date_format(), $payment->client->locale()),
            'method' => $payment->translatedType(),
            'currency' => $payment->currency->code ?? $this->company->currency()->code,
            'exchange_rate' => $payment->exchange_rate,
            'transaction_reference' => $payment->transaction_reference,
            'is_manual' => $payment->is_manual,
            'number' => $payment->number,
            'custom_value1' => $payment->custom_value1 ?? '',
            'custom_value2' => $payment->custom_value2 ?? '',
            'custom_value3' => $payment->custom_value3 ?? '',
            'custom_value4' => $payment->custom_value4 ?? '',
            'created_at' => $this->translateDate($payment->created_at, $payment->client->date_format(), $payment->client->locale()),
            'updated_at' => $this->translateDate($payment->updated_at, $payment->client->date_format(), $payment->client->locale()),
            'client' => $this->getClient($payment),
            'paymentables' => $pivot,
            'refund_activity' => $this->getPaymentRefundActivity($payment),
        ];

    }

    /**
     *  [
      "id" => 12,
      "date" => "2023-10-08",
      "invoices" => [
        [
          "amount" => 1,
          "invoice_id" => 23,
          "id" => null,
        ],
      ],
      "q" => "/api/v1/payments/refund",
      "email_receipt" => "true",
      "gateway_refund" => false,
      "send_email" => false,
    ],
     *
     * @param Payment $payment
     * @return array
     */
    private function getPaymentRefundActivity(Payment $payment): array
    {

        if (!is_array($payment->refund_meta)) {
            return [];
        }

        return collect($payment->refund_meta)
        ->map(function ($refund) use ($payment) {

            $date = \Carbon\Carbon::parse($refund['date'] ?? $payment->date)->addSeconds($payment->client->timezone_offset());
            $date = $this->translateDate($date, $payment->client->date_format(), $payment->client->locale());
            $entity = ctrans('texts.invoice');

            $map = [];

            foreach ($refund['invoices'] as $refunded_invoice) {
                $invoice = Invoice::withTrashed()->find($refunded_invoice['invoice_id']);
                $amount = Number::formatMoney($refunded_invoice['amount'], $payment->client);
                $notes = ctrans('texts.status_partially_refunded_amount', ['amount' => $amount]);

                array_push($map, "{$date} {$entity} #{$invoice->number} {$notes}\n");

            }

            return $map;

        })->flatten()->toArray();

    }

    /**
     *
     *
     * @param  array | \Illuminate\Support\Collection $quotes
     * @return array
     */
    public function processQuotes($quotes): array
    {

        return collect($quotes)->map(function ($quote) {
            return [
                'amount' => Number::formatMoney($quote->amount, $quote->client),
                'balance' => Number::formatMoney($quote->balance, $quote->client),
                'status_id' => $quote->status_id,
                'status' => Quote::stringStatus($quote->status_id),
                'amount_raw' => $quote->amount ,
                'balance_raw' => $quote->balance,
                'number' => $quote->number ?: '',
                'discount' => $quote->discount,
                'po_number' => $quote->po_number ?: '',
                'date' => $this->translateDate($quote->date, $quote->client->date_format(), $quote->client->locale()),
                'last_sent_date' => $this->translateDate($quote->last_sent_date, $quote->client->date_format(), $quote->client->locale()),
                'next_send_date' => $this->translateDate($quote->next_send_date, $quote->client->date_format(), $quote->client->locale()),
                'due_date' => $this->translateDate($quote->due_date, $quote->client->date_format(), $quote->client->locale()),
                'valid_until' => $this->translateDate($quote->due_date, $quote->client->date_format(), $quote->client->locale()),
                'terms' => $quote->terms ?: '',
                'public_notes' => $quote->public_notes ?: '',
                'private_notes' => $quote->private_notes ?: '',
                'uses_inclusive_taxes' => (bool) $quote->uses_inclusive_taxes,
                'tax_name1' => $quote->tax_name1 ?? '',
                'tax_rate1' => (float) $quote->tax_rate1,
                'tax_name2' => $quote->tax_name2 ?? '',
                'tax_rate2' => (float) $quote->tax_rate2,
                'tax_name3' => $quote->tax_name3 ?? '',
                'tax_rate3' => (float) $quote->tax_rate3,
                'total_taxes' => Number::formatMoney($quote->total_taxes, $quote->client),
                'total_taxes_raw' => $quote->total_taxes,
                'is_amount_discount' => (bool) $quote->is_amount_discount ?? false,//@phpstan-ignore-line
                'footer' => $quote->footer ?? '',
                'partial' => $quote->partial ?? 0,
                'partial_due_date' => $this->translateDate($quote->partial_due_date, $quote->client->date_format(), $quote->client->locale()),
                'custom_value1' => (string) $quote->custom_value1 ?: '',
                'custom_value2' => (string) $quote->custom_value2 ?: '',
                'custom_value3' => (string) $quote->custom_value3 ?: '',
                'custom_value4' => (string) $quote->custom_value4 ?: '',
                'custom_surcharge1' => (float) $quote->custom_surcharge1,
                'custom_surcharge2' => (float) $quote->custom_surcharge2,
                'custom_surcharge3' => (float) $quote->custom_surcharge3,
                'custom_surcharge4' => (float) $quote->custom_surcharge4,
                'exchange_rate' => (float) $quote->exchange_rate,
                'custom_surcharge_tax1' => (bool) $quote->custom_surcharge_tax1,
                'custom_surcharge_tax2' => (bool) $quote->custom_surcharge_tax2,
                'custom_surcharge_tax3' => (bool) $quote->custom_surcharge_tax3,
                'custom_surcharge_tax4' => (bool) $quote->custom_surcharge_tax4,
                'line_items' => $quote->line_items ? $this->padLineItems($quote->line_items, $quote->client) : (array) [],
                'paid_to_date' => Number::formatMoney($quote->paid_to_date, $quote->client),
                'client' => $this->getClient($quote),
                'total_tax_map' => $quote->calc()->getTotalTaxMap(),
                'line_tax_map' => $quote->calc()->getTaxMap(),
            ];
        })->toArray();

    }

    /**
     * Pushes credits through the appropriate transformer
     * and builds any required relationships
     *
     * @param  array | \Illuminate\Support\Collection $credits
     * @return array
     */
    public function processCredits($credits): array
    {
        $credits = collect($credits)
                ->map(function ($credit) {

                    $payments = [];

                    $this->entity = $credit;

                    if ($credit->payments ?? false) {
                        $payments = $credit->payments->map(function ($payment) {
                            return $this->transformPayment($payment);
                        })->toArray();
                    }

                    return [
                        'amount' => Number::formatMoney($credit->amount, $credit->client),
                        'balance' => Number::formatMoney($credit->balance, $credit->client),
                        'amount_raw' => $credit->amount ,
                        'balance_raw' => $credit->balance,
                        'status_id' => $credit->status_id,
                        'status' => Credit::stringStatus($credit->status_id),
                        'number' => $credit->number ?: '',
                        'discount' => $credit->discount,
                        'po_number' => $credit->po_number ?: '',
                        'date' => $this->translateDate($credit->date, $credit->client->date_format(), $credit->client->locale()),
                        'last_sent_date' => $this->translateDate($credit->last_sent_date, $credit->client->date_format(), $credit->client->locale()),
                        'next_send_date' => $this->translateDate($credit->next_send_date, $credit->client->date_format(), $credit->client->locale()),
                        'due_date' => $this->translateDate($credit->due_date, $credit->client->date_format(), $credit->client->locale()),
                        'valid_until' => $this->translateDate($credit->due_date, $credit->client->date_format(), $credit->client->locale()),
                        'terms' => $credit->terms ?: '',
                        'public_notes' => $credit->public_notes ?: '',
                        'private_notes' => $credit->private_notes ?: '',
                        'uses_inclusive_taxes' => (bool) $credit->uses_inclusive_taxes,
                        'tax_name1' => $credit->tax_name1 ?? '',
                        'tax_rate1' => (float) $credit->tax_rate1,
                        'tax_name2' => $credit->tax_name2 ?? '',
                        'tax_rate2' => (float) $credit->tax_rate2,
                        'tax_name3' => $credit->tax_name3 ?? '',
                        'tax_rate3' => (float) $credit->tax_rate3,
                        'total_taxes' => Number::formatMoney($credit->total_taxes, $credit->client),
                        'total_taxes_raw' => $credit->total_taxes,
                        'is_amount_discount' => (bool) $credit->is_amount_discount ?? false, //@phpstan-ignore-line
                        'footer' => $credit->footer ?? '',
                        'partial' => $credit->partial ?? 0,
                        'partial_due_date' => $this->translateDate($credit->partial_due_date, $credit->client->date_format(), $credit->client->locale()),
                        'custom_value1' => (string) $credit->custom_value1 ?: '',
                        'custom_value2' => (string) $credit->custom_value2 ?: '',
                        'custom_value3' => (string) $credit->custom_value3 ?: '',
                        'custom_value4' => (string) $credit->custom_value4 ?: '',
                        'custom_surcharge1' => (float) $credit->custom_surcharge1,
                        'custom_surcharge2' => (float) $credit->custom_surcharge2,
                        'custom_surcharge3' => (float) $credit->custom_surcharge3,
                        'custom_surcharge4' => (float) $credit->custom_surcharge4,
                        'exchange_rate' => (float) $credit->exchange_rate,
                        'custom_surcharge_tax1' => (bool) $credit->custom_surcharge_tax1,
                        'custom_surcharge_tax2' => (bool) $credit->custom_surcharge_tax2,
                        'custom_surcharge_tax3' => (bool) $credit->custom_surcharge_tax3,
                        'custom_surcharge_tax4' => (bool) $credit->custom_surcharge_tax4,
                        'line_items' => $credit->line_items ? $this->padLineItems($credit->line_items, $credit->client) : (array) [],
                        'reminder1_sent' => $this->translateDate($credit->reminder1_sent, $credit->client->date_format(), $credit->client->locale()),
                        'reminder2_sent' => $this->translateDate($credit->reminder2_sent, $credit->client->date_format(), $credit->client->locale()),
                        'reminder3_sent' => $this->translateDate($credit->reminder3_sent, $credit->client->date_format(), $credit->client->locale()),
                        'reminder_last_sent' => $this->translateDate($credit->reminder_last_sent, $credit->client->date_format(), $credit->client->locale()),
                        'paid_to_date' => Number::formatMoney($credit->paid_to_date, $credit->client),
                        'auto_bill_enabled' => (bool) $credit->auto_bill_enabled,
                        'client' => $this->getClient($credit),
                        'payments' => $payments,
                        'total_tax_map' => $credit->calc()->getTotalTaxMap(),
                        'line_tax_map' => $credit->calc()->getTaxMap(),
                    ];

                });

        return $credits->toArray();

    }

    /**
     * Pushes payments through the appropriate transformer
     *
     * @param  array | \Illuminate\Support\Collection $payments
     * @return array
     */
    public function processPayments($payments): array
    {

        $payments = collect($payments)->map(function ($payment) {
            return $this->transformPayment($payment);
        })->toArray();

        return $payments;

    }

    private function getClient($entity): array
    {

        return $entity->client ? [
            'name' => $entity->client->present()->name(),
            'balance' => $entity->client->balance,
            'payment_balance' => $entity->client->payment_balance,
            'credit_balance' => $entity->client->credit_balance,
            'number' => $entity->client->number ?? '',
            'id_number' => $entity->client->id_number ?? '',
            'vat_number' => $entity->client->vat_number ?? '',
            'currency' => $entity->client->currency()->code ?? 'USD',
            'custom_value1' => $entity->client->custom_value1 ?? '',
            'custom_value2' => $entity->client->custom_value2 ?? '',
            'custom_value3' => $entity->client->custom_value3 ?? '',
            'custom_value4' => $entity->client->custom_value4 ?? '',
            'address' => $entity->client->present()->address(),
            'shipping_address' => $entity->client->present()->shipping_address(),
            'locale' => substr($entity->client->locale(), 0, 2),
            ] : [];
    }

    private function processInvoiceTask(string $task_id): array
    {
        $task = Task::where('company_id', $this->company->id)
                    ->where('id', $this->decodePrimaryKey($task_id))
                    ->first();

        return $task ? [
            'number' => (string) $task->number ?: '',
            'description' => (string) $task->description ?: '',
            'duration' => $task->calcDuration() ?: 0,
            'rate' => Number::formatMoney($task->rate ?? 0, $task->client ?? $task->company),
            'rate_raw' => $task->rate ?? 0,
            'created_at' => $this->translateDate($task->created_at, $task->client ? $task->client->date_format() : $task->company->date_format(), $task->client ? $task->client->locale() : $task->company->locale()),
            'updated_at' => $this->translateDate($task->updated_at, $task->client ? $task->client->date_format() : $task->company->date_format(), $task->client ? $task->client->locale() : $task->company->locale()),
            'date' => $task->calculated_start_date ? $this->translateDate($task->calculated_start_date, $task->client ? $task->client->date_format() : $task->company->date_format(), $task->client ? $task->client->locale() : $task->company->locale()) : '',
            // 'project' => $task->project ? $this->transformProject($task->project, true) : [],
            'project' => $task->project ? $task->project->name : '',
            'time_log' => $task->processLogsExpandedNotation(),
            'custom_value1' => $task->custom_value1 ?: '',
            'custom_value2' => $task->custom_value2 ?: '',
            'custom_value3' => $task->custom_value3 ?: '',
            'custom_value4' => $task->custom_value4 ?: '',
            'status' => $task->status ? $task->status->name : '',
            'user' => $this->userInfo($task->user),
            'assigned_user' => $task->assigned_user ? $this->userInfo($task->assigned_user) : [],
        ] : [];
    }


    /**
     * @todo refactor
     *
     * @param  \App\Models\Task[] $tasks
     * @return array
     */
    public function processTasks($tasks, bool $nested = false): array
    {

        return collect($tasks)->map(function ($task) use ($nested) {
            /** @var \App\Models\Task $task */
            return [
                'number' => (string) $task->number ?: '',
                'description' => (string) $task->description ?: '',
                'duration' => $task->calcDuration() ?: 0,
                'rate' => Number::formatMoney($task->rate ?? 0, $task->client ?? $task->company),
                'rate_raw' => $task->rate ?? 0,
                'created_at' => $this->translateDate($task->created_at, $task->client ? $task->client->date_format() : $task->company->date_format(), $task->client ? $task->client->locale() : $task->company->locale()),
                'updated_at' => $this->translateDate($task->updated_at, $task->client ? $task->client->date_format() : $task->company->date_format(), $task->client ? $task->client->locale() : $task->company->locale()),
                'date' => $task->calculated_start_date ? $this->translateDate($task->calculated_start_date, $task->client ? $task->client->date_format() : $task->company->date_format(), $task->client ? $task->client->locale() : $task->company->locale()) : '',
                // 'invoice_id' => $this->encodePrimaryKey($task->invoice_id) ?: '',
                'project' => ($task->project && !$nested) ? $this->transformProject($task->project, true) : [],
                'time_log' => $task->processLogsExpandedNotation(),
                'custom_value1' => $task->custom_value1 ?: '',
                'custom_value2' => $task->custom_value2 ?: '',
                'custom_value3' => $task->custom_value3 ?: '',
                'custom_value4' => $task->custom_value4 ?: '',
                'status' => $task->status ? $task->status->name : '',
                'user' => $this->userInfo($task->user),
                'assigned_user' => $task->assigned_user ? $this->userInfo($task->assigned_user) : [],
                'client' => $this->getClient($task),
            ];


        })->toArray();

    }

    /**
     * @todo refactor
     *
     * @param  array | \Illuminate\Support\Collection $projects
     * @return array
     */
    public function processProjects($projects): array
    {

        return
        collect($projects)->map(function ($project) {

            return $this->transformProject($project);

        })->toArray();

    }

    private function userInfo(User $user): array
    {
        return [
            'name' => $user->present()->name(),
            'email' => $user->email,
            'signature' => $user->signature ?? '',
        ];
    }

    private function transformProject(Project $project, bool $nested = false): array
    {

        return [
            'name' => $project->name ?: '',
            'number' => $project->number ?: '',
            'created_at' => $this->translateDate($project->created_at, $project->client->date_format(), $project->client->locale()),
            'updated_at' =>  $this->translateDate($project->updated_at, $project->client->date_format(), $project->client->locale()),
            'task_rate' => Number::formatMoney($project->task_rate ?? 0, $project->client),
            'task_rate_raw' => $project->task_rate ?? 0,
            'due_date' => $project->due_date ? $this->translateDate($project->due_date, $project->client->date_format(), $project->client->locale()) : '',
            'private_notes' => (string) $project->private_notes ?: '',
            'public_notes' => (string) $project->public_notes ?: '',
            'budgeted_hours' => (float) $project->budgeted_hours,
            'custom_value1' => (string) $project->custom_value1 ?: '',
            'custom_value2' => (string) $project->custom_value2 ?: '',
            'custom_value3' => (string) $project->custom_value3 ?: '',
            'custom_value4' => (string) $project->custom_value4 ?: '',
            'color' => (string) $project->color ?: '',
            'current_hours' => (int) $project->current_hours ?: 0,
            'tasks' => ($project->tasks && !$nested) ? $this->processTasks($project->tasks, true) : [], //@phpstan-ignore-line
            'client' => $this->getClient($project),
            'user' => $this->userInfo($project->user),
            'assigned_user' => $project->assigned_user ? $this->userInfo($project->assigned_user) : [],
            'invoices' => $this->processInvoices($project->invoices)
        ];

    }

    /**
     *
     * @param  array | \Illuminate\Support\Collection $purchase_orders
     * @return array
     */
    public function processPurchaseOrders($purchase_orders): array
    {

        return collect($purchase_orders)->map(function ($purchase_order) {

            return [
                'vendor' => $purchase_order->vendor ? [
                    'name' => $purchase_order->vendor->present()->name(),
                    'vat_number' => $purchase_order->vendor->vat_number ?? '',
                    'currency' => $purchase_order->vendor->currency()->code ?? 'USD',
                ] : [],
                'amount' => (float)$purchase_order->amount,
                'balance' => (float)$purchase_order->balance,
                'client' => $this->getClient($purchase_order),
                'status_id' => (string)($purchase_order->status_id ?: 1),
                'status' => PurchaseOrder::stringStatus($purchase_order->status_id ?? 1),
                'is_deleted' => (bool)$purchase_order->is_deleted,
                'number' => $purchase_order->number ?: '',
                'discount' => (float)$purchase_order->discount,
                'po_number' => $purchase_order->po_number ?: '',
                'date' => $purchase_order->date ? $this->translateDate($purchase_order->date, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'last_sent_date' => $purchase_order->last_sent_date ? $this->translateDate($purchase_order->last_sent_date, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'next_send_date' => $purchase_order->next_send_date ? $this->translateDate($purchase_order->next_send_date, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'reminder1_sent' => $purchase_order->reminder1_sent ? $this->translateDate($purchase_order->reminder1_sent, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'reminder2_sent' => $purchase_order->reminder2_sent ? $this->translateDate($purchase_order->reminder2_sent, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'reminder3_sent' => $purchase_order->reminder3_sent ? $this->translateDate($purchase_order->reminder3_sent, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'reminder_last_sent' => $purchase_order->reminder_last_sent ? $this->translateDate($purchase_order->reminder_last_sent, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'due_date' => $purchase_order->due_date ? $this->translateDate($purchase_order->due_date, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'terms' => $purchase_order->terms ?: '',
                'public_notes' => $purchase_order->public_notes ?: '',
                'private_notes' => $purchase_order->private_notes ?: '',
                'uses_inclusive_taxes' => (bool)$purchase_order->uses_inclusive_taxes,
                'tax_name1' => $purchase_order->tax_name1 ? $purchase_order->tax_name1 : '',
                'tax_rate1' => (float)$purchase_order->tax_rate1,
                'tax_name2' => $purchase_order->tax_name2 ? $purchase_order->tax_name2 : '',
                'tax_rate2' => (float)$purchase_order->tax_rate2,
                'tax_name3' => $purchase_order->tax_name3 ? $purchase_order->tax_name3 : '',
                'tax_rate3' => (float)$purchase_order->tax_rate3,
                'total_taxes' => (float)$purchase_order->total_taxes,
                'is_amount_discount' => (bool)($purchase_order->is_amount_discount ?: false),
                'footer' => $purchase_order->footer ?: '',
                'partial' => (float)($purchase_order->partial ?: 0.0),
                'partial_due_date' => $purchase_order->partial_due_date ? $this->translateDate($purchase_order->partial_due_date, $purchase_order->vendor->date_format(), $purchase_order->vendor->locale()) : '',
                'custom_value1' => (string)$purchase_order->custom_value1 ?: '',
                'custom_value2' => (string)$purchase_order->custom_value2 ?: '',
                'custom_value3' => (string)$purchase_order->custom_value3 ?: '',
                'custom_value4' => (string)$purchase_order->custom_value4 ?: '',
                'has_tasks' => (bool)$purchase_order->has_tasks,
                'has_expenses' => (bool)$purchase_order->has_expenses,
                'custom_surcharge1' => (float)$purchase_order->custom_surcharge1,
                'custom_surcharge2' => (float)$purchase_order->custom_surcharge2,
                'custom_surcharge3' => (float)$purchase_order->custom_surcharge3,
                'custom_surcharge4' => (float)$purchase_order->custom_surcharge4,
                'custom_surcharge_tax1' => (bool)$purchase_order->custom_surcharge_tax1,
                'custom_surcharge_tax2' => (bool)$purchase_order->custom_surcharge_tax2,
                'custom_surcharge_tax3' => (bool)$purchase_order->custom_surcharge_tax3,
                'custom_surcharge_tax4' => (bool)$purchase_order->custom_surcharge_tax4,
                'line_items' => $purchase_order->line_items ? $this->padLineItems($purchase_order->line_items, $purchase_order->vendor) : (array)[],
                'exchange_rate' => (float)$purchase_order->exchange_rate,
                'currency_id' => $purchase_order->currency_id ? (string) $purchase_order->currency_id : '',
            ];

        })->toArray();

    }

    /**
     * Set Company
     *
     * @param  Company $company
     * @return self
     */
    public function setCompany(Company $company): self
    {
        $this->company = $company;

        return $this;
    }

    /**
     * Get Company
     *
     * @return Company
     */
    public function getCompany(): Company
    {
        return $this->company;
    }

    /**
     * Setter that allows external variables to override the
     * resolved ones from this class
     *
     * @param  mixed $variables
     * @return self
     */
    public function overrideVariables($variables): self
    {
        $this->variables = $variables;

        return $this;
    }

    /**
     * Parses and finds any field stacks to inject into the DOM Document
     *
     * @return self
     */
    public function parseGlobalStacks(): self
    {
        $stacks = [
            'entity-details',
            'client-details',
            'vendor-details',
            'company-details',
            'company-address',
            'shipping-details',
        ];

        collect($stacks)->filter(function ($stack) {
            return $this->document->getElementById($stack) ?? false;
        })
        ->map(function ($stack) {
            $node = $this->document->getElementById($stack);
            return ['stack' => $stack, 'labels' => $node->getAttribute('labels')];
        })
        ->each(function ($stack) {
            $this->parseStack($stack);
        });

        return $this;

    }

    /**
     * Injects field stacks into Template
     *
     * @param  array $stack
     * @return self
     */
    private function parseStack(array $stack): self
    {

        match($stack['stack']) {
            'entity-details' => $this->entityDetails(),
            'client-details' => $this->clientDetails($stack['labels'] == 'true'),
            'vendor-details' => $this->vendorDetails($stack['labels'] == 'true'),
            'company-details' => $this->companyDetails($stack['labels'] == 'true'),
            'company-address' => $this->companyAddress($stack['labels'] == 'true'),
            'shipping-details' => $this->shippingDetails($stack['labels'] == 'true'),
            default => $this->entityDetails(),
        };

        $this->save();

        return $this;
    }

    /**
     * Inject the Company Details into the DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function companyDetails(bool $include_labels): self
    {
        $var_set = $this->getVarSet();

        $company_details =
        collect($this->getSettings()->pdf_variables->company_details)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })
            ->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_details-' . substr($variable, 1)]];
                });
            })->toArray();

        $company_details = $include_labels ? $this->labelledFieldStack($company_details, 'company_details-') : $company_details;

        $this->updateElementProperties('company-details', $company_details);

        return $this;
    }

    private function companyAddress(bool $include_labels = false): self
    {

        $var_set = $this->getVarSet();

        $company_address =
        collect($this->getSettings()->pdf_variables->company_address)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })
            ->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'company_address-' . substr($variable, 1)]];
                });
            })->toArray();

        $company_address = $include_labels ? $this->labelledFieldStack($company_address, 'company_address-') : $company_address;

        $this->updateElementProperties('company-address', $company_address);

        return $this;
    }

    /**
     * Injects the Shipping Details into the DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function shippingDetails(bool $include_labels = false): self
    {
        if (!$this->entity->client) {
            return $this;
        }

        $this->client = $this->entity->client;

        $shipping_address = [
            // ['element' => 'p', 'content' => ctrans('texts.shipping_address'), 'properties' => ['data-ref' => 'shipping_address-label', 'style' => 'font-weight: bold; text-transform: uppercase']],
            // ['element' => 'p', 'content' => $this->client->name, 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.name']],
            ['element' => 'p', 'content' => $this->client->shipping_address1, 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_address1']],
            ['element' => 'p', 'content' => $this->client->shipping_address2, 'show_empty' => false, 'properties' => ['data-ref' => 'shipping_address-client.shipping_address2']],
            ['element' => 'p', 'show_empty' => false, 'elements' => [
                ['element' => 'span', 'content' => "{$this->client->shipping_city} ", 'properties' => ['ref' => 'shipping_address-client.shipping_city']],
                ['element' => 'span', 'content' => "{$this->client->shipping_state} ", 'properties' => ['ref' => 'shipping_address-client.shipping_state']],
                ['element' => 'span', 'content' => "{$this->client->shipping_postal_code} ", 'properties' => ['ref' => 'shipping_address-client.shipping_postal_code']],
            ]],
            ['element' => 'p', 'content' => optional($this->client->shipping_country)->name, 'show_empty' => false],
        ];

        $shipping_address =
        collect($shipping_address)->filter(function ($address) {
            return isset($address['content']) && !empty($address['content']);
        })->toArray();

        $this->updateElementProperties('shipping-details', $shipping_address);

        return $this;
    }

    /**
     * Injects the Client Details into the DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function clientDetails(bool $include_labels = false): self
    {
        $var_set = $this->getVarSet();

        $client_details =
        collect($this->getSettings()->pdf_variables->client_details)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })
            ->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'client_details-' . substr($variable, 1)]];
                });
            })->toArray();

        $client_details = $include_labels ? $this->labelledFieldStack($client_details, 'client_details-') : $client_details;

        $this->updateElementProperties('client-details', $client_details);

        return $this;
    }

    /**
     * Resolves the entity.
     *
     * Only required for resolving the entity-details stack
     *
     * @return string
     */
    private function resolveEntity(): string
    {
        switch ($this->entity) {
            case ($this->entity instanceof Invoice):
                return 'invoice';
            case ($this->entity instanceof Quote):
                return 'quote';
            case ($this->entity instanceof Credit):
                return 'credit';
            case ($this->entity instanceof RecurringInvoice):
                return 'invoice';
            case ($this->entity instanceof PurchaseOrder):
                return 'purchase_order';

            default:
                return 'invoice';
        }

    }

    /**
     * Returns the variable array by first key, if it exists
     *
     * @return array
     */
    private function getVarSet(): array
    {
        return array_key_exists(array_key_first($this->variables), $this->variables) ? $this->variables[array_key_first($this->variables)] : $this->variables;
    }

    /**
     * Injects the entity details to the DOM document
     *
     * @return self
     */
    private function entityDetails(): self
    {
        $entity_string = $this->resolveEntity();
        $entity_string_prop = "{$entity_string}_details";
        $var_set = $this->getVarSet();

        $entity_details =
        collect($this->getSettings()->pdf_variables->{$entity_string_prop})
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })->toArray();

        $this->updateElementProperties("entity-details", $this->labelledFieldStack($entity_details, 'entity_details-'));

        return $this;
    }

    /**
     * Generates the field stacks with labels
     *
     * @param  array $variables
     * @return array
     */
    private function labelledFieldStack(array $variables, string $data_ref): array
    {

        $elements = [];

        foreach ($variables as $variable) {
            $_variable = explode('.', $variable)[1];
            $_customs = ['custom1', 'custom2', 'custom3', 'custom4'];

            $var = str_replace("custom", "custom_value", $_variable);

            $hidden_prop = ($data_ref == 'entity_details-') ? $this->entityVariableCheck($variable) : false;

            if (in_array($_variable, $_customs) && !empty($this->entity->{$var})) {
                $elements[] = ['element' => 'tr', 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => $data_ref . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => $data_ref . substr($variable, 1)]],
                ]];
            } else {
                $elements[] = ['element' => 'tr', 'properties' => ['hidden' => $hidden_prop], 'elements' => [
                    ['element' => 'th', 'content' => $variable . '_label', 'properties' => ['data-ref' => $data_ref . substr($variable, 1) . '_label']],
                    ['element' => 'th', 'content' => $variable, 'properties' => ['data-ref' => $data_ref . substr($variable, 1)]],
                ]];
            }
        }

        return $elements;

    }

    /**
     * Inject Vendor Details into DOM Document
     *
     * @param  bool $include_labels
     * @return self
     */
    private function vendorDetails(bool $include_labels = false): self
    {

        $var_set = $this->getVarSet();

        $vendor_details =
        collect($this->getSettings()->pdf_variables->vendor_details)
            ->filter(function ($variable) use ($var_set) {
                return isset($var_set['values'][$variable]) && !empty($var_set['values'][$variable]);
            })->when(!$include_labels, function ($collection) {
                return $collection->map(function ($variable) {
                    return ['element' => 'p', 'content' => $variable, 'show_empty' => false, 'properties' => ['data-ref' => 'vendor_details-' . substr($variable, 1)]];
                });
            })->toArray();

        $vendor_details = $include_labels ? $this->labelledFieldStack($vendor_details, 'vendor_details-') : $vendor_details;

        $this->updateElementProperties('vendor-details', $vendor_details);

        return $this;
    }


    /**
     * Performs a variable check to ensure
     * the variable exists
     *
     * @param  string $variable
     * @return bool
     *
     */
    public function entityVariableCheck(string $variable): bool
    {
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
        } catch (\Exception $e) {
            throw new \Exception('Company settings seems to be broken. Missing $this->service->config->entity.variable type.');
        }

        if (\in_array($variable, \array_keys($aliases))) {
            $_variable = $aliases[$variable];
        }

        if (is_null($this->entity->{$_variable}) || empty($this->entity->{$_variable})) {
            return true;
        }

        return false;
    }

    ////////////////////////////////////////
    // Dom Traversal
    ///////////////////////////////////////

    public function updateElementProperties(string $element_id, array $elements): self
    {
        $node = $this->document->getElementById($element_id);

        $this->createElementContent($node, $elements);

        return $this;
    }

    public function updateElementProperty($element, string $attribute, ?string $value)
    {

        if ($attribute == 'hidden' && ($value == false || $value == 'false')) {
            return $element;
        }

        $element->setAttribute($attribute, $value);

        if ($element->getAttribute($attribute) === $value) {
            return $element;
        }

        return $element;

    }

    public function createElementContent($element, $children): self
    {

        foreach ($children as $child) {
            $contains_html = false;

            //06-11-2023 for some reason this parses content as HTML
            // if ($child['element'] !== 'script') {
            //     if ($this->company->markdown_enabled && array_key_exists('content', $child)) {
            //         $child['content'] = str_replace('<br>', "\r", $child['content']);
            //         $child['content'] = $this->commonmark->convert($child['content'] ?? '');
            //     }
            // }

            if (isset($child['content'])) {
                if (isset($child['is_empty']) && $child['is_empty'] === true) {
                    continue;
                }

                $contains_html = preg_match('#(?<=<)\w+(?=[^<]*?>)#', $child['content'], $m) != 0;
            }

            if ($contains_html) {
                // If the element contains the HTML, we gonna display it as is. Backend is going to
                // encode it for us, preventing any errors on the processing stage.
                // Later, we decode this using Javascript so it looks like it's normal HTML being injected.
                // To get all elements that need frontend decoding, we use 'data-state' property.

                $_child = $this->document->createElement($child['element'], '');
                $_child->setAttribute('data-state', 'encoded-html');
                $_child->nodeValue = htmlspecialchars($child['content']);

            } else {
                // .. in case string doesn't contain any HTML, we'll just return
                // raw $content.
                $_child = $this->document->createElement($child['element'], isset($child['content']) ? $child['content'] : '');
            }

            $element->appendChild($_child);

            if (isset($child['properties'])) {
                foreach ($child['properties'] as $property => $value) {
                    $this->updateElementProperty($_child, $property, $value);
                }
            }

            if (isset($child['elements'])) {
                $this->createElementContent($_child, $child['elements']);
            }

        }

        return $this;
    }

}

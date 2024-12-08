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

namespace App\Services\Client;

use App\Factory\InvoiceFactory;
use App\Factory\InvoiceInvitationFactory;
use App\Factory\InvoiceItemFactory;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Design;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PdfMaker\Design as PdfMakerDesign;
use App\Services\PdfMaker\PdfMaker;
use App\Utils\HostedPDF\NinjaPdf;
use App\Utils\HtmlEngine;
use App\Utils\Number;
use App\Utils\PhantomJS\Phantom;
use App\Utils\Traits\MakesDates;
use App\Utils\Traits\MakesHash;
use App\Utils\Traits\Pdf\PdfMaker as PdfMakerTrait;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class Statement
{
    use PdfMakerTrait;
    use MakesHash;
    use MakesDates;

    /**
     * @var ?Invoice
     */
    protected $entity;

    private array $variables = [];

    public function __construct(protected Client $client, public array $options)
    {
    }

    public function run(): ?string
    {

        try {
            $this->setupOptions();

            $this->setupEntity();

            $invitation = $this->getInvitation();

            if(!$invitation)
                return null;

            $html = new HtmlEngine($invitation);

            $variables = [];
            $variables = $html->generateLabelsAndValues();

            $custom_statement_template = \App\Models\Design::where('id', $this->decodePrimaryKey($this->client->getSetting('statement_design_id')))->where('is_template', true)->first();

            if ($custom_statement_template || (isset($this->options['template']) && $this->options['template'] != '')) {

                $variables['values']['$start_date'] = $this->translateDate($this->options['start_date'], $this->client->date_format(), $this->client->locale());
                $variables['values']['$end_date'] = $this->translateDate($this->options['end_date'], $this->client->date_format(), $this->client->locale());
                $variables['labels']['$start_date_label'] = ctrans('texts.start_date');
                $variables['labels']['$end_date_label'] = ctrans('texts.end_date');
                
                $pdf = null;

                try{
                    $pdf = $this->templateStatement($variables);
                }
                catch(\Throwable $e){
                    nlog("wrapped");
                    nlog($e->getMessage());
                }

                return $pdf;
            }

            if ($this->getDesign()->is_custom) {
                $this->options['custom_partials'] = \json_decode(\json_encode($this->getDesign()->design), true);

                $template = new PdfMakerDesign(\App\Services\PdfMaker\Design::CUSTOM, $this->options);
            } else {
                $template = new PdfMakerDesign(strtolower($this->getDesign()->name), $this->options);
            }

            $variables['values']['$show_paid_stamp'] = 'none'; //do not show paid stamp on statement

            $state = [
                'template' => $template->elements([
                    'client' => $this->client,
                    'entity' => $this->entity,
                    'pdf_variables' => (array) $this->entity->company->settings->pdf_variables,
                    '$product' => $this->getDesign()->design->product,
                    'variables' => $variables,
                    'invoices' => $this->getInvoices()->cursor(),
                    'payments' => $this->getPayments()->cursor(),
                    'credits' => $this->getCredits()->cursor(),
                    'aging' => $this->getAging(),
                    'unapplied' => $this->getUnapplied()->cursor(),
                ], \App\Services\PdfMaker\Design::STATEMENT),
                'variables' => $variables,
                'options' => [
                ],
                'process_markdown' => $this->entity->client->company->markdown_enabled,
            ];

            $maker = new PdfMaker($state);

            $maker
                ->design($template)
                ->build();

            $pdf = null;
            $html = $maker->getCompiledHTML(true);

            // nlog($html);

            $pdf = $this->convertToPdf($html);

            $this->setVariables($variables);

            $maker = null;
            $state = null;

            return $pdf;

        } catch (\Throwable $th) {
            nlog("Statement threw => ". $th->getMessage());
        }

        return null;

    }

    public function setVariables($variables): self
    {
        $this->variables = $variables;

        return $this;
    }

    public function getVariables(): array
    {
        return $this->variables;
    }

    private function templateStatement($variables)
    {

        if (isset($this->options['template'])) {
            $statement_design_id = $this->options['template'];
        } else {
            $statement_design_id = $this->client->getSetting('statement_design_id');
        }

        $html = '';

        $template = Design::query()
                            ->where('id', $this->decodePrimaryKey($statement_design_id))
                            ->where('company_id', $this->client->company_id)
                            ->first();

        if($template)
        {
            $ts = $template->service();
            $ts->addGlobal(['show_credits' => $this->options['show_credits_table']]);
            $ts->addGlobal(['show_aging' => $this->options['show_aging_table']]);
            $ts->addGlobal(['show_payments' => $this->options['show_payments_table']]);
            $ts->addGlobal(['currency_code' => $this->client->company->currency()->code]);

            $ts->build([
                'variables' => collect([$variables]),
                'invoices' => $this->getInvoices()->get(),
                'payments' => $this->options['show_payments_table'] ? $this->getPayments()->get() : collect([]),
                'credits' => $this->options['show_credits_table'] ? $this->getCredits()->get() : collect([]),
                'aging' => $this->options['show_aging_table'] ? $this->getAging() : collect([]),
                'unapplied' => $this->options['show_payments_table'] ? $this->getPayments()->get() : collect([]),
            ]);

            $html = $ts->getHtml();
        }

        return $this->convertToPdf($html);
    }

    private function convertToPdf(string $html): mixed
    {
        $pdf = false;

        if (config('ninja.phantomjs_pdf_generation') || config('ninja.pdf_generator') == 'phantom') {
            $pdf = (new Phantom())->convertHtmlToPdf($html);
        } elseif (config('ninja.invoiceninja_hosted_pdf_generation') || config('ninja.pdf_generator') == 'hosted_ninja') {
            $pdf = (new NinjaPdf())->build($html);
        } else {
            $pdf = $this->makePdf(null, null, $html);
        }

        return $pdf;
    }
    /**
     * Setup correct entity instance.
     *
     * @return Statement
     */
    protected function setupEntity(): self
    {
        if ($this->getInvoices()->count() >= 1) {
            $this->entity = $this->getInvoices()->first();//@phpstan-ignore-line
        }
        else {
            $this->entity = $this->client->invoices()->whereHas('invitations')->first();
        }

        if(\is_null($this->entity)){
            $settings = new \stdClass();
            $settings->entity = \App\Models\Client::class;
            $settings->currency_id = '1';
            $settings->industry_id = '';
            $settings->size_id = '';

            $this->entity = \App\Models\Invoice::factory()->make(); //@phpstan-ignore-line
            $this->entity->client = \App\Models\Client::factory()->make(['settings' => $settings]); //@phpstan-ignore-line
            $this->entity->client->setRelation('company', $this->client->company);
            $this->entity->invitation = \App\Models\InvoiceInvitation::factory()->make(); //@phpstan-ignore-line
            $this->entity->setRelation('company', $this->client->company);
            $this->entity->setRelation('user', $this->client->user);

        }

        return $this;
    }

    protected function buildLineItems($count = 1)
    {
        $line_items = [];

        for ($x = 0; $x < $count; $x++) {
            $item = InvoiceItemFactory::create();
            $item->quantity = 1;
            //$item->cost = 10;

            if (rand(0, 1)) {
                $item->tax_name1 = 'GST';
                $item->tax_rate1 = 10.00;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'VAT';
                $item->tax_rate1 = 17.50;
            }

            if (rand(0, 1)) {
                $item->tax_name1 = 'Sales Tax';
                $item->tax_rate1 = 5;
            }

            //$product = Product::first();

            $product = new \stdClass();

            $item->cost = (float) 10;
            $item->product_key = 'test';
            $item->notes = 'test notes';
            $item->custom_value1 = 'custom value1';
            $item->custom_value2 = 'custom value2';
            $item->custom_value3 = 'custom value3';
            $item->custom_value4 = 'custom value4';

            $line_items[] = $item;
        }

        return $line_items;
    }

    /**
     * Setup & prepare options.
     *
     * @return Statement
     */
    protected function setupOptions(): self
    {
        if (! \array_key_exists('start_date', $this->options)) {
            $this->options['start_date'] = now()->startOfYear()->format('Y-m-d');
        }

        if (! \array_key_exists('end_date', $this->options)) {
            $this->options['end_date'] = now()->format('Y-m-d');
        }

        if (! \array_key_exists('show_payments_table', $this->options)) {
            $this->options['show_payments_table'] = false;
        }

        if (! \array_key_exists('show_aging_table', $this->options)) {
            $this->options['show_aging_table'] = false;
        }

        if (! \array_key_exists('show_credits_table', $this->options)) {
            $this->options['show_credits_table'] = false;
        }

        if (!\array_key_exists('only_clients_with_invoices', $this->options)) {
            $this->options['only_clients_with_invoices'] = false;
        }

        return $this;
    }

    /**
     * The collection of invoices for the statement.
     *
     * @return Builder
     */
    public function getInvoices(): Builder
    {
        return Invoice::withTrashed()
            ->with('payments.type')
            ->where('is_deleted', false)
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', $this->invoiceStatuses())
            ->whereBetween('date', [Carbon::parse($this->options['start_date']), Carbon::parse($this->options['end_date'])])
            ->orderBy('due_date', 'ASC')
            ->orderBy('date', 'ASC');
    }

    private function invoiceStatuses(): array
    {
        $status = 'all';

        if (array_key_exists('status', $this->options)) {
            $status = $this->options['status'];
        }

        switch ($status) {
            case 'all':
                return [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];

            case 'paid':
                return [Invoice::STATUS_PAID];

            case 'unpaid':
                return [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL];

            default:
                return [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL, Invoice::STATUS_PAID];

        }
    }

    /**
     * The collection of payments for the statement.
     *
     * @return Builder
     */
    protected function getPayments(): Builder
    {
        return Payment::withTrashed()
            ->with('client.country', 'invoices')
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
            ->whereBetween('date', [Carbon::parse($this->options['start_date']), Carbon::parse($this->options['end_date'])])
            ->where('is_deleted', false)
            ->orderBy('date', 'ASC');
    }

    protected function getUnapplied(): Builder
    {
        return Payment::query()
                        ->withTrashed()
                        ->where('company_id', $this->client->company_id)
                        ->where('client_id', $this->client->id)
                        ->whereIn('status_id', [Payment::STATUS_COMPLETED, Payment::STATUS_PENDING, Payment::STATUS_PARTIALLY_REFUNDED, Payment::STATUS_REFUNDED])
                        ->where('is_deleted', 0)
                        ->whereRaw('payments.amount > payments.applied');
    }

    /**
     * The collection of credits for the statement.
     *
     * @return Builder
     */
    protected function getCredits(): Builder
    {
        return Credit::withTrashed()
            ->with('client.country', 'invoice')
            ->where('is_deleted', false)
            ->where('company_id', $this->client->company_id)
            ->where('client_id', $this->client->id)
            ->whereIn('status_id', [Credit::STATUS_SENT, Credit::STATUS_PARTIAL, Credit::STATUS_APPLIED])
            ->whereBetween('date', [Carbon::parse($this->options['start_date']), Carbon::parse($this->options['end_date'])])
            ->where(function ($query) {
                $query->whereDate('due_date', '>=', now())
                      ->orWhereNull('due_date');
            })
            ->orderBy('date', 'ASC');
    }

    /**
     * Get correct invitation ID.
     *
     */
    protected function getInvitation()
    {
        if($this->entity instanceof Invoice) {
            $invitation = $this->entity->invitations->first();
            
            if($invitation)
                return $invitation;

        $invitation = $this->client->invoices()->whereHas('invitations')->first()->invitations->first();
        
        if ($invitation) 
            return $invitation;

        }

        return false;
    }

    /**
     * Get the array of aging data.
     *
     * @return array
     */
    protected function getAging(): array
    {
        return [
            ctrans('texts.current') => $this->getAgingAmount('0'),
            '0-30' => $this->getAgingAmount('30'),
            '30-60' => $this->getAgingAmount('60'),
            '60-90' => $this->getAgingAmount('90'),
            '90-120' => $this->getAgingAmount('120'),
            '120+' => $this->getAgingAmount('120+'),
        ];
    }

    /**
     * Generate aging amount.
     *
     * @param mixed $range
     * @return string
     */
    private function getAgingAmount($range): string
    {
        $ranges = $this->calculateDateRanges($range);

        $from = $ranges[0];
        $to = $ranges[1];

        $query = Invoice::withTrashed()
            ->where('client_id', $this->client->id)
            ->where('company_id', $this->client->company_id)
            ->whereIn('status_id', [Invoice::STATUS_SENT, Invoice::STATUS_PARTIAL])
            ->where('balance', '>', 0)
            ->where('is_deleted', 0);

        if ($range == '0') {
            $query->where(function ($q) use ($to, $from) {
                $q->whereBetween('due_date', [$to, $from])->orWhereNull('due_date');
            });
        } else {
            $query->whereBetween('due_date', [$to, $from]);
        }

        $amount = $query->sum('balance');

        return Number::formatMoney($amount, $this->client);
    }

    /**
     * Calculate date ranges for aging.
     *
     * @param mixed $range
     * @return array
     */
    private function calculateDateRanges($range)
    {
        $ranges = [];

        switch ($range) {
            case '0':
                $ranges[0] = now()->subYears(50);
                $ranges[1] = now()->startOfDay()->subMinute();
                return $ranges;
            case '30':
                $ranges[0] = now()->startOfDay();
                $ranges[1] = now()->startOfDay()->subDays(30);

                return $ranges;
            case '60':
                $ranges[0] = now()->startOfDay()->subDays(31);
                $ranges[1] = now()->startOfDay()->subDays(60);

                return $ranges;
            case '90':
                $ranges[0] = now()->startOfDay()->subDays(61);
                $ranges[1] = now()->startOfDay()->subDays(90);

                return $ranges;
            case '120':
                $ranges[0] = now()->startOfDay()->subDays(91);
                $ranges[1] = now()->startOfDay()->subDays(120);

                return $ranges;
            case '120+':
                $ranges[0] = now()->startOfDay()->subDays(121);
                $ranges[1] = now()->startOfDay()->subYears(20);

                return $ranges;
            default:
                $ranges[0] = now()->startOfDay();
                $ranges[1] = now()->startOfDay()->subDays(30);

                return $ranges;
        }
    }

    /**
     * Get correct design for statement.
     *
     * @return \App\Models\Design
     */
    protected function getDesign(): Design
    {
        $id = 1;

        if (! empty($this->client->getSetting('entity_design_id'))) {
            $id = (int) $this->client->getSetting('entity_design_id');
        }

        return Design::withTrashed()->find($id);
    }
}

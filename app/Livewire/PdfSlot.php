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

namespace App\Livewire;

use App\Utils\Number;
use Livewire\Component;
use App\Utils\HtmlEngine;
use App\Libraries\MultiDB;
use Illuminate\Support\Str;
use App\Models\QuoteInvitation;
use App\Utils\VendorHtmlEngine;
use App\Models\CreditInvitation;
use App\Models\InvoiceInvitation;
use Livewire\Attributes\Computed;
use Illuminate\Support\Facades\Cache;
use App\Jobs\EDocument\CreateEDocument;
use App\Models\PurchaseOrderInvitation;
use App\Models\RecurringInvoiceInvitation;
use App\Services\PdfMaker\Designs\Utilities\DesignHelpers;

class PdfSlot extends Component
{
    public $invitation_id;

    public $db;

    public $entity_id;

    public $class;

    public $pdf;

    public $url;

    private $settings;

    private $html_variables;

    private $entity_type;

    public $show_cost = true;

    public $show_quantity = true;

    public $show_line_total = true;

    public $route_entity = 'client';

    public $is_quote = false;

    private $entity_calc;

    public $html_entity_option = true;

    public ?string $with_close_button = null;

    public function mount()
    {
        MultiDB::setDb($this->db);

        if(!$this->invitation_id) {
            $this->entity()->service()->createInvitations();
        }

    }

    #[Computed]
    public function entity()
    {
        return $this->class::withTrashed()->find($this->entity_id);
    }

    #[Computed]
    public function invitation()
    {
        return $this->entity()->invitations()->first();
    }

    public function getPdf()
    {

        // if(!$this->invitation) {
        //     $this->entity()->service()->createInvitations();
        //     $this->invitation = $this->entity()->invitations()->first();
        // }

        $blob = [
            'entity_type' => $this->resolveEntityType(),
            'entity_id' => $this->entity()->id,
            'invitation_id' => $this->invitation()->id,
            'download' => false,
        ];

        $hash = Str::random(64);

        Cache::put($hash, $blob, 1800);

        $this->pdf = $hash;

    }

    public function downloadPdf()
    {

        $file_name = $this->entity()->numberFormatter().'.pdf';

        $file = (new \App\Jobs\Entity\CreateRawPdf($this->invitation()))->handle();

        $headers = ['Content-Type' => 'application/pdf'];

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

    public function downloadEDocument()
    {

        $file_name = $this->entity()->numberFormatter().'.xml';

        $file = (new CreateEDocument($this->entity()))->handle();

        $headers = ['Content-Type' => 'application/xml'];

        return response()->streamDownload(function () use ($file) {
            echo $file;
        }, $file_name, $headers);

    }

    public function render()
    {

        $this->entity_type = $this->resolveEntityType();
        $this->entity_calc = $this->entity()->calc();

        $this->settings = $this->entity()->client ? $this->entity()->client->getMergedSettings() : $this->entity()->company->settings;
        $this->html_entity_option = $this->entity()->client ? $this->entity()->client->getSetting('show_pdfhtml_on_mobile') : $this->entity()->company->getSetting('show_pdfhtml_on_mobile');

        $this->show_cost = in_array('$product.unit_cost', $this->settings->pdf_variables->product_columns);
        $this->show_line_total = in_array('$product.line_total', $this->settings->pdf_variables->product_columns);
        $this->show_quantity = in_array('$product.quantity', $this->settings->pdf_variables->product_columns);

        if($this->entity_type == 'quote' && !$this->settings->sync_invoice_quote_columns) {
            $this->show_cost = in_array('$product.unit_cost', $this->settings->pdf_variables->product_quote_columns);
            $this->show_quantity = in_array('$product.quantity', $this->settings->pdf_variables->product_quote_columns);
            $this->show_line_total = in_array('$product.line_total', $this->settings->pdf_variables->product_quote_columns);
        }

        $this->html_variables = $this->entity_type == 'purchase_order' ?
                            (new VendorHtmlEngine($this->invitation()))->generateLabelsAndValues() :
                            (new HtmlEngine($this->invitation()))->generateLabelsAndValues();

        $this->entity()->terms = $this->entity()->parseHtmlVariables('terms', $this->html_variables);
        $this->entity()->public_notes = $this->entity()->parseHtmlVariables('public_notes', $this->html_variables);

        return render('components.livewire.pdf-slot', [
            'invitation' => $this->invitation(),
            'entity' => $this->entity(),
            'settings' => $this->settings,
            'data' => $this->invitation()->company->settings,
            'entity_type' => $this->entity_type,
            'products' => $this->getProducts(),
            'services' => $this->getServices(),
            'amount' => Number::formatMoney($this->entity()->amount, $this->entity()->client ?: $this->entity()->vendor),
            'balance' => Number::formatMoney($this->entity()->balance, $this->entity()->client ?: $this->entity()->vendor),
            'discount' => $this->entity_calc->getTotalDiscount() > 0 ? Number::formatMoney($this->entity_calc->getTotalDiscount(), $this->entity()->client ?: $this->entity()->vendor) : false,
            'taxes' => $this->entity_calc->getTotalTaxes() > 0 ? Number::formatMoney($this->entity_calc->getTotalTaxes(), $this->entity()->client ?: $this->entity()->vendor) : false,
            'company_details' => $this->getCompanyDetails(),
            'company_address' => $this->getCompanyAddress(),
            'entity_details' => $this->getEntityDetails(),
            'user_details' => $this->getUserDetails(),
            'user_name' => $this->getUserName(),
        ]);
    }

    private function convertVariables($string): string
    {

        $html = strtr($string, $this->html_variables['labels']);
        $html = strtr($html, $this->html_variables['values']);

        return $html;

    }

    private function getCompanyAddress()
    {

        $company_address = "";

        foreach($this->settings->pdf_variables->company_address as $variable) {
            $company_address .= "<p>{$variable}</p>";
        }

        return $this->convertVariables($company_address);

    }

    private function getCompanyDetails()
    {
        $company_details = "";

        foreach($this->settings->pdf_variables->company_details as $variable) {
            $company_details .= "<p>{$variable}</p>";
        }

        return $this->convertVariables($company_details);

    }

    private function getEntityDetails()
    {
        $entity_details = "";

        if($this->entity_type == 'invoice' || $this->entity_type == 'recurring_invoice') {
            foreach($this->settings->pdf_variables->invoice_details as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }

        } elseif($this->entity_type == 'quote') {
            foreach($this->settings->pdf_variables->quote_details ?? [] as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }
        } elseif($this->entity_type == 'credit') {
            foreach($this->settings->pdf_variables->credit_details ?? [] as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }
        } elseif($this->entity_type == 'purchase_order') {
            foreach($this->settings->pdf_variables->purchase_order_details ?? [] as $variable) {
                $entity_details .= "<div class='flex px-5 block'><p class= w-36 block'>{$variable}_label</p><p class='ml-5 w-36 block entity-field'>{$variable}</p></div>";
            }
        }

        return $this->convertVariables($entity_details);

    }

    private function getUserName()
    {
        $name = ctrans('texts.details');

        if($this->entity_type == 'purchase_order' && isset($this->settings->pdf_variables->vendor_details[0])) {
            $name = $this->settings->pdf_variables->vendor_details[0];

        } elseif(isset($this->settings->pdf_variables->client_details[0])) {

            $name = $this->settings->pdf_variables->client_details[0];
        }

        return $this->convertVariables($name);

    }

    private function getUserDetails()
    {
        $user_details = "";

        if($this->entity_type == 'purchase_order') {
            foreach(array_slice($this->settings->pdf_variables->vendor_details, 1) as $variable) {
                $user_details .= "<p>{$variable}</p>";
            }
        } else {
            foreach(array_slice($this->settings->pdf_variables->client_details, 1) as $variable) {
                $user_details .= "<p>{$variable}</p>";
            }
        }

        return $this->convertVariables($user_details);
    }

    private function getProducts()
    {

        $product_items = collect($this->entity()->line_items)->filter(function ($item) {
            return $item->type_id == 1 || $item->type_id == 6 || $item->type_id == 5;
        })->map(function ($item) {

            $notes = strlen($item->notes) > 4 ? $item->notes : $item->product_key;

            return [
                'quantity' => $item->quantity,
                'cost' => Number::formatMoney($item->cost, $this->entity()->client ?: $this->entity()->vendor),
                'notes' => $this->invitation()->company->markdown_enabled ? DesignHelpers::parseMarkdownToHtml($notes) : $notes,
                'line_total' => Number::formatMoney($item->line_total, $this->entity()->client ?: $this->entity()->vendor),
            ];
        });

        return $product_items;
    }

    private function getServices()
    {
        $task_items = collect($this->entity()->line_items)->filter(function ($item) {
            return $item->type_id == 2;
        })->map(function ($item) {
            return [
                'quantity' => $item->quantity,
                'cost' => Number::formatMoney($item->cost, $this->entity()->client ?: $this->entity()->vendor),
                'notes' => $this->invitation()->company->markdown_enabled ? DesignHelpers::parseMarkdownToHtml($item->notes) : $item->notes,
                'line_total' => Number::formatMoney($item->line_total, $this->entity()->client ?: $this->entity()->vendor),
            ];
        });

        return $task_items;

    }

    private function resolveEntityType(): string
    {
        if ($this->invitation() instanceof InvoiceInvitation) {
            return 'invoice';
        } elseif ($this->invitation() instanceof QuoteInvitation) {
            $this->is_quote = true;
            return 'quote';
        } elseif ($this->invitation() instanceof CreditInvitation) {
            return 'credit';
        } elseif ($this->invitation() instanceof RecurringInvoiceInvitation) {
            return 'recurring_invoice';
        } elseif ($this->invitation() instanceof PurchaseOrderInvitation) {
            $this->route_entity = 'vendor';
            return 'purchase_order';
        }


        return '';
    }
}

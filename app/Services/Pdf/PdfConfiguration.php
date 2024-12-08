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

use App\DataMapper\CompanySettings;
use App\Libraries\MultiDB;
use App\Models\Client;
use App\Models\ClientContact;
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
use App\Models\RecurringInvoice;
use App\Models\RecurringInvoiceInvitation;
use App\Models\Vendor;
use App\Models\VendorContact;
use App\Utils\Ninja;
use App\Utils\Traits\AppSetup;
use App\Utils\Traits\MakesHash;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;

class PdfConfiguration
{
    use MakesHash;
    use AppSetup;

    public ?Client $client;

    public ?ClientContact $contact;

    public Country $country;

    public Currency $currency;

    public Client | Vendor $currency_entity;

    public Design $design;

    public Invoice | Credit | Quote | PurchaseOrder | RecurringInvoice $entity;

    public string $entity_design_id;

    public string $entity_string;

    public ?string $path;

    public array $pdf_variables;

    public object $settings;

    public $settings_object;

    public ?Vendor $vendor;

    public ?VendorContact $vendor_contact;

    public string $date_format;

    public string $locale;

    public Collection $tax_map;

    public ?array $total_tax_map;
    /**
     * __construct
     *
     * @param  PdfService $service
     * @return void
     */
    public function __construct(public PdfService $service)
    {
    }

    /**
     * init
     *
     * @return self
     */
    public function init(): self
    {
        MultiDB::setDb($this->service->company->db);

        $this->setEntityType()
             ->setDateFormat()
             ->setPdfVariables()
             ->setDesign()
             ->setCurrencyForPdf()
             ->setLocale();

        return $this;
    }

    /**
     * setLocale
     *
     * @return self
     */
    private function setLocale(): self
    {
        App::forgetInstance('translator');

        $t = app('translator');

        App::setLocale($this->settings_object->locale());

        $t->replace(Ninja::transformTranslations($this->settings));

        $this->locale = $this->settings_object->locale();

        return $this;
    }

    /**
     * setCurrency
     *
     * @return self
     */
    private function setCurrencyForPdf(): self
    {
        $this->currency = $this->client ? $this->client->currency() : $this->vendor->currency();

        $this->currency_entity = $this->client ? $this->client : $this->vendor;

        return $this;
    }

    /**
     * setPdfVariables
     *
     * @return self
     */
    public function setPdfVariables(): self
    {
        $default = (array) CompanySettings::getEntityVariableDefaults();

        // $variables = (array)$this->service->company->settings->pdf_variables;
        $variables = (array)$this->settings->pdf_variables;

        foreach ($default as $property => $value) {
            if (array_key_exists($property, $variables)) {
                continue;
            }

            $variables[$property] = $value;
        }

        $this->pdf_variables = $variables;

        return $this;
    }

    /**
     * setEntityType
     *
     * @return self
     */
    private function setEntityType(): self
    {
        $entity_design_id = '';

        if ($this->service->invitation instanceof InvoiceInvitation) {
            $this->entity = $this->service->invitation->invoice;
            $this->entity_string = 'invoice';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->invoice_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
            $this->country = $this->client->country ?? $this->client->company->country();
        } elseif ($this->service->invitation instanceof QuoteInvitation) {
            $this->entity = $this->service->invitation->quote;
            $this->entity_string = 'quote';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->quote_filepath($this->service->invitation);
            $this->entity_design_id = 'quote_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
            $this->country = $this->client->country ?? $this->client->company->country();
        } elseif ($this->service->invitation instanceof CreditInvitation) {
            $this->entity = $this->service->invitation->credit;
            $this->entity_string = 'credit';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->credit_filepath($this->service->invitation);
            $this->entity_design_id = 'credit_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
            $this->country = $this->client->country ?? $this->client->company->country();
        } elseif ($this->service->invitation instanceof RecurringInvoiceInvitation) {
            $this->entity = $this->service->invitation->recurring_invoice;
            $this->entity_string = 'recurring_invoice';
            $this->client = $this->entity->client;
            $this->contact = $this->service->invitation->contact;
            $this->path = $this->client->recurring_invoice_filepath($this->service->invitation);
            $this->entity_design_id = 'invoice_design_id';
            $this->settings = $this->client->getMergedSettings();
            $this->settings_object = $this->client;
            $this->country = $this->client->country ?? $this->client->company->country();
        } elseif ($this->service->invitation instanceof PurchaseOrderInvitation) {
            $this->entity = $this->service->invitation->purchase_order;
            $this->entity_string = 'purchase_order';
            $this->vendor = $this->entity->vendor;
            $this->vendor_contact = $this->service->invitation->contact;
            $this->path = $this->vendor->purchase_order_filepath($this->service->invitation);
            $this->entity_design_id = 'purchase_order_design_id';
            $this->settings = $this->vendor->company->settings;
            $this->settings_object = $this->vendor;
            $this->client = null;
            $this->country = $this->vendor->country ?? $this->vendor->company->country();
        } else {
            throw new \Exception('Unable to resolve entity', 500);
        }

        $this->setTaxMap($this->entity->calc()->getTaxMap());
        $this->setTotalTaxMap($this->entity->calc()->getTotalTaxMap());

        $this->path = $this->path.$this->entity->numberFormatter().'.pdf';

        return $this;
    }

    public function setTaxMap($map): self
    {
        $this->tax_map = $map;

        return $this;
    }

    public function setTotalTaxMap($map): self
    {
        $this->total_tax_map = $map;

        return $this;
    }

    public function setCurrency(Currency $currency): self
    {
        $this->currency = $currency;

        return $this;
    }

    public function setCountry(Country $country): self
    {
        $this->country = $country;

        return $this;
    }

    /**
     * setDesign
     *
     * @return self
     */
    private function setDesign(): self
    {

        $design_id = $this->entity->design_id ?: $this->decodePrimaryKey($this->settings_object->getSetting($this->entity_design_id));

        $this->design = Design::withTrashed()->find($design_id) ?? Design::withTrashed()->find(2);

        return $this;
    }

    /**
     * formatMoney
     *
     * @param  float $value
     * @return string
     */
    public function formatMoney($value): string
    {
        $value = floatval($value);

        $thousand = $this->currency->thousand_separator;
        $decimal = $this->currency->decimal_separator;
        $precision = $this->currency->precision;
        $code = $this->currency->code;
        $swapSymbol = $this->currency->swap_currency_symbol;

        if (isset($this->country->thousand_separator) && strlen($this->country->thousand_separator) >= 1) {
            $thousand = $this->country->thousand_separator;
        }

        if (isset($this->country->decimal_separator) && strlen($this->country->decimal_separator) >= 1) {
            $decimal = $this->country->decimal_separator;
        }

        if (isset($this->country->swap_currency_symbol) && $this->country->swap_currency_symbol) {
            $swapSymbol = $this->country->swap_currency_symbol;
        }

        $value = number_format($value, $precision, $decimal, $thousand);
        $symbol = $this->currency->symbol;

        if ($this->settings->show_currency_code === true && $this->currency->code == 'CHF') {
            return "{$code} {$value}";
        } elseif ($this->settings->show_currency_code === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} ".trim($symbol);
        } elseif ($this->settings->show_currency_code === false) {
            return "{$symbol}{$value}";
        } else {
            $value = floatval($value);
            $thousand = $this->currency->thousand_separator;
            $decimal = $this->currency->decimal_separator;
            $precision = $this->currency->precision;

            return number_format($value, $precision, $decimal, $thousand);
        }
    }

    /**
     * Formats a given value based on the clients currency.
     *
     * @param  float  $value    The number to be formatted
     *
     * @return string           The formatted value
     */
    public function formatValueNoTrailingZeroes($value): string
    {
        $value = floatval($value);

        $thousand = $this->currency->thousand_separator;
        $decimal = $this->currency->decimal_separator;

        /* Country settings override client settings */
        if (isset($this->country->thousand_separator) && strlen($this->country->thousand_separator) >= 1) {
            $thousand = $this->country->thousand_separator;
        }

        if (isset($this->country->decimal_separator) && strlen($this->country->decimal_separator) >= 1) {
            $decimal = $this->country->decimal_separator;
        }

        $precision = 10;

        return rtrim(rtrim(number_format($value, $precision, $decimal, $thousand), '0'), $decimal);
    }


    /**
     * Formats a given value based on the clients currency AND country.
     *
     * @param float $value The number to be formatted
     * @return string           The formatted value
     */
    public function formatMoneyNoRounding($value): string
    {

        $_value = $value;

        $thousand = $this->currency->thousand_separator;
        $decimal = $this->currency->decimal_separator;
        $precision = $this->currency->precision;
        $code = $this->currency->code;
        $swapSymbol = $this->currency->swap_currency_symbol;

        /* Country settings override client settings */
        if (isset($this->country->thousand_separator) && strlen($this->country->thousand_separator) >= 1) {
            $thousand = $this->country->thousand_separator;
        }

        if (isset($this->country->decimal_separator) && strlen($this->country->decimal_separator) >= 1) {
            $decimal = $this->country->decimal_separator;
        }

        if (isset($this->country->swap_currency_symbol) && $this->country->swap_currency_symbol == 1) {
            $swapSymbol = $this->country->swap_currency_symbol;
        }

        /* 08-01-2022 allow increased precision for unit price*/
        $v = rtrim(sprintf('%f', $value), '0');
        $parts = explode('.', $v);

        /* 08-02-2023 special if block to render $0.5 to $0.50*/
        if ($v < 1 && strlen($v) == 3) {
            $precision = 2;
        } elseif ($v < 1) {
            $precision = strlen($v) - strrpos($v, '.') - 1;
        }

        if (is_array($parts) && $parts[0] != 0) {
            $precision = 2;
        }

        //04-04-2023 if currency = JPY override precision to 0
        if ($this->currency->code == 'JPY') {
            $precision = 0;
        }

        $value = number_format($v, $precision, $decimal, $thousand); //@phpstan-ignore-line
        $symbol = $this->currency->symbol;

        if ($this->settings->show_currency_code === true && $this->currency->code == 'CHF') {
            return "{$code} {$value}";
        } elseif ($this->settings->show_currency_code === true) {
            return "{$value} {$code}";
        } elseif ($swapSymbol) {
            return "{$value} ".trim($symbol);
        } elseif ($this->settings->show_currency_code === false) {
            if ($_value < 0) {
                $value = substr($value, 1);
                $symbol = "-{$symbol}";
            }

            return "{$symbol}{$value}";
        } else {
            return $this->formatValue($value); // @phpstan-ignore-line
        }
    }

    /**
     * Formats a given value based on the clients currency.
     *
     * @param  float  $value    The number to be formatted
     *
     * @return string           The formatted value
     */
    public function formatValue($value): string
    {
        $value = floatval($value);

        $thousand = $this->currency->thousand_separator;
        $decimal = $this->currency->decimal_separator;
        $precision = $this->currency->precision;

        return number_format($value, $precision, $decimal, $thousand);
    }


    /**
     * date_format
     *
     * @return self
     */
    public function setDateFormat(): self
    {

        /** @var \Illuminate\Support\Collection<\App\Models\DateFormat> */
        $date_formats = app('date_formats');

        $this->date_format = $date_formats->first(function ($item) {
            return $item->id == $this->settings->date_format_id;
        })->format;

        return $this;
    }
}

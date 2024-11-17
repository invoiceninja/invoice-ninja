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

namespace App\Import\Transformer;

use App\Factory\ClientFactory;
use App\Factory\ExpenseCategoryFactory;
use App\Factory\ProjectFactory;
use App\Factory\VendorFactory;
use App\Models\Client;
use App\Models\ClientContact;
use App\Models\Country;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Invoice;
use App\Models\PaymentType;
use App\Models\Product;
use App\Models\Project;
use App\Models\Quote;
use App\Models\RecurringInvoice;
use App\Models\TaxRate;
use App\Models\Vendor;
use App\Repositories\ClientRepository;
use App\Utils\Number;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Class BaseTransformer.
 */
class BaseTransformer
{
    protected $company;

    public function __construct($company)
    {
        $this->company = $company;
    }

    public function parseDate($date)
    {
        if(stripos($date, "/") !== false && $this->company->settings->country_id != 840) {
            $date = str_replace('/', '-', $date);
        }

        try {
            $parsed_date = Carbon::parse($date);

            return $parsed_date->format('Y-m-d');
        } catch(\Exception $e) {
            $parsed_date = date('Y-m-d', strtotime($date));

            if ($parsed_date == '1970-01-01') {
                return now()->format('Y-m-d');
            }

            return $parsed_date;
        }
    }

    public function parseDateOrNull($data, $field)
    {
        $date = &$data[$field];

        if(!$date || strlen($date) <= 1) {
            return null;
        }

        if(stripos($date, "/") !== false && $this->company->settings->country_id != 840) {
            $date = str_replace('/', '-', $date);
        }

        try {
            $parsed_date = Carbon::parse($date);

            return $parsed_date->format('Y-m-d');
        } catch(\Exception $e) {
            $parsed_date = date('Y-m-d', strtotime($date));

            if ($parsed_date == '1970-01-01') {
                return now()->format('Y-m-d');
            }

            return $parsed_date;
        }


    }

    public function getInvoiceTypeId($data, $field)
    {
        return isset($data[$field]) && $data[$field] ? (string)$data[$field] : '1';
    }

    public function getNumber($data, $field)
    {
        return (isset($data->$field) && $data->$field) ? (int)$data->$field : 0;
    }

    public function getString($data, $field)
    {
        return isset($data[$field]) && $data[$field] ? trim($data[$field]) : '';
    }

    public function getValueOrNull($data, $field)
    {
        return isset($data[$field]) && $data[$field] ? $data[$field] : null;
    }

    public function getCurrencyByCode(array $data, string $key = 'client.currency_id')
    {
        $code = array_key_exists($key, $data) ? $data[$key] : false;

        if(!$code) {
            return $this->company->settings->currency_id;
        }

        /** @var \Illuminate\Support\Collection<\App\Models\Currency> */
        $currencies = app('currencies');

        $currency = $currencies->first(function ($item) use ($code) {
            return $item->code == $code;
        });

        return $currency ? (string) $currency->id : $this->company->settings->currency_id;

    }

    public function getFrequency($frequency = RecurringInvoice::FREQUENCY_MONTHLY): int
    {

        switch ($frequency) {
            case RecurringInvoice::FREQUENCY_DAILY:
            case 'daily':
                return RecurringInvoice::FREQUENCY_DAILY;
            case RecurringInvoice::FREQUENCY_WEEKLY:
            case 'weekly':
                return RecurringInvoice::FREQUENCY_WEEKLY;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
            case 'biweekly':
                return RecurringInvoice::FREQUENCY_TWO_WEEKS;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
            case '4weeks':
                return RecurringInvoice::FREQUENCY_FOUR_WEEKS;
            case RecurringInvoice::FREQUENCY_MONTHLY:
            case 'monthly':
                return RecurringInvoice::FREQUENCY_MONTHLY;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
            case 'bimonthly':
                return RecurringInvoice::FREQUENCY_TWO_MONTHS;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
            case 'quarterly':
                return RecurringInvoice::FREQUENCY_THREE_MONTHS;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
            case '4months':
                return RecurringInvoice::FREQUENCY_FOUR_MONTHS;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
            case '6months':
                return RecurringInvoice::FREQUENCY_SIX_MONTHS;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
            case 'yearly':
                return RecurringInvoice::FREQUENCY_ANNUALLY;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
            case '2years':
                return RecurringInvoice::FREQUENCY_TWO_YEARS;
            case RecurringInvoice::FREQUENCY_THREE_YEARS:
            case '3years':
                return RecurringInvoice::FREQUENCY_THREE_YEARS;
            default:
                return RecurringInvoice::FREQUENCY_MONTHLY;
        }

    }

    public function getRemainingCycles($remaining_cycles = -1): int
    {

        if ($remaining_cycles == 'endless') {
            return -1;
        }

        return (int)$remaining_cycles;
    }

    public function getAutoBillFlag(string $option): string
    {
        switch ($option) {
            case 'off':
            case 'false':
                return 'off';
            case 'always':
            case 'true':
                return 'always';
            case 'optin':
                return 'opt_in';
            case 'optout':
                return 'opt_out';
            default:
                return 'off';
        }
    }

    public function getClient($client_name, $client_email)
    {

        if (strlen($client_name ?? '') >= 1) {
            $client_id_search = Client::query()->where('company_id', $this->company->id)
                ->where('is_deleted', false)
                ->where('id_number', $client_name);

            if ($client_id_search->count() >= 1) {
                return $client_id_search->first()->id;
            }

            $client_name_search = Client::query()->where('company_id', $this->company->id)
                ->where('is_deleted', false)
                ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                    strtolower(str_replace(' ', '', $client_name)),
                ]);

            if ($client_name_search->count() >= 1) {
                return $client_name_search->first()->id;
            }
        }
        if (strlen($client_email ?? '' ) >= 1) {
            $contacts = ClientContact::query()->whereHas('client', function ($query) {
                $query->where('is_deleted', false);
            })
            ->where('company_id', $this->company->id)
            ->where('email', $client_email);
            
            if ($contacts->count() >= 1) {
                return $contacts->first()->client_id;
            }
        }

        $client_repository = app()->make(ClientRepository::class);
        $client_repository->import_mode = true;

        $client = $client_repository->save(
            [
                'name' => $client_name,
                'contacts' => [
                    [
                        'first_name' => $client_name,
                        'email' => $client_email,
                    ],
                ],
            ],
            ClientFactory::create(
                $this->company->id,
                $this->company->owner()->id
            )
        );

        $client_repository = null;

        return $client->id;
    }

    ///////////////////////////////////////////////////////////////////////////////////
    /**
     * @param $name
     *
     * @return bool
     */
    public function hasClient($name)
    {

        $x= Client::query()
            ->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' , '')) = ?", [
                strtolower(str_replace(' ', '', $name)),
            ]);

            return $x->exists();
    }

    public function hasClientIdNumber($id_number)
    {
        return Client::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->where('id_number', trim($id_number))
            ->exists();
    }


    /**
     * @param $name
     *
     * @return bool
     */
    public function hasVendor($name)
    {
        return Vendor::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->exists();
    }

    /**
     * @param $name
     *
     * @return bool
     */
    public function hasProject($name)
    {
        return Project::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->exists();
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public function hasProduct($key)
    {
        return Product::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`product_key`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $key)),
            ])
            ->exists();
    }

    /**
     * @param $data
     * @param $field
     *
     * @return float
     */
    public function getFloat($data, $field)
    {
        
        if (array_key_exists($field, $data)) {

            if($this->company->use_comma_as_decimal_place)
                return $this->parseCommaFloat($data, $field);

            return Number::parseFloat($data[$field]);
        }

        return 0;

    }

    private function parseCommaFloat($data, $field): float
    {

        $amount = $data[$field] ?? '';

        // Remove any non-numeric characters except for the decimal and thousand separators
        $amount = preg_replace('/[^\d' . preg_quote(",") . preg_quote(".") . '-]/', '', $amount);

        // Handle negative numbers
        $isNegative = strpos($amount, '-') !== false;
        $amount = str_replace('-', '', $amount);

        // Remove thousand separators
        $amount = str_replace(".", "", $amount);

        $amount = str_replace(",", '.', $amount);
        
        // Convert to float and apply negative sign if necessary
        $result = (float) $amount;
        return $isNegative ? -$result : $result;


    }

    /**
     * @param $data
     * @param $field
     *
     * @return float
     */
    public function getFloatOrOne($data, $field)
    {
        if (array_key_exists($field, $data)) {
            return Number::parseFloat($data[$field]) > 0 ? Number::parseFloat($data[$field]) : 1;
        }

        return 1;

    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getClientId($name)
    {
        $client = Client::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $client ? $client->id : null;
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function getProduct($key)
    {
        $product = Product::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`product_key`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $key)),
            ])
            ->first();

        return $product;
    }

    /**
     * @param $email
     *
     * @return ?ClientContact
     */
    public function getContact($email): ?ClientContact
    {
        $contact = ClientContact::query()
            ->where('company_id', $this->company->id)
            ->whereRaw("LOWER(REPLACE(`email`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $email)),
            ])
            ->first();

        if (! $contact) {
            return null;
        }

        return $contact;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getCountryId($name)
    {

        if (strlen(trim($name)) == 2) {
            return $this->getCountryIdBy2($name);
        }

        $country = Country::query()->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
            strtolower(str_replace(' ', '', $name)),
        ])->first();

        return $country ? $country->id : null;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getCountryIdBy2($name)
    {
        return Country::query()->where('iso_3166_2', $name)->exists()
            ? Country::query()->where('iso_3166_2', $name)->first()->id
            : null;
    }

    /**
     * @param $name
     *
     * @return float
     */
    public function getTaxRate($name)
    {
        $name = strtolower(trim($name));

        $tax_rate = TaxRate::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $tax_rate ? $tax_rate->rate : 0;
    }

    /**
     * @param $name
     *
     * @return string
     */
    public function getTaxName($name)
    {
        $name = strtolower(trim($name));

        $tax_rate = TaxRate::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $tax_rate ? $tax_rate->name : '';
    }

    /**
     *
     * @param mixed  $data
     * @param mixed  $field
     *
     * @return ?string
     */
    public function getDate($data, $field)
    {
        if ($date = data_get($data, $field)) {
            try {
                $date = new Carbon($date);
            } catch (\Exception $e) {
                // if we fail to parse return blank
                $date = false;
            }
        }

        return $date ? $date->format('Y-m-d') : null;
    }

    /**
     * @param $number
     *
     * @return ?string
     */
    public function getInvoiceNumber($number)
    {
        return $number ? ltrim(trim($number), '0') : null;
    }

    /**
     * @param $invoice_number
     *
     * @return int|null
     */
    public function getInvoiceId($invoice_number)
    {
        $invoice = Invoice::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $invoice_number)),
            ])
            ->first();

        return $invoice ? $invoice->id : null;
    }

    /**
     * @param $invoice_number
     *
     * @return bool
     */
    public function hasInvoice($invoice_number)
    {
        return Invoice::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $invoice_number)),
            ])
            ->exists();
    }


    /**
     * @param $invoice_number
     *
     * @return bool
     */
    public function hasRecurringInvoice($invoice_number)
    {
        return RecurringInvoice::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $invoice_number)),
            ])
            ->exists();
    }

    /**     *
     * @return bool
     */
    public function hasExpense($expense_number)
    {
        return Expense::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $expense_number)),
            ])
            ->exists();
    }

    /**
     * @param $quote_number
     *
     * @return bool
     */
    public function hasQuote($quote_number)
    {
        return Quote::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $quote_number)),
            ])
            ->exists();
    }

    /**
     * @param $invoice_number
     *
     * @return int|null
     */
    public function getInvoiceClientId($invoice_number)
    {
        $invoice = Invoice::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`number`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $invoice_number)),
            ])
            ->first();

        return $invoice ? $invoice->client_id : null;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getVendorId($name)
    {
        $vendor = Vendor::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $vendor ? $vendor->id : null;
    }

    public function getVendorIdOrCreate($name)
    {
        if (empty($name)) {
            return null;
        }

        $vendor = $this->getVendorId($name);

        if ($vendor) {
            return $vendor;
        }

        $vendor = VendorFactory::create($this->company->id, $this->company->owner()->id);
        $vendor->name = $name;
        $vendor->save();

        return $vendor->id;
    }

    /**
     * @param $name
     *
     * @return int
     */
    public function getExpenseCategoryId($name)
    {
        /** @var ?\App\Models\ExpenseCategory $ec */
        $ec = ExpenseCategory::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        if($ec) {
            return $ec->id;
        }

        $ec = ExpenseCategoryFactory::create($this->company->id, $this->company->owner()->id);
        $ec->name = $name;
        $ec->save();

        return $ec->id;
    }

    public function getOrCreateExpenseCategry($name)
    {
        if (empty($name)) {
            return null;
        }

        $ec = $this->getExpenseCategoryId($name);

        if ($ec) {
            return $ec;
        }

        $expense_category = ExpenseCategoryFactory::create($this->company->id, $this->company->owner()->id);
        $expense_category->name = $name;
        $expense_category->save();

        return $expense_category->id;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getProjectId($name, $clientId = null)
    {
        if(strlen($name) == 0) {
            return null;
        }

        $project = Project::query()->where('company_id', $this->company->id)
            ->where('is_deleted', false)
            ->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
                strtolower(str_replace(' ', '', $name)),
            ])
            ->first();

        return $project ? $project->id : $this->createProject($name, $clientId);
    }

    private function createProject($name, $clientId)
    {
        $project = ProjectFactory::create($this->company->id, $this->company->owner()->id);
        $project->name = $name;

        if ($clientId) {
            $project->client_id = $clientId;
        }

        $project->saveQuietly();

        return $project->id;
    }

    /**
     * @param $name
     *
     * @return int|null
     */
    public function getPaymentTypeId($name)
    {
        $pt = PaymentType::query()->whereRaw("LOWER(REPLACE(`name`, ' ' ,''))  = ?", [
            strtolower(str_replace(' ', '', $name)),
        ])->first();

        return $pt ? $pt->id : null;
    }
}

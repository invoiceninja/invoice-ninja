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

namespace App\Utils\Traits;

use App\Models\BaseModel;
use App\Models\Client;
use App\Models\Credit;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\Quote;
use App\Models\RecurringExpense;
use App\Models\RecurringInvoice;
use App\Models\RecurringQuote;
use App\Models\Task;
use App\Models\Timezone;
use App\Models\Vendor;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Class GeneratesCounter.
 */
trait GeneratesCounter
{
    private int $update_counter;

    //todo in the form validation, we need to ensure that if a prefix and pattern is set we throw a validation error,
    //only one type is allow else this will cause confusion to the end user

    private function getNextEntityNumber($entity, Client $client, $is_recurring = false)
    {
        $prefix = '';

        $this->resetCounters($client);

        $is_client_counter = false;

        $counter_string = $this->getEntityCounter($entity, $client);
        $pattern = $this->getNumberPattern($entity, $client);

        if ((strpos($pattern, 'clientCounter') !== false) || (strpos($pattern, 'client_counter') !== false)) {
            if (property_exists($client->settings, $counter_string)) {
                $counter = $client->settings->{$counter_string};
            } else {
                $counter = 1;
            }

            $counter_entity = $client;
        } elseif ((strpos($pattern, 'groupCounter') !== false) || (strpos($pattern, 'group_counter') !== false)) {
            if ($client->group_settings()->exists() && property_exists($client->group_settings?->settings, $counter_string)) {
                $counter = $client->group_settings?->settings?->{$counter_string};
            } else {
                $counter = 1;
            }

            $counter_entity = $client->group_settings ?: $client->company;
        } else {
            $counter = $client->company->settings->{$counter_string};
            $counter_entity = $client->company;
        }

        //If it is a quote - we need to
        $pattern = $this->getNumberPattern($entity, $client);

        if (strlen($pattern) > 1 && (stripos($pattern, 'counter') === false)) {
            $pattern = $pattern.'{$counter}';
        }

        $padding = $client->getSetting('counter_padding');

        if ($is_recurring) {
            $prefix = $client->getSetting('recurring_number_prefix');
        }

        $entity_number = $this->checkEntityNumber($entity, $client, $counter, $padding, $pattern, $prefix);

        $this->incrementCounter($counter_entity, $counter_string);

        return $entity_number;
    }

    private function getNumberPattern($entity, Client $client)
    {
        $pattern_string = '';

        switch ($entity) {
            case Invoice::class:
                $pattern_string = 'invoice_number_pattern';
                break;
            case Quote::class:
                $pattern_string = 'quote_number_pattern';
                break;
            case RecurringInvoice::class:
                $pattern_string = 'recurring_invoice_number_pattern';
                break;
            case Payment::class:
                $pattern_string = 'payment_number_pattern';
                break;
            case Credit::class:
                $pattern_string = 'credit_number_pattern';
                break;
            case Project::class:
                $pattern_string = 'project_number_pattern';
                break;
        }

        return $client->getSetting($pattern_string);
    }

    private function getEntityCounter($entity, $client)
    {
        switch ($entity) {
            case Invoice::class:
                return 'invoice_number_counter';

            case Quote::class:

                if ($this->hasSharedCounter($client, 'quote')) {
                    return 'invoice_number_counter';
                }

                return 'quote_number_counter';

            case RecurringInvoice::class:
                return 'recurring_invoice_number_counter';

            case RecurringQuote::class:
                return 'recurring_quote_number_counter';

            case RecurringExpense::class:
                return 'recurring_expense_number_counter';

            case Payment::class:
                return 'payment_number_counter';

            case Credit::class:
                if ($this->hasSharedCounter($client, 'credit')) {
                    return 'invoice_number_counter';
                }

                return 'credit_number_counter';

            case Project::class:
                return 'project_number_counter';

            case PurchaseOrder::class:
                return 'purchase_order_number_counter';

            default:
                return 'default_number_counter';
        }
    }

    /**
     * Gets the next invoice number.
     *
     * @param Client $client The client
     *
     * @param Invoice|null $invoice
     * @return     string              The next invoice number.
     */
    public function getNextInvoiceNumber(Client $client, ?Invoice $invoice, $is_recurring = false): string
    {
        $entity_number = $this->getNextEntityNumber(Invoice::class, $client, $is_recurring);

        return $this->replaceUserVars($invoice, $entity_number);
    }

    /**
     * Gets the next credit number.
     *
     * @param Client $client  The client
     *
     * @return     string              The next credit number.
     */
    public function getNextCreditNumber(Client $client, ?Credit $credit): string
    {
        $entity_number = $this->getNextEntityNumber(Credit::class, $client);

        return $this->replaceUserVars($credit, $entity_number);
    }

    /**
     * Gets the next quote number.
     *
     * @param Client $client  The client
     *
     * @return     string              The next credit number.
     */
    public function getNextQuoteNumber(Client $client, ?Quote $quote)
    {
        $entity_number = $this->getNextEntityNumber(Quote::class, $client);

        return $this->replaceUserVars($quote, $entity_number);
    }

    public function getNextRecurringInvoiceNumber(Client $client, $recurring_invoice)
    {
        $entity_number = $this->getNextEntityNumber(RecurringInvoice::class, $client);

        return $this->replaceUserVars($recurring_invoice, $entity_number);
    }

    public function getNextRecurringQuoteNumber(Client $client, $recurring_quote)
    {
        $entity_number = $this->getNextEntityNumber(RecurringQuote::class, $client);

        return $this->replaceUserVars($recurring_quote, $entity_number);
    }

    /**
     * Gets the next Payment number.
     *
     * @param Client $client  The client
     *
     * @return     string              The next payment number.
     */
    public function getNextPaymentNumber(Client $client, ?Payment $payment): string
    {
        $entity_number = $this->getNextEntityNumber(Payment::class, $client);

        return $this->replaceUserVars($payment, $entity_number);
    }

    /**
     * Gets the next client number.
     *
     * @param Client $client The client
     *
     * @return     string              The next client number.
     * @throws \Exception
     */
    public function getNextClientNumber(Client $client): string
    {
        //Reset counters if enabled
        $this->resetCounters($client);

        $counter = $client->getSetting('client_number_counter');
        $setting_entity = $client->getSettingEntity('client_number_counter');

        $client_number = $this->checkEntityNumber(Client::class, $client, $counter, $client->getSetting('counter_padding'), $client->getSetting('client_number_pattern'));

        $this->incrementCounter($setting_entity, 'client_number_counter');

        $entity_number = $client_number;

        return $this->replaceUserVars($client, $entity_number);
    }

    /**
     * Gets the next client number.
     *
     * @param Vendor $vendor    The vendor
     * @return     string                         The next vendor number.
     */
    public function getNextVendorNumber(Vendor $vendor): string
    {
        $this->resetCompanyCounters($vendor->company);

        $counter = $vendor->company->settings->vendor_number_counter;
        $setting_entity = $vendor->company->settings->vendor_number_counter;

        $vendor_number = $this->checkEntityNumber(Vendor::class, $vendor, $counter, $vendor->company->settings->counter_padding, $vendor->company->settings->vendor_number_pattern);

        $this->incrementCounter($vendor->company, 'vendor_number_counter');

        $entity_number = $vendor_number;

        return $this->replaceUserVars($vendor, $entity_number);
    }

    /**
     * Project Number Generator.
     * @param  Project $project
     * @return string  The project number
     */
    public function getNextProjectNumber(Project $project): string
    {
        $entity_number = $this->getNextEntityNumber(Project::class, $project->client, false);

        return $this->replaceUserVars($project, $entity_number);
    }

    /**
     * Gets the next task number.
     *
     * @param   Task    $task    The task
     * @return  string           The next task number.
     */
    public function getNextTaskNumber(Task $task): string
    {
        $this->resetCompanyCounters($task->company);

        $counter = $task->company->settings->task_number_counter;
        $setting_entity = $task->company->settings->task_number_counter;

        $task_number = $this->checkEntityNumber(Task::class, $task, $counter, $task->company->settings->counter_padding, $task->company->settings->task_number_pattern);

        $this->incrementCounter($task->company, 'task_number_counter');

        $entity_number = $task_number;

        return $this->replaceUserVars($task, $entity_number);
    }

    /**
     * Gets the next expense number.
     *
     * @param   Expense    $expense    The expense
     * @return  string                 The next expense number.
     */
    public function getNextExpenseNumber(Expense $expense): string
    {
        $this->resetCompanyCounters($expense->company);

        $counter = $expense->company->settings->expense_number_counter;
        $setting_entity = $expense->company->settings->expense_number_counter;

        $pattern = $expense->company->settings->expense_number_pattern;
        
        if (strlen($pattern) > 1 && (stripos($pattern, 'counter') === false)) {
            $pattern = $pattern.'{$counter}';
        }

        $expense_number = $this->checkEntityNumber(Expense::class, $expense, $counter, $expense->company->settings->counter_padding, $pattern);

        $this->incrementCounter($expense->company, 'expense_number_counter');

        $entity_number = $expense_number;

        return $this->replaceUserVars($expense, $entity_number);
    }

    public function getNextPurchaseOrderNumber(PurchaseOrder $purchase_order): string
    {
        $this->resetCompanyCounters($purchase_order->company);

        $counter = $purchase_order->company->settings->purchase_order_number_counter;
        $setting_entity = $purchase_order->company->settings->purchase_order_number_counter;

        $purchase_order_number = $this->checkEntityNumber(PurchaseOrder::class, $purchase_order, $counter, $purchase_order->company->settings->counter_padding, $purchase_order->company->settings->purchase_order_number_pattern);

        $this->incrementCounter($purchase_order->company, 'purchase_order_number_counter');

        $entity_number = $purchase_order_number;

        return $this->replaceUserVars($purchase_order, $entity_number);
    }

    /**
     * Gets the next expense number.
     *
     * @param   RecurringExpense       $expense    The expense
     * @return  string                 The next expense number.
     */
    public function getNextRecurringExpenseNumber(RecurringExpense $expense): string
    {
        $this->resetCompanyCounters($expense->company);

        // - 18/09/21 need to set this property if it doesn't exist. //todo refactor this for other properties
        if (! property_exists($expense->company->settings, 'recurring_expense_number_counter')) {
            $settings = $expense->company->settings;
            $settings->recurring_expense_number_counter = 1;
            $settings->recurring_expense_number_pattern = '';
            $expense->company->settings = $settings;
            $expense->company->save();
        }

        $counter = $expense->company->settings->recurring_expense_number_counter;
        $setting_entity = $expense->company->settings->recurring_expense_number_counter;

        $expense_number = $this->checkEntityNumber(RecurringExpense::class, $expense, $counter, $expense->company->settings->counter_padding, $expense->company->settings->recurring_expense_number_pattern);

        $this->incrementCounter($expense->company, 'recurring_expense_number_counter');

        $entity_number = $expense_number;

        return $this->replaceUserVars($expense, $entity_number);
    }

    /**
     * Determines if it has shared counter.
     *
     * @param Client $client  The client
     *
     * @return     bool             True if has shared counter, False otherwise.
     */
    public function hasSharedCounter(Client $client, string $type = 'quote'): bool
    {
        if ($type == 'quote') {
            return (bool) $client->getSetting('shared_invoice_quote_counter');
        }

        //credit
        return (bool) $client->getSetting('shared_invoice_credit_counter');
    }

    /**
     * Checks that the number has not already been used.
     *
     * @param $class
     * @param Collection $entity The entity ie App\Models\Client, Invoice, Quote etc
     * @param int $counter The counter
     * @param int $padding The padding
     *
     * @param      string $pattern
     * @param      string $prefix
     * @return     string The padded, prefixed and unique entity number
     */
    private function checkEntityNumber($class, $entity, $counter, $padding, $pattern, $prefix = ''): string
    {
        $check = false;
        $check_counter = 1;

        do {
            $number = $this->getFormattedEntityNumber($entity, $counter, $padding, $pattern, $prefix);

            $check = $class::where('company_id', $entity->company_id)->where('number', $number)->withTrashed()->exists();

            $counter++;
            $check_counter++;

            if ($check_counter > 100) {
                $this->update_counter = $counter--;

                return $number.'_'.Str::random(5);
            }
        } while ($check);

        $this->update_counter = $counter--;

        return $number;
    }

    /**
     * Formats the entity number according to pattern, prefix and padding.
     *
     * @param mixed $entity The entity ie App\Models\Client, Invoice, Quote etc
     * @param int $counter The counter
     * @param int $padding The padding
     * @param      string $pattern
     * @param      string $prefix
     *
     * @return     string The padded and prefixed entity number
     */
    public function getFormattedEntityNumber($entity, $counter, $padding, $pattern, $prefix = ''): string
    {
        $number = $this->padCounter($counter, $padding);

        $number = $this->applyNumberPattern($entity, $number, $pattern);

        return $this->prefixCounter($number, $prefix);
    }

    /*Check if a number is available for use. */
    public function checkNumberAvailable($class, $entity, $number): bool
    {
        if ($entity = $class::whereCompanyId($entity->company_id)->whereNumber($number)->withTrashed()->exists()) {
            return false;
        }

        return true;
    }

    /**
     * Saves counters at both the company and client level.
     *
     * @param $entity
     * @param string $counter_name The counter name
     */
    private function incrementCounter($entity, string $counter_name): void
    {
        $settings = $entity->settings;

        if ($counter_name == 'invoice_number_counter' && ! property_exists($entity->settings, 'invoice_number_counter')) {
            $settings->invoice_number_counter = 0;
        }

        if (! property_exists($settings, $counter_name)) {
            $settings->{$counter_name} = 1;
        }

        // $settings->{$counter_name} = $settings->{$counter_name} + 1;
        $settings->{$counter_name} = $this->update_counter;

        $entity->settings = $settings;

        $entity->save();
    }

    private function prefixCounter($counter, $prefix): string
    {
        if (strlen($prefix) == 0) {
            return $counter;
        }

        return  $prefix.$counter;
    }

    /**
     * Pads a number with leading 000000's.
     *
     * @param      int  $counter  The counter
     * @param      int  $padding  The padding
     *
     * @return     string  the padded counter
     */
    private function padCounter($counter, $padding): string
    {
        return str_pad($counter, $padding, '0', STR_PAD_LEFT);
    }

    /**
     * If we are using counter reset,
     * check if we need to reset here.
     *
     * @param Client $client client entity
     * @return void
     */
    private function resetCounters(Client $client)
    {
        $reset_counter_frequency = (int) $client->getSetting('reset_counter_frequency_id');
        $settings_entity = $client->getSettingEntity('reset_counter_frequency_id');
        $settings = $settings_entity->settings;

        if ($reset_counter_frequency == 0) {
            if ($client->getSetting('reset_counter_date')) {
                // $settings = $client->company->settings;
                $settings->reset_counter_date = "";
                $settings_entity->settings = $settings;
                $settings_entity->saveQuietly();
                // $client->company->settings = $settings;
                // $client->company->save();
            }

            return;
        }

        $timezone = Timezone::find($client->getSetting('timezone_id'));

        $reset_date = Carbon::parse($client->getSetting('reset_counter_date'), $timezone->name);

        if (! $reset_date->lte(now()) || ! $client->getSetting('reset_counter_date')) {
            return false;
        }

        switch ($reset_counter_frequency) {
            case RecurringInvoice::FREQUENCY_DAILY:
                $new_reset_date = $reset_date->addDay();
                break;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $new_reset_date = $reset_date->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $new_reset_date = $reset_date->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $new_reset_date = $reset_date->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $new_reset_date = $reset_date->addMonth();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $new_reset_date = $reset_date->addMonths(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $new_reset_date = $reset_date->addMonths(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $new_reset_date = $reset_date->addMonths(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $new_reset_date = $reset_date->addMonths(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $new_reset_date = $reset_date->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $new_reset_date = $reset_date->addYears(2);
                break;

            default:
                $new_reset_date = $reset_date->addYear();
                break;
        }

        // $settings = $client->company->settings;
        $settings->reset_counter_date = $new_reset_date->format('Y-m-d');
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;
        $settings->ticket_number_counter = 1;
        $settings->payment_number_counter = 1;
        $settings->project_number_counter = 1;
        $settings->task_number_counter = 1;
        $settings->expense_number_counter = 1;
        $settings->recurring_expense_number_counter = 1;
        $settings->purchase_order_number_counter = 1;

        // $client->company->settings = $settings;
        // $client->company->save();
        $settings_entity->settings = $settings;
        $settings_entity->saveQuietly();
    }

    private function resetCompanyCounters($company)
    {
        $timezone = Timezone::find($company->settings->timezone_id);

        $reset_date = Carbon::parse($company->settings->reset_counter_date, $timezone->name);

        if (! $reset_date->lte(now()) || ! $company->settings->reset_counter_date) {
            return false;
        }

        $settings = $company->settings;

        $reset_counter_frequency = (int) $settings->reset_counter_frequency_id;

        if ($reset_counter_frequency == 0) {
            if ($settings->reset_counter_date) {
                $settings->reset_counter_date = "";
                $company->settings = $settings;
                $company->save();
            }

            return;
        }

        switch ($reset_counter_frequency) {
            case RecurringInvoice::FREQUENCY_DAILY:
                $new_reset_date = $reset_date->addDay();
                break;
            case RecurringInvoice::FREQUENCY_WEEKLY:
                $new_reset_date = $reset_date->addWeek();
                break;
            case RecurringInvoice::FREQUENCY_TWO_WEEKS:
                $new_reset_date = $reset_date->addWeeks(2);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
                $new_reset_date = $reset_date->addWeeks(4);
                break;
            case RecurringInvoice::FREQUENCY_MONTHLY:
                $new_reset_date = $reset_date->addMonth();
                break;
            case RecurringInvoice::FREQUENCY_TWO_MONTHS:
                $new_reset_date = $reset_date->addMonths(2);
                break;
            case RecurringInvoice::FREQUENCY_THREE_MONTHS:
                $new_reset_date = $reset_date->addMonths(3);
                break;
            case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
                $new_reset_date = $reset_date->addMonths(4);
                break;
            case RecurringInvoice::FREQUENCY_SIX_MONTHS:
                $new_reset_date = $reset_date->addMonths(6);
                break;
            case RecurringInvoice::FREQUENCY_ANNUALLY:
                $new_reset_date = $reset_date->addYear();
                break;
            case RecurringInvoice::FREQUENCY_TWO_YEARS:
                $new_reset_date = $reset_date->addYears(2);
                break;

            default:
                $new_reset_date = $reset_date->addYear();
                break;
        }

        $settings->reset_counter_date = $new_reset_date->format('Y-m-d');
        $settings->invoice_number_counter = 1;
        $settings->quote_number_counter = 1;
        $settings->credit_number_counter = 1;
        $settings->ticket_number_counter = 1;
        $settings->payment_number_counter = 1;
        $settings->project_number_counter = 1;
        $settings->task_number_counter = 1;
        $settings->expense_number_counter = 1;
        $settings->recurring_expense_number_counter = 1;
        $settings->purchase_order_number_counter = 1;

        $company->settings = $settings;
        $company->save();
    }

    /**
     * Formats a entity number by pattern
     *
     * @param      BaseModel  $entity   The entity object
     * @param      string                 $counter  The counter
     * @param      null|string            $pattern  The pattern
     *
     * @return     string                The formatted number pattern
     */
    private function applyNumberPattern($entity, string $counter, $pattern): string
    {
        if (! $pattern) {
            return $counter;
        }

        $search = [];
        $replace = [];

        $search[] = '{$counter}';
        $replace[] = $counter;

        $search[] = '{$client_counter}';
        $replace[] = $counter;

        $search[] = '{$clientCounter}';
        $replace[] = $counter;

        $search[] = '{$group_counter}';
        $replace[] = $counter;

        $search[] = '{$year}';
        $replace[] = Carbon::now($entity->company->timezone()->name)->format('Y');

        if (strstr($pattern, '{$user_id}') || strstr($pattern, '{$userId}')) {
            $user_id = $entity->user_id ? $entity->user_id : 0;
            $search[] = '{$user_id}';
            $replace[] = str_pad(($user_id), 2, '0', STR_PAD_LEFT);
            $search[] = '{$userId}';
            $replace[] = str_pad(($user_id), 2, '0', STR_PAD_LEFT);
        }

        $matches = [];

        preg_match('/{\$date:(.*?)}/', $pattern, $matches);
        if (count($matches) > 1) {
            $format = $matches[1];
            $search[] = $matches[0];

            /* The following adjusts for the company timezone - may bork tests depending on the time of day the tests are run!!!!!!*/
            $date = Carbon::now($entity->company->timezone()->name)->format($format);
            $replace[] = str_replace($format, $date, $matches[1]);
        }

        if ($entity instanceof Vendor) {
            $search[] = '{$vendor_id_number}';
            $replace[] = $entity->id_number;
        }

        if ($entity instanceof Expense) {
            if ($entity->vendor) {
                $search[] = '{$vendor_id_number}';
                $replace[] = $entity->vendor->id_number;

                $search[] = '{$vendor_number}';
                $replace[] = $entity->vendor->number;

                $search[] = '{$vendor_custom1}';
                $replace[] = $entity->vendor->custom_value1;

                $search[] = '{$vendor_custom2}';
                $replace[] = $entity->vendor->custom_value2;

                $search[] = '{$vendor_custom3}';
                $replace[] = $entity->vendor->custom_value3;

                $search[] = '{$vendor_custom4}';
                $replace[] = $entity->vendor->custom_value4;
            }

            $search[] = '{$expense_id_number}';
            $replace[] = $entity->id_number;
        }

        if ($entity->client || ($entity instanceof Client)) {
            $client = $entity->client ?: $entity;

            $search[] = '{$client_custom1}';
            $replace[] = $client->custom_value1;

            $search[] = '{$clientCustom1}';
            $replace[] = $client->custom_value1;

            $search[] = '{$client_custom2}';
            $replace[] = $client->custom_value2;

            $search[] = '{$clientCustom2}';
            $replace[] = $client->custom_value2;

            $search[] = '{$client_custom3}';
            $replace[] = $client->custom_value3;

            $search[] = '{$client_custom4}';
            $replace[] = $client->custom_value4;

            $search[] = '{$client_number}';
            $replace[] = $client->number;

            $search[] = '{$client_id_number}';
            $replace[] = $client->id_number ?: $client->number;

            $search[] = '{$clientIdNumber}';
            $replace[] = $client->id_number ?: $client->number;
        }

        return str_replace($search, $replace, $pattern);
    }

    private function replaceUserVars($entity, $pattern)
    {
        if (! $entity) {
            return $pattern;
        }

        $search = [];
        $replace = [];

        $search[] = '{$user_custom1}';
        $replace[] = $entity->user->custom_value1 ?? '';

        $search[] = '{$user_custom2}';
        $replace[] = $entity->user->custom_value2 ?? '';

        $search[] = '{$user_custom3}';
        $replace[] = $entity->user->custom_value3 ?? '';

        $search[] = '{$user_custom4}';
        $replace[] = $entity->user->custom_value4 ?? '';

        return str_replace($search, $replace, $pattern);
    }
}

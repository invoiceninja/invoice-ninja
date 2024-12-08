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

namespace App\Services\Quote;

use App\Models\Quote;
use App\Services\AbstractService;
use App\Models\RecurringInvoice;
use Carbon\Carbon;

class UpdateReminder extends AbstractService
{
    public function __construct(public Quote $quote, public mixed $settings = null)
    {
    }

    /* We only support setting reminders based on the due date, not the partial due date */
    public function run()
    {
        if (! $this->settings) {
            $this->settings = $this->quote->client->getMergedSettings();
        }

        if (!$this->quote->canRemind()) {
            $this->quote->next_send_date = null;
            $this->quote->saveQuietly();

            return $this->quote; //exit early
        }

        if ($this->quote->next_send_date) {
            $this->quote->next_send_date = null;
        }

        $offset = $this->quote->client->timezone_offset();

        $date_collection = collect();

        if (is_null($this->quote->reminder1_sent) &&
            $this->settings->quote_schedule_reminder1 == 'after_quote_date') {
            $reminder_date = Carbon::parse($this->quote->date)->startOfDay()->addDays((int)$this->settings->quote_num_days_reminder1)->addSeconds($offset);

            if ($reminder_date->gt(now())) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->quote->reminder1_sent) &&
            ($this->quote->partial_due_date || $this->quote->due_date) &&
            $this->settings->quote_schedule_reminder1 == 'before_valid_until_date') {
            $partial_or_due_date = ($this->quote->partial > 0 && isset($this->quote->partial_due_date)) ? $this->quote->partial_due_date : $this->quote->due_date;
            $reminder_date = Carbon::parse($partial_or_due_date)->startOfDay()->subDays((int)$this->settings->quote_num_days_reminder1)->addSeconds($offset);
            // nlog("1. {$reminder_date->format('Y-m-d')}");

            if ($reminder_date->gt(now())) {
                $date_collection->push($reminder_date);
            }
        }

        if (is_null($this->quote->reminder1_sent) &&
            ($this->quote->partial_due_date || $this->quote->due_date) &&
            $this->settings->quote_schedule_reminder1 == 'after_valid_until_date') {

            $partial_or_due_date = ($this->quote->partial > 0 && isset($this->quote->partial_due_date)) ? $this->quote->partial_due_date : $this->quote->due_date;
            $reminder_date = Carbon::parse($partial_or_due_date)->startOfDay()->addDays((int)$this->settings->quote_num_days_reminder1)->addSeconds($offset);
            // nlog("2. {$reminder_date->format('Y-m-d')}");

            if ($reminder_date->gt(now())) {
                $date_collection->push($reminder_date);
            }
        }

        // if (is_null($this->quote->reminder2_sent) &&
        //     $this->settings->schedule_reminder2 == 'after_valid_until_date') {
        //     $reminder_date = Carbon::parse($this->quote->date)->startOfDay()->addDays($this->settings->num_days_reminder2);

        //     if ($reminder_date->gt(now())) {
        //         $date_collection->push($reminder_date);
        //     }
        // }

        // if (is_null($this->quote->reminder2_sent) &&
        //     ($this->quote->partial_due_date || $this->quote->due_date) &&
        //     $this->settings->schedule_reminder2 == 'before_valid_until_date') {

        //     $partial_or_due_date = ($this->quote->partial > 0 && isset($this->quote->partial_due_date)) ? $this->quote->partial_due_date : $this->quote->due_date;
        //     $reminder_date = Carbon::parse($partial_or_due_date)->startOfDay()->subDays($this->settings->num_days_reminder2);
        //     // nlog("3. {$reminder_date->format('Y-m-d')}");

        //     if ($reminder_date->gt(now())) {
        //         $date_collection->push($reminder_date);
        //     }
        // }

        // if (is_null($this->quote->reminder2_sent) &&
        //     ($this->quote->partial_due_date || $this->quote->due_date) &&
        //     $this->settings->schedule_reminder2 == 'after_valid_until_date') {

        //     $partial_or_due_date = ($this->quote->partial > 0 && isset($this->quote->partial_due_date)) ? $this->quote->partial_due_date : $this->quote->due_date;
        //     $reminder_date = Carbon::parse($partial_or_due_date)->startOfDay()->addDays($this->settings->num_days_reminder2);
        //     // nlog("4. {$reminder_date->format('Y-m-d')}");

        //     if ($reminder_date->gt(now())) {
        //         $date_collection->push($reminder_date);
        //     }
        // }

        // if (is_null($this->quote->reminder3_sent) &&
        //     $this->settings->schedule_reminder3 == 'after_valid_until_date') {
        //     $reminder_date = Carbon::parse($this->quote->date)->startOfDay()->addDays($this->settings->num_days_reminder3);

        //     if ($reminder_date->gt(now())) {
        //         $date_collection->push($reminder_date);
        //     }
        // }

        // if (is_null($this->quote->reminder3_sent) &&
        //     ($this->quote->partial_due_date || $this->quote->due_date) &&
        //     $this->settings->schedule_reminder3 == 'before_valid_until_date') {

        //     $partial_or_due_date = ($this->quote->partial > 0 && isset($this->quote->partial_due_date)) ? $this->quote->partial_due_date : $this->quote->due_date;
        //     $reminder_date = Carbon::parse($partial_or_due_date)->startOfDay()->subDays($this->settings->num_days_reminder3);
        //     // nlog("5. {$reminder_date->format('Y-m-d')}");

        //     if ($reminder_date->gt(now())) {
        //         $date_collection->push($reminder_date);
        //     }
        // }

        // if (is_null($this->quote->reminder3_sent) &&
        //     ($this->quote->partial_due_date || $this->quote->due_date) &&
        //     $this->settings->schedule_reminder3 == 'after_valid_until_date') {

        //     $partial_or_due_date = ($this->quote->partial > 0 && isset($this->quote->partial_due_date)) ? $this->quote->partial_due_date : $this->quote->due_date;
        //     $reminder_date = Carbon::parse($partial_or_due_date)->startOfDay()->addDays($this->settings->num_days_reminder3);
        //     // nlog("6. {$reminder_date->format('Y-m-d')}");

        //     if ($reminder_date->gt(now())) {
        //         $date_collection->push($reminder_date);
        //     }
        // }

        // if ($this->quote->last_sent_date &&
        //     $this->settings->enable_reminder_endless &&
        //     ($this->quote->reminder1_sent || $this->settings->schedule_reminder1 == "" || !$this->settings->enable_reminder1) &&
        //     ($this->quote->reminder2_sent || $this->settings->schedule_reminder2 == "" || !$this->settings->enable_reminder2) &&
        //     ($this->quote->reminder3_sent || $this->settings->schedule_reminder3 == "" || !$this->settings->enable_reminder3)) {
        //     $reminder_date = $this->addTimeInterval($this->quote->last_sent_date, (int) $this->settings->endless_reminder_frequency_id);

        //     if ($reminder_date) {
        //         if ($reminder_date->gt(now())) {
        //             $date_collection->push($reminder_date);
        //         }
        //     }
        // }

        if ($date_collection->count() >= 1 && $date_collection->sort()->first()->gte(now())) {
            $this->quote->next_send_date = $date_collection->sort()->first()->addSeconds($offset);
        } else {
            $this->quote->next_send_date = null;
        }

        return $this->quote;
    }

    // private function addTimeInterval($date, $endless_reminder_frequency_id): ?Carbon
    // {
    //     if (! $date) {
    //         return null;
    //     }

    //     switch ($endless_reminder_frequency_id) {
    //         case RecurringInvoice::FREQUENCY_DAILY:
    //             return Carbon::parse($date)->addDay()->startOfDay();
    //         case RecurringInvoice::FREQUENCY_WEEKLY:
    //             return Carbon::parse($date)->addWeek()->startOfDay();
    //         case RecurringInvoice::FREQUENCY_TWO_WEEKS:
    //             return Carbon::parse($date)->addWeeks(2)->startOfDay();
    //         case RecurringInvoice::FREQUENCY_FOUR_WEEKS:
    //             return Carbon::parse($date)->addWeeks(4)->startOfDay();
    //         case RecurringInvoice::FREQUENCY_MONTHLY:
    //             return Carbon::parse($date)->addMonthNoOverflow()->startOfDay();
    //         case RecurringInvoice::FREQUENCY_TWO_MONTHS:
    //             return Carbon::parse($date)->addMonthsNoOverflow(2)->startOfDay();
    //         case RecurringInvoice::FREQUENCY_THREE_MONTHS:
    //             return Carbon::parse($date)->addMonthsNoOverflow(3)->startOfDay();
    //         case RecurringInvoice::FREQUENCY_FOUR_MONTHS:
    //             return Carbon::parse($date)->addMonthsNoOverflow(4)->startOfDay();
    //         case RecurringInvoice::FREQUENCY_SIX_MONTHS:
    //             return Carbon::parse($date)->addMonthsNoOverflow(6)->startOfDay();
    //         case RecurringInvoice::FREQUENCY_ANNUALLY:
    //             return Carbon::parse($date)->addYear()->startOfDay();
    //         case RecurringInvoice::FREQUENCY_TWO_YEARS:
    //             return Carbon::parse($date)->addYears(2)->startOfDay();
    //         case RecurringInvoice::FREQUENCY_THREE_YEARS:
    //             return Carbon::parse($date)->addYears(3)->startOfDay();
    //         default:
    //             return null;
    //     }
    // }
}

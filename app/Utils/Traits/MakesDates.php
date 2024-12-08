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

use App\DataMapper\Schedule\EmailStatement;
use App\Models\Company;
use Carbon\Carbon;
use DateTime;
use DateTimeZone;

/**
 * Class MakesDates.
 */
trait MakesDates
{
    /**
     * Converts from UTC to client timezone.
     * @param  datetime 	object 		$utc_date
     * @param  string 		$timezone 	ie Australia/Sydney
     * @return Carbon           		Carbon object
     */
    public function createClientDate($utc_date, $timezone)
    {
        if (is_string($utc_date)) {
            $utc_date = $this->convertToDateObject($utc_date);
        }

        return $utc_date->setTimezone(new DateTimeZone($timezone));
    }

    /**
     * Converts from client timezone to UTC.
     * @param datetime    object        $utc_date
     * @return Carbon                Carbon object
     */
    public function createUtcDate($client_date)
    {
        if (is_string($client_date)) {
            $client_date = $this->convertToDateObject($client_date);
        }

        return $client_date->setTimezone(new DateTimeZone('GMT'));
    }

    /**
     * Formats a date.
     * @param  Carbon|string $date   Carbon object or date string
     * @param  string $format The date display format
     * @return string         The formatted date
     */
    public function formatDate($date, string $format): string
    {
        if (! isset($date)) {
            return '';
        }

        if (is_string($date)) {
            $date = $this->convertToDateObject($date);
        }

        return $date->format($format);
    }

    /**
     * Formats a datedate.
     * @param  $date   Carbon object or date string
     * @param  string $format The date display format
     * @return string         The formatted date
     */
    public function formatDatetime($date, string $format): string
    {
        return Carbon::createFromTimestamp((int) ($date ?? 0))->format($format.' g:i a');
    }

    /**
     * Formats a date.
     * @param  Carbon/String $date   Carbon object or date string
     * @param  string $format The date display format
     * @return string         The formatted date
     */
    public function formatDateTimestamp($timestamp, string $format): string
    {
        return Carbon::createFromTimestamp((int)$timestamp)->format($format);
    }

    private function convertToDateObject($date)
    {
        $dt = new DateTime($date);
        $dt->setTimezone(new DateTimeZone('UTC'));

        return $dt;
    }

    public function translateDate($date, $format, $locale)
    {
        if (empty($date)) {
            return '';
        }

        Carbon::setLocale($locale);

        try {
            return Carbon::parse($date)->translatedFormat($format);
        } catch (\Exception $e) {
            return 'Invalid date!';
        }
    }

    /**
     * Start and end date of the statement
     *
     * @return array [$start_date, $end_date];
     */
    public function calculateStartAndEndDates(array $data, ?Company $company = null): array
    {
        //override for financial years
        if ($data['date_range'] == 'this_year') {
            $first_month_of_year = $company ? $company?->first_month_of_year : 1;
            $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

            if (now()->lt($fin_year_start)) {
                $fin_year_start->subYearNoOverflow();
            }

        }

        //override for financial years
        if ($data['date_range'] == 'last_year') {
            $first_month_of_year = $company ? $company?->first_month_of_year : 1;
            $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

            $fin_year_start->subYearNoOverflow();

            if (now()->subYear()->lt($fin_year_start)) {
                $fin_year_start->subYearNoOverflow();
            }

        }

        return match ($data['date_range']) {
            EmailStatement::LAST7 => [now()->startOfDay()->subDays(7)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST30 => [now()->startOfDay()->subDays(30)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST365 => [now()->startOfDay()->subDays(365)->format('Y-m-d'), now()->startOfDay()->format('Y-m-d')],
            EmailStatement::THIS_MONTH => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::LAST_MONTH => [now()->startOfDay()->subMonthNoOverflow()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->subMonthNoOverflow()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::THIS_QUARTER => [now()->startOfDay()->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->endOfQuarter()->format('Y-m-d')],
            EmailStatement::LAST_QUARTER => [now()->startOfDay()->subQuarterNoOverflow()->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuarterNoOverflow()->endOfQuarter()->format('Y-m-d')],
            EmailStatement::THIS_YEAR => [$fin_year_start->format('Y-m-d'), $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d')],
            EmailStatement::LAST_YEAR => [$fin_year_start->format('Y-m-d'), $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d')],
            EmailStatement::CUSTOM_RANGE => [$data['start_date'], $data['end_date']],
            default => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
        };
    }

    public function calculatePreviousPeriodStartAndEndDates(array $data, ?Company $company = null): array
    {

        //override for financial years
        if ($data['date_range'] == 'this_year') {

            $first_month_of_year = $company ? $company?->first_month_of_year : 1;
            $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

            $fin_year_start->subYearNoOverflow();

            if (now()->subYear()->lt($fin_year_start)) {
                $fin_year_start->subYearNoOverflow();
            }

        }

        return match ($data['date_range']) {
            EmailStatement::LAST7 => [now()->startOfDay()->subDays(14)->format('Y-m-d'), now()->subDays(7)->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST30 => [now()->startOfDay()->subDays(60)->format('Y-m-d'), now()->subDays(30)->startOfDay()->format('Y-m-d')],
            EmailStatement::LAST365 => [now()->startOfDay()->subDays(739)->format('Y-m-d'), now()->subDays(365)->startOfDay()->format('Y-m-d')],
            EmailStatement::THIS_MONTH => [now()->startOfDay()->subMonthNoOverflow()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->subMonthNoOverflow()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::LAST_MONTH => [now()->startOfDay()->subMonthsNoOverflow(2)->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->subMonthNoOverflow()->lastOfMonth()->format('Y-m-d')],
            EmailStatement::THIS_QUARTER => [now()->startOfDay()->subQuarterNoOverflow()->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuarterNoOverflow()->endOfQuarter()->format('Y-m-d')],
            EmailStatement::LAST_QUARTER => [now()->startOfDay()->subQuartersNoOverflow(2)->startOfQuarter()->format('Y-m-d'), now()->startOfDay()->subQuartersNoOverflow(2)->endOfQuarter()->format('Y-m-d')],
            EmailStatement::THIS_YEAR => [$fin_year_start->subYear()->format('Y-m-d'), $fin_year_start->copy()->subDay()->format('Y-m-d')],
            EmailStatement::LAST_YEAR => [$fin_year_start->subYear(2)->format('Y-m-d'), $fin_year_start->copy()->subYear()->subDay()->format('Y-m-d')],
            EmailStatement::CUSTOM_RANGE => [$data['start_date'], $data['end_date']],
            default => [now()->startOfDay()->firstOfMonth()->format('Y-m-d'), now()->startOfDay()->lastOfMonth()->format('Y-m-d')],
        };

    }

}

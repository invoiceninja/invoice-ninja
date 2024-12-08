<?php
/**
 * Invoice Ninja (https://invoiceninja.com).
 *
 * @link https://github.com/invoiceninja/invoiceninja source repository
 *
 * @copyright Copyright (c) 2021. Invoice Ninja LLC (https://invoiceninja.com)
 *
 * @license https://www.elastic.co/licensing/elastic-license
 */

namespace Tests\Unit;

use Carbon\Carbon;
use Tests\TestCase;

class DatesTest extends TestCase
{

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function testDateNotGreaterThanMonthsEnd()
    {
        $this->travelTo(now()->createFromDate(2024, 6, 20));
        $date = '2024-05-20';

        $this->assertTrue(\Carbon\Carbon::parse($date)->endOfMonth()->lte(now()));

        $this->travelBack();

    }

    public function testDatLessThanMonthsEnd()
    {
        $this->travelTo(now()->createFromDate(2024, 5, 30));
        $date = '2024-05-20';

        $this->assertFalse(\Carbon\Carbon::parse($date)->endOfMonth()->lte(now()));

        $this->travelBack();

    }


    public function testLastFinancialYear3()
    {
        $this->travelTo(now()->createFromDate(2020, 6, 30));

        //override for financial years
        $first_month_of_year = 7;
        $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

        $fin_year_start->subYearNoOverflow();

        if(now()->subYear()->lt($fin_year_start)) {
            $fin_year_start->subYearNoOverflow();
        }

        $this->assertEquals('2018-07-01', $fin_year_start->format('Y-m-d'));
        $this->assertEquals('2019-06-30', $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d'));

        $this->travelBack();

    }

    public function testLastFinancialYear2()
    {
        $this->travelTo(now()->createFromDate(2020, 7, 1));

        //override for financial years
        $first_month_of_year = 7;
        $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

        $fin_year_start->subYearNoOverflow();

        if(now()->subYear()->lt($fin_year_start)) {
            $fin_year_start->subYearNoOverflow();
        }

        $this->assertEquals('2019-07-01', $fin_year_start->format('Y-m-d'));
        $this->assertEquals('2020-06-30', $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d'));

        $this->travelBack();

    }

    public function testLastFinancialYear()
    {
        $this->travelTo(now()->createFromDate(2020, 12, 1));

        //override for financial years
        $first_month_of_year = 7;
        $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

        $fin_year_start->subYearNoOverflow();

        if(now()->subYear()->lt($fin_year_start)) {
            $fin_year_start->subYearNoOverflow();
        }

        $this->assertEquals('2019-07-01', $fin_year_start->format('Y-m-d'));
        $this->assertEquals('2020-06-30', $fin_year_start->copy()->addYear()->subDay()->format('Y-m-d'));

        $this->travelBack();

    }

    public function testFinancialYearDates4()
    {
        $this->travelTo(now()->createFromDate(2020, 12, 1));

        $first_month_of_year = 7;

        $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

        if(now()->lt($fin_year_start)) {
            $fin_year_start->subYear();
        }

        $fin_year_end = $fin_year_start->copy()->addYear()->subDay();

        $this->assertEquals('2020-07-01', $fin_year_start->format('Y-m-d'));
        $this->assertEquals('2021-06-30', $fin_year_end->format('Y-m-d'));

        $this->travelBack();

    }

    public function testFinancialYearDates3()
    {
        $this->travelTo(now()->createFromDate(2021, 12, 1));

        $first_month_of_year = 7;

        $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

        if(now()->lt($fin_year_start)) {
            $fin_year_start->subYear();
        }

        $fin_year_end = $fin_year_start->copy()->addYear()->subDay();

        $this->assertEquals('2021-07-01', $fin_year_start->format('Y-m-d'));
        $this->assertEquals('2022-06-30', $fin_year_end->format('Y-m-d'));

        $this->travelBack();

    }

    public function testFinancialYearDates2()
    {
        $this->travelTo(now()->createFromDate(2021, 8, 1));

        $first_month_of_year = 7;

        $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

        if(now()->lt($fin_year_start)) {
            $fin_year_start->subYear();
        }

        $fin_year_end = $fin_year_start->copy()->addYear()->subDay();

        $this->assertEquals('2021-07-01', $fin_year_start->format('Y-m-d'));
        $this->assertEquals('2022-06-30', $fin_year_end->format('Y-m-d'));

        $this->travelBack();

    }


    public function testFinancialYearDates()
    {
        $this->travelTo(now()->createFromDate(2021, 1, 1));

        $first_month_of_year = 7;

        $fin_year_start = now()->createFromDate(now()->year, $first_month_of_year, 1);

        if(now()->lt($fin_year_start)) {
            $fin_year_start->subYear();
        }

        $fin_year_end = $fin_year_start->copy()->addYear()->subDay();

        $this->assertEquals('2020-07-01', $fin_year_start->format('Y-m-d'));
        $this->assertEquals('2021-06-30', $fin_year_end->format('Y-m-d'));

        $this->travelBack();

    }

    public function testDaysDiff()
    {
        $string_date = '2021-06-01';

        $start_date = Carbon::parse($string_date);
        $current_date = Carbon::parse('2021-06-20');

        $diff_in_days = intval(abs($start_date->diffInDays($current_date)));

        $this->assertEquals(19, $diff_in_days);
    }

    public function testDiffInDaysRange()
    {
        $now = Carbon::parse('2020-01-01');

        $x = intval(abs(now()->diffInDays(now()->addDays(7))));

        $this->assertEquals(7, intval(abs($x)));
    }

    public function testFourteenDaysFromNow()
    {
        $date_in_past = '2020-01-01';

        $date_in_future = Carbon::parse('2020-01-16');

        $this->assertTrue($date_in_future->gt(Carbon::parse($date_in_past)->addDays(14)));
    }

    public function testThirteenteenDaysFromNow()
    {
        $date_in_past = '2020-01-01';

        $date_in_future = Carbon::parse('2020-01-15');

        $this->assertFalse($date_in_future->gt(Carbon::parse($date_in_past)->addDays(14)));
    }

    /*Test time travelling behaves as expected */
    // public function testTimezoneShifts()
    // {
    //     $this->travel(Carbon::parse('2022-12-20'));

    //     $this->assertEquals('2022-12-20', now()->setTimeZone('Pacific/Midway')->format('Y-m-d'));

    //     $this->travelBack();
    // }
}

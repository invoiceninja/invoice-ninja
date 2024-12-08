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

use App\Utils\Traits\Recurring\HasRecurrence;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RecurringDueDatesTest extends TestCase
{
    use HasRecurrence;

    public function testFirstDate()
    {
        $date = Carbon::parse('2020-02-15');

        $due_date = $this->calculateFirstDayOfMonth($date);

        $this->assertEquals('2020-03-01', $due_date->format('Y-m-d'));
    }

    public function testFirstOfMonthOnFirst()
    {
        $date = Carbon::parse('2020-02-01');

        $due_date = $this->calculateFirstDayOfMonth($date);

        $this->assertEquals('2020-03-01', $due_date->format('Y-m-d'));
    }

    public function testFirstOfMonthOnLast()
    {
        $date = Carbon::parse('2020-03-31');

        $due_date = $this->calculateFirstDayOfMonth($date);

        $this->assertEquals('2020-04-01', $due_date->format('Y-m-d'));
    }

    public function testLastOfMonth()
    {
        $date = Carbon::parse('2020-02-15');

        $due_date = $this->calculateLastDayOfMonth($date);

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testLastOfMonthOnFirst()
    {
        $date = Carbon::parse('2020-02-1');

        $due_date = $this->calculateLastDayOfMonth($date);

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testLastOfMonthOnLast()
    {
        $date = Carbon::parse('2020-02-29');

        $due_date = $this->calculateLastDayOfMonth($date);

        $this->assertEquals('2020-03-31', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonth()
    {
        $date = Carbon::parse('2020-02-01');

        $due_date = $this->setDayOfMonth($date, '15');

        $this->assertEquals('2020-02-15', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthInFuture()
    {
        $date = Carbon::parse('2020-02-16');

        $due_date = $this->setDayOfMonth($date, '15');

        $this->assertEquals('2020-03-15', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthSameDay()
    {
        $date = Carbon::parse('2020-02-01');

        $due_date = $this->setDayOfMonth($date, '1');

        $this->assertEquals('2020-03-01', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow()
    {
        $date = Carbon::parse('2020-1-31');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow2()
    {
        $date = Carbon::parse('2020-02-29');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2020-03-31', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow3()
    {
        $date = Carbon::parse('2020-01-30');

        $due_date = $this->setDayOfMonth($date, '30');

        $this->assertEquals('2020-02-29', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow4()
    {
        $date = Carbon::parse('2019-02-28');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2019-03-31', $due_date->format('Y-m-d'));
    }

    public function testDayOfMonthWithOverflow5()
    {
        $date = Carbon::parse('2019-1-31');

        $due_date = $this->setDayOfMonth($date, '31');

        $this->assertEquals('2019-02-28', $due_date->format('Y-m-d'));
    }
}

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

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Tests\MockAccountData;
use Tests\TestCase;

/**
 * 
 */
class RecurringDateTest extends TestCase
{
    use DatabaseTransactions;
    use MockAccountData;

    protected function setUp(): void
    {
        parent::setUp();

        //$this->makeTestData();
    }

    public function testNextDay()
    {
        $trial = 60 * 60 * 24;

        $now = Carbon::parse('2021-12-01');

        $trial_ends = $now->addSeconds($trial)->addDays(1);

        $this->assertequals($trial_ends->format('Y-m-d'), '2021-12-03');
    }

    public function testDateOverflowsForEndOfMonth()
    {
        $today = Carbon::parse('2022-01-31');

        $next_month = $today->addMonthNoOverflow();

        $this->assertEquals('2022-02-28', $next_month->format('Y-m-d'));

    }

}

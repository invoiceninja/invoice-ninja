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

use App\Utils\Number;
use Tests\TestCase;

/**
 * 
 *    */
class NumberRoundingTest extends TestCase
{

    public function testMercantileRounding()
    {
        $this->assertEquals(32.33, round(32.325,2));
    }

    // public function testMercantileRoundingTwo()
    // {
    //     $this->assertEquals(32.32, round(32.32499999999996,2));
    // }

    public function testMercantileRoundingThreeo()
    {
        $this->assertEquals(32.325, round(32.32499999999996,3));
    }
}

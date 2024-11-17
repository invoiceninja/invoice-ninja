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

namespace Tests\Pdf;

use Beganovich\Snappdf\Snappdf;
use Tests\TestCase;

/**
 * 
 *   App\DataMapper\BaseSettings
 */
class PdfGenerationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (config('ninja.testvars.travis') !== false) {
            $this->markTestSkipped('Skip test for GH Actions');
        }

    }

    public function testPdfGeneration()
    {
        $pdf = new Snappdf();

        if (config('ninja.snappdf_chromium_path')) {
            $pdf->setChromiumPath(config('ninja.snappdf_chromium_path'));
        }

        if (config('ninja.snappdf_chromium_arguments')) {
            $pdf->clearChromiumArguments();
            $pdf->addChromiumArguments(config('ninja.snappdf_chromium_arguments'));
        }

        $pdf = $pdf
            ->setHtml('<h1>Invoice Ninja</h1>')
            ->generate();

        $this->assertNotNull($pdf);
    }
}

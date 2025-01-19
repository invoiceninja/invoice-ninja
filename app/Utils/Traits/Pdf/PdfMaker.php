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

namespace App\Utils\Traits\Pdf;

use App\Exceptions\InternalPDFFailure;
use Beganovich\Snappdf\Snappdf;

trait PdfMaker
{
    /**
     * Returns a PDF stream.
     *
     * @param  string|null $header Header to be included in PDF
     * @param  string|null $footer Footer to be included in PDF
     * @param  string $html   The HTML object to be converted into PDF
     *
     * @return string        The PDF string
     */
    public function makePdf($header, $footer, $html)
    {
        $pdf = new Snappdf();

        $chrome_flags = [
            '--headless',
            '--no-sandbox',
            '--disable-gpu',
            '--no-margins',
            '--hide-scrollbars',
            '--no-first-run',
            '--no-default-browser-check',

            // PDF-specific settings
            '--print-to-pdf-no-header',
            '--no-pdf-header-footer',

            // Security settings
            '--disable-web-security=false',
            '--block-insecure-private-network-requests',
            '--block-port=22,25,465,587',
            '--disable-usb',
            '--disable-webrtc',
            '--block-new-web-contents',
            '--deny-permission-prompts',
            '--ignore-certificate-errors',

            // Performance & resource settings
            '--disable-dev-shm-usage',
            '--disable-software-rasterizer',
            '--run-all-compositor-stages-before-draw',
            '--disable-renderer-backgrounding',
            '--disable-background-timer-throttling',
            '--disable-background-networking',
            '--disable-domain-reliability',
            '--disable-ipc-flooding-protection',

            // Feature disabling
            '--disable-translate',
            '--disable-extensions',
            '--disable-sync',
            '--disable-default-apps',
            '--disable-plugins',
            '--disable-notifications',
            '--disable-device-discovery-notifications',
            '--disable-reading-from-canvas',
            '--safebrowsing-disable-auto-update',
            '--disable-features=SharedArrayBuffer,OutOfBlinkCors,NetworkService,NetworkServiceInProcess',

            '--virtual-time-budget=2000',
            '--font-render-hinting=medium',
            '--enable-font-antialiasing',
            
            // Debug/Output
            '--dump-dom',
        ];

        // if (config('ninja.snappdf_chromium_arguments')) {
            $pdf->clearChromiumArguments();
            // $pdf->addChromiumArguments(config('ninja.snappdf_chromium_arguments'));
            $pdf->addChromiumArguments(implode(' ', $chrome_flags));
        // }

        if (config('ninja.snappdf_chromium_path')) {
            $pdf->setChromiumPath(config('ninja.snappdf_chromium_path'));
        }

        $html = str_ireplace(['file:/', 'iframe', '<embed', '&lt;embed', '&lt;object', '<object', '127.0.0.1', 'localhost', '<?xml encoding="UTF-8">'], '', $html);

        // nlog($html);
        $generated = $pdf
                        ->setHtml($html)
                        ->generate();

        if ($generated) {
            return $generated;
        }

        throw new InternalPDFFailure('There was an issue generating the PDF locally');
    }
}

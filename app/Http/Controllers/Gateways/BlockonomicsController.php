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

namespace App\Http\Controllers\Gateways;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request; // Import the Request class
use Illuminate\Support\Facades\Http; // Import the Http facade
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class BlockonomicsController extends Controller
{
    public function getBTCPrice(Request $request)
    {
        $currency = $request->query('currency');
        $response = Http::get("https://www.blockonomics.co/api/price?currency={$currency}");

        if ($response->successful()) {
            return response()->json(['price' => $response->json('price')]);
        }

        return response()->json(['error' => 'Unable to fetch BTC price'], 500);
    }

    public function getQRCode(Request $request)
    {
        $qr_string = $request->query('qr_string');
        $svg = $this->getPaymentQrCodeRaw($qr_string);
        return response($svg)->header('Content-Type', 'image/svg+xml');
    }

    private function getPaymentQrCodeRaw($qr_string)
    {

        $renderer = new ImageRenderer(
            new RendererStyle(150, margin: 0),
            new SvgImageBackEnd()
        );
        $writer = new Writer($renderer);

        $qr = $writer->writeString($qr_string, 'utf-8');

        return $qr;

    }
}

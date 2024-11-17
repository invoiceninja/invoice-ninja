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

namespace App\Http\Controllers;

use App\Http\Controllers\BaseController;
use App\Http\Requests\EInvoice\UpdateTokenRequest;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;

class EInvoiceTokenController extends BaseController
{
    public function __invoke(UpdateTokenRequest $request): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('/api/einvoice/tokens/rotate', data: [
                'license' => config('ninja.license_key'),
                'account_key' => $user->account->key,
            ]);

        if ($response->successful()) {
            $user->account->update([
                'e_invoicing_token' => $response->json('token'),
            ]);

            return response()->noContent();
        }

        return response()->noContent(status: 422);
    }
}

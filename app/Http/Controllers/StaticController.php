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

use App\Utils\Statics;
use Illuminate\Http\Response;
use InvoiceNinja\EInvoice\Decoder\Schema;

class StaticController extends BaseController
{
    /**
     * Show the list of Invoices.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/statics",
     *      operationId="getStatics",
     *      tags={"statics"},
     *      summary="Gets a list of statics",
     *      description="Lists all statics",
     *
     *      @OA\Parameter(ref="#/components/parameters/X-API-TOKEN"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="A list of static data",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function __invoke()
    {

        /** @var \App\Models\User $user */
        $user = auth()->user();

        $response_data = Statics::company($user->getLocale() ?? $user->company()->getLocale());

        if (request()->has('einvoice')) {

            $schema = new Schema();
            $response_data['einvoice_schema'] = $schema('Peppol');

        }

        if (\App\Utils\Ninja::isSelfHost()) {
            $response_data['license_key'] = config('ninja.license_key');
        }

        return response()->json($response_data, 200, ['Content-type' => 'application/json; charset=utf-8'], JSON_PRETTY_PRINT);
    }
}

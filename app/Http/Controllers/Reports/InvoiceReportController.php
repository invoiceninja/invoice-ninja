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

namespace App\Http\Controllers\Reports;

use App\Export\CSV\InvoiceExport;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Report\GenericReportRequest;
use App\Jobs\Report\PreviewReport;
use App\Jobs\Report\SendToAdmin;
use App\Utils\Traits\MakesHash;
use Illuminate\Http\Response;

class InvoiceReportController extends BaseController
{
    use MakesHash;

    private string $filename = 'invoices.csv';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * @OA\Post(
     *      path="/api/v1/reports/invoices",
     *      operationId="getInvoiceReport",
     *      tags={"reports"},
     *      summary="Invoice reports",
     *      description="Export invoice reports",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\RequestBody(
     *          required=true,
     *          @OA\JsonContent(ref="#/components/schemas/GenericReportSchema")
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="success",
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
    public function __invoke(GenericReportRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
nlog($request->all());
        if ($request->has('send_email') && $request->get('send_email') && $request->missing('output')) {
            SendToAdmin::dispatch($user->company(), $request->all(), InvoiceExport::class, $this->filename);

            return response()->json(['message' => 'working...'], 200);
        }

        $hash = \Illuminate\Support\Str::uuid();

        PreviewReport::dispatch($user->company(), $request->all(), InvoiceExport::class, $hash);

        return response()->json(['message' => $hash], 200);

    }
}

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

use App\Http\Requests\OneTimeToken\OneTimeRouterRequest;
use App\Http\Requests\OneTimeToken\OneTimeTokenRequest;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class OneTimeTokenController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param OneTimeTokenRequest $request
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Post(
     *      path="/api/v1/one_time_token",
     *      operationId="oneTimeToken",
     *      tags={"one_time_token"},
     *      summary="Attempts to create a one time token",
     *      description="Attempts to create a one time token",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Response(
     *          response=200,
     *          description="The Company User response",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit")
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
    public function create(OneTimeTokenRequest $request)
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();

        $hash = Str::random(64);

        $data = [
            'user_id' => $user->id,
            'company_key' => $user->company()->company_key,
            'context' => $request->input('context'),
            'is_react' => $request->hasHeader('X-REACT') ? true : false,
        ];

        if ($request->institution_id) {
            $data['institution_id'] = $request->institution_id;
        }

        Cache::put($hash, $data, 3600);

        return response()->json(['hash' => $hash], 200);
    }

    public function router(OneTimeRouterRequest $request)
    {
        $data = Cache::get($request->input('hash'));

        MultiDB::findAndSetDbByCompanyKey($data['company_key']);

        $this->sendTo($data['context']);
    }

    /* We need to merge all contexts here and redirect to the correct location */
    private function sendTo($context)
    {
        return redirect();
    }
}

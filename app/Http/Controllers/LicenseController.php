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

use App\Http\Requests\License\CheckRequest;
use App\Models\Account;
use App\Utils\CurlUtils;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Http;
use stdClass;

class LicenseController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Claim a white label license.
     *
     * @return Response| \Illuminate\Http\JsonResponse
     *
     * @OA\Get(
     *      path="/api/v1/claim_license",
     *      operationId="getClaimLicense",
     *      tags={"claim_license"},
     *      summary="Attempts to claim a white label license",
     *      description="Attempts to claim a white label license",
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(
     *          name="license_key",
     *          in="query",
     *          description="The license hash",
     *          example="d87sh-s755s-s7d76-sdsd8",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Parameter(
     *          name="product_id",
     *          in="query",
     *          description="The ID of the product purchased.",
     *          example="1",
     *          required=true,
     *          @OA\Schema(
     *              type="string",
     *              format="string",
     *          ),
     *      ),
     *      @OA\Response(
     *          response=200,
     *          description="Success!",
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
    public function index()
    {
        $this->checkLicense();

        nlog("License Check::");
        nlog(config('ninja.environment'));
        nlog(request()->has('license_key'));
        nlog(request()->input('license_key','No License Found'));

        /* Catch claim license requests */
        if (config('ninja.environment') == 'selfhost' && request()->has('license_key')) {
            $license_key = trim(request()->input('license_key'));
            $product_id = 3;

            if(substr($license_key, 0, 3) == 'v5_') {
                return $this->v5ClaimLicense($license_key, $product_id);
            }

            $url = config('ninja.license_url')."/claim_license?license_key={$license_key}&product_id={$product_id}&get_date=true";
            $data = trim(CurlUtils::get($url));

            if ($data == Account::RESULT_FAILURE) {
                $error = [
                    'message' => trans('texts.invalid_white_label_license'),
                    'errors' => new stdClass(),
                ];

                return response()->json($error, 400);
            } elseif ($data) {
                $date = date_create($data)->modify('+1 year');

                if ($date < date_create()) {
                    $error = [
                        'message' => trans('texts.invalid_white_label_license'),
                        'errors' => new stdClass(),
                    ];
                    $account = auth()->user()->account;
                    $account->plan_term = Account::PLAN_TERM_YEARLY;
                    $account->plan_paid = null;
                    $account->plan_expires = null;
                    $account->plan = Account::PLAN_FREE;
                    $account->save();

                    return response()->json($error, 400);
                } else {
                    $account = auth()->user()->account;

                    $account->plan_term = Account::PLAN_TERM_YEARLY;
                    $account->plan_paid = $data;
                    $account->plan_expires = $date->format('Y-m-d');
                    $account->plan = Account::PLAN_WHITE_LABEL;
                    $account->save();

                    $error = [
                        'message' => trans('texts.bought_white_label'),
                        'errors' => new stdClass(),
                    ];

                    return response()->json($error, 200);
                }
            } else {
                $error = [
                    'message' => 'There was an issue connecting to the license server. Please check your network.',
                    'errors' => new stdClass(),
                ];

                return response()->json($error, 400);
            }
        }

        nlog("No license key or environment not set to selfhost");

        $error = [
            'message' => ctrans('texts.invoice_license_or_environment', ['environment' => config('ninja.environment')]),
            'errors' => new stdClass(),
        ];

        return response()->json($error, 400);
    }

    public function v5ClaimLicense(string $license_key)
    {
        $this->checkLicense();

        /* Catch claim license requests */ 
        if (config('ninja.environment') == 'selfhost') {

            nlog("Claiming v5 license");

            $response = Http::get("https://invoicing.co/claim_license", [
                'license_key' => $license_key,
                'product_id' => 3,
            ]);

            $payload = $response->json();

            nlog("Ninja Server Response");
            nlog($payload);

            if ($response->successful()) {

                $account = auth()->user()->account;

                $account->plan_term = Account::PLAN_TERM_YEARLY;
                $account->plan_expires = Carbon::parse($payload['expires'])->addYear()->format('Y-m-d');
                $account->plan = Account::PLAN_WHITE_LABEL;
                $account->save();

                $error = [
                    'message' => trans('texts.bought_white_label'),
                    'errors' => new \stdClass(),
                ];

                return response()->json($error, 200);
            } else {
                $error = [
                    'message' => trans('texts.white_label_license_error'),
                    'errors' => new stdClass(),
                ];

                return response()->json($error, 400);
            }
        }

        nlog("Environment not set to selfhost - failing");

        $error = [
            'message' => ctrans('texts.invoice_license_or_environment', ['environment' => config('ninja.environment')]),
            'errors' => new stdClass(),
        ];

        return response()->json($error, 400);
    }


    private function checkLicense()
    {
        $account = auth()->user()->account;

        if ($account->plan == 'white_label' && Carbon::parse($account->plan_expires)->lt(now())) {
            $account->plan = null;
            $account->plan_expires = null;
            $account->save();
        }
    }

    public function check(CheckRequest $request): Response|JsonResponse
    {
        if (! config('ninja.license_key')) {
            return response()->json(['message' => ctrans('texts.white_label_license_not_present')], status: 422);
        }

        $response = Http::baseUrl(config('ninja.hosted_ninja_url'))
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ])
            ->post('/api/check/whitelabel', data: [
                'license' => config('ninja.license_key'),
            ]);

        if ($response->successful()) {
            return response()->json($response->json());
        }

        return response()->json(['message' => $response->json('message')], status: 422);
    }
}

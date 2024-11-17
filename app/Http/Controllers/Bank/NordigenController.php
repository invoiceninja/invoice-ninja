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

namespace App\Http\Controllers\Bank;

use App\Helpers\Bank\Nordigen\Nordigen;
use App\Http\Controllers\BaseController;
use App\Http\Requests\Nordigen\ConfirmNordigenBankIntegrationRequest;
use App\Http\Requests\Nordigen\ConnectNordigenBankIntegrationRequest;
use App\Jobs\Bank\ProcessBankTransactionsNordigen;
use App\Models\BankIntegration;
use App\Utils\Ninja;
use Cache;
use Illuminate\Http\Request;
use Nordigen\NordigenPHP\Exceptions\NordigenExceptions\NordigenException;

class NordigenController extends BaseController
{
    /**
     * VIEW: Connect Nordigen Bank Integration
     * @param ConnectNordigenBankIntegrationRequest $request
     */
    public function connect(ConnectNordigenBankIntegrationRequest $request)
    {
        $data = $request->all();

        /** @var array $context */
        $context = $request->getTokenContent();
        $company = $request->getCompany();
        $lang = substr($company->locale(), 0, 2);
        $context["lang"] = $lang;

        if (!$context) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'failed_reason' => "token-invalid",
                "redirectUrl" => config("ninja.app_url") . "?action=nordigen_connect&status=failed&reason=token-invalid",
            ]);
        }

        $context["redirect"] = $data["redirect"];
        if ($context["context"] != "nordigen" || array_key_exists("requisitionId", $context)) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'failed_reason' => "token-invalid",
                "redirectUrl" => ($context["redirect"]) . "?action=nordigen_connect&status=failed&reason=token-invalid",
            ]);
        }

        $company = $request->getCompany();
        $account = $company->account;

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key'))) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'failed_reason' => "account-config-invalid",
                "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=account-config-invalid",
            ]);
        }

        if (!(Ninja::isSelfHost() || (Ninja::isHosted() && $account->isEnterprisePaidClient()))) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'failed_reason' => "not-available",
                "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=not-available",
            ]);
        }

        $nordigen = new Nordigen();

        // show bank_selection_screen, when institution_id is not present
        if (!array_key_exists("institution_id", $data)) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'institutions' => $nordigen->getInstitutions(),
                'redirectUrl' => $context["redirect"] . "?action=nordigen_connect&status=user-aborted"
            ]);
        }

        // redirect to requisition flow
        try {
            $requisition = $nordigen->createRequisition(config('ninja.app_url') . '/nordigen/confirm', $data['institution_id'], $request->token, $lang);
        } catch (NordigenException $e) { // TODO: property_exists returns null in these cases... => why => therefore we just get unknown error everytime $responseBody is typeof GuzzleHttp\Psr7\Stream
            $responseBody = (string) $e->getResponse()->getBody();

            if (str_contains($responseBody, '"institution_id"')) { // provided institution_id was wrong
                return view('bank.nordigen.handler', [
                    'lang' => $lang,
                    'company' => $company,
                    'account' => $company->account,
                    'failed_reason' => "institution-invalid",
                    "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=institution-invalid",
                ]);
            } elseif (str_contains($responseBody, '"reference"')) { // this error can occur, when a reference was used double or is invalid => therefor we suggest the frontend to use another token
                return view('bank.nordigen.handler', [
                    'lang' => $lang,
                    'company' => $company,
                    'account' => $company->account,
                    'failed_reason' => "token-invalid",
                    "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=token-invalid",
                ]);
            } else {
                nlog("Unknown Error from nordigen: " . $e);
                nlog($responseBody);

                return view('bank.nordigen.handler', [
                    'lang' => $lang,
                    'company' => $company,
                    'account' => $company->account,
                    'failed_reason' => "unknown",
                    "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=unknown",
                ]);
            }
        }

        // save cache
        $context["requisitionId"] = $requisition["id"];
        Cache::put($request->token, $context, 3600);

        return response()->redirectTo($requisition["link"]);
    }

    /**
     * VIEW: Confirm Nordigen Bank Integration (redirect after nordigen flow)
     * @param ConfirmNordigenBankIntegrationRequest $request
     */
    public function confirm(ConfirmNordigenBankIntegrationRequest $request)
    {
        $data = $request->all();
        $company = $request->getCompany();
        $account = $company->account;
        $lang = substr($company->locale(), 0, 2);

        /** @var array $context */
        $context = $request->getTokenContent();
        if (!array_key_exists('lang', $data) && $context['lang'] != 'en') {
            return redirect()->route('nordigen.confirm', array_merge(["lang" => $context['lang']], $request->query()));
        }

        if (!$context || $context["context"] != "nordigen" || !array_key_exists("requisitionId", $context)) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'failed_reason' => "ref-invalid",
                "redirectUrl" => ($context && array_key_exists("redirect", $context) ? $context["redirect"] : config('ninja.app_url')) . "?action=nordigen_connect&status=failed&reason=ref-invalid",
            ]);
        }

        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key'))) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'failed_reason' => "account-config-invalid",
                "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=account-config-invalid",
            ]);
        }

        if (!(Ninja::isSelfHost() || (Ninja::isHosted() && $account->isEnterprisePaidClient()))) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'failed_reason' => "not-available",
                "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=not-available",
            ]);
        }

        // fetch requisition
        $nordigen = new Nordigen();
        $requisition = $nordigen->getRequisition($context["requisitionId"]);

        // check validity of requisition
        if (!$requisition) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'failed_reason' => "requisition-not-found",
                "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=requisition-not-found",
            ]);
        }
        if ($requisition["status"] != "LN") {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'failed_reason' => "requisition-invalid-status",
                "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=requisition-invalid-status&status=" . $requisition["status"],
            ]);
        }
        if (sizeof($requisition["accounts"]) == 0) {
            return view('bank.nordigen.handler', [
                'lang' => $lang,
                'company' => $company,
                'account' => $company->account,
                'failed_reason' => "requisition-no-accounts",
                "redirectUrl" => $context["redirect"] . "?action=nordigen_connect&status=failed&reason=requisition-no-accounts",
            ]);
        }

        // connect new accounts
        $bank_integration_ids = [];
        foreach ($requisition["accounts"] as $nordigenAccountId) {

            $nordigen_account = $nordigen->getAccount($nordigenAccountId);

            if(!$nordigen_account)
                continue;

            $existing_bank_integration = BankIntegration::withTrashed()->where('nordigen_account_id', $nordigen_account['id'])->where('company_id', $company->id)->where('is_deleted', 0)->first();

            if (!$existing_bank_integration) {

                $bank_integration = new BankIntegration();
                $bank_integration->integration_type = BankIntegration::INTEGRATION_TYPE_NORDIGEN;
                $bank_integration->company_id = $company->id;
                $bank_integration->account_id = $company->account_id;
                $bank_integration->user_id = $company->owner()->id;
                $bank_integration->nordigen_account_id = $nordigen_account['id'];
                $bank_integration->bank_account_type = $nordigen_account['account_type'];
                $bank_integration->bank_account_name = $nordigen_account['account_name'];
                $bank_integration->bank_account_status = $nordigen_account['account_status'];
                $bank_integration->bank_account_number = $nordigen_account['account_number'];
                $bank_integration->nordigen_institution_id = $nordigen_account['provider_id'];
                $bank_integration->provider_name = $nordigen_account['provider_name'];
                $bank_integration->nickname = $nordigen_account['nickname'];
                $bank_integration->balance = $nordigen_account['current_balance'];
                $bank_integration->currency = $nordigen_account['account_currency'];
                $bank_integration->disabled_upstream = false;
                $bank_integration->auto_sync = true;
                $bank_integration->from_date = now()->subDays(90); // default max-fetch interval of nordigen is 90 days

                $bank_integration->save();

                array_push($bank_integration_ids, $bank_integration->id);

            } else {

                // resetting metadata for account status
                $existing_bank_integration->balance = $account['current_balance'];
                $existing_bank_integration->bank_account_status = $account['account_status'];
                $existing_bank_integration->disabled_upstream = false;
                $existing_bank_integration->auto_sync = true;
                $existing_bank_integration->from_date = now()->subDays(90); // default max-fetch interval of nordigen is 90 days
                $existing_bank_integration->deleted_at = null;

                $existing_bank_integration->save();

                array_push($bank_integration_ids, $existing_bank_integration->id);
            }

        }

        // perform update in background
        $company->account->bank_integrations->where("integration_type", BankIntegration::INTEGRATION_TYPE_NORDIGEN)->where('auto_sync', true)->each(function ($bank_integration) {
            ProcessBankTransactionsNordigen::dispatch($bank_integration);
        });

        // prevent rerun of this method with same ref
        Cache::delete($data["ref"]);

        // Successfull Response => Redirect
        return response()->redirectTo($context["redirect"] . "?action=nordigen_connect&status=success&bank_integrations=" . implode(',', $bank_integration_ids));
    }

    /**
     * Process Nordigen Institutions GETTER.
     *
     *
     * @OA\Post(
     *      path="/api/v1/nordigen/institutions",
     *      operationId="nordigenRefreshWebhook",
     *      tags={"nordigen"},
     *      summary="Getting available institutions from nordigen",
     *      description="Used to determine the available institutions for sending and creating a new connect-link",
     *      @OA\Parameter(ref="#/components/parameters/X-Api-Token"),
     *      @OA\Parameter(ref="#/components/parameters/X-Requested-With"),
     *      @OA\Parameter(ref="#/components/parameters/include"),
     *      @OA\Response(
     *          response=200,
     *          description="",
     *          @OA\Header(header="X-MINIMUM-CLIENT-VERSION", ref="#/components/headers/X-MINIMUM-CLIENT-VERSION"),
     *          @OA\Header(header="X-RateLimit-Remaining", ref="#/components/headers/X-RateLimit-Remaining"),
     *          @OA\Header(header="X-RateLimit-Limit", ref="#/components/headers/X-RateLimit-Limit"),
     *          @OA\JsonContent(ref="#/components/schemas/Credit"),
     *       ),
     *       @OA\Response(
     *          response=422,
     *          description="Validation error",
     *          @OA\JsonContent(ref="#/components/schemas/ValidationError"),
     *
     *       ),
     *       @OA\Response(
     *           response="default",
     *           description="Unexpected Error",
     *           @OA\JsonContent(ref="#/components/schemas/Error"),
     *       ),
     *     )
     */
    public function institutions(Request $request)
    {
        if (!(config('ninja.nordigen.secret_id') && config('ninja.nordigen.secret_key'))) {
            return response()->json(['message' => 'Not yet authenticated with Nordigen Bank Integration service'], 400);
        }

        $nordigen = new Nordigen();
        return response()->json($nordigen->getInstitutions());
    }

}

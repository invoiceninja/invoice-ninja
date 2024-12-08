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

use App\Jobs\Account\CreateAccount;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyToken;
use Illuminate\Http\Request;

class HostedMigrationController extends Controller
{
    public function checkStatus(Request $request)
    {

        if ($request->header('X-API-HOSTED-SECRET') != config('ninja.ninja_hosted_secret')) {
            return;
        }

        MultiDB::findAndSetDbByCompanyKey($request->company_key);
        $c = Company::where('company_key', $request->company_key)->first();

        if (!$c || $c->is_disabled) {
            return response()->json(['message' => 'ok'], 200);
        }

        // if(\App\Models\Invoice::query()->where('company_id', $c->id)->where('created_at', '>', now()->subMonths(2))->first())
        //     return response()->json(['message' => 'New data exists, are you sure? Please log in here https://app.invoicing.co and delete the company if you really need to migrate again.'], 400);

        // if(\App\Models\Client::query()->where('company_id', $c->id)->where('created_at', '>', now()->subMonths(2))->first())
        //     return response()->json(['message' => 'New data exists, are you sure? Please log in here https://app.invoicing.co and delete the company if you really need to migrate again.'], 400);

        // if(\App\Models\Quote::query()->where('company_id', $c->id)->where('created_at', '>', now()->subMonths(2)))
        //     return response()->json(['message' => 'New data exists, are you sure? Please log in here https://app.invoicing.co and delete the company if you really need to migrate again.'], 400);

        // if(\App\Models\RecurringInvoice::query()->where('company_id', $c->id)->where('created_at', '>', now()->subMonths(2)))
        //     return response()->json(['message' => 'New data exists, are you sure? Please log in here https://app.invoicing.co and delete the company if you really need to migrate again.'], 400);

        return response()->json(['message' => 'You have already activated this company on v5!!!!!! This migration may be a BAD idea. Contact us contact@invoiceninja.com to confirm this action.'], 400);

    }

    public function getAccount(Request $request)
    {
        if ($request->header('X-API-HOSTED-SECRET') != config('ninja.ninja_hosted_secret')) {
            return;
        }

        if ($user = MultiDB::hasUser(['email' => $request->input('email')])) {
            if ($user->account->owner() && $user->account->companies()->count() >= 1) {
                return response()->json(['token' => $user->account->companies->first()->tokens->first()->token], 200);
            }

            return response()->json(['error' => 'This user is not able to perform a migration. Please contact us at contact@invoiceninja.com to discuss.'], 401);
        }

        $account = (new CreateAccount($request->all(), $request->getClientIp()))->handle();
        $account->hosted_client_count = 100;
        $account->hosted_company_count = 10;
        $account->created_at = now()->subYears(2);
        $account->save();

        MultiDB::findAndSetDbByAccountKey($account->key);

        $company = $account->companies->first();

        /** @var \App\Models\CompanyToken $company_token **/

        $company_token = CompanyToken::where('user_id', auth()->user()->id)
            ->where('company_id', $company->id)
            ->first();

        return response()->json(['token' => $company_token->token], 200);
    }

    public function confirmForwarding(Request $request)
    {
        if ($request->header('X-API-HOSTED-SECRET') != config('ninja.ninja_hosted_secret')) {
            return;
        }

        $input = $request->all();

        MultiDB::findAndSetDbByCompanyKey($input['account_key']);

        /** @var \App\Models\Company $company **/
        $company = Company::with('account')->where('company_key', $input['account_key'])->first();

        $forward_url = $company->domain();

        $billing_transferred = (new \Modules\Admin\Jobs\Account\TransferAccountPlan($input))->handle();

        return response()->json(['forward_url' => $forward_url, 'billing_transferred' => $billing_transferred], 200);
    }
}

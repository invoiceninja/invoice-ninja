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

use App\DataMapper\FeesAndLimits;
use App\Factory\CompanyGatewayFactory;
use App\Http\Requests\StripeConnect\InitializeStripeConnectRequest;
use App\Libraries\MultiDB;
use App\Models\Company;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Stripe\Exception\ApiErrorException;

class StripeConnectController extends BaseController
{
    /**
     * Initialize Stripe Connect flow.
     *
     * @param string $token One-time token
     * @throws ApiErrorException
     */
    public function initialize(InitializeStripeConnectRequest $request, string $token)
    {

        if (! is_array($request->getTokenContent())) {
            abort(400, 'Invalid token');
        }

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $stripe_client_id = config('ninja.ninja_stripe_client_id');
        $redirect_uri = config('ninja.app_url').'/stripe/completed';
        $endpoint = "https://connect.stripe.com/oauth/authorize?response_type=code&client_id={$stripe_client_id}&redirect_uri={$redirect_uri}&scope=read_write&state={$token}";

        return redirect($endpoint);
    }

    public function completed(InitializeStripeConnectRequest $request)
    {
        \Stripe\Stripe::setApiKey(config('ninja.ninja_stripe_key'));

        if ($request->has('error') && $request->error == 'access_denied') {
            return view('auth.connect.access_denied');
        }

        $response = false;

        try {
            /** @class \stdClass $response
             *  @property string $scope
             *  @property string $stripe_user_id
             *  @property string $stripe_publishable_key
             *  @property string $refresh_token
             *  @property string $livemode
             *  @property string $access_token
             *  @property string $token_type
             *  @property string $stripe_user
             *  @property string $stripe_account
             *  @property string $error
            */

            /** @var  \stdClass $response */
            $response = \Stripe\OAuth::token([
                'grant_type' => 'authorization_code',
                'code' => $request->input('code'),
            ]);

            nlog($response);

        } catch (\Exception $e) {


        }

        if (!$response) {
            return view('auth.connect.access_denied');
        }

        if (!$request->getTokenContent()) {
            return view('auth.connect.session_expired');
        }

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);

        $company = Company::query()->where('company_key', $request->getTokenContent()['company_key'])->first();

        $company_gateway = CompanyGateway::query()
            ->where('gateway_key', 'd14dd26a47cecc30fdd65700bfb67b34')
            ->where('company_id', $company->id)
            ->first();

        if (! $company_gateway) {
            $company_gateway = CompanyGatewayFactory::create($company->id, $company->owner()->id);
            $fees_and_limits = new \stdClass();
            $fees_and_limits->{GatewayType::CREDIT_CARD} = new FeesAndLimits();
            $company_gateway->gateway_key = 'd14dd26a47cecc30fdd65700bfb67b34';
            $company_gateway->fees_and_limits = $fees_and_limits;
            $company_gateway->setConfig([]);
            $company_gateway->token_billing = 'always';
        }

        $payload = [
            'account_id' => $response->stripe_user_id,
            'token_type' => 'bearer',
            'stripe_publishable_key' => $response->stripe_publishable_key,
            'scope' => $response->scope,
            'livemode' => $response->livemode,
            'stripe_user_id' => $response->stripe_user_id,
            'refresh_token' => $response->refresh_token,
            'access_token' => $response->access_token,
            'appleDomainVerification' => '',
        ];

        $company_gateway->setConfig($payload);
        $company_gateway->save();

        try {
            $stripe = $company_gateway->driver()->init();
            $a = \Stripe\Account::retrieve($response->stripe_user_id, $stripe->stripe_connect_auth);

            if ($a->business_name ?? false) {
                $company_gateway->label = substr("Stripe - {$a->business_name}", 0, 250);
                $company_gateway->save();
            }
        } catch (\Exception $e) {
            nlog("Exception:: StripeConnectController::" . $e->getMessage());
            nlog("could not harvest stripe company name");
        }

        if (isset($request->getTokenContent()['is_react']) && $request->getTokenContent()['is_react']) {
            $redirect_uri = config('ninja.react_url').'/#/settings/online_payments';
        } else {
            $redirect_uri = config('ninja.app_url');
        }

        \Illuminate\Support\Facades\Cache::pull($request->token);

        //response here
        return view('auth.connect.completed', ['url' => $redirect_uri]);

    }

}

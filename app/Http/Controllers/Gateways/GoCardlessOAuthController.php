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

use App\DataMapper\FeesAndLimits;
use App\Factory\CompanyGatewayFactory;
use App\Http\Controllers\Controller;
use App\Http\Requests\GoCardless\OAuthConnectConfirmRequest;
use App\Http\Requests\GoCardless\OAuthConnectRequest;
use App\Models\CompanyGateway;
use App\Models\GatewayType;
use Illuminate\Support\Facades\Http;

class GoCardlessOAuthController extends Controller
{
    public function connect(OAuthConnectRequest $request): \Illuminate\Http\RedirectResponse
    {
        /** @var \App\Models\Company $company */
        $company = $request->getCompany();

        $params = [
            'client_id' => config('services.gocardless.client_id'),
            'redirect_uri' => config('services.gocardless.redirect_uri'),
            'scope' => 'read_write',
            'response_type' => 'code',
            'state' => $company->company_key,
            'prefill[email]' => $company->settings->email,
            'prefill[organisation_name]' => $company->settings->name,
            'prefill[country_code]' => $company->country()->iso_3166_2,
        ];

        $url = config('services.gocardless.environment') === 'production'
            ? 'https://connect.gocardless.com/oauth/authorize?%s'
            : 'https://connect-sandbox.gocardless.com/oauth/authorize?%s';

        if (config('services.gocardless.testing_company') == $company->id) {
            $url = 'https://connect-sandbox.gocardless.com/oauth/authorize?%s';
        }

        return redirect()->to(
            sprintf($url, http_build_query($params))
        );
    }

    public function confirm(OAuthConnectConfirmRequest $request): \Illuminate\Http\RedirectResponse|\Illuminate\View\View
    {
        /** @var \App\Models\Company $company */
        $company = $request->getCompany();

        $url = config('services.gocardless.environment') === 'production'
            ? 'https://connect.gocardless.com/oauth/access_token'
            : 'https://connect-sandbox.gocardless.com/oauth/access_token';

        if (config('services.gocardless.testing_company') == $company->id) {
            $url = 'https://connect-sandbox.gocardless.com/oauth/access_token';
        }

        $response = Http::post($url, [
            'client_id' => config('services.gocardless.client_id'),
            'client_secret' => config('services.gocardless.client_secret'),
            'grant_type' => 'authorization_code',
            'code' => $request->query('code'),
            'redirect_uri' => config('services.gocardless.redirect_uri'),
        ]);

        if ($response->failed()) {
            return view('auth.gocardless_connect.access_denied');
        }

        $response = $response->json();

        $company_gateway = CompanyGateway::query()
            ->where('gateway_key', 'b9886f9257f0c6ee7c302f1c74475f6c')
            ->where('company_id', $company->id)
            ->first();

        if ($company_gateway === null) {
            $company_gateway = CompanyGatewayFactory::create($company->id, $company->owner()->id);
            $fees_and_limits = new \stdClass();
            $fees_and_limits->{GatewayType::INSTANT_BANK_PAY} = new FeesAndLimits();
            $company_gateway->gateway_key = 'b9886f9257f0c6ee7c302f1c74475f6c';
            $company_gateway->fees_and_limits = $fees_and_limits;
            $company_gateway->setConfig([]);
        }

        $payload = [
            '__current' => $company_gateway->getConfig(),
            'account_id' => $response['organisation_id'],
            'token_type' => $response['token_type'],
            'scope' => $response['scope'],
            'active' => $response['active'],
            'accessToken' => $response['access_token'],
            'testMode' => $company_gateway->getConfigField('testMode'),
            'oauth2' => true,
        ];

        $settings = new \stdClass();
        $settings->organisation_id = $response['organisation_id'];

        $company_gateway->setSettings($settings);

        $company_gateway->setConfig($payload);
        $company_gateway->save();

        return view('auth.gocardless_connect.completed');
    }
}

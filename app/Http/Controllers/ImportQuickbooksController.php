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

use App\Http\Requests\Quickbooks\AuthorizedQuickbooksRequest;
use App\Libraries\MultiDB;
use Illuminate\Support\Facades\Cache;
use App\Http\Requests\Quickbooks\AuthQuickbooksRequest;
use App\Services\Quickbooks\QuickbooksService;

class ImportQuickbooksController extends BaseController
{
    /**
     * Determine if the user is authorized to make this request.
     *
     */
    public function authorizeQuickbooks(AuthQuickbooksRequest $request, string $token)
    {

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);
        $company = $request->getCompany();
        $qb = new QuickbooksService($company);

        $authorizationUrl = $qb->sdk()->getAuthorizationUrl();
        $state = $qb->sdk()->getState();

        Cache::put($state, $token, 190);

        return redirect()->to($authorizationUrl);
    }

    public function onAuthorized(AuthorizedQuickbooksRequest $request)
    {

        MultiDB::findAndSetDbByCompanyKey($request->getTokenContent()['company_key']);
        $company = $request->getCompany();
        $qb = new QuickbooksService($company);

        $realm = $request->query('realmId');
        $access_token_object = $qb->sdk()->accessTokenFromCode($request->query('code'), $realm);
        $qb->sdk()->saveOAuthToken($access_token_object);

        return redirect(config('ninja.react_url'));

    }



}

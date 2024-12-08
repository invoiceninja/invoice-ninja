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

namespace App\Http\Controllers\Auth;

use App\Events\Contact\ContactLoggedIn;
use App\Http\Controllers\Controller;
use App\Http\Requests\ClientPortal\Contact\ContactLoginRequest;
use App\Http\ViewComposers\PortalComposer;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\ClientContact;
use App\Models\Company;
use App\Utils\Ninja;
use Auth;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ContactLoginController extends Controller
{
    use AuthenticatesUsers;

    protected $redirectTo = '/client/invoices';

    public function __construct()
    {
        $this->middleware('guest:contact', ['except' => ['logout']]);
    }

    private function resolveCompany($request, $company_key)
    {

        if($company_key && MultiDB::findAndSetDbByCompanyKey($company_key))
            return Company::where('company_key', $company_key)->first();

        $domain_name = $request->getHost();

        if (strpos($domain_name, config('ninja.app_domain')) !== false) {
            $subdomain = explode('.', $domain_name)[0];
            
            $query = ['subdomain' => $subdomain];
            
            if($company = MultiDB::findAndSetDbByDomain($query))
                return $company;
        }

        $query = [
            'portal_domain' => $request->getSchemeAndHttpHost(),
            'portal_mode' => 'domain',
        ];

        if ($company = MultiDB::findAndSetDbByDomain($query)) {
            return $company;
        }

        if(Ninja::isSelfHost())
            return Company::first();

        return false;
    }

    public function showLoginForm(Request $request, $company_key = false)
    {
        $company = false;
        $account = false;
        $intended = $request->query('intended') ?: false;
        
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($intended) {
            $request->session()->put('url.intended', $intended);
        }

        $company = $this->resolveCompany($request, $company_key);

        if ($company) {
            $account = $company->account;
        }
        else {
            abort(404, "We could not find this site, if you think this is an error, please contact the administrator.");
        }

        return $this->render('auth.login', ['account' => $account, 'company' => $company]);
    }

    public function login(ContactLoginRequest $request)
    {

        Auth::shouldUse('contact');

        if (Ninja::isHosted() && $request->has('company_key')) {
            MultiDB::findAndSetDbByCompanyKey($request->input('company_key'));
        }

        $this->validateLogin($request);
        // If the class is using the ThrottlesLogins trait, we can automatically throttle
        // the login attempts for this application. We'll key this by the username and
        // the IP address of the client making these requests into this application.
        if (method_exists($this, 'hasTooManyLoginAttempts') &&
            $this->hasTooManyLoginAttempts($request)) {
            $this->fireLockoutEvent($request);

            return $this->sendLockoutResponse($request);
        }

        if (Ninja::isHosted() && $request->has('password') && $company = Company::where('company_key', $request->input('company_key'))->first()) {
            /** @var \App\Models\Company $company **/
            $contact = ClientContact::where(['email' => $request->input('email'), 'company_id' => $company->id])
                                     ->whereHas('client', function ($query) {
                                         $query->where('is_deleted', 0);
                                     })->first();

            if (! $contact) {
                return $this->sendFailedLoginResponse($request);
            }

            if (Hash::check($request->input('password'), $contact->password)) {
                return $this->authenticated($request, $contact);
            }
        } elseif ($this->attemptLogin($request)) {
            return $this->sendLoginResponse($request);
        }
        // If the login attempt was unsuccessful we will increment the number of attempts
        // to login and redirect the user back to the login form. Of course, when this
        // user surpasses their maximum number of attempts they will get locked out.
        $this->incrementLoginAttempts($request);

        return $this->sendFailedLoginResponse($request);
    }

    protected function sendLoginResponse(Request $request)
    {

        $intended = $request->session()->has('url.intended') ? $request->session()->get('url.intended') : false;

        $request->session()->regenerate();

        $this->clearLoginAttempts($request);

        if ($response = $this->authenticated($request, $this->guard()->user())) {
            return $response;
        }

        $this->setRedirectPath();

        if ($intended) {
            $this->redirectTo = $intended;
        }

        return $request->wantsJson()
                    ? new JsonResponse([], 204)
                    : redirect()->intended($this->redirectPath());
    }

    public function authenticated(Request $request, ClientContact $client)
    {
        auth()->guard('contact')->loginUsingId($client->id, true);

        event(new ContactLoggedIn($client, $client->company, Ninja::eventVars()));

        if ($request->session()->has('url.intended')) {
            return redirect($request->session()->get('url.intended'));
        }

        $this->setRedirectPath();

        return redirect($this->redirectTo);
    }

    public function logout()
    {
        Auth::guard('contact')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/client/login');
    }

    private function setRedirectPath()
    {

        if (auth()->guard('contact')->user()->client->getSetting('enable_client_portal_dashboard') === true) {
            $this->redirectTo = '/client/dashboard';
        } elseif ((bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_INVOICES)) {
            $this->redirectTo = '/client/invoices';
        } elseif ((bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_RECURRING_INVOICES)) {
            $this->redirectTo = '/client/recurring_invoices';
        } elseif ((bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_QUOTES)) {
            $this->redirectTo = '/client/quotes';
        } elseif ((bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_CREDITS)) {
            $this->redirectTo = '/client/credits';
        } elseif ((bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_TASKS)) {
            $this->redirectTo = '/client/tasks';
        } elseif ((bool)(auth()->guard('contact')->user()->company->enabled_modules & PortalComposer::MODULE_EXPENSES)) {
            $this->redirectTo = '/client/expenses';
        }
    }
}

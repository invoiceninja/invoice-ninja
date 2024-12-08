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

use App\Http\Controllers\Controller;
use App\Libraries\MultiDB;
use App\Models\Account;
use App\Models\Company;
use App\Utils\Ninja;
use Illuminate\Foundation\Auth\ResetsPasswords;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Password Reset Controller
    |--------------------------------------------------------------------------
    |
    | This controller is responsible for handling password reset requests
    | and uses a simple trait to include this behavior. You're free to
    | explore this trait and override any methods you wish to tweak.
    |
    */

    use ResetsPasswords;

    /**
     * Where to redirect users after resetting their password.
     *
     * @var string
     */
    protected $redirectTo = '/';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest');
    }

    public function showResetForm(Request $request, $token = null)
    {
        $company = false;

        if (Ninja::isHosted()) {
            MultiDB::findAndSetDbByCompanyKey($request->session()->get('company_key'));
            /** @var \App\Models\Company $company **/
            $company = Company::where('company_key', $request->session()->get('company_key'))->first();
        }

        if ($company) {
            $account = $company->account;
        } else {
            $account = Account::first();
        }


        return $this->render('auth.passwords.reset', ['root' => 'themes', 'token' => $token, 'account' => $account, 'email' => $request->email]);
    }

    /**
     * Reset the given user's password.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse | \Illuminate\Http\RedirectResponse|JsonResponse
     * @throws \Illuminate\Validation\ValidationException
     */
    public function reset(Request $request)
    {
        $request->validate($this->rules(), $this->validationErrorMessages());

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        $response = $this->broker()->reset(
            $this->credentials($request),
            function ($user, $password) {
                $this->resetPassword($user, $password);
            }
        );

        // Added this because it collides the session between
        // client & main portal giving unlimited redirects.
        auth()->logout();

        // If the password was successfully reset, we will redirect the user back to
        // the application's home authenticated view. If there is an error we can
        // redirect them back to where they came from with their error message.
        return $response == Password::PASSWORD_RESET
            ? $this->sendResetResponse($request, $response)
            : $this->sendResetFailedResponse($request, $response);
    }

    public function afterReset()
    {
        auth()->logout();

        if (request()->has('react') || request()->hasHeader('X-React')) {
            return redirect(config('ninja.react_url').'/#/login');
        }

        return redirect('/');
    }

    /**
     * Get the response for a successful password reset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  string  $response
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function sendResetResponse(Request $request, $response)
    {
        if ($request->wantsJson()) {
            return new JsonResponse(['message' => trans($response)], 200);
        }

        if ($request->hasHeader('X-REACT') || $request->has('react')) {
            return redirect(config('ninja.react_url').'/#/login');
        } else {
            return redirect('/#/login');
        }

        // return redirect($this->redirectPath())
        //                     ->with('status', trans($response));
    }

}

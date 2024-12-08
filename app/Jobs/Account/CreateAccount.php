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

namespace App\Jobs\Account;

use App\Utils\Ninja;
use App\Models\Account;
use Illuminate\Support\Str;
use App\Jobs\User\CreateUser;
use App\DataProviders\Domains;
use App\Jobs\Util\VersionCheck;
use App\Jobs\Mail\NinjaMailerJob;
use App\Jobs\Company\CreateCompany;
use Illuminate\Support\Facades\App;
use App\Jobs\Mail\NinjaMailerObject;
use App\Utils\Traits\User\LoginCache;
use App\Events\Account\AccountCreated;
use Turbo124\Beacon\Facades\LightLogs;
use App\Jobs\Company\CreateCompanyToken;
use Illuminate\Foundation\Bus\Dispatchable;
use App\DataMapper\Analytics\AccountPlatform;
use App\Jobs\Company\CreateCompanyPaymentTerms;
use App\Jobs\Company\CreateCompanyTaskStatuses;
use App\DataMapper\Analytics\AccountCreated as AnalyticsAccountCreated;

class CreateAccount
{
    use Dispatchable;
    use LoginCache;

    protected $request;

    protected $client_ip;



    public function __construct(array $sp660339, $client_ip)
    {
        $this->request = $sp660339;
        $this->client_ip = $client_ip;
    }

    public function handle()
    {
        if (config('ninja.environment') == 'selfhost' && Account::count() == 0) {
            return $this->create();
        } elseif (config('ninja.environment') == 'selfhost' && Account::count() > 1) {
            return response()->json(['message' => Ninja::selfHostedMessage()], 400);
        } elseif (! Ninja::boot()) {
            return response()->json(['message' => Ninja::parse()], 401);
        }

        return $this->create();
    }

    private function create()
    {
        Account::reguard();
        $sp794f3f = new Account();
        $sp794f3f->fill($this->request);

        if (array_key_exists('rc', $this->request)) {
            $sp794f3f->referral_code = $this->request['rc'];
        }

        if (! $sp794f3f->key) {
            $sp794f3f->key = Str::random(32);
        }

        if (Ninja::isHosted()) {
            $sp794f3f->hosted_client_count = config('ninja.quotas.free.clients');
            $sp794f3f->hosted_company_count = config('ninja.quotas.free.max_companies');
            $sp794f3f->account_sms_verified = true;

            if (in_array($this->getDomain($this->request['email']), Domains::getDomains())) {
                $sp794f3f->account_sms_verified = false;
            }

        }

        $sp794f3f->save();

        $sp035a66 = (new CreateCompany($this->request, $sp794f3f))->handle();
        $sp035a66->load('account');
        $sp794f3f->default_company_id = $sp035a66->id;
        $sp794f3f->save();

        $spaa9f78 = (new CreateUser($this->request, $sp794f3f, $sp035a66, true))->handle();

        $sp035a66->service()->localizeCompany($spaa9f78);

        (new CreateCompanyPaymentTerms($sp035a66, $spaa9f78))->handle();
        (new CreateCompanyTaskStatuses($sp035a66, $spaa9f78))->handle();

        if ($spaa9f78) {
            auth()->login($spaa9f78, false);
        }

        $spaa9f78->setCompany($sp035a66);
        $this->setLoginCache($spaa9f78);

        $spafe62e = isset($this->request['token_name']) ? $this->request['token_name'] : request()->server('HTTP_USER_AGENT');
        $sp2d97e8 = (new CreateCompanyToken($sp035a66, $spaa9f78, $spafe62e))->handle();

        if ($spaa9f78) {
            event(new AccountCreated($spaa9f78, $sp035a66, Ninja::eventVars()));
        }

        $spaa9f78->fresh();

        if (Ninja::isHosted()) {
            App::forgetInstance('translator');
            $t = app('translator');
            $t->replace(Ninja::transformTranslations($sp035a66->settings));

            $nmo = new NinjaMailerObject();
            $nmo->mailable = new \Modules\Admin\Mail\Welcome($sp035a66->owner());
            $nmo->company = $sp035a66;
            $nmo->settings = $sp035a66->settings;
            $nmo->to_user = $sp035a66->owner();

            NinjaMailerJob::dispatch($nmo, true);

            (new \Modules\Admin\Jobs\Account\NinjaUser([], $sp035a66))->handle();

            // if($sp794f3f->referral_code && Ninja::isHosted()) //2024-11-29 - pausing on this.
            //     \Modules\Admin\Jobs\Account\NewReferredAccount::dispatch($sp794f3f->key);
        }

        VersionCheck::dispatch();

        LightLogs::create(new AnalyticsAccountCreated())
                 ->increment()
                 ->queue();

        $ip = '';

        if (request()->hasHeader('Cf-Connecting-Ip')) {
            $ip = request()->header('Cf-Connecting-Ip');
        } elseif (request()->hasHeader('X-Forwarded-For')) {
            $ip = request()->header('Cf-Connecting-Ip');
        } else {
            $ip = request()->ip();
        }

        $platform = request()->has('platform') ? request()->input('platform') : 'www';

        LightLogs::create(new AccountPlatform($platform, request()->server('HTTP_USER_AGENT'), $ip))
                 ->queue();

        return $sp794f3f;
    }

    private function getDomain($email)
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            // split on @ and return last value of array (the domain)
            $domain = explode('@', $email);

            $domain_name = end($domain);

            return $domain_name;
        }

        return 'gmail.com';
    }




}
